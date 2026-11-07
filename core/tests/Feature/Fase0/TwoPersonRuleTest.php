<?php

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Enums\Role;
use App\Models\Agent;
use App\Models\ResponseAction;
use App\Models\User;

function usulanBlokir(User $pengusul, ActionStatus $status = ActionStatus::Diusulkan): ResponseAction
{
    return ResponseAction::create([
        'type' => ActionType::BlockIp,
        'target_agent_id' => Agent::factory()->create()->id,
        'params' => ['ip' => '172.30.0.66', 'duration_s' => 3600],
        'status' => $status,
        'proposed_by' => $pengusul->id,
    ]);
}

test('komandan bisa memutuskan usulan dari operator', function () {
    $operator = User::factory()->role(Role::Operator)->create();
    $komandan = User::factory()->role(Role::Komandan)->create();

    expect($komandan->can('decide', usulanBlokir($operator)))->toBeTrue();
});

test('pengusul tidak bisa menyetujui tindakannya sendiri', function () {
    // Kasus tepi: pengusul yang kemudian berperan Komandan tetap ditolak.
    $user = User::factory()->role(Role::Komandan)->create();

    expect($user->can('decide', usulanBlokir($user)))->toBeFalse();
});

test('selain komandan tidak bisa memutuskan tindakan', function (Role $role) {
    $operator = User::factory()->role(Role::Operator)->create();
    $user = User::factory()->role($role)->create();

    expect($user->can('decide', usulanBlokir($operator)))->toBeFalse();
})->with([Role::Operator, Role::Analis, Role::Admin, Role::Demo]);

test('tindakan yang sudah diputuskan tidak bisa diputuskan lagi', function () {
    $operator = User::factory()->role(Role::Operator)->create();
    $komandan = User::factory()->role(Role::Komandan)->create();

    expect($komandan->can('decide', usulanBlokir($operator, ActionStatus::Ditolak)))->toBeFalse();
});

test('rollback hanya untuk blokir yang sudah dieksekusi dan hanya oleh komandan', function () {
    $operator = User::factory()->role(Role::Operator)->create();
    $komandan = User::factory()->role(Role::Komandan)->create();
    $dieksekusi = usulanBlokir($operator, ActionStatus::Dieksekusi);

    expect($komandan->can('rollback', $dieksekusi))->toBeTrue()
        ->and($operator->can('rollback', $dieksekusi))->toBeFalse()
        ->and($komandan->can('rollback', usulanBlokir($operator)))->toBeFalse();
});
