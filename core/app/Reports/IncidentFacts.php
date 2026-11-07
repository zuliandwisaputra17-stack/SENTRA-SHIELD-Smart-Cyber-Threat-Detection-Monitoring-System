<?php

namespace App\Reports;

use App\Enums\ActionType;
use App\Enums\KillChainStage;
use App\Models\Detection;
use App\Models\Incident;
use App\Models\ResponseAction;
use App\Models\SecurityEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fakta insiden untuk LAPSIT (FR-12): event, aturan, skor, tindakan.
 * Hanya data dari database; inilah satu-satunya isi prompt LLM, dan template
 * cadangan menulis dari data yang sama.
 */
final class IncidentFacts
{
    /** Batas event bukti yang dikirim ke LLM agar prompt tetap kecil. */
    public const MAX_EVENTS = 24;

    /** Contoh event per aturan: beberapa pertama + satu terakhir. */
    private const FIRST_PER_RULE = 3;

    /**
     * @param  array<string, mixed>  $data  fakta dalam bentuk JSON-able
     * @param  list<int>  $eventIds  ID event yang boleh dikutip
     * @param  list<string>  $ips  IP yang ada di fakta
     */
    private function __construct(
        public readonly array $data,
        public readonly array $eventIds,
        public readonly array $ips,
    ) {}

    public static function for(Incident $incident): self
    {
        $incident->loadMissing(['unit', 'campaign.incidents.unit', 'agent']);
        $detections = Detection::query()
            ->where('incident_id', $incident->id)
            ->with(['rule', 'event.agent', 'agent'])
            ->orderBy('detected_at')
            ->orderBy('id')
            ->get();

        $events = self::sampleEvents($detections);
        $breakdown = $incident->score_breakdown ?? [];

        $data = [
            'insiden' => [
                'id' => $incident->id,
                'judul' => $incident->title,
                'satuan' => $incident->unit->name,
                'ip_sumber' => $incident->src_ip,
                'agent_sensor_buta' => $incident->agent?->hostname,
                'severity' => $incident->severity?->label(),
                'skor' => $incident->score,
                'status' => $incident->status->label(),
                'tahap_tertinggi' => $incident->stage_max?->label(),
                'pertama_terlihat' => self::wib($incident->first_seen_at),
                'terakhir_terlihat' => self::wib($incident->last_seen_at),
                'tingkat_siaga_satuan' => $incident->unit->alert_level?->label(),
            ],
            'rincian_skor' => [
                'rumus' => $breakdown['rumus'] ?? null,
                'pengali_aset' => $breakdown['pengali_aset'] ?? null,
                'pengali_kampanye' => $breakdown['pengali_kampanye']['nilai'] ?? 1,
            ],
            'kampanye' => $incident->campaign ? [
                'id' => $incident->campaign->id,
                'ip' => $incident->campaign->src_ip,
                'satuan_terdampak' => $incident->campaign->incidents->pluck('unit.name')->unique()->values()->all(),
            ] : null,
            'deteksi' => $detections->groupBy('rule_id')->map(function (Collection $group) use ($events) {
                $rule = $group->first()->rule;
                $ids = $group->pluck('event_id')->filter()->unique();

                return [
                    'aturan' => $rule->code.' '.$rule->name,
                    'teknik_attack' => trim($rule->attack_id.' '.$rule->attack_name),
                    'tahap' => $rule->stage->label(),
                    'bobot' => (int) $group->max('weight'),
                    'jumlah_deteksi' => $group->count(),
                    'pertama' => self::wib($group->first()->detected_at),
                    'atribusi' => $group->first()->attribution?->value,
                    'event_bukti' => $ids->intersect($events->keys())->map(fn ($id) => 'EV-'.$id)->values()->all(),
                ];
            })->sortBy(fn ($d) => $d['pertama'])->values()->all(),
            'event' => $events->map(fn ($e) => [
                'id' => 'EV-'.$e->id,
                'waktu' => self::wib($e->occurred_at),
                'sumber' => $e->source->label(),
                'ip' => $e->src_ip,
                'host' => $e->agent?->hostname,
                'ringkasan' => $e->summary(),
            ])->values()->all(),
            'tindakan' => self::actions($incident)->map(fn (ResponseAction $a) => [
                'jenis' => $a->type->label(),
                'ip' => $a->params['ip'] ?? null,
                'agent' => $a->targetAgent?->hostname,
                'status' => $a->status->label(),
                'diusulkan_oleh' => $a->proposer?->name,
                'diputuskan_oleh' => $a->approver?->name,
                'waktu_eksekusi' => self::wib($a->executed_at),
                'berlaku_sampai' => self::wib($a->expires_at),
            ])->values()->all(),
        ];

        $ips = collect([$incident->src_ip, $incident->campaign?->src_ip])
            ->merge($events->pluck('src_ip'))
            ->merge($events->map(fn ($e) => $e->payload['dst_ip'] ?? null))
            ->merge($events->map(fn ($e) => $e->agent?->ip))
            ->merge(self::actions($incident)->map(fn ($a) => $a->params['ip'] ?? null))
            ->filter()->unique()->values()->all();

        return new self($data, $events->keys()->all(), $ips);
    }

    /** JSON untuk prompt LLM. */
    public function json(): string
    {
        // Padat (tanpa spasi) agar prompt lebih sedikit token: penting untuk LLM di CPU laptop.
        return json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Event bukti yang dikirim: setiap aturan pasti terwakili (event pertamanya),
     * lalu ditambah beberapa event berikut + terakhir per aturan sampai
     * MAX_EVENTS; hasil diurutkan menurut waktu.
     *
     * @return Collection<int, SecurityEvent>
     */
    private static function sampleEvents(Collection $detections): Collection
    {
        $groups = $detections->filter(fn ($d) => $d->event !== null)
            ->groupBy('rule_id')
            ->map(fn ($group) => $group->unique('event_id')->values());

        $first = $groups->map(fn ($group) => $group->first());
        $more = $groups->flatMap(fn ($group) => $group->slice(1, self::FIRST_PER_RULE - 1)->push($group->last()));

        return $first->merge($more)->pluck('event')
            ->unique('id')
            ->take(self::MAX_EVENTS)
            ->sortBy([['occurred_at', 'asc'], ['id', 'asc']])
            ->keyBy('id');
    }

    /** @return Collection<int, ResponseAction> */
    public static function actions(Incident $incident): Collection
    {
        return ResponseAction::query()
            ->with(['targetAgent', 'proposer', 'approver'])
            ->whereIn('type', [ActionType::BlockIp, ActionType::UnblockIp])
            ->where(function ($q) use ($incident) {
                $q->where('incident_id', $incident->id);
                if ($incident->campaign_id && $incident->src_ip) {
                    // Blokir serentak kampanye ke agent di satuan insiden ini.
                    $q->orWhere(fn ($c) => $c->where('campaign_id', $incident->campaign_id)
                        ->whereHas('targetAgent', fn ($a) => $a->where('unit_id', $incident->unit_id)));
                }
            })
            ->orderBy('id')
            ->get();
    }

    public static function wib(?\DateTimeInterface $time): ?string
    {
        return $time ? Carbon::instance($time)->setTimezone(config('sentra.display_timezone'))->format('d/m/Y H:i:s').' WIB' : null;
    }

    public static function stageLabel(?string $value): ?string
    {
        return $value ? KillChainStage::tryFrom($value)?->label() : null;
    }
}
