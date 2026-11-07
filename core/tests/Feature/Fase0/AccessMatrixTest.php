<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
| Matriks hak akses PRD bagian 3, ditulis ulang di sini apa adanya supaya test
| membandingkan implementasi dengan tabel PRD, bukan dengan dirinya sendiri.
| Urutan kolom: Operator, Analis, Komandan, Admin.
*/
dataset('matriks_prd', [
    'Lihat dashboard dan insiden' => ['lihat-dashboard', [true, true, true, true]],
    'Ubah status insiden' => ['ubah-status-insiden', [true, true, true, false]],
    'Usulkan blokir IP / isolasi' => ['usul-tindakan', [true, true, false, false]],
    'Setujui atau tolak tindakan' => ['putuskan-tindakan', [false, false, true, false]],
    'Buat dan ekspor LAPSIT' => ['kelola-lapsit', [false, true, true, false]],
    'Jalankan Uji Kesiapan' => ['jalankan-uji-kesiapan', [false, true, false, true]],
    'Kelola agent, pengguna, aturan' => ['kelola-sistem', [false, false, false, true]],
    'Lihat dan verifikasi audit log' => ['lihat-audit', [false, true, true, true]],
    'Verifikasi rantai audit log' => ['verifikasi-audit', [false, true, true, true]],
    // Bagian 11: akses halaman
    'Halaman Kampanye' => ['lihat-kampanye', [false, true, true, false]],
    'Halaman Satuan dan Agent' => ['lihat-satuan-agent', [false, true, false, true]],
    'Halaman Uji Kesiapan' => ['lihat-uji-kesiapan', [false, true, false, true]],
    'Halaman Penjaga Situs' => ['lihat-penjaga-situs', [false, true, false, false]],
    'Kelola situs Penjaga Situs' => ['kelola-penjaga-situs', [false, true, false, false]],
]);

test('gate sesuai matriks hak akses PRD', function (string $ability, array $expected) {
    $roles = [Role::Operator, Role::Analis, Role::Komandan, Role::Admin];

    foreach ($roles as $i => $role) {
        $user = User::factory()->role($role)->create();
        expect(Gate::forUser($user)->allows($ability))
            ->toBe($expected[$i], "{$role->value} → {$ability}");
    }
})->with('matriks_prd');

test('akun demo hanya bisa melihat, tanpa aksi apa pun', function () {
    $demo = User::factory()->role(Role::Demo)->create();
    $gate = Gate::forUser($demo);

    foreach (['ubah-status-insiden', 'usul-tindakan', 'putuskan-tindakan', 'kelola-lapsit', 'jalankan-uji-kesiapan', 'kelola-sistem', 'verifikasi-audit', 'kelola-penjaga-situs'] as $action) {
        expect($gate->allows($action))->toBeFalse("demo tidak boleh {$action}");
    }
    expect($gate->allows('lihat-dashboard'))->toBeTrue();
});

test('semua peran bisa membuka Beranda Komando', function (Role $role) {
    $this->actingAs(User::factory()->role($role)->create());

    $this->get('/beranda')->assertOk()->assertSee('Pohon Satuan');
})->with(Role::cases());

test('label DATA LATIHAN tampil di lab dan untuk akun Demo (FR-15)', function () {
    $this->seed();
    $analis = User::where('email', 'analis@sentra.lab')->first();
    $demo = User::where('email', 'demo@sentra.lab')->first();

    config(['sentra.lab.training_label' => false]);
    $this->actingAs($analis)->get('/beranda')->assertDontSee('data-testid="label-latihan"', false);
    $this->actingAs($demo)->get('/beranda')->assertSee('Data latihan')->assertSee('mode demo, hanya lihat');

    config(['sentra.lab.training_label' => true]);
    $this->actingAs($analis)->get('/beranda')->assertSee('Data latihan')->assertDontSee('mode demo, hanya lihat');
});
