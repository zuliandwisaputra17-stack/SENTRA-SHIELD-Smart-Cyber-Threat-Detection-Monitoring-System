<?php

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Enums\AgentStatus;
use App\Enums\Role;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\ResponseAction;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\AgentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

function agentMenunggu(): array
{
    $agent = Agent::factory()->create(['status' => AgentStatus::Menunggu, 'ip' => null, 'version' => null]);
    $code = app(AgentEnrollment::class)->issueCode($agent);

    return [$agent, $code];
}

function daftarkan(string $code, string $hostname = 'kodim-a'): TestResponse
{
    return test()->postJson('/api/v1/agent/register', [
        'enrollment_code' => $code,
        'hostname' => $hostname,
        'ip' => '172.31.0.11',
        'version' => '0.1.0',
    ]);
}

function agentTerdaftar(): array
{
    [$agent, $code] = agentMenunggu();
    $token = daftarkan($code)->json('token');

    return [$agent->fresh(), $token];
}

function eventWeb(array $override = []): array
{
    return array_merge([
        'source' => 'web',
        'occurred_at' => '2026-11-12T09:14:03+07:00',
        'src_ip' => '172.20.0.66',
        'payload' => ['method' => 'GET', 'path' => '/berita.php', 'query' => "id=1' OR '1'='1", 'status' => 500, 'user_agent' => 'sqlmap/1.8'],
        'raw' => '172.20.0.66 - - [12/Nov/2026:09:14:03 +0700] "GET /berita.php?id=1\'..."',
        'is_test' => false,
    ], $override);
}

test('agent mendaftar dengan kode sekali pakai dan menerima token', function () {
    [$agent, $code] = agentMenunggu();

    daftarkan($code)
        ->assertOk()
        ->assertJsonStructure(['agent_id', 'token', 'unit' => ['id', 'code', 'name'], 'heartbeat_interval_s', 'max_batch'])
        ->assertJson(['agent_id' => $agent->id, 'heartbeat_interval_s' => 10, 'max_batch' => 500]);

    $agent->refresh();
    expect($agent->status)->toBe(AgentStatus::Online)
        ->and($agent->hostname)->toBe('kodim-a')
        ->and($agent->enrollment_code_hash)->toBeNull()
        ->and(AuditLog::where('action', 'agent_terdaftar')->count())->toBe(1);
});

test('kode pendaftaran tidak bisa dipakai dua kali', function () {
    [, $code] = agentMenunggu();

    daftarkan($code)->assertOk();
    daftarkan($code, 'penyusup')->assertUnauthorized();
});

test('kode salah atau kedaluwarsa ditolak', function () {
    [$agent, $code] = agentMenunggu();

    daftarkan('SNTR-SALAH-0000-0000')->assertUnauthorized();

    $agent->update(['enrollment_expires_at' => now()->subMinute()]);
    daftarkan($code)->assertUnauthorized();
});

test('token agent disimpan ter-hash, bukan teks asli', function () {
    [$agent, $token] = agentTerdaftar();
    [, $secret] = explode('|', $token, 2);

    $stored = $agent->tokens()->first()->token;
    expect($stored)->toBe(hash('sha256', $secret))->not->toBe($secret);
});

