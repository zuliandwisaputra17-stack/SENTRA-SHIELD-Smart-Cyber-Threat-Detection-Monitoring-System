<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['run_id', 'scenario', 'rule_id', 'agent_id', 'detected', 'ttd_ms', 'note', 'started_at'])]
class ReadinessResult extends Model
{
    /** Simpan pecahan detik (kolom DATETIME(3)/(6)). */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'detected' => 'boolean',
            'ttd_ms' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReadinessRun::class, 'run_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DetectionRule::class, 'rule_id');
    }
}
