<?php

use App\Detection\SensorWatch;
use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Enums\AgentStatus;
use App\Enums\AssetType;
use App\Enums\ReadinessStatus;
use App\Livewire\Readiness\ReadinessPage;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Detection;
use App\Models\Incident;
use App\Models\ReadinessRun;
use App\Models\ResponseAction;
use App\Models\Unit;
use App\Models\User;
use App\Readiness\ReadinessService;
use App\Readiness\ScenarioCatalog;
use App\Services\AgentEnrollment;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/../Fase1b/helpers.php';

/** Status sumber log seperti yang dilaporkan heartbeat agent. */
function sumberAktif(array $aktif): array
{
    return collect(['web', 'ssh', 'firewall', 'fim', 'modbus'])->mapWithKeys(fn ($n) => [$n => [
        'status' => in_array($n, $aktif, true) ? 'ok' : 'disabled', 'detail' => '', 'last_event_at' => null,
    ]])->all();
}

function tokenAgentUji(Agent $agent): string
{
    $code = app(AgentEnrollment::class)->issueCode($agent);

    return test()->postJson('/api/v1/agent/register', ['enrollment_code' => $code, 'hostname' => $agent->hostname, 'version' => '0.1.0'])
        ->assertOk()->json('token');
}

function lapor(string $token, ResponseAction $action, array $skenario, string $status = 'success', string $message = 'selesai'): void
{
    app('auth')->forgetGuards(); // guard Sanctum menyimpan agent dari request sebelumnya
    test()->withToken($token)->postJson("/api/v1/agent/commands/{$action->id}/result", [
        'status' => $status,
        'message' => $message,
        'executed_at' => now()->subSeconds(60)->toIso8601ZuluString('millisecond'),
        'details' => ['skenario' => $skenario],
    ])->assertOk();
}

function perintahUji(Agent $agent): ResponseAction
{
    return ResponseAction::where('type', ActionType::RunTest)->where('target_agent_id', $agent->id)->latest('id')->firstOrFail();
}

beforeEach(function () {
    $this->seed();
    $this->travelTo(now()->startOfSecond());
    $this->unit = Unit::where('code', 'KODIM-LAT-A')->first();
    $this->web = agentLab('kodim-a', 'KODIM-LAT-A');
    $this->web->update(['sources' => sumberAktif(['web', 'ssh', 'firewall', 'fim']), 'last_heartbeat_at' => now()]);
    $this->plc = agentLab('plc-sim', 'KODIM-LAT-A', AssetType::Ot);
    $this->plc->update(['sources' => sumberAktif(['firewall', 'modbus']), 'last_heartbeat_at' => now()]);
    $this->analis = User::where('email', 'analis@sentra.lab')->first();
    $this->svc = app(ReadinessService::class);
});

test('skenario dibagi ke agent yang sumber telemetrinya aktif, perintah run_test dari daftar tetap', function () {
    $run = $this->svc->start($this->unit, $this->analis);

    expect($run->status)->toBe(ReadinessStatus::Berjalan)
        ->and($run->unit_id)->toBe($this->unit->id)
        ->and($run->results)->toHaveCount(11)
        ->and($run->results->pluck('scenario')->sort()->values()->all())->toBe(collect(ScenarioCatalog::codes())->sort()->values()->all())
        ->and($run->results->firstWhere('scenario', 'R11')->agent_id)->toBe($this->plc->id)
        ->and($run->results->where('agent_id', $this->web->id))->toHaveCount(10);

    $web = perintahUji($this->web);
    expect($web->status)->toBe(ActionStatus::Disetujui)
        ->and($web->params['scenarios'])->toBe(['R01', 'R02', 'R03', 'R04', 'R05', 'R06', 'R07', 'R09', 'R10', 'R12'])
        ->and($web->params['token'])->toBe(ReadinessService::token($run))->toMatch('/^[0-9a-f]{32}$/')
        ->and(perintahUji($this->plc)->params['scenarios'])->toBe(['R11'])
        ->and(AuditLog::where('action', 'uji_kesiapan_dimulai')->sole()->subject_id)->toBe($run->id);

    $this->withToken(tokenAgentUji($this->web))->getJson('/api/v1/agent/commands')
        ->assertOk()
        ->assertJsonPath('commands.0.type', 'run_test')
        ->assertJsonPath('commands.0.params.scenarios.0', 'R01');
});

test('skenario tanpa sumber telemetri yang aktif tidak diuji', function () {
    $this->plc->update(['status' => AgentStatus::Buta]);
    $run = $this->svc->start($this->unit, $this->analis);

    expect($run->results->pluck('scenario')->all())->not->toContain('R11')
        ->and($run->results)->toHaveCount(10)
        ->and(ResponseAction::where('type', ActionType::RunTest)->count())->toBe(1);
});

