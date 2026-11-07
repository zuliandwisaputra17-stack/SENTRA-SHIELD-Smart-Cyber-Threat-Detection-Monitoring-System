<?php

namespace App\Models;

use App\Enums\AlertLevel;
use App\Enums\UnitLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'code', 'level', 'alert_level'])]
class Unit extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'level' => UnitLevel::class,
            'alert_level' => AlertLevel::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Unit::class, 'parent_id')->orderBy('name');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * ID satuan ini beserta seluruh keturunannya. Tabel satuan kecil, jadi
     * cukup dimuat sekali lalu ditelusuri di memori.
     *
     * @return list<int>
     */
    public static function subtreeIds(int $rootId): array
    {
        $childrenOf = [];
        foreach (static::query()->get(['id', 'parent_id']) as $unit) {
            $childrenOf[$unit->parent_id ?? 0][] = $unit->id;
        }

        $result = [];
        $stack = [$rootId];
        while ($stack !== []) {
            $id = array_pop($stack);
            if (in_array($id, $result, true)) {
                continue;
            }
            $result[] = $id;
            foreach ($childrenOf[$id] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        sort($result);

        return $result;
    }

    /**
     * Pohon satuan (akar beserta anak bertingkat), opsional dibatasi ke daftar ID.
     *
     * @param  list<int>|null  $onlyIds
     * @return Collection<int, Unit>
     */
    public static function tree(?array $onlyIds = null): Collection
    {
        $units = static::query()->orderBy('name')->get();
        if ($onlyIds !== null) {
            $units = $units->whereIn('id', $onlyIds)->values();
        }

        $byParent = $units->groupBy(fn (Unit $u) => $u->parent_id ?? 0);
        $present = $units->pluck('id')->all();

        foreach ($units as $unit) {
            $unit->setRelation('children', new Collection($byParent->get($unit->id, collect())->all()));
        }

        // Akar = satuan yang induknya tidak ikut dimuat.
        return new Collection($units->filter(
            fn (Unit $u) => $u->parent_id === null || ! in_array($u->parent_id, $present, true)
        )->values()->all());
    }

    /**
     * Tingkat Siaga efektif: tertinggi dari satuan ini dan seluruh anaknya.
     * Memakai relasi children yang sudah dimuat (lihat tree()).
     */
    public function effectiveAlertLevel(): AlertLevel
    {
        $levels = [$this->alert_level];
        foreach ($this->children as $child) {
            $levels[] = $child->effectiveAlertLevel();
        }

        return AlertLevel::highest(...$levels);
    }
}
