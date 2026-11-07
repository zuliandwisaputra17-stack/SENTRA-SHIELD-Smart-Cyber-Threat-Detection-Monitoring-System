<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Enums\AssetType;
use App\Models\Agent;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hanya untuk test otomatis.
 *
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'hostname' => 'host-uji-'.fake()->unique()->numberBetween(1, 99999),
            'ip' => fake()->localIpv4(),
            'version' => '0.1.0',
            'asset_type' => AssetType::Web,
            'status' => AgentStatus::Online,
        ];
    }
}
