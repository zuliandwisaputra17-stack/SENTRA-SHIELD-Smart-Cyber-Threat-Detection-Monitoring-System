<?php

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\SecurityEvent;
use App\Services\AuditLogger;

test('sentra:status menampilkan agent dan event asli', function () {
    $agent = Agent::factory()->create(['hostname' => 'kodim-a', 'sources' => ['web' => ['status' => 'ok'], 'modbus' => ['status' => 'disabled']]]);
    SecurityEvent::create([
        'agent_id' => $agent->id, 'unit_id' => $agent->unit_id, 'source' => 'ssh', 'src_ip' => '172.30.0.66',
        'payload' => ['outcome' => 'failed'], 'occurred_at' => now(), 'created_at' => now(),
    ]);

    $this->artisan('sentra:status', ['--tunggu-agent' => 1, '--batas' => 1])
        // Satu baris output hanya mencocokkan satu ekspektasi; baris agent memuat hostname dan sumbernya.
        ->expectsOutputToContain('web=ok')
        ->doesntExpectOutputToContain('modbus=disabled')
        ->expectsOutputToContain('ssh             1 event')
        ->expectsOutputToContain('HASIL: LULUS - 1 agent online.')
        ->assertSuccessful();
});

test('sentra:status gagal bila agent online kurang dari target', function () {
    Agent::factory()->create(['status' => AgentStatus::Buta]);

    $this->artisan('sentra:status', ['--tunggu-agent' => 3, '--batas' => 0])
        ->expectsOutputToContain('(belum ada event)')
        ->expectsOutputToContain('HASIL: GAGAL - agent online 0 dari 3.')
        ->assertFailed();
});

test('sentra:status menampilkan mode log firewall', function () {
    Agent::factory()->create(['hostname' => 'kodim-b', 'sources' => ['firewall' => ['status' => 'ok', 'detail' => 'raw socket (cadangan NFLOG)']]]);

    $this->artisan('sentra:status')
        ->expectsOutputToContain('firewall=ok(raw socket)')
        ->assertSuccessful();
});

test('sentra:status merangkum Uji Kesiapan terakhir dan verifikasi rantai audit (bukti demo)', function () {
    $this->seed();
    app(AuditLogger::class)->record('login');

    $this->artisan('sentra:status')
        ->expectsOutputToContain('UJI KESIAPAN TERAKHIR')
        ->expectsOutputToContain('(belum pernah dijalankan)')
        ->expectsOutputToContain('Rantai utuh: 1 baris diperiksa, semua hash cocok.');
});