test('uji ditolak bila masih berjalan atau tidak ada agent online', function () {
    $this->svc->start($this->unit, $this->analis);
    expect(fn () => $this->svc->start($this->unit, $this->analis))->toThrow(ValidationException::class);

    $kosong = Unit::where('code', 'KODIM-LAT-B')->first();
    expect(fn () => $this->svc->start($kosong, $this->analis))->toThrow(ValidationException::class);
});

test('deteksi uji dicocokkan ke skenario dengan waktu deteksi, tanpa insiden', function () {
    $token = tokenAgentUji($this->web);
    $run = $this->svc->start($this->unit, $this->analis);
    $mulai = now()->copy();

    // Event uji dari loopback memicu R02 dua detik setelah skenario dimulai.
    $this->travel(2)->seconds();
    $this->withToken($token)->postJson('/api/v1/agent/events', ['events' => [[
        'source' => 'web', 'occurred_at' => now()->toIso8601ZuluString('millisecond'), 'src_ip' => '127.0.0.1',
        'payload' => ['method' => 'GET', 'path' => '/', 'status' => 200, 'user_agent' => 'sqlmap/1.8 SENTRA-UJI/'.ReadinessService::token($run)],
        'raw' => 'uji', 'is_test' => true,
    ]]])->assertOk();

    $iso = $mulai->toIso8601ZuluString('millisecond');
    lapor($token, perintahUji($this->web), [
        'R02' => ['mulai' => $iso, 'ok' => true, 'pesan' => 'request dengan user agent sqlmap'],
        'R07' => ['mulai' => $iso, 'ok' => false, 'pesan' => 'klien ssh tidak tersedia di host ini'],
    ]);

    $hasil = $run->results()->get()->keyBy('scenario');
    expect($hasil['R02']->detected)->toBeTrue()
        ->and($hasil['R02']->ttd_ms)->toBe(2000)
        ->and($hasil['R07']->detected)->toBeFalse()
        ->and($hasil['R07']->note)->toBe('klien ssh tidak tersedia di host ini')
        ->and($hasil['R01']->note)->toContain('Tidak dijalankan agent')
        ->and(Incident::count())->toBe(0)
        ->and(Detection::sole()->incident_id)->toBeNull()
        ->and($run->fresh()->status)->toBe(ReadinessStatus::Berjalan);
});

test('deteksi asli (bukan event uji) tidak dihitung sebagai hasil uji', function () {
    $run = $this->svc->start($this->unit, $this->analis);
    $run->results()->where('scenario', 'R02')->update(['started_at' => now()]);

    deteksi(eventLab($this->web, 'web', ['path' => '/', 'status' => 200, 'user_agent' => 'sqlmap/1.8'], PENYERANG));
    $this->svc->evaluate($run);

    expect(Incident::count())->toBe(1)
        ->and($run->results()->where('scenario', 'R02')->sole()->detected)->toBeFalse();
});

test('run selesai setelah semua agent melapor dan masa tenggang lewat: cakupan dan rata-rata waktu deteksi', function () {
    $webToken = tokenAgentUji($this->web);
    $plcToken = tokenAgentUji($this->plc);
    $run = $this->svc->start($this->unit, $this->analis);
    $iso = now()->toIso8601ZuluString('millisecond');

    $this->travel(3)->seconds();
    deteksi(eventLab($this->web, 'web', ['path' => '/', 'status' => 200, 'user_agent' => 'sqlmap'], '127.0.0.1', null, null, true));
    $this->travel(2)->seconds();
    deteksi(eventLab($this->plc, 'modbus', ['function_code' => 6, 'function_name' => 'Write Single Register', 'address' => 31, 'value' => 0], '127.0.0.1', 502, null, true));

    lapor($webToken, perintahUji($this->web), ['R02' => ['mulai' => $iso, 'ok' => true, 'pesan' => 'ok']]);
    lapor($plcToken, perintahUji($this->plc), ['R11' => ['mulai' => $iso, 'ok' => true, 'pesan' => 'ok']]);
    expect($run->fresh()->status)->toBe(ReadinessStatus::Berjalan);

    $this->travel(ReadinessService::GRACE_S + 1)->seconds();
    $this->svc->tick();

    $run->refresh();
    expect($run->status)->toBe(ReadinessStatus::Selesai)
        ->and($run->coverage)->toBe(round(2 / 11, 4))
        ->and($run->avg_ttd_ms)->toBe(4000)
        ->and($run->finished_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'uji_kesiapan_selesai')->sole()->data['cakupan'])->toBe('2/11')
        ->and(Incident::count())->toBe(0);

    // Uji baru boleh dijalankan lagi.
    expect($this->svc->start($this->unit, $this->analis)->id)->toBeGreaterThan($run->id);
});

