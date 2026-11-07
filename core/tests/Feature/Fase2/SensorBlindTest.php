<?php

use App\Detection\SensorWatch;
use App\Enums\AgentStatus;
use App\Enums\AssetType;
use App\Models\AuditLog;
use App\Models\Detection;
use App\Models\DetectionRule;
use App\Models\Incident;
use App\Models\User;
use App\Services\AgentEnrollment;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../Fase1b/helpers.php';

beforeEach(function () {
    $this->seed();
    $this->web = agentLab('kodim-b', 'KODIM-LAT-B');
    $this->web->update(['last_heartbeat_at' => now()]);
    $this->watch = app(SensorWatch::class);
});

test('heartbeat hilang lebih dari 30 detik: agent BUTA dan insiden Tinggi', function () {
    $this->travel(25)->seconds();
    expect($this->watch->checkHeartbeats())->toBe(0);

    $this->travel(10)->seconds();
    expect($this->watch->checkHeartbeats())->toBe(1);

    $incident = Incident::sole();
    expect($this->web->fresh()->status)->toBe(AgentStatus::Buta)
        ->and($incident->agent_id)->toBe($this->web->id)
        ->and($incident->src_ip)->toBeNull()
        ->and($incident->title)->toContain('Sensor Buta: kodim-b')
        ->and($incident->score)->toBe(96)
        ->and($incident->severity->value)->toBe('tinggi')
        ->and($incident->stage_max->value)->toBe('pertahanan_dilumpuhkan')
        ->and($incident->unit->fresh()->alert_level->value)->toBe('siaga')
        ->and(AuditLog::where('action', 'agent_buta')->sole()->subject_id)->toBe($incident->id);

    // Pemeriksaan berikutnya tidak membuat alarm ganda.
    $this->travel(10)->seconds();
    expect($this->watch->checkHeartbeats())->toBe(0)
        ->and(Detection::count())->toBe(1);
});

test('insiden Sensor Buta tetap Tinggi untuk semua jenis aset', function (AssetType $asset, int $score) {
    $this->web->update(['asset_type' => $asset]);
    $this->travel(31)->seconds();
    $this->watch->checkHeartbeats();

    expect(Incident::sole()->score)->toBe($score)
        ->and(Incident::sole()->severity->value)->toBe('tinggi');
})->with([
    [AssetType::Lainnya, 80],
    [AssetType::Web, 96],
    [AssetType::Ot, 120],
]);

test('agent yang kembali hidup tercatat di garis waktu insiden', function () {
    $code = app(AgentEnrollment::class)->issueCode($this->web);
    $token = $this->postJson('/api/v1/agent/register', ['enrollment_code' => $code, 'hostname' => 'kodim-b', 'version' => '0.1.0'])->json('token');

    $this->travel(40)->seconds();
    $this->watch->checkHeartbeats();
    $incident = Incident::sole();

    $this->travel(20)->seconds();
    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', ['version' => '0.1.0', 'sources' => []])->assertOk();

    $log = AuditLog::where('action', 'agent_kembali_hidup')->sole();
    expect($this->web->fresh()->status)->toBe(AgentStatus::Online)
        ->and($log->subject_id)->toBe($incident->id)
        ->and($log->data['mati_selama_detik'])->toBeGreaterThanOrEqual(59);

    $this->actingAs(User::where('email', 'analis@sentra.lab')->first())
        ->get(route('incidents.show', $incident))
        ->assertOk()
        ->assertSee('Garis waktu')
        ->assertSee('dinyatakan BUTA')
        ->assertSee('kembali hidup setelah')
        ->assertSee('Pertahanan dilumpuhkan')
        ->assertSee('dideteksi Core dari heartbeat yang hilang');
});

test('agent yang mati lagi dalam 30 menit masuk ke insiden yang sama', function () {
    $this->travel(31)->seconds();
    $this->watch->checkHeartbeats();
    // Agent hidup sebentar (data segar: objek lama masih berstatus online di memori).
    $this->web->fresh()->update(['status' => AgentStatus::Online, 'last_heartbeat_at' => now()]);

    $this->travel(5)->minutes();
    $this->watch->checkHeartbeats();

    expect(Incident::count())->toBe(1)
        ->and(Detection::count())->toBe(2)
        ->and(Incident::sole()->score)->toBe(96);
});

test('R12 yang dinonaktifkan tidak menimbulkan alarm', function () {
    DetectionRule::where('code', 'R12')->update(['enabled' => false]);
    $this->travel(5)->minutes();

    expect($this->watch->checkHeartbeats())->toBe(0)
        ->and($this->web->fresh()->status)->toBe(AgentStatus::Online);
});

test('sumber log rutin yang diam lebih dari 5 menit menghasilkan peringatan', function () {
    $start = now()->subMinutes(70);
    for ($m = 0; $m < 60; $m++) {
        eventLab($this->web, 'web', ['path' => '/', 'status' => 200], '10.1.1.'.($m % 200 + 1), null, Carbon::instance($start)->addMinutes($m));
    }

    $warnings = $this->watch->sourceWarnings();
    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]['source'])->toBe('web')
        ->and($warnings[0]['alasan'])->toBe('diam');

    eventLab($this->web, 'web', ['path' => '/', 'status' => 200], '10.1.1.1', null, now());
    expect($this->watch->sourceWarnings())->toHaveCount(0);
});

test('lonjakan singkat (serangan) yang lalu sepi tidak dianggap sumber rutin', function () {
    for ($i = 0; $i < 200; $i++) {
        eventLab($this->web, 'web', ['path' => '/x', 'status' => 404], PENYERANG, null, now()->subMinutes(20)->addSeconds($i));
    }

    expect($this->watch->sourceWarnings())->toHaveCount(0);
});

test('sumber yang dilaporkan hilang oleh agent langsung diberi peringatan', function () {
    $this->web->update(['sources' => ['web' => ['status' => 'missing', 'detail' => '/var/log/nginx/access.log tidak ditemukan']]]);

    $warnings = $this->watch->sourceWarnings();
    expect($warnings[0]['alasan'])->toBe('hilang');

    $this->actingAs(User::where('email', 'admin@sentra.lab')->first())
        ->get('/satuan-agent')->assertSee('Peringatan: sumber web hilang');
    $this->actingAs(User::where('email', 'analis@sentra.lab')->first())
        ->get('/beranda')->assertSee('1 peringatan sumber log');
});
