<?php

use App\Detection\DetectionEngine;
use App\Enums\AgentStatus;
use App\Enums\AssetType;
use App\Models\Agent;
use App\Models\Detection;
use App\Models\SecurityEvent;
use App\Models\Unit;
use Illuminate\Support\Carbon;

const PENYERANG = '172.30.0.66';

/** Agent lab di satuan seeder. */
function agentLab(string $hostname, string $unitCode = 'KODIM-LAT-A', AssetType $asset = AssetType::Web): Agent
{
    return Agent::factory()->create([
        'hostname' => $hostname,
        'unit_id' => Unit::where('code', $unitCode)->value('id'),
        'asset_type' => $asset,
        'status' => AgentStatus::Online,
    ]);
}

function eventLab(Agent $agent, string $source, array $payload = [], ?string $ip = PENYERANG, ?int $port = null, ?Carbon $at = null, bool $test = false): SecurityEvent
{
    return SecurityEvent::create([
        'agent_id' => $agent->id,
        'unit_id' => $agent->unit_id,
        'source' => $source,
        'src_ip' => $ip,
        'dst_port' => $port,
        'payload' => $payload,
        'raw' => $source.' uji',
        'is_test' => $test,
        'occurred_at' => $at ?? now(),
        'created_at' => now(),
    ]);
}

/** @return list<Detection> */
function deteksi(SecurityEvent ...$events): array
{
    return app(DetectionEngine::class)->processIds(array_map(fn ($e) => $e->id, $events));
}

function kodeAturan(array $detections): array
{
    return array_values(array_unique(array_map(fn (Detection $d) => $d->rule->code, $detections)));
}

/** Pemindaian n port berbeda dalam beberapa detik. */
function pindai(Agent $agent, int $ports, ?Carbon $start = null, string $ip = PENYERANG): array
{
    $start ??= now()->subSeconds(30);
    $events = [];
    for ($i = 0; $i < $ports; $i++) {
        $events[] = eventLab($agent, 'firewall', ['action' => 'port_tertutup', 'protocol' => 'tcp'], $ip, 1000 + $i, $start->copy()->addMilliseconds($i * 100));
    }

    return $events;
}

function sshGagal(Agent $agent, int $n, ?Carbon $start = null, string $ip = PENYERANG): array
{
    $start ??= now()->subSeconds(20);
    $events = [];
    for ($i = 0; $i < $n; $i++) {
        $events[] = eventLab($agent, 'ssh', ['outcome' => 'failed', 'user' => 'root'], $ip, 22, $start->copy()->addSeconds($i));
    }

    return $events;
}
