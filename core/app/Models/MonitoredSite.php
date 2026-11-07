<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['unit_id', 'url', 'baseline_hash', 'update_windows'])]
class MonitoredSite extends Model
{
    protected function casts(): array
    {
        return [
            'update_windows' => 'array',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(SiteCheck::class, 'site_id');
    }
}
