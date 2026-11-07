<?php

namespace App\Detection;

use App\Enums\DetectionAttribution;
use App\Enums\RuleType;
use App\Models\Detection;
use App\Models\DetectionRule;
use App\Models\SecurityEvent;
use App\Support\DbTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mesin deteksi berbasis aturan (FR-03).
 *
 * - Aturan pola dievaluasi per event; satu deteksi per event yang cocok.
 * - Aturan ambang memakai jendela waktu berdasarkan waktu kejadian event dan
 *   menghasilkan satu deteksi per (aturan, satuan, IP) per jendela.
 * - IP di daftar putih tidak memicu deteksi; event-nya tetap tersimpan.
 * - Event Uji Kesiapan (is_test) dihitung terpisah dan tidak pernah menjadi insiden.
 */
class DetectionEngine
{
    /** @var Collection<int, DetectionRule>|null */
    private ?Collection $rules = null;

    private ?IpWhitelist $whitelist = null;

    public function __construct(
        private RuleMatcher $matcher,
        private IncidentCorrelator $correlator,
    ) {}

    /**
     * @param  list<int>  $eventIds
     * @return list<Detection>
     */
    public function processIds(array $eventIds): array
    {
        $this->rules = DetectionRule::query()->enabled()->get();
        $this->whitelist = IpWhitelist::fromSettings();

        $created = [];
        SecurityEvent::query()->whereIn('id', $eventIds)->orderBy('id')
            ->each(function (SecurityEvent $event) use (&$created) {
                foreach ($this->evaluate($event) as $detection) {
                    $created[] = $detection;
                }
            }, 200);

        return $created;
    }

    /** @return list<Detection> */
    public function evaluate(SecurityEvent $event): array
    {
        $this->rules ??= DetectionRule::query()->enabled()->get();
        $this->whitelist ??= IpWhitelist::fromSettings();

        if ($this->whitelist->contains($event->src_ip)) {
            return [];
        }

        $detections = [];
        foreach ($this->rules as $rule) {
            if (! in_array($event->source->value, $rule->sources(), true)) {
                continue;
            }
            $hit = match ($rule->type) {
                RuleType::Pattern => $this->matcher->matchesPattern($rule, $event) && $this->prerequisiteMet($rule, $event),
                RuleType::Threshold => $this->thresholdReached($rule, $event),
            };
            if ($hit) {
                $detections[] = $this->record($rule, $event);
            }
        }

        return $detections;
    }

    private function record(DetectionRule $rule, SecurityEvent $event): Detection
    {
        return DB::transaction(function () use ($rule, $event) {
            $detection = Detection::create([
                'rule_id' => $rule->id,
                'event_id' => $event->id,
                'agent_id' => $event->agent_id,
                'src_ip' => $event->src_ip,
                'unit_id' => $event->unit_id,
                'attack_id' => $rule->attack_id,
                'weight' => $rule->weight,
                'attribution' => $event->src_ip ? DetectionAttribution::Ip : DetectionAttribution::Waktu,
                'detected_at' => now(),
            ]);
            $detection->setRelation('rule', $rule)->setRelation('event', $event);

            if (! $event->is_test) {
                $this->correlator->attach($detection);
            }

            return $detection;
        });
    }

    /** R08: login berhasil hanya dihitung bila IP yang sama memicu R07 sebelumnya. */
    private function prerequisiteMet(DetectionRule $rule, SecurityEvent $event): bool
    {
        $required = $rule->config['requires_rule'] ?? null;
        if ($required === null) {
            return true;
        }
        if ($event->src_ip === null) {
            return false;
        }
        $within = (int) ($rule->config['within_s'] ?? 1800);

        return Detection::query()
            ->whereHas('rule', fn ($q) => $q->where('code', $required))
            ->where('unit_id', $event->unit_id)
            ->where('src_ip', $event->src_ip)
            ->whereHas('event', fn ($q) => $q
                ->where('is_test', $event->is_test)
                ->whereBetween('occurred_at', [DbTime::of($event->occurred_at->copy()->subSeconds($within)), DbTime::of($event->occurred_at)]))
            ->exists();
    }

    private function thresholdReached(DetectionRule $rule, SecurityEvent $event): bool
    {
        $cfg = $rule->config;
        if (! isset($cfg['window_s'], $cfg['threshold']) || $event->src_ip === null) {
            return false;
        }
        if (isset($cfg['match']) && ! $this->matcher->matchesConditions($cfg['match'], $event->payload ?? [])) {
            return false;
        }

        $from = DbTime::of($event->occurred_at->copy()->subSeconds((int) $cfg['window_s']));
        $until = DbTime::of($event->occurred_at);

        // Sudah terdeteksi di jendela ini: jangan buat deteksi ganda.
        $suppressed = Detection::query()
            ->where('rule_id', $rule->id)
            ->where('unit_id', $event->unit_id)
            ->where('src_ip', $event->src_ip)
            ->whereHas('event', fn ($q) => $q->where('is_test', $event->is_test)->where('occurred_at', '>=', $from))
            ->exists();
        if ($suppressed) {
            return false;
        }

        $query = SecurityEvent::query()
            ->where('unit_id', $event->unit_id)
            ->where('src_ip', $event->src_ip)
            ->where('source', $event->source)
            ->where('is_test', $event->is_test)
            ->whereBetween('occurred_at', [$from, $until]);
        foreach ($cfg['match'] ?? [] as $key => $value) {
            $query->where("payload->{$key}", $value);
        }

        $count = isset($cfg['distinct_field'])
            ? (int) $query->distinct()->count($cfg['distinct_field'])
            : $query->count();

        return $count >= (int) $cfg['threshold'];
    }
}
