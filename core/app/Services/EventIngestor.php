<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan batch event dari agent ke tabel `events` (append-only).
 * Waktu kejadian dinormalkan ke UTC. Mengembalikan ID event yang tersimpan
 * agar bisa diteruskan ke mesin deteksi.
 */
class EventIngestor
{
    /**
     * @param  list<array<string, mixed>>  $events  sudah tervalidasi
     * @return list<int>
     */
    public function ingest(Agent $agent, array $events): array
    {
        $receivedAt = Carbon::now('UTC')->format('Y-m-d H:i:s.v');

        return DB::transaction(function () use ($agent, $events, $receivedAt) {
            $ids = [];
            foreach ($events as $event) {
                $ids[] = DB::table('events')->insertGetId([
                    'agent_id' => $agent->id,
                    'unit_id' => $agent->unit_id,
                    'source' => $event['source'],
                    'src_ip' => $event['src_ip'] ?? null,
                    'dst_port' => $event['dst_port'] ?? null,
                    'payload' => json_encode($event['payload'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'raw' => $event['raw'] ?? null,
                    'is_test' => (bool) ($event['is_test'] ?? false),
                    'occurred_at' => Carbon::parse($event['occurred_at'])->utc()->format('Y-m-d H:i:s.v'),
                    'created_at' => $receivedAt,
                ]);
            }

            return $ids;
        });
    }
}
