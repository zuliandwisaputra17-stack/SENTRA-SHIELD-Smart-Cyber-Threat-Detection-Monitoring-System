<?php

namespace App\Livewire\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Audit Log (FR-11, PRD bagian 11): tabel aksi + tombol Verifikasi Rantai.
 * Analis, Komandan, Admin melihat dan memverifikasi; Demo hanya melihat.
 */
#[Layout('layouts.app')]
#[Title('Audit Log')]
class AuditLogPage extends Component
{
    use WithPagination;

    /** Nilai filter pengguna untuk baris tanpa pengguna (sistem/agent). */
    public const SYSTEM_USER = 'sistem';

    #[Url]
    public string $aksi = '';

    #[Url]
    public string $pengguna = '';

    #[Url]
    public string $dari = '';

    #[Url]
    public string $sampai = '';

    /** Tampilkan tabel di sekitar baris ini (dipakai tautan dari hasil verifikasi). */
    #[Url]
    public ?int $baris = null;

    /** @var array{ok: bool, checked: int, broken_id: ?int, reason: ?string, at: string}|null */
    public ?array $verification = null;

    public function mount(): void
    {
        $this->authorize('lihat-audit');
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void {}

    public function updating(string $name): void
    {
        if (in_array($name, ['aksi', 'pengguna', 'dari', 'sampai'], true)) {
            $this->baris = null;
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('aksi', 'pengguna', 'dari', 'sampai', 'baris');
        $this->resetPage();
    }

    public function verify(AuditLogger $audit): void
    {
        $this->authorize('verifikasi-audit');

        $result = $audit->verify();
        $this->verification = $result + ['at' => now()->toIso8601String()];
        $audit->record('audit_diverifikasi', null, [
            'utuh' => $result['ok'],
            'baris_diperiksa' => $result['checked'],
            'baris_rusak' => $result['broken_id'],
        ]);
    }

    public function showRow(int $id): void
    {
        $this->reset('aksi', 'pengguna', 'dari', 'sampai');
        $this->baris = $id;
        $this->resetPage();
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($this->aksi !== '', fn ($q) => $q->where('action', $this->aksi))
            ->when($this->pengguna === self::SYSTEM_USER, fn ($q) => $q->whereNull('user_id'))
            ->when(ctype_digit($this->pengguna), fn ($q) => $q->where('user_id', (int) $this->pengguna))
            ->when($this->date($this->dari), fn ($q, $d) => $q->where('created_at', '>=', $d->startOfDay()->utc()->format('Y-m-d H:i:s.u')))
            ->when($this->date($this->sampai), fn ($q, $d) => $q->where('created_at', '<=', $d->endOfDay()->utc()->format('Y-m-d H:i:s.u')))
            ->when($this->baris, fn ($q, $id) => $q->where('id', '<=', $id + 10))
            ->orderByDesc('id')
            ->paginate(50);

        return view('livewire.audit.audit-log-page', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'total' => AuditLog::query()->count(),
            'canVerify' => auth()->user()->can('verifikasi-audit'),
        ]);
    }

    /** Tanggal filter (WIB) dari input type=date; null bila kosong/tidak valid. */
    private function date(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value, config('sentra.display_timezone')) ?: null;
    }

    /** Label objek audit dan tautannya bila halaman itu ada dan boleh dibuka. */
    public static function subject(AuditLog $log): array
    {
        $id = $log->subject_id;
        $user = auth()->user();

        return match ($log->subject_type) {
            'Incident' => ['Insiden #'.$id, route('incidents.show', $id)],
            'Campaign' => ['Kampanye #'.$id, $user->can('lihat-kampanye') ? route('campaigns.show', $id) : null],
            'ReadinessRun' => ['Uji Kesiapan #'.$id, $user->can('lihat-uji-kesiapan') ? route('readiness', ['run' => $id]) : null],
            'ResponseAction' => ['Tindakan #'.$id, null],
            'Agent' => ['Agent #'.$id, null],
            'Unit' => ['Satuan #'.$id, null],
            'User' => ['Pengguna #'.$id, null],
            'DetectionRule' => ['Aturan #'.$id, null],
            null => ['—', null],
            default => [$log->subject_type.' #'.$id, null],
        };
    }

    /** Ringkasan isi data: "kunci: nilai" per baris. */
    public static function details(?array $data): string
    {
        return collect($data ?? [])
            ->map(fn ($v, $k) => $k.': '.match (true) {
                $v === null => '—',
                is_bool($v) => $v ? 'ya' : 'tidak',
                is_scalar($v) => (string) $v,
                default => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            })
            ->implode(' · ');
    }
}
