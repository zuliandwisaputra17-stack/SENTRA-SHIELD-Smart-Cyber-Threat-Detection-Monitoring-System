<?php

namespace App\Console\Commands;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Incident;
use App\Models\ReadinessRun;
use App\Models\ResponseAction;
use App\Models\SecurityEvent;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

/**
 * Ringkasan status lab untuk skrip cek-lab (Windows maupun Linux).
 * Semua angka diambil langsung dari database; tidak ada yang dihitung ulang
 * atau dikarang. Keluaran sengaja ASCII agar terbaca di konsol Windows.
 */
class SentraStatus extends Command
{
    protected $signature = 'sentra:status
        {--tunggu-agent=0 : Tunggu sampai sejumlah agent online}
        {--batas=120 : Batas waktu menunggu, dalam detik}';

    protected $description = 'Tampilkan status agent, event, insiden, dan tindakan (untuk cek lab)';

    public function handle(): int
    {
        $target = (int) $this->option('tunggu-agent');
        $deadline = time() + (int) $this->option('batas');
        while ($target > 0 && $this->onlineCount() < $target && time() < $deadline) {
            sleep(2);
        }

        $this->line('SENTRA SHIELD - status lab ('.waktu_wib(now()).')');

        $this->line('');
        $this->line('AGENT');
        $agents = Agent::with('unit')->orderBy('hostname')->get();
        if ($agents->isEmpty()) {
            $this->line('  (belum ada agent)');
        }
        foreach ($agents as $agent) {
            $sources = collect($agent->sources ?? [])
                ->reject(fn ($s) => ($s['status'] ?? '') === 'disabled')
                ->map(fn ($s, $name) => $name.'='.($s['status'] ?? '?').$this->firewallMode($name, $s))
                ->implode(' ');
            $this->line(sprintf(
                '  %-9s %-9s %-16s heartbeat %-18s %s',
                $agent->hostname,
                strtoupper($agent->status->value),
                $agent->unit->name,
                $agent->last_heartbeat_at?->diffForHumans() ?? '-',
                $sources,
            ));
        }

        $this->line('');
        $this->line('EVENT PER SUMBER (data asli dari agent)');
        $rows = SecurityEvent::query()->selectRaw('source, count(*) as n, max(created_at) as terakhir')->groupBy('source')->get();
        if ($rows->isEmpty()) {
            $this->line('  (belum ada event)');
        }
        foreach ($rows as $row) {
            $this->line(sprintf('  %-9s %7d event, terakhir %s', $row->source->value, $row->n, waktu_wib(now()->parse($row->terakhir), 'H:i:s')));
        }

        $this->line('');
        $this->line('INSIDEN TERBUKA');
        $incidents = Incident::query()->open()->with('unit')->orderByDesc('score')->limit(10)->get();
        if ($incidents->isEmpty()) {
            $this->line('  (tidak ada)');
        }
        foreach ($incidents as $incident) {
            $this->line(sprintf('  #%-4d %-7s skor %-4d %s', $incident->id, $incident->severity->label(), $incident->score, $incident->title));
        }

        $this->line('');
        $this->line('TINDAKAN TERAKHIR');
        $actions = ResponseAction::query()->with('targetAgent')->latest('id')->limit(5)->get();
        if ($actions->isEmpty()) {
            $this->line('  (belum ada)');
        }
        foreach ($actions as $action) {
            $delay = $action->approved_at && $action->executed_at && $action->type->value !== 'run_test'
                ? sprintf(' (dieksekusi %d dtk setelah disetujui)', max(0, $action->approved_at->diffInSeconds($action->executed_at)))
                : '';
            $this->line(sprintf('  #%-4d %-10s %-15s di %-8s %s%s', $action->id, $action->type->value, $action->params['ip'] ?? '-', $action->targetAgent?->hostname ?? '-', $action->status->label(), $delay));
        }

        $this->line('');
        $this->line('UJI KESIAPAN TERAKHIR');
        $run = ReadinessRun::query()->with(['unit', 'results.rule'])->latest('id')->first();
        if ($run === null) {
            $this->line('  (belum pernah dijalankan)');
        } else {
            $detected = $run->results->where('detected', true);
            $this->line(sprintf('  Uji #%d %s, %s: cakupan %d/%d%s', $run->id, $run->unit?->name ?? '-', $run->status->value,
                $detected->count(), $run->results->count(),
                $detected->isNotEmpty() ? ', rata-rata waktu deteksi '.number_format($detected->avg('ttd_ms') / 1000, 1, ',', '.').' dtk' : ''));
            foreach ($run->results->sortBy('scenario') as $result) {
                $this->line(sprintf('    %-4s %-12s %s', $result->scenario,
                    $result->detected ? 'Terdeteksi' : 'Lolos',
                    $result->ttd_ms !== null ? number_format($result->ttd_ms / 1000, 1, ',', '.').' dtk' : ($result->note ?? '')));
            }
        }

        $this->line('');
        $this->line('AUDIT LOG');
        $chain = app(AuditLogger::class)->verify();
        $this->line($chain['ok']
            ? "  Rantai utuh: {$chain['checked']} baris diperiksa, semua hash cocok."
            : "  RANTAI RUSAK mulai baris #{$chain['broken_id']}: {$chain['reason']}.");

        $online = $this->onlineCount();
        $this->line('');
        if ($target > 0 && $online < $target) {
            $this->line("HASIL: GAGAL - agent online {$online} dari {$target}.");

            return self::FAILURE;
        }
        $this->line("HASIL: LULUS - {$online} agent online.");

        return self::SUCCESS;
    }

    /** Mode log port tertutup: NFLOG atau cadangan raw socket (kernel tanpa NFLOG). */
    private function firewallMode(string $name, array $source): string
    {
        if ($name !== 'firewall' || ($source['status'] ?? '') !== 'ok') {
            return '';
        }

        $detail = (string) ($source['detail'] ?? '');

        return match (true) {
            str_contains($detail, 'raw socket') => '(raw socket)',
            str_contains($detail, 'nflog') => '(nflog)',
            default => '',
        };
    }

    private function onlineCount(): int
    {
        return Agent::query()->where('status', AgentStatus::Online)->count();
    }
}
