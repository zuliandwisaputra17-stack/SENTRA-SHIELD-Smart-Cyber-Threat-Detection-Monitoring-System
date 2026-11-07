<?php

namespace Database\Factories;

use App\Enums\AlertLevel;
use App\Enums\UnitLevel;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hanya untuk test otomatis.
 *
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => 'Satuan Uji '.fake()->unique()->numberBetween(1, 99999),
            'code' => 'UJI-'.fake()->unique()->numberBetween(1, 99999),
            'level' => UnitLevel::Kodim,
            'alert_level' => AlertLevel::Aman,
        ];
    }

    public function childOf(Unit $parent, UnitLevel $level = UnitLevel::Kodim): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id, 'level' => $level]);
    }
}
