<?php

namespace App\Models;

use App\Enums\AgentStatus;
use App\Enums\AssetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * Satu host = satu agent. Token API dikelola Sanctum (ter-hash).
 */
#[Fillable([
    'unit_id', 'hostname', 'ip', 'version', 'asset_type', 'status',
    'enrollment_code_hash', 'enrollment_expires_at', 'sources', 'last_heartbeat_at',
])]
#[Hidden(['enrollment_code_hash'])]
class Agent extends Model
{
    /** Simpan pecahan detik (kolom DATETIME(3)/(6)). */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    use HasApiTokens, HasFactory;

    protected function casts(): array
    {
        return [
            'asset_type' => AssetType::class,
            'status' => AgentStatus::class,
            'sources' => 'array',
            'enrollment_expires_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ResponseAction::class, 'target_agent_id');
    }
}
