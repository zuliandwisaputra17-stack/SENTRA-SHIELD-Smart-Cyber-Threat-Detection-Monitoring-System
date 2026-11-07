<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('halaman login tampil dalam bahasa Indonesia', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSeeVolt('pages.auth.login')
        ->assertSee('Kata sandi')
        ->assertSee('Masuk')
        ->assertDontSee('fonts.bunny.net');
});

test('pengguna bisa login', function () {
    $user = User::factory()->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('kata sandi salah ditolak dengan pesan bahasa Indonesia', function () {
    $user = User::factory()->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'salah')
        ->call('login')
        ->assertHasErrors(['form.email'])
        ->assertSee('Email atau kata sandi salah.')
        ->assertNoRedirect();

    $this->assertGuest();
});

test('login dibatasi setelah 5 percobaan gagal', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'salah')
            ->call('login');
    }

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors(['form.email'])
        ->assertSee('Terlalu banyak percobaan masuk');

    $this->assertGuest();
});

test('tidak ada registrasi publik maupun reset kata sandi lewat email', function (string $uri) {
    $this->get($uri)->assertNotFound();
})->with(['/register', '/forgot-password', '/reset-password/token', '/verify-email']);

test('tamu diarahkan ke login', function () {
    $this->get('/beranda')->assertRedirect('/login');
    $this->get('/')->assertRedirect('/beranda');
});

test('menu navigasi tampil', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/beranda')
        ->assertOk()
        ->assertSeeVolt('layout.navigation')
        ->assertSee('Beranda Komando');
});

test('pengguna bisa keluar', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('layout.navigation')
        ->call('logout')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
});
