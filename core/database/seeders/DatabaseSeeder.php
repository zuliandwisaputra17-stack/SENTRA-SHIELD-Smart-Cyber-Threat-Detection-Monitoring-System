<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Hanya data master (satuan, akun, aturan, pengaturan). Seeder ini sengaja
 * TIDAK membuat event, deteksi, insiden, atau angka apa pun: semua data
 * dashboard harus berasal dari event asli yang dikirim agent.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnitSeeder::class,
            UserSeeder::class,
            DetectionRuleSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
