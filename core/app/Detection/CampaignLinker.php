<?php

namespace App\Detection;

use App\Enums\CampaignStatus;
use App\Enums\IncidentStatus;
use App\Models\Campaign;
use App\Models\Incident;
use App\Services\AuditLogger;
use App\Support\DbTime;
use App\Support\LiveUpdate;

/**
 * Korelasi lintas satuan / Kampanye (FR-10, aturan korelasi 2):
 * bila IP yang sama punya insiden di dua satuan atau lebih dalam 24 jam
 * terakhir, semua insiden itu ditautkan ke satu Kampanye dan skornya
 * dikalikan 1,3 (lihat RiskScorer).
 */
class CampaignLinker
{
    public const WINDOW_HOURS = 24;

    public function __construct(
        private RiskScorer $scorer,
        private AuditLogger $audit,
    ) {}

    public function link(Incident $incident): ?Campaign
    {
        if ($incident->src_ip === null || $incident->agent_id !== null) {
            return null;
        }

        $since = DbTime::of($incident->last_seen_at->copy()->subHours(self::WINDOW_HOURS));
        $related = Incident::query()
            ->where('src_ip', $incident->src_ip)
            ->whereNull('agent_id')
            ->where('last_seen_at', '>=', $since)
            ->orderBy('first_seen_at')
            ->get();

        if ($related->pluck('unit_id')->unique()->count() < 2) {
            return null;
        }

        $campaign = $related->pluck('campaign_id')->filter()->map(fn ($id) => Campaign::find($id))
            ->first(fn (?Campaign $c) => $c && $c->status === CampaignStatus::Aktif);
        $created = $campaign === null;
        $campaign ??= Campaign::create([
            'src_ip' => $incident->src_ip,
            'unit_count' => 0,
            'first_seen_at' => $related->min('first_seen_at'),
            'last_seen_at' => $related->max('last_seen_at'),
            'status' => CampaignStatus::Aktif,
        ]);

        foreach ($related as $item) {
            if ($item->campaign_id !== $campaign->id) {
                $item->campaign_id = $campaign->id;
                $this->scorer->apply($item);
            }
        }

        $campaign->update([
            'unit_count' => $related->pluck('unit_id')->unique()->count(),
            'first_seen_at' => $related->min('first_seen_at'),
            'last_seen_at' => $related->max('last_seen_at'),
        ]);

        if ($created) {
            $this->audit->record('kampanye_dibuat', $campaign, [
                'ip' => $campaign->src_ip,
                'satuan' => $related->pluck('unit_id')->unique()->values()->all(),
            ], null);
        }
        LiveUpdate::notify('kampanye', $related->pluck('unit_id')->all());

        return $campaign;
    }

    /** Kampanye selesai bila semua insiden tertautnya sudah selesai. */
    public function refreshStatus(?Campaign $campaign): void
    {
        if ($campaign === null) {
            return;
        }
        $open = $campaign->incidents()->whereIn('status', IncidentStatus::openValues())->exists();
        $campaign->update(['status' => $open ? CampaignStatus::Aktif : CampaignStatus::Selesai]);
    }
}
