<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Matriks hak akses (PRD bagian 3) dan akses halaman (bagian 11).
 * Setiap kemampuan didaftarkan sebagai Gate di AppServiceProvider.
 *
 * Peran Demo (F15) hanya mendapat kemampuan "lihat-*", tanpa aksi apa pun.
 */
final class AccessMatrix
{
    /** @var array<string, list<Role>> */
    public const ABILITIES = [
        // Bagian 3: matriks hak akses
        'lihat-dashboard' => [Role::Operator, Role::Analis, Role::Komandan, Role::Admin, Role::Demo],
        'ubah-status-insiden' => [Role::Operator, Role::Analis, Role::Komandan],
        'usul-tindakan' => [Role::Operator, Role::Analis],
        'putuskan-tindakan' => [Role::Komandan],
        'kelola-lapsit' => [Role::Analis, Role::Komandan],
        'jalankan-uji-kesiapan' => [Role::Analis, Role::Admin],
        'kelola-sistem' => [Role::Admin],
        'lihat-audit' => [Role::Analis, Role::Komandan, Role::Admin, Role::Demo],
        'verifikasi-audit' => [Role::Analis, Role::Komandan, Role::Admin],

        // Bagian 11: akses halaman
        'lihat-kampanye' => [Role::Analis, Role::Komandan, Role::Demo],
        'lihat-satuan-agent' => [Role::Admin, Role::Analis],
        'lihat-uji-kesiapan' => [Role::Analis, Role::Admin, Role::Demo],
        'lihat-penjaga-situs' => [Role::Analis, Role::Demo],
        // Halaman Penjaga Situs hanya untuk Analis: Analis juga mengelola daftar situsnya.
        'kelola-penjaga-situs' => [Role::Analis],
    ];

    public static function allows(Role $role, string $ability): bool
    {
        return in_array($role, self::ABILITIES[$ability] ?? [], true);
    }
}
