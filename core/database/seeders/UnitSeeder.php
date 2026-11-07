<?php

namespace Database\Seeders;

use App\Enums\UnitLevel;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Hierarki satuan lab dengan nama generik (keputusan B11), sesuai lab demo
 * bagian 12: Kodim A dan Kodim B di bawah satu Korem dan satu Kodam.
 */
class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $kodam = Unit::query()->updateOrCreate(
            ['code' => 'KODAM-LAT-I'],
            ['name' => 'Kodam Latihan I', 'level' => UnitLevel::Kodam, 'parent_id' => null],
        );

        $korem = Unit::query()->updateOrCreate(
            ['code' => 'KOREM-LAT-01'],
            ['name' => 'Korem Latihan 01', 'level' => UnitLevel::Korem, 'parent_id' => $kodam->id],
        );

        foreach (['A', 'B'] as $suffix) {
            Unit::query()->updateOrCreate(
                ['code' => 'KODIM-LAT-'.$suffix],
                ['name' => 'Kodim Latihan '.$suffix, 'level' => UnitLevel::Kodim, 'parent_id' => $korem->id],
            );
        }
    }
}
