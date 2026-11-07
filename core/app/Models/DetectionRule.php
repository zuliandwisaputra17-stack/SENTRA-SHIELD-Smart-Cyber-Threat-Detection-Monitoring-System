<?php

namespace App\Models;

use App\Enums\KillChainStage;
use App\Enums\RuleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'source', 'type', 'config', 'attack_id', 'attack_name',
    'stage', 'weight', 'enabled',
])]
class DetectionRule extends Model
{
    protected function casts(): array
    {
        return [
            'type' => RuleType::class,
            'stage' => KillChainStage::class,
            'config' => 'array',
            'weight' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    /** Sumber bisa lebih dari satu, contoh R10 "fim/site". @return list<string> */
    public function sources(): array
    {
        return array_values(array_filter(array_map('trim', explode('/', $this->source))));
    }

    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    public function detections(): HasMany
    {
        return $this->hasMany(Detection::class, 'rule_id');
    }
}
