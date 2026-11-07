<?php

namespace App\Livewire\Dashboard;

use App\Detection\SensorWatch;
use App\Enums\AgentStatus;
use App\Enums\Severity;
use App\Models\Agent;
use App\Models\Incident;
use App\Models\SecurityEvent;
use App\Models\Unit;
use App\Response\ActionService;
use App\Support\DbTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Beranda Komando (PRD bagian 11, FR-07): pohon satuan dengan Tingkat Siaga,
 * kartu metrik, feed event live, grafik event per menit. Semua angka dihitung
 * dari database dan dibatasi ke satuan yang boleh dilihat pengguna.
 */
#[Layout('layouts.app')]
#[Title('Beranda Komando')]
class CommandDashboard extends Component
{
    public const CHART_MINUTES = 30;

    /** @var array{labels: list<string>, counts: list<int>} */
    public array $series = ['labels' => [], 'counts' => []];

    public function mount(): void
    {
        $this->authorize('lihat-dashboard');
    }

    #[On('sentra-refresh')]
    public function refreshLive(): void
    {
        // Render ulang saja; data diambil ulang di render().
    }

    public function render()
    {
        $user = auth()->user();
        $units = $user->visibleUnitIds();
        $scope = fn ($q) => $units === null ? $q : $q->whereIn('unit_id', $units);

        $open = Incident::query()->open()->visibleTo($user)->get(['id', 'severity', 'score', 'unit_id', 'src_ip']);
        $bySeverity = collect(Severity::cases())->reverse()
            ->mapWithKeys(fn (Severity $s) => [$s->value => $open->where('severity', $s)->count()]);

        $agents = $scope(Agent::query())->whereNotIn('status', [AgentStatus::Dicabut->value, AgentStatus::Menunggu->value])->get(['status']);

        $blocks = ActionService::activeBlocks()
            ->when($units !== null, fn ($q) => $q->whereHas('targetAgent', fn ($a) => $a->whereIn('unit_id', $units)))
            ->get(['params']);

        $this->series = $this->eventSeries($units);

        return view('livewire.dashboard.command-dashboard', [
            'tree' => Unit::tree($units),
            // Untuk peta operasi: posisi penyerang (IP sumber insiden terbuka) dan satuan sasarannya.
            'openIncidents' => $open,
            'openCount' => $open->count(),
            'bySeverity' => $bySeverity,
            'agentTotal' => $agents->count(),
            'agentOnline' => $agents->where('status', AgentStatus::Online)->count(),
            'agentBlind' => $agents->where('status', AgentStatus::Buta)->count(),
            'blockCount' => $blocks->count(),
            'blockIps' => $blocks->pluck('params.ip')->unique()->count(),
            'feed' => $scope(SecurityEvent::query())->with(['unit', 'agent', 'detections.rule', 'detections.incident:id,severity'])
                ->where('is_test', false)->orderByDesc('id')->limit(12)->get(),
            'canSeeAgents' => $user->can('lihat-satuan-agent'),
            'sourceWarnings' => app(SensorWatch::class)->sourceWarnings($units)->count(),
        ]);
    }

    /**
     * Jumlah event nyata per menit selama 30 menit terakhir (menit kosong = 0).
     *
     * @param  list<int>|null  $units
     * @return array{labels: list<string>, counts: list<int>}
     */
    private function eventSeries(?array $units): array
    {
        $end = now()->utc()->startOfMinute();
        $start = $end->copy()->subMinutes(self::CHART_MINUTES - 1);

        $rows = SecurityEvent::query()
            ->when($units !== null, fn ($q) => $q->whereIn('unit_id', $units))
            ->where('is_test', false)
            ->where('occurred_at', '>=', DbTime::of($start))
            ->groupBy('menit')
            ->get([DB::raw("DATE_FORMAT(occurred_at, '%Y-%m-%d %H:%i') as menit"), DB::raw('count(*) as n')])
            ->pluck('n', 'menit');

        $labels = [];
        $counts = [];
        for ($t = $start->copy(); $t <= $end; $t->addMinute()) {
            $labels[] = Carbon::instance($t)->setTimezone(config('sentra.display_timezone'))->format('H:i');
            $counts[] = (int) ($rows[$t->format('Y-m-d H:i')] ?? 0);
        }

        return ['labels' => $labels, 'counts' => $counts];
    }
}
