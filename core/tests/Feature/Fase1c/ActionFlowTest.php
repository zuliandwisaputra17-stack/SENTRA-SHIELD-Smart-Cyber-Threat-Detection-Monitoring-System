<?php

use App\Enums\ActionStatus;
use App\Enums\AgentStatus;
use App\Enums\AssetType;
use App\Events\DashboardChanged;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\ResponseAction;
use App\Models\User;
use App\Response\ActionService;
use App\Services\AgentEnrollment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fase1b/helpers.php';

function akunLab(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

/** Daftarkan agent lewat API dan kembalikan token-nya. */
function tokenAgent($agent): string
{
    $agent->update(['status' => AgentStatus::Menunggu]);
    $code = app(AgentEnrollment::class)->issueCode($agent);

    return test()->postJson('/api/v1/agent/register', ['enrollment_code' => $code, 'hostname' => $agent->hostname, 'version' => '0.1.0'])->json('token');
}

beforeEach(function () {
    $this->seed();
    $this->web = agentLab('kodim-a');
    $this->plc = agentLab('plc-sim', 'KODIM-LAT-A', AssetType::Ot);
    $this->dicabut = agentLab('lama', 'KODIM-LAT-A');
    $this->dicabut->update(['status' => AgentStatus::Dicabut]);
    deteksi(eventLab($this->web, 'web', ['user_agent' => 'sqlmap', 'path' => '/']));
    $this->incident = Incident::sole();
    $this->svc = app(ActionService::class);
});

test('operator mengusulkan blokir: satu baris per agent aktif di satuan insiden', function () {
    Event::fake([DashboardChanged::class]);

    $group = $this->svc->proposeBlock($this->incident, akunLab('operator.a@sentra.lab'), 3600);
    $rows = ResponseAction::where('group_uuid', $group)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('target_agent_id')->sort()->values()->all())->toBe(collect([$this->web->id, $this->plc->id])->sort()->values()->all())
        ->and($rows->every(fn ($r) => $r->status === ActionStatus::Diusulkan && $r->params === ['ip' => PENYERANG, 'duration_s' => 3600]))->toBeTrue()
        ->and(AuditLog::where('action', 'tindakan_diusulkan')->sole()->data['agent'])->toEqualCanonicalizing(['kodim-a', 'plc-sim']);
    Event::assertDispatched(DashboardChanged::class, fn ($e) => $e->jenis === 'tindakan');
});

test('operator satuan lain dan komandan tidak bisa mengusulkan', function (string $email) {
    $this->svc->proposeBlock($this->incident, akunLab($email), 3600);
})->with(['operator.b@sentra.lab', 'komandan@sentra.lab', 'admin@sentra.lab', 'demo@sentra.lab'])
    ->throws(AuthorizationException::class);

test('usulan ganda untuk IP yang sama ditolak', function () {
    $this->svc->proposeBlock($this->incident, akunLab('operator.a@sentra.lab'), 3600);
    $this->svc->proposeBlock($this->incident, akunLab('analis@sentra.lab'), 3600);
})->throws(ValidationException::class);

test('komandan menyetujui, agent mengambil perintah lalu melaporkan hasil', function () {
    $group = $this->svc->proposeBlock($this->incident, akunLab('operator.a@sentra.lab'), 3600);
    $this->svc->decide($group, akunLab('komandan@sentra.lab'), true);

    $token = tokenAgent($this->web);
    $commands = $this->withToken($token)->getJson('/api/v1/agent/commands')->assertOk()->json('commands');
    expect($commands)->toHaveCount(1)
        ->and($commands[0]['type'])->toBe('block_ip')
        ->and($commands[0]['approved_by'])->toBe('Komandan Latihan');

    $this->withToken($token)->postJson("/api/v1/agent/commands/{$commands[0]['id']}/result", ['status' => 'success', 'message' => 'IP diblokir'])->assertOk();

    $action = ResponseAction::find($commands[0]['id']);
    expect($action->status)->toBe(ActionStatus::Dieksekusi)
        ->and(ActionService::activeBlocks()->count())->toBe(1)
        ->and(AuditLog::pluck('action')->all())->toContain('tindakan_disetujui', 'tindakan_dieksekusi');
});

test('penolakan wajib beralasan', function () {
    $group = $this->svc->proposeBlock($this->incident, akunLab('operator.a@sentra.lab'), 3600);

    expect(fn () => $this->svc->decide($group, akunLab('komandan@sentra.lab'), false, '  '))->toThrow(ValidationException::class);

    $this->svc->decide($group, akunLab('komandan@sentra.lab'), false, 'IP milik mitra latihan');
    $rows = ResponseAction::where('group_uuid', $group)->get();
    expect($rows->every(fn ($r) => $r->status === ActionStatus::Ditolak && $r->decision_note === 'IP milik mitra latihan'))->toBeTrue();
});