test('run yang tidak pernah dilaporkan agent selesai setelah batas waktu', function () {
    $run = $this->svc->start($this->unit, $this->analis);

    $this->travel(ReadinessService::TIMEOUT_S - 10)->seconds();
    $this->svc->tick();
    expect($run->fresh()->status)->toBe(ReadinessStatus::Berjalan);

    $this->travel(11)->seconds();
    $this->svc->tick();
    expect($run->fresh()->status)->toBe(ReadinessStatus::Selesai)
        ->and($run->fresh()->coverage)->toBe(0.0)
        ->and($run->fresh()->avg_ttd_ms)->toBeNull();
});

test('R12: jeda heartbeat bertanda uji tercatat sebagai deteksi uji, agent tidak BUTA, tanpa insiden', function () {
    $token = tokenAgentUji($this->web);
    $run = $this->svc->start($this->unit, $this->analis);
    $sources = sumberAktif(['web', 'ssh', 'firewall', 'fim']);

    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', [
        'version' => '0.1.0', 'sources' => $sources, 'uji' => ['token' => ReadinessService::token($run), 'skenario' => 'R12'],
    ])->assertOk();
    $r12 = $run->results()->where('scenario', 'R12')->sole();
    expect($r12->started_at)->not->toBeNull();

    $this->travel(35)->seconds();
    $this->plc->update(['last_heartbeat_at' => now()]);
    expect(app(SensorWatch::class)->checkHeartbeats())->toBe(0);
    $this->svc->evaluate($run);

    expect($this->web->fresh()->status)->toBe(AgentStatus::Online)
        ->and(Incident::count())->toBe(0)
        ->and($r12->fresh()->detected)->toBeTrue()
        ->and($r12->fresh()->ttd_ms)->toBe(35000);

    // Pemeriksaan berikutnya selama jeda tidak membuat deteksi ganda.
    $this->travel(10)->seconds();
    app(SensorWatch::class)->checkHeartbeats();
    expect(Detection::count())->toBe(1);

    // Heartbeat normal kembali: kematian berikutnya adalah alarm sungguhan.
    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', ['version' => '0.1.0', 'sources' => $sources])->assertOk();
    $this->travel(31)->seconds();
    $this->plc->update(['last_heartbeat_at' => now()]);
    expect(app(SensorWatch::class)->checkHeartbeats())->toBe(1)
        ->and($this->web->fresh()->status)->toBe(AgentStatus::Buta)
        ->and(Incident::count())->toBe(1);
});

test('R12: penanda dengan token salah ditolak sehingga jeda tetap menjadi alarm Sensor Buta', function () {
    $token = tokenAgentUji($this->web);
    $run = $this->svc->start($this->unit, $this->analis);

    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', [
        'version' => '0.1.0', 'sources' => [], 'uji' => ['token' => str_repeat('f', 32), 'skenario' => 'R12'],
    ])->assertOk();
    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', [
        'version' => '0.1.0', 'sources' => [], 'uji' => ['token' => ReadinessService::token($run), 'skenario' => 'R01'],
    ])->assertUnprocessable();

    $this->travel(31)->seconds();
    $this->plc->update(['last_heartbeat_at' => now()]);
    expect($run->results()->where('scenario', 'R12')->sole()->started_at)->toBeNull()
        ->and(app(SensorWatch::class)->checkHeartbeats())->toBe(1)
        ->and(Incident::count())->toBe(1);
});

test('Analis dan Admin menjalankan uji dari halaman; matriks dan riwayat tampil', function (string $email) {
    $user = User::where('email', $email)->first();

    Livewire::actingAs($user)->test(ReadinessPage::class)
        ->assertSee('Jalankan Uji Kesiapan')
        ->set('unitId', $this->unit->id)
        ->call('run')
        ->assertHasNoErrors()
        ->assertSee('Koneksi ke 20 port uji yang tertutup')
        ->assertSee('Jeda heartbeat agent 45 detik')
        ->assertSee('T1110.001')
        ->assertSee('0/11')
        ->assertSee('Menunggu')
        ->assertSee('Riwayat')
        ->call('run')
        ->assertSee('masih berjalan');

    expect(ReadinessRun::count())->toBe(1);
})->with(['analis@sentra.lab', 'admin@sentra.lab']);

test('Demo hanya melihat; Operator dan Komandan tidak punya akses', function () {
    $run = $this->svc->start($this->unit, $this->analis);
    $run->results()->where('scenario', 'R02')->update(['detected' => true, 'ttd_ms' => 1500, 'started_at' => now()]);
    $demo = User::where('email', 'demo@sentra.lab')->first();

    $this->actingAs($demo)->get(route('readiness'))
        ->assertOk()
        ->assertSee('Terdeteksi')
        ->assertSee('1,5 dtk')
        ->assertSee('1/11')
        ->assertDontSee('Jalankan Uji Kesiapan');
    Livewire::actingAs($demo)->test(ReadinessPage::class)->call('run')->assertForbidden();

    foreach (['operator.a@sentra.lab', 'komandan@sentra.lab'] as $email) {
        $this->actingAs(User::where('email', $email)->first())->get(route('readiness'))->assertForbidden();
    }
    expect(ReadinessRun::count())->toBe(1);
});
