<?php

namespace App\Models;

use App\Enums\ReadinessStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['started_by', 'unit_id', 'agent_id', 'status', 'coverage', 'avg_ttd_ms', 'started_at', 'finished_at'])]
class ReadinessRun extends Model
{
    /** Simpan pecahan detik (kolom DATETIME(3)/(6)). */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'status' => ReadinessStatus::class,
            'coverage' => 'float',
            'avg_ttd_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ReadinessResult::class, 'run_id');
    }
}