test('selain komandan tidak bisa memutuskan', function (string $email) {
    $group = $this->svc->proposeBlock($this->incident, akunLab('operator.a@sentra.lab'), 3600);
    $this->svc->decide($group, akunLab($email), true);
})->with(['operator.a@sentra.lab', 'analis@sentra.lab', 'admin@sentra.lab'])->throws(AuthorizationException::class);

test('usulan yang sudah diputuskan tidak bisa diputuskan lagi', function () {
    $group = $this->svc->proposeBlock($this->incident, akunLab('operator.a@sentra.lab'), 3600);
    $this->svc->decide($group, akunLab('komandan@sentra.lab'), true);
    $this->svc->decide($group, akunLab('komandan@sentra.lab'), false, 'ubah pikiran');
})->throws(ValidationException::class);

test('hanya pengusul yang bisa membatalkan usulannya', function () {
    $group = $this->svc->proposeBlock($this->incident, akunLab('operator.a@sentra.lab'), 3600);

    expect(fn () => $this->svc->cancel($group, akunLab('analis@sentra.lab')))->toThrow(AuthorizationException::class);
    $this->svc->cancel($group, akunLab('operator.a@sentra.lab'));

    expect(ResponseAction::where('group_uuid', $group)->pluck('status')->unique()->all())->toEqual([ActionStatus::Dibatalkan])
        ->and(AuditLog::where('action', 'usulan_dibatalkan')->count())->toBe(1);
});

test('blokir yang durasinya habis menjadi Kedaluwarsa', function () {
    $group = $this->svc->proposeBlock($this->incident, akunLab('operator.a@sentra.lab'), 900);
    $this->svc->decide($group, akunLab('komandan@sentra.lab'), true);
    $action = ResponseAction::where('group_uuid', $group)->first();
    $action->update(['status' => ActionStatus::Dieksekusi, 'executed_at' => now(), 'expires_at' => now()->addSeconds(900)]);

    expect($this->svc->expireBlocks())->toBe(0);
    $this->travel(901)->seconds();
    expect($this->svc->expireBlocks())->toBe(1)
        ->and($action->fresh()->status)->toBe(ActionStatus::Kedaluwarsa)
        ->and(AuditLog::where('action', 'blokir_kedaluwarsa')->count())->toBe(1);
});

test('rollback komandan: perintah buka blokir, lalu blokir asal Dibatalkan', function () {
    $group = $this->svc->proposeBlock($this->incident, akunLab('operator.a@sentra.lab'), 3600);
    $this->svc->decide($group, akunLab('komandan@sentra.lab'), true);
    $token = tokenAgent($this->web);
    $block = ResponseAction::where('group_uuid', $group)->where('target_agent_id', $this->web->id)->first();
    $this->withToken($token)->postJson("/api/v1/agent/commands/{$block->id}/result", ['status' => 'success'])->assertOk();

    expect(fn () => $this->svc->rollback($block->fresh(), akunLab('operator.a@sentra.lab')))->toThrow(AuthorizationException::class);
    $unblock = $this->svc->rollback($block->fresh(), akunLab('komandan@sentra.lab'));

    expect($unblock->type->value)->toBe('unblock_ip')
        ->and($unblock->status)->toBe(ActionStatus::Disetujui)
        ->and($unblock->params['rollback_of'])->toBe($block->id);

    $this->withToken($token)->postJson("/api/v1/agent/commands/{$unblock->id}/result", ['status' => 'success', 'message' => 'blokir dibuka'])->assertOk();

    expect($block->fresh()->status)->toBe(ActionStatus::Dibatalkan)
        ->and($block->fresh()->result['dibuka_oleh'])->toBe($unblock->id)
        ->and(ActionService::activeBlocks()->count())->toBe(0)
        ->and(AuditLog::pluck('action')->all())->toContain('rollback_diminta', 'blokir_dibuka');
});

test('Reverb mati tidak menggagalkan ingest event', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 1,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);
    $token = tokenAgent($this->web);

    $this->withToken($token)->postJson('/api/v1/agent/events', ['events' => [[
        'source' => 'ssh', 'occurred_at' => now()->toIso8601String(), 'src_ip' => PENYERANG,
        'payload' => ['outcome' => 'failed', 'user' => 'root'], 'is_test' => false,
    ]]])->assertOk()->assertJson(['accepted' => 1]);
});
