<?php

namespace App\Detection;

use App\Enums\AgentStatus;
use App\Enums\DetectionAttribution;
use App\Models\Agent;
use App\Models\Detection;
use App\Models\DetectionRule;
use App\Models\Incident;
use App\Readiness\ReadinessService;
use App\Services\AuditLogger;
use App\Support\DbTime;
use App\Support\LiveUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Deteksi Sensor Buta (FR-09).
 *
 * - Heartbeat hilang lebih dari ambang R12 (bawaan 30 detik): agent BUTA dan
 *   insiden Sensor Buta (Tinggi) dibuat per agent.
 * - Agent yang kembali hidup tercatat di garis waktu insiden (audit log).
 * - Sumber log yang biasanya aktif tetapi diam lebih dari 5 menit, atau yang
 *   dilaporkan agent hilang/error, menghasilkan peringatan.
 *
 * "Biasanya aktif" = ada event pada minimal 30 dari 60 menit sebelum jendela
 * diam. Lonjakan singkat (mis. serangan demo) tidak dianggap aktivitas rutin,
 * sehingga tidak menimbulkan peringatan palsu saat log kembali sepi.
 */
class SensorWatch
{
    public const ACTIVE_MINUTES = 30;

    public const LOOKBACK_MINUTES = 60;

    public function __construct(
        private IncidentCorrelator $correlator,
        private AuditLogger $audit,
    ) {}

    /** @return int jumlah agent yang baru dinyatakan BUTA */
    public function checkHeartbeats(): int
    {
        $rule = DetectionRule::query()->where('code', 'R12')->where('enabled', true)->first();
        if ($rule === null) {
            return 0;
        }
        $timeout = (int) ($rule->config['heartbeat_timeout_s'] ?? 30);

        $silent = Agent::query()
            ->where('status', AgentStatus::Online)
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '<', DbTime::of(now()->subSeconds($timeout)))
            ->get();

        $count = 0;
        foreach ($silent as $agent) {
            // Jeda heartbeat bagian dari Uji Kesiapan (R12): catat sebagai deteksi
            // uji (tanpa insiden, agent tidak dinyatakan BUTA).
            $gap = Cache::get(ReadinessService::gapKey($agent->id));
            if ($gap !== null) {
                if ($gap !== 'tercatat') {
                    Detection::create([
                        'rule_id' => $rule->id,
                        'agent_id' => $agent->id,
                        'unit_id' => $agent->unit_id,
                        'attack_id' => $rule->attack_id,
                        'weight' => $rule->weight,
                        'attribution' => DetectionAttribution::Agent,
                        'detected_at' => now(),
                    ]);
                    Cache::put(ReadinessService::gapKey($agent->id), 'tercatat', now()->addSeconds(120));
                }

                continue;
            }

            $count++;
            DB::transaction(function () use ($agent, $rule) {
                $agent->update(['status' => AgentStatus::Buta]);
                $detection = Detection::create([
                    'rule_id' => $rule->id,
                    'event_id' => null,
                    'agent_id' => $agent->id,
                    'src_ip' => null,
                    'unit_id' => $agent->unit_id,
                    'attack_id' => $rule->attack_id,
                    'weight' => $rule->weight,
                    'attribution' => DetectionAttribution::Agent,
                    'detected_at' => now(),
                ]);
                $detection->setRelation('agent', $agent);
                $incident = $this->correlator->attachAgent($detection);
                $this->audit->record('agent_buta', $incident, [
                    'agent' => $agent->hostname,
                    'heartbeat_terakhir' => $agent->last_heartbeat_at?->toIso8601String(),
                ], null);
            });
            LiveUpdate::notify('agent', [$agent->unit_id]);
        }

        return $count;
    }

    /** Dipanggil saat agent BUTA kembali mengirim heartbeat. */
    public function agentRecovered(Agent $agent, ?\DateTimeInterface $lastHeartbeat): void
    {
        $incident = Incident::query()->open()->where('agent_id', $agent->id)->latest('last_seen_at')->first();
        if ($incident !== null) {
            $this->audit->record('agent_kembali_hidup', $incident, [
                'agent' => $agent->hostname,
                'mati_selama_detik' => $lastHeartbeat ? (int) round(now()->diffInSeconds($lastHeartbeat, true)) : null,
            ], null);
        }
        LiveUpdate::notify('agent', [$agent->unit_id]);
    }

    /**
     * Peringatan sumber log per agent.
     *
     * @param  list<int>|null  $unitIds
     * @return Collection<int, array{agent: Agent, source: string, alasan: string, detail: string}>
     */
    public function sourceWarnings(?array $unitIds = null): Collection
    {
        $rule = DetectionRule::query()->where('code', 'R12')->first();
        $silenceS = (int) ($rule?->config['source_silence_s'] ?? 300);

        $agents = Agent::query()
            ->whereIn('status', [AgentStatus::Online->value, AgentStatus::Buta->value])
            ->when($unitIds !== null, fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->get()
            ->keyBy('id');
        if ($agents->isEmpty()) {
            return collect();
        }

        $warnings = collect();

        // 1) Dilaporkan agent: file log hilang atau collector error.
        foreach ($agents as $agent) {
            foreach ($agent->sources ?? [] as $source => $info) {
                if (in_array($info['status'] ?? '', ['missing', 'error'], true)) {
                    $warnings->push([
                        'agent' => $agent,
                        'source' => $source,
                        'alasan' => $info['status'] === 'missing' ? 'hilang' : 'error',
                        'detail' => (string) ($info['detail'] ?? ''),
                    ]);
                }
            }
        }

        // 2) Biasanya aktif, tetapi diam lebih dari 5 menit.
        $silenceStart = now()->subSeconds($silenceS);
        $lookbackStart = $silenceStart->copy()->subMinutes(self::LOOKBACK_MINUTES);
        $regular = DB::table('events')
            ->whereIn('agent_id', $agents->keys())
            ->where('is_test', false)
            ->whereBetween('occurred_at', [DbTime::of($lookbackStart), DbTime::of($silenceStart)])
            ->groupBy('agent_id', 'source')
            ->havingRaw('count(distinct date_format(occurred_at, "%Y%m%d%H%i")) >= ?', [self::ACTIVE_MINUTES])
            ->get(['agent_id', 'source']);

        foreach ($regular as $row) {
            $recent = DB::table('events')
                ->where('agent_id', $row->agent_id)
                ->where('source', $row->source)
                ->where('occurred_at', '>=', DbTime::of($silenceStart))
                ->exists();
            if (! $recent) {
                $warnings->push([
                    'agent' => $agents[$row->agent_id],
                    'source' => $row->source,
                    'alasan' => 'diam',
                    'detail' => 'biasanya aktif, tidak ada event lebih dari '.intdiv($silenceS, 60).' menit',
                ]);
            }
        }

        return $warnings->values();
    }
}