test('heartbeat memperbarui status, versi, dan sumber log', function () {
    [$agent, $token] = agentTerdaftar();
    $agent->update(['last_heartbeat_at' => now()->subHour()]);

    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', [
        'version' => '0.2.0',
        'sources' => [
            'web' => ['status' => 'ok', 'detail' => '/var/log/nginx/access.log'],
            'ssh' => ['status' => 'missing', 'detail' => 'file tidak ditemukan'],
        ],
    ])->assertOk()->assertJsonStructure(['server_time', 'pending_commands']);

    $agent->refresh();
    expect($agent->version)->toBe('0.2.0')
        ->and($agent->sources['ssh']['status'])->toBe('missing')
        ->and($agent->last_heartbeat_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('batch event tersimpan dengan waktu UTC dan satuan agent', function () {
    [$agent, $token] = agentTerdaftar();

    $this->withToken($token)->postJson('/api/v1/agent/events', ['events' => [eventWeb(), eventWeb(['src_ip' => '172.20.0.67'])]])
        ->assertOk()
        ->assertJson(['accepted' => 2]);

    $event = SecurityEvent::orderBy('id')->first();
    expect(SecurityEvent::count())->toBe(2)
        ->and($event->agent_id)->toBe($agent->id)
        ->and($event->unit_id)->toBe($agent->unit_id)
        ->and($event->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-11-12 02:14:03')
        ->and($event->payload['user_agent'])->toBe('sqlmap/1.8')
        ->and($event->is_test)->toBeFalse();
});

test('skema event tidak valid ditolak 422', function (array $event) {
    [, $token] = agentTerdaftar();

    $this->withToken($token)->postJson('/api/v1/agent/events', ['events' => [$event]])->assertStatus(422);
    expect(SecurityEvent::count())->toBe(0);
})->with([
    'sumber tidak dikenal' => [eventWeb(['source' => 'printer'])],
    'sumber site hanya dari Core' => [eventWeb(['source' => 'site'])],
    'IP tidak valid' => [eventWeb(['src_ip' => '999.1.1.1'])],
    'port di luar rentang' => [eventWeb(['dst_port' => 70000])],
    'waktu tidak valid' => [eventWeb(['occurred_at' => 'kemarin sore'])],
    'payload bukan objek' => [eventWeb(['payload' => 'teks'])],
]);

test('batch lebih dari 500 event ditolak 422', function () {
    [, $token] = agentTerdaftar();

    $this->withToken($token)->postJson('/api/v1/agent/events', ['events' => array_fill(0, 501, eventWeb())])
        ->assertStatus(422);
});

test('tanpa token atau token salah ditolak 401', function () {
    $this->postJson('/api/v1/agent/heartbeat', ['version' => '1', 'sources' => []])->assertUnauthorized();
    $this->withToken('1|palsu')->postJson('/api/v1/agent/events', ['events' => [eventWeb()]])->assertUnauthorized();
});

test('agent yang dicabut langsung ditolak 401', function () {
    [$agent, $token] = agentTerdaftar();
    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', ['version' => '1', 'sources' => []])->assertOk();

    app(AgentEnrollment::class)->revoke($agent);
    app('auth')->forgetGuards();

    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', ['version' => '1', 'sources' => []])
        ->assertUnauthorized()
        ->assertJson(['message' => 'Token agent salah atau dicabut.']);
    expect($agent->fresh()->status)->toBe(AgentStatus::Dicabut);
});

test('token pengguna web tidak bisa memakai API agent', function () {
    // User sengaja tidak memakai HasApiTokens; token disisipkan langsung untuk
    // mensimulasikan token milik tokenable selain Agent.
    $user = User::factory()->role(Role::Admin)->create();
    $id = DB::table('personal_access_tokens')->insertGetId([
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'coba',
        'token' => hash('sha256', 'rahasia-uji'),
        'abilities' => '["*"]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $token = $id.'|rahasia-uji';

    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', ['version' => '1', 'sources' => []])->assertUnauthorized();
});

test('terlalu banyak permintaan dibalas 429', function () {
    [, $token] = agentTerdaftar();

    $codes = [];
    foreach (range(1, 605) as $i) {
        $codes[] = $this->withToken($token)->getJson('/api/v1/agent/commands')->status();
    }
    expect($codes)->toContain(429);
});

test('agent hanya menerima perintah yang sudah disetujui miliknya sendiri', function () {
    [$agent, $token] = agentTerdaftar();
    $lain = Agent::factory()->create();
    $komandan = User::factory()->role(Role::Komandan)->create(['name' => 'Komandan Kodim A']);

    $disetujui = ResponseAction::create(['type' => ActionType::BlockIp, 'target_agent_id' => $agent->id, 'params' => ['ip' => '172.30.0.66', 'duration_s' => 3600], 'status' => ActionStatus::Disetujui, 'approved_by' => $komandan->id]);
    ResponseAction::create(['type' => ActionType::BlockIp, 'target_agent_id' => $agent->id, 'params' => ['ip' => '1.1.1.1'], 'status' => ActionStatus::Diusulkan]);
    ResponseAction::create(['type' => ActionType::BlockIp, 'target_agent_id' => $lain->id, 'params' => ['ip' => '2.2.2.2'], 'status' => ActionStatus::Disetujui]);

    $this->withToken($token)->getJson('/api/v1/agent/commands')
        ->assertOk()
        ->assertExactJson(['commands' => [[
            'id' => $disetujui->id,
            'type' => 'block_ip',
            'params' => ['ip' => '172.30.0.66', 'duration_s' => 3600],
            'approved_by' => 'Komandan Kodim A',
        ]]]);
});

test('hasil eksekusi blokir dicatat beserta waktu kedaluwarsa', function () {
    [$agent, $token] = agentTerdaftar();
    $action = ResponseAction::create(['type' => ActionType::BlockIp, 'target_agent_id' => $agent->id, 'params' => ['ip' => '172.30.0.66', 'duration_s' => 3600], 'status' => ActionStatus::Disetujui]);

    $this->withToken($token)->postJson("/api/v1/agent/commands/{$action->id}/result", [
        'status' => 'success', 'message' => 'IP diblokir di nftables', 'executed_at' => '2026-11-12T02:20:00Z',
    ])->assertOk()->assertJson(['status' => 'dieksekusi']);

    $action->refresh();
    expect($action->status)->toBe(ActionStatus::Dieksekusi)
        ->and($action->expires_at->toIso8601ZuluString())->toBe('2026-11-12T03:20:00Z')
        ->and($action->result['message'])->toBe('IP diblokir di nftables')
        ->and(AuditLog::where('action', 'tindakan_dieksekusi')->count())->toBe(1);

    // Laporan kedua tidak mengubah apa pun.
    $this->withToken($token)->postJson("/api/v1/agent/commands/{$action->id}/result", ['status' => 'failed'])->assertOk();
    expect($action->fresh()->status)->toBe(ActionStatus::Dieksekusi);
});

test('agent tidak bisa melaporkan perintah milik agent lain', function () {
    [, $token] = agentTerdaftar();
    $lain = Agent::factory()->create();
    $action = ResponseAction::create(['type' => ActionType::BlockIp, 'target_agent_id' => $lain->id, 'params' => ['ip' => '2.2.2.2'], 'status' => ActionStatus::Disetujui]);

    $this->withToken($token)->postJson("/api/v1/agent/commands/{$action->id}/result", ['status' => 'success'])->assertNotFound();
    expect($action->fresh()->status)->toBe(ActionStatus::Disetujui);
});
