<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Satu akun per peran untuk lab. Kata sandi awal diambil dari
 * SENTRA_SEED_PASSWORD. Akun yang sudah ada tidak ditimpa, sehingga kata sandi
 * yang sudah diganti tetap aman saat seeder dijalankan ulang.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('sentra.seed_password');
        $unit = fn (string $code) => Unit::query()->where('code', $code)->value('id');

        $accounts = [
            ['admin@sentra.lab', 'Admin Sistem', Role::Admin, 'KODAM-LAT-I'],
            ['komandan@sentra.lab', 'Komandan Latihan', Role::Komandan, 'KODAM-LAT-I'],
            ['analis@sentra.lab', 'Analis Siber', Role::Analis, 'KODAM-LAT-I'],
            ['operator.a@sentra.lab', 'Operator Kodim A', Role::Operator, 'KODIM-LAT-A'],
            ['operator.b@sentra.lab', 'Operator Kodim B', Role::Operator, 'KODIM-LAT-B'],
            ['demo@sentra.lab', 'Akun Demo', Role::Demo, 'KODAM-LAT-I'],
        ];

        foreach ($accounts as [$email, $name, $role, $unitCode]) {
            User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => $role,
                    'unit_id' => $unit($unitCode),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
