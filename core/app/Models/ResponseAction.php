<?php

namespace App\Models;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tindakan respons (tabel `actions`). Jenis hanya dari daftar tetap ActionType.
 */
#[Fillable([
    'group_uuid', 'incident_id', 'campaign_id', 'type', 'target_agent_id', 'params',
    'status', 'proposed_by', 'approved_by', 'approved_at', 'decision_note',
    'executed_at', 'expires_at', 'result',
])]
class ResponseAction extends Model
{
    protected $table = 'actions';

    protected function casts(): array
    {
        return [
            'type' => ActionType::class,
            'status' => ActionStatus::class,
            'params' => 'array',
            'result' => 'array',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function targetAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'target_agent_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
