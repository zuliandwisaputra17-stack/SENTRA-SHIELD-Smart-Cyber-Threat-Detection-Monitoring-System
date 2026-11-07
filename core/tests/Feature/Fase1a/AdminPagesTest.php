<?php

use App\Enums\AgentStatus;
use App\Enums\Role;
use App\Livewire\Admin\UnitsAgents;
use App\Livewire\Admin\Users;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Unit;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->seed());

function akun(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

test('akses halaman Satuan & Agent sesuai PRD bagian 11', function (string $email, int $status) {
    $this->actingAs(akun($email))->get('/satuan-agent')->assertStatus($status);
})->with([
    ['admin@sentra.lab', 200],
    ['analis@sentra.lab', 200],
    ['komandan@sentra.lab', 403],
    ['operator.a@sentra.lab', 403],
    ['demo@sentra.lab', 403],
]);

test('admin menambah agent dan melihat kode pendaftaran sekali', function () {
    $kodimA = Unit::where('code', 'KODIM-LAT-A')->first();

    $component = Livewire::actingAs(akun('admin@sentra.lab'))
        ->test(UnitsAgents::class)
        ->set('agentUnitId', $kodimA->id)
        ->set('agentHostname', 'kodim-a')
        ->set('agentAssetType', 'web')
        ->call('createAgent')
        ->assertHasNoErrors();

    $code = $component->get('newCode');
    $agent = Agent::where('hostname', 'kodim-a')->firstOrFail();

    expect($code)->toStartWith('SNTR-')
        ->and($agent->status)->toBe(AgentStatus::Menunggu)
        ->and($agent->enrollment_code_hash)->toBe(hash('sha256', $code))
        ->and(AuditLog::where('action', 'agent_dibuat')->count())->toBe(1);

    $component->call('dismissCode')->assertSet('newCode', null);

    // Kode asli tidak tersimpan di mana pun.
    $this->postJson('/api/v1/agent/register', ['enrollment_code' => $code, 'hostname' => 'kodim-a', 'version' => '0.1.0'])->assertOk();
});

test('admin mencabut token agent dari halaman', function () {
    $agent = Agent::factory()->create();
    $token = $agent->createToken('agent')->plainTextToken;

    Livewire::actingAs(akun('admin@sentra.lab'))->test(UnitsAgents::class)->call('revokeAgent', $agent->id);

    expect($agent->fresh()->status)->toBe(AgentStatus::Dicabut);
    $this->withToken($token)->postJson('/api/v1/agent/heartbeat', ['version' => '1', 'sources' => []])->assertUnauthorized();
});

test('analis hanya bisa melihat, tidak bisa mengelola agent', function () {
    $agent = Agent::factory()->create();

    Livewire::actingAs(akun('analis@sentra.lab'))
        ->test(UnitsAgents::class)
        ->assertSee($agent->hostname)
        ->assertDontSee('Tambah agent')
        ->call('revokeAgent', $agent->id)
        ->assertForbidden();

    expect($agent->fresh()->status)->not->toBe(AgentStatus::Dicabut);
});

test('admin membuat satuan baru di bawah induk', function () {
    $korem = Unit::where('code', 'KOREM-LAT-01')->first();

    Livewire::actingAs(akun('admin@sentra.lab'))->test(UnitsAgents::class)
        ->set('unitName', 'Kodim Latihan C')
        ->set('unitCode', 'KODIM-LAT-C')
        ->set('unitLevel', 'kodim')
        ->set('unitParentId', $korem->id)
        ->call('saveUnit')
        ->assertHasNoErrors();

    expect(Unit::where('code', 'KODIM-LAT-C')->first()->parent_id)->toBe($korem->id);
});

test('satuan tidak boleh berinduk ke keturunannya sendiri', function () {
    $kodam = Unit::where('code', 'KODAM-LAT-I')->first();
    $kodimA = Unit::where('code', 'KODIM-LAT-A')->first();

    Livewire::actingAs(akun('admin@sentra.lab'))->test(UnitsAgents::class)
        ->call('editUnit', $kodam->id)
        ->set('unitParentId', $kodimA->id)
        ->call('saveUnit')
        ->assertHasErrors('unitParentId');
});

test('hanya admin yang membuka halaman Pengguna', function (string $email, int $status) {
    $this->actingAs(akun($email))->get('/pengguna')->assertStatus($status);
})->with([
    ['admin@sentra.lab', 200],
    ['analis@sentra.lab', 403],
    ['komandan@sentra.lab', 403],
]);

test('admin membuat pengguna operator dengan satuan', function () {
    $kodimB = Unit::where('code', 'KODIM-LAT-B')->first();

    Livewire::actingAs(akun('admin@sentra.lab'))->test(Users::class)
        ->set('name', 'Serka Uji')
        ->set('email', 'serka.uji@sentra.lab')
        ->set('role', 'operator')
        ->set('unitId', $kodimB->id)
        ->set('password', 'KataSandiPanjang1')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'serka.uji@sentra.lab')->first();
    expect($user->role)->toBe(Role::Operator)
        ->and($user->visibleUnitIds())->toBe([$kodimB->id])
        ->and(AuditLog::where('action', 'pengguna_dibuat')->first()->data)->not->toHaveKey('password');
});

test('operator wajib punya satuan', function () {
    Livewire::actingAs(akun('admin@sentra.lab'))->test(Users::class)
        ->set('name', 'Tanpa Satuan')->set('email', 'tanpa@sentra.lab')->set('role', 'operator')
        ->set('password', 'KataSandiPanjang1')
        ->call('save')
        ->assertHasErrors('unitId');
});

test('admin tidak bisa menurunkan perannya sendiri', function () {
    $admin = akun('admin@sentra.lab');

    Livewire::actingAs($admin)->test(Users::class)
        ->call('edit', $admin->id)
        ->set('role', 'operator')
        ->set('unitId', Unit::first()->id)
        ->call('save')
        ->assertHasErrors('role');

    expect($admin->fresh()->role)->toBe(Role::Admin);
});

test('hanya sumber log aktif yang ditampilkan', function () {
    Agent::factory()->create([
        'hostname' => 'plc-uji',
        'sources' => [
            'modbus' => ['status' => 'ok', 'detail' => 'tcp/502'],
            'web' => ['status' => 'disabled', 'detail' => ''],
            'firewall' => ['status' => 'error', 'detail' => 'nft gagal'],
        ],
    ]);

    Livewire::actingAs(akun('analis@sentra.lab'))->test(UnitsAgents::class)
        ->assertSee('modbus: ok')
        ->assertSee('firewall: error')
        ->assertDontSee('web: disabled');
});
