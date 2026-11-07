<?php

namespace Database\Factories;

use App\Enums\IncidentStatus;
use App\Enums\Severity;
use App\Models\Incident;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hanya untuk test otomatis.
 *
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'src_ip' => fake()->ipv4(),
            'title' => 'Insiden uji',
            'score' => 0,
            'severity' => Severity::Rendah,
            'status' => IncidentStatus::Baru,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
