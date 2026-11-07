<?php

namespace App\Reports;

use App\Enums\ReportGenerator;
use App\Jobs\GenerateLapsit;
use App\Models\Incident;
use App\Models\Report;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\LiveUpdate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * LAPSIT otomatis (FR-12). Disusun di antrean terpisah ("lapsit") agar
 * panggilan LLM yang lama tidak menahan mesin deteksi. Bila Ollama tidak
 * tersedia, lebih dari batas waktu, atau keluarannya tidak lolos validasi
 * sitasi, LAPSIT dibuat dari template.
 *
 * Kaki halaman PDF memuat SHA-256 isi LAPSIT; SHA-256 file PDF disimpan di
 * reports.sha256 dan audit log (keputusan B10).
 */
final class LapsitService
{
    public const QUEUE = 'lapsit';

    public function __construct(
        private OllamaWriter $ollama,
        private TemplateWriter $template,
        private AuditLogger $audit,
    ) {}

    public static function pendingKey(int $incidentId): string
    {
        return 'sentra:lapsit:'.$incidentId;
    }

    public static function pending(Incident $incident): bool
    {
        return Cache::has(self::pendingKey($incident->id));
    }

    /** @return bool false bila LAPSIT insiden ini masih disusun */
    public function request(Incident $incident, User $user): bool
    {
        Gate::forUser($user)->authorize('kelola-lapsit');
        Gate::forUser($user)->authorize('view', $incident);

        // Batas aman di atas batas waktu LLM; kunci dilepas job saat selesai.
        if (! Cache::add(self::pendingKey($incident->id), $user->id, now()->addSeconds(OllamaWriter::timeout() + 120))) {
            return false;
        }
        LiveUpdate::notify('insiden', [$incident->unit_id]);
        GenerateLapsit::dispatch($incident->id, $user->id)->onQueue(self::QUEUE);

        return true;
    }

    public function generate(Incident $incident, ?User $user): Report
    {
        try {
            $facts = IncidentFacts::for($incident);
            [$draft, $generator, $note, $dropped] = $this->draft($incident, $facts);

            $sequence = Report::query()->where('incident_id', $incident->id)->count() + 1;
            $number = Report::numberFor($incident->id, $sequence);
            $report = Report::create([
                'incident_id' => $incident->id,
                'generator' => $generator,
                'content' => $this->compose($incident, $facts, $draft, $number, $user, $generator, $note, $dropped),
                'created_by' => $user?->id,
            ]);

            $pdf = $this->pdf($report, $number);
            $path = 'lapsit/lapsit-'.$report->id.'.pdf';
            Storage::disk('local')->put($path, $pdf);
            $report->update(['pdf_path' => $path, 'sha256' => hash('sha256', $pdf)]);

            $this->audit->record('lapsit_dibuat', $incident, array_filter([
                'nomor' => $number,
                'laporan_id' => $report->id,
                'penyusun' => $generator->value,
                'model' => $generator === ReportGenerator::Llm ? OllamaWriter::model() : null,
                'catatan' => $note,
                'sha256_isi' => $report->contentHash(),
                'sha256_pdf' => $report->sha256,
            ], fn ($v) => $v !== null), $user);

            return $report;
        } finally {
            Cache::forget(self::pendingKey($incident->id));
            LiveUpdate::notify('insiden', [$incident->unit_id]);
        }
    }

    /**
     * @return array{0: array{situasi: string, temuan: list<string>, penilaian: string, saran: list<string>}, 1: ReportGenerator, 2: ?string, 3: int}
     */
    private function draft(Incident $incident, IncidentFacts $facts): array
    {
        try {
            $guard = new CitationGuard($facts);
            $draft = $guard->clean($this->ollama->write($facts));
            if ($draft['situasi'] === '' || $draft['temuan'] === []) {
                throw new LlmUnavailable('Keluaran LLM tidak lolos validasi sitasi bukti');
            }

            return [$draft, ReportGenerator::Llm, null, $guard->dropped];
        } catch (LlmUnavailable $e) {
            return [$this->template->write($incident, $facts), ReportGenerator::Template, $e->getMessage(), 0];
        }
    }

