<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('halaman profil tampil tanpa opsi hapus akun', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/profil')
        ->assertOk()
        ->assertSeeVolt('profile.update-profile-information-form')
        ->assertSeeVolt('profile.update-password-form')
        ->assertDontSee('delete-user-form');
});

test('informasi profil bisa diperbarui', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('profile.update-profile-information-form')
        ->set('name', 'Pengguna Uji')
        ->set('email', 'uji@sentra.lab')
        ->call('updateProfileInformation')
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $user->refresh();
    expect($user->name)->toBe('Pengguna Uji')
        ->and($user->email)->toBe('uji@sentra.lab');
});
