<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Enums\KillChainStage;
use App\Enums\Severity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'unit_id', 'campaign_id', 'agent_id', 'src_ip', 'title', 'stage_max', 'score',
    'score_breakdown', 'severity', 'status', 'first_seen_at', 'last_seen_at',
])]
class Incident extends Model
{
    /** Simpan pecahan detik (kolom DATETIME(3)/(6)). */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    use HasFactory;

    protected function casts(): array
    {
        return [
            'stage_max' => KillChainStage::class,
            'score' => 'integer',
            'score_breakdown' => 'array',
            'severity' => Severity::class,
            'status' => IncidentStatus::class,
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', IncidentStatus::openValues());
    }

    /** Batasi ke satuan yang boleh dilihat pengguna. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $ids = $user->visibleUnitIds();
        if ($ids !== null) {
            $query->whereIn('unit_id', $ids);
        }
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function detections(): HasMany
    {
        return $this->hasMany(Detection::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ResponseAction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
