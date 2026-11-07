<?php

namespace App\Detection;

use App\Enums\AlertLevel;
use App\Enums\AssetType;
use App\Enums\KillChainStage;
use App\Enums\Severity;
use App\Models\Agent;
use App\Models\Incident;
use App\Models\Unit;

/**
 * Skor risiko yang bisa dijelaskan (FR-05, PRD bagian 10):
 *
 *   S = (Σ w_r untuk aturan unik + 20 · (T − 1)) · m_aset · m_kampanye
 *
 * w = bobot tiap aturan unik (aturan berulang dihitung sekali), T = jumlah tahap
 * kill chain berbeda, m_aset = 1,5 OT / 1,2 server web / 1,0 lainnya (nilai
 * tertinggi dari agent yang terlibat), m_kampanye = 1,3 bila tertaut kampanye.
 */
class RiskScorer
{
    public const STAGE_BONUS = 20;

    public const CAMPAIGN_MULTIPLIER = 1.3;

    /** Hitung ulang skor, rincian, severity, tahap tertinggi, lalu Tingkat Siaga satuan. */
    public function apply(Incident $incident): Incident
    {
        $result = $this->compute($incident);

        $incident->forceFill([
            'score' => $result['total'],
            'score_breakdown' => $result,
            'severity' => Severity::fromScore($result['total']),
            'stage_max' => $result['tahap']['tertinggi'],
        ])->save();

        $this->refreshUnitAlert($incident->unit_id);

        return $incident;
    }

    /** @return array<string, mixed> */
    public function compute(Incident $incident): array
    {
        $detections = $incident->detections()->with('rule')->get();

        $rules = $detections->groupBy('rule_id')->map(function ($group) {
            $rule = $group->first()->rule;

            return [
                'kode' => $rule->code,
                'label' => $rule->name,
                'teknik' => $rule->attack_id,
                'tahap' => $rule->stage->value,
                'poin' => (int) $group->max('weight'),
                'jumlah_deteksi' => $group->count(),
            ];
        })->sortBy('kode')->values();

        $stages = $rules->pluck('tahap')->unique()
            ->map(fn ($s) => KillChainStage::from($s))
            ->sortBy(fn (KillChainStage $s) => $s->order())
            ->values();
        $stageCount = $stages->count();
        $stageBonus = self::STAGE_BONUS * max(0, $stageCount - 1);
        $subtotal = (int) $rules->sum('poin') + $stageBonus;

        // Deteksi Penjaga Situs tidak punya agent; asetnya situs web satuan.
        $siteWatched = $detections->contains(fn ($d) => $d->agent_id === null && in_array('site', $d->rule->sources(), true));
        [$assetMultiplier, $assetLabel, $assetHost] = $this->assetMultiplier($detections->pluck('agent_id')->filter()->unique()->all(), $siteWatched);
        $campaignActive = $incident->campaign_id !== null;
        $campaignMultiplier = $campaignActive ? self::CAMPAIGN_MULTIPLIER : 1.0;

        $total = (int) round($subtotal * $assetMultiplier * $campaignMultiplier);

        return [
            'aturan' => $rules->all(),
            'tahap' => [
                'jumlah' => $stageCount,
                'daftar' => $stages->map->value->all(),
                'tertinggi' => $stages->last()?->value,
                'poin' => $stageBonus,
            ],
            'subtotal' => $subtotal,
            'pengali_aset' => ['nilai' => $assetMultiplier, 'label' => $assetLabel, 'host' => $assetHost],
            'pengali_kampanye' => ['nilai' => $campaignMultiplier, 'aktif' => $campaignActive],
            'total' => $total,
            'rumus' => sprintf(
                '(%d + %d) x %s x %s = %d',
                (int) $rules->sum('poin'),
                $stageBonus,
                rtrim(rtrim(number_format($assetMultiplier, 2, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($campaignMultiplier, 2, '.', ''), '0'), '.'),
                $total,
            ),
        ];
    }

    /**
     * @param  list<int>  $agentIds
     * @return array{0: float, 1: string, 2: ?string}
     */
    private function assetMultiplier(array $agentIds, bool $siteWatched = false): array
    {
        $best = $siteWatched
            ? [AssetType::Web->multiplier(), AssetType::Web->label(), 'situs dipantau']
            : [AssetType::Lainnya->multiplier(), AssetType::Lainnya->label(), null];
        foreach (Agent::query()->whereIn('id', $agentIds)->get() as $agent) {
            if ($agent->asset_type->multiplier() > $best[0]) {
                $best = [$agent->asset_type->multiplier(), $agent->asset_type->label(), $agent->hostname];
            }
        }

        return $best;
    }

    /** Tingkat Siaga satuan = severity tertinggi dari insiden terbukanya. */
    public function refreshUnitAlert(int $unitId): void
    {
        $highest = Incident::query()->open()->where('unit_id', $unitId)->get(['severity'])
            ->map->severity
            ->sortByDesc(fn (Severity $s) => $s->rank())
            ->first();

        Unit::query()->whereKey($unitId)->update(['alert_level' => AlertLevel::fromSeverity($highest)->value]);
    }
}
