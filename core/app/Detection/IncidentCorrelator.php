<?php

namespace App\Detection;

use App\Enums\EventSource;
use App\Enums\IncidentStatus;
use App\Enums\Severity;
use App\Models\Detection;
use App\Models\Incident;
use App\Models\Unit;
use App\Support\DbTime;

/**
 * Korelasi deteksi menjadi insiden / Attack Story (FR-04, PRD bagian 10).
 *
 * 1. Deteksi dengan IP dicocokkan ke insiden terbuka di satuan yang sama dengan
 *    IP yang sama dan last_seen_at kurang dari 30 menit lalu; bila tidak ada,
 *    insiden baru dibuat.
 * 2. Deteksi tanpa IP (FIM) ditempelkan ke insiden terbuka paling baru aktif di
 *    satuan yang sama (30 menit) dengan label "Atribusi berdasarkan waktu"
 *    (keputusan A2); bila tidak ada, ke insiden tanpa IP di satuan itu atau
 *    insiden baru.
 */
class IncidentCorrelator
{
    public const WINDOW_MINUTES = 30;

    public function __construct(private RiskScorer $scorer) {}

    public function attach(Detection $detection): Incident
    {
        $time = $detection->event?->occurred_at ?? $detection->detected_at;
        $since = DbTime::of($time->copy()->subMinutes(self::WINDOW_MINUTES));

        $open = Incident::query()->open()
            ->where('unit_id', $detection->unit_id)
            ->whereNull('agent_id')
            ->where('last_seen_at', '>=', $since)
            ->lockForUpdate();

        if ($detection->src_ip !== null) {
            $incident = (clone $open)->where('src_ip', $detection->src_ip)->orderByDesc('last_seen_at')->first();
        } else {
            // Atribusi "waktu" sudah ditetapkan DetectionEngine untuk deteksi tanpa IP.
            $incident = (clone $open)->whereNotNull('src_ip')->orderByDesc('last_seen_at')->first()
                ?? (clone $open)->whereNull('src_ip')->orderByDesc('last_seen_at')->first();
        }

        $incident ??= Incident::create([
            'unit_id' => $detection->unit_id,
            'src_ip' => $detection->src_ip,
            'title' => $this->title($detection),
            'severity' => Severity::Rendah,
            'status' => IncidentStatus::Baru,
            'first_seen_at' => $time,
            'last_seen_at' => $time,
        ]);

        $detection->incident_id = $incident->id;
        $detection->save();

        $incident->first_seen_at = $incident->first_seen_at->min($time);
        $incident->last_seen_at = $incident->last_seen_at->max($time);
        $this->scorer->apply($incident);
        app(CampaignLinker::class)->link($incident);

        return $incident;
    }

    /**
     * Aturan korelasi 3: deteksi R12 (Sensor Buta) membentuk insidennya sendiri
     * per agent karena tidak punya IP penyerang. Agent yang mati lagi dalam 30
     * menit masuk ke insiden yang sama.
     */
    public function attachAgent(Detection $detection): Incident
    {
        $time = $detection->detected_at;
        $incident = Incident::query()->open()
            ->where('agent_id', $detection->agent_id)
            ->where('last_seen_at', '>=', DbTime::of($time->copy()->subMinutes(self::WINDOW_MINUTES)))
            ->lockForUpdate()
            ->orderByDesc('last_seen_at')
            ->first();

        if ($incident === null) {
            $hostname = $detection->agent?->hostname ?? 'agent #'.$detection->agent_id;
            $incident = Incident::create([
                'unit_id' => $detection->unit_id,
                'agent_id' => $detection->agent_id,
                'src_ip' => null,
                'title' => "Sensor Buta: {$hostname} berhenti mengirim heartbeat",
                'severity' => Severity::Rendah,
                'status' => IncidentStatus::Baru,
                'first_seen_at' => $time,
                'last_seen_at' => $time,
            ]);
        }

        $detection->incident_id = $incident->id;
        $detection->save();
        $incident->last_seen_at = $incident->last_seen_at->max($time);
        $this->scorer->apply($incident);

        return $incident;
    }

    private function title(Detection $detection): string
    {
        $unit = Unit::query()->whereKey($detection->unit_id)->value('name');

        if ($detection->src_ip) {
            return "Serangan dari {$detection->src_ip} terhadap {$unit}";
        }
        if ($detection->event?->source === EventSource::Site) {
            $host = parse_url((string) ($detection->event->payload['url'] ?? ''), PHP_URL_HOST) ?: 'situs';

            return ($detection->event->payload['temuan'] ?? '') === 'cloaking'
                ? "Injeksi judi online pada situs {$host} ({$unit})"
                : "Perubahan halaman utama situs {$host} ({$unit})";
        }

        return "Aktivitas mencurigakan pada file di {$unit}";
    }
}
