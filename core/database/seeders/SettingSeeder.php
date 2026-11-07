<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Nilai awal halaman Pengaturan. Nilai yang sudah diubah Admin tidak ditimpa.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'block_duration_s' => 3600,
            'ip_whitelist' => [],
            'ollama_url' => config('sentra.ollama.url'),
            'ollama_model' => config('sentra.ollama.model'),
            'ollama_timeout_s' => 60,
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
