<?php

use App\Enums\Role;
use App\Enums\UnitLevel;
use App\Models\Incident;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    $this->seed();
    $this->kodam = Unit::where('code', 'KODAM-LAT-I')->first();
    $this->korem = Unit::where('code', 'KOREM-LAT-01')->first();
    $this->kodimA = Unit::where('code', 'KODIM-LAT-A')->first();
    $this->kodimB = Unit::where('code', 'KODIM-LAT-B')->first();
});

test('operator hanya melihat satuannya sendiri', function () {
    $operatorA = User::where('email', 'operator.a@sentra.lab')->first();

    expect($operatorA->visibleUnitIds())->toBe([$this->kodimA->id])
        ->and($operatorA->canSeeUnit($this->kodimB->id))->toBeFalse();
});

test('operator di tingkat Korem melihat satuannya beserta Kodim di bawahnya', function () {
    $operatorKorem = User::factory()->role(Role::Operator)->inUnit($this->korem)->create();

    expect($operatorKorem->visibleUnitIds())
        ->toEqualCanonicalizing([$this->korem->id, $this->kodimA->id, $this->kodimB->id]);
});

test('analis, komandan, admin, dan demo melihat semua satuan', function (string $email) {
    expect(User::where('email', $email)->first()->visibleUnitIds())->toBeNull();
})->with(['analis@sentra.lab', 'komandan@sentra.lab', 'admin@sentra.lab', 'demo@sentra.lab']);

test('policy insiden membatasi operator ke satuannya', function () {
    $operatorA = User::where('email', 'operator.a@sentra.lab')->first();
    $analis = User::where('email', 'analis@sentra.lab')->first();
    $insidenA = Incident::factory()->create(['unit_id' => $this->kodimA->id]);
    $insidenB = Incident::factory()->create(['unit_id' => $this->kodimB->id]);

    expect($operatorA->can('view', $insidenA))->toBeTrue()
        ->and($operatorA->can('view', $insidenB))->toBeFalse()
        ->and($operatorA->can('proposeAction', $insidenB))->toBeFalse()
        ->and($analis->can('view', $insidenB))->toBeTrue()
        ->and(Incident::visibleTo($operatorA)->pluck('id')->all())->toBe([$insidenA->id]);
});

test('Beranda operator tidak menampilkan satuan lain', function () {
    $this->actingAs(User::where('email', 'operator.a@sentra.lab')->first());

    $this->get('/beranda')
        ->assertOk()
        ->assertSee('Kodim Latihan A')
        ->assertDontSee('Kodim Latihan B');
});

test('Beranda analis menampilkan seluruh pohon satuan', function () {
    $this->actingAs(User::where('email', 'analis@sentra.lab')->first());

    $this->get('/beranda')
        ->assertOk()
        ->assertSeeInOrder(['Kodam Latihan I', 'Korem Latihan 01', 'Kodim Latihan A', 'Kodim Latihan B']);
});

test('tingkat siaga induk mengikuti tingkat tertinggi anaknya', function () {
    $this->kodimB->update(['alert_level' => 'siaga']);

    $root = Unit::tree()->first();
    expect($root->code)->toBe('KODAM-LAT-I')
        ->and($root->effectiveAlertLevel()->value)->toBe('siaga')
        ->and($root->level)->toBe(UnitLevel::Kodam);
});
