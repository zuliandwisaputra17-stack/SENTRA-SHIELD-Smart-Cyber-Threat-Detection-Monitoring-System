<?php

namespace App\Readiness;

/**
 * Daftar tetap skenario Uji Kesiapan (FR-08). Setiap skenario terhubung ke
 * satu aturan (kode sama) dan satu teknik ATT&CK, menyasar host agent itu
 * sendiri (127.0.0.1), dan menghasilkan event bertanda uji (is_test) yang
 * tidak pernah menjadi insiden.
 *
 * R08 tidak dipakai: butuh akun SSH yang benar-benar bisa login.
 */
final class ScenarioCatalog
{
    /** @var array<string, array{nama: string, sumber: ?string}> */
    public const SCENARIOS = [
        'R01' => ['nama' => 'Koneksi ke 20 port uji yang tertutup', 'sumber' => 'firewall'],
        'R02' => ['nama' => 'Request dengan user agent alat pemindai', 'sumber' => 'web'],
        'R03' => ['nama' => '30 request ke halaman yang tidak ada (404)', 'sumber' => 'web'],
        'R04' => ['nama' => 'Request berpola SQL injection', 'sumber' => 'web'],
        'R05' => ['nama' => 'Request berpola XSS', 'sumber' => 'web'],
        'R06' => ['nama' => 'Request berpola path traversal', 'sumber' => 'web'],
        'R07' => ['nama' => '10 login SSH gagal ke akun uji', 'sumber' => 'ssh'],
        'R09' => ['nama' => 'File uji berpola webshell di folder web', 'sumber' => 'fim'],
        'R10' => ['nama' => 'Perubahan metadata halaman utama (isi tidak diubah)', 'sumber' => 'fim'],
        'R11' => ['nama' => 'Perintah tulis Modbus ke register uji', 'sumber' => 'modbus'],
        'R12' => ['nama' => 'Jeda heartbeat agent 45 detik', 'sumber' => null],
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::SCENARIOS);
    }
}
