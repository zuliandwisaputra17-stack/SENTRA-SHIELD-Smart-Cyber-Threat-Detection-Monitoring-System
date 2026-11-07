<?php

namespace App\Models;

use App\Enums\DetectionAttribution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rule_id', 'event_id', 'incident_id', 'agent_id', 'src_ip', 'unit_id',
    'attack_id', 'weight', 'attribution', 'detected_at',
])]
class Detection extends Model
{
    /** Simpan pecahan detik (kolom DATETIME(3)/(6)). */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'attribution' => DetectionAttribution::class,
            'detected_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DetectionRule::class, 'rule_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SecurityEvent::class, 'event_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