    /** Isi LAPSIT (teks biasa). Inilah yang di-hash dan dicetak di PDF. */
    private function compose(Incident $incident, IncidentFacts $facts, array $draft, string $number, ?User $user, ReportGenerator $generator, ?string $note, int $dropped): string
    {
        $i = $facts->data['insiden'];
        $lines = [
            'LAPORAN SITUASI (LAPSIT) SIBER',
            'Nomor    : '.$number,
            'Insiden  : #'.$incident->id.' '.$incident->title,
            'Satuan   : '.$i['satuan'],
            'Waktu    : '.IncidentFacts::wib(now()),
            'Disusun  : '.($user ? $user->name.' ('.$user->role->label().')' : 'SENTRA SHIELD'),
            'Penyusun : '.($generator === ReportGenerator::Llm
                ? 'LLM lokal '.OllamaWriter::model().' melalui Ollama. DRAF: wajib diperiksa Analis sebelum diteruskan.'
                : 'Template otomatis SENTRA SHIELD (LLM tidak dipakai: '.$note.').'),
        ];
        if ($dropped > 0) {
            $lines[] = 'Validasi : '.$dropped.' kalimat tanpa bukti yang sah dibuang dari draf LLM.';
        }

        $lines[] = '';
        $lines[] = '1. SITUASI';
        $lines[] = $draft['situasi'];
        $lines[] = '';
        $lines[] = '2. KRONOLOGI DAN TEMUAN';
        array_push($lines, ...self::lettered($draft['temuan'] ?: ['Tidak ada temuan yang dapat dikutip dari bukti.']));
        $lines[] = '';
        $lines[] = '3. PENILAIAN';
        $lines[] = $draft['penilaian'];
        if ($generator === ReportGenerator::Llm && ($facts->data['rincian_skor']['rumus'] ?? null)) {
            $lines[] = 'Rumus skor: '.$facts->data['rincian_skor']['rumus'].'; severity '.$i['severity'].'.';
        }
        $lines[] = '';
        $lines[] = '4. TINDAKAN YANG SUDAH DIAMBIL';
        array_push($lines, ...self::lettered(self::actionLines($facts) ?: ['Belum ada tindakan respons.']));
        $lines[] = '';
        $lines[] = '5. SARAN';
        array_push($lines, ...self::lettered($draft['saran'] ?: ['Lanjutkan pemantauan.']));
        $lines[] = '';
        $lines[] = 'Rujukan bukti: setiap [EV-n] adalah ID event di SENTRA SHIELD (halaman Detail Event n).';

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private static function actionLines(IncidentFacts $facts): array
    {
        return array_map(fn ($a) => sprintf('%s %s di %s: %s (diusulkan %s%s%s%s).',
            $a['jenis'], $a['ip'] ?? '', $a['agent'] ?? '-', $a['status'], $a['diusulkan_oleh'] ?? 'sistem',
            $a['diputuskan_oleh'] ? ', diputuskan '.$a['diputuskan_oleh'] : '',
            $a['waktu_eksekusi'] ? ', dieksekusi '.$a['waktu_eksekusi'] : '',
            $a['berlaku_sampai'] ? ', berlaku sampai '.$a['berlaku_sampai'] : '',
        ), $facts->data['tindakan']);
    }

    /** @return list<string> */
    private static function lettered(array $items): array
    {
        return array_values(array_map(
            fn ($item, $n) => self::letter($n).'. '.preg_replace('/\s*\n\s*/', ' ', trim($item)),
            $items, array_keys(array_values($items))
        ));
    }

    private static function letter(int $n): string
    {
        return $n < 26 ? chr(97 + $n) : chr(97 + intdiv($n, 26) - 1).chr(97 + $n % 26);
    }

    private function pdf(Report $report, string $number): string
    {
        $pdf = Pdf::loadView('pdf.lapsit', [
            'report' => $report,
            'number' => $number,
            'lines' => explode("\n", $report->content),
            'contentHash' => $report->contentHash(),
        ])->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);

        // Nomor halaman "n / total" di kaki halaman (CSS counter(pages) tidak didukung dompdf).
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 120, $canvas->get_height() - 42, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 7.5, [0.2, 0.2, 0.2]);

        return $dompdf->output();
    }
}
