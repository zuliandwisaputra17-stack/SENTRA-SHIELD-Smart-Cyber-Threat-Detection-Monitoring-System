<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'unit_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Satuan yang boleh dilihat pengguna ini. null berarti semua satuan.
     * Operator: satuannya sendiri beserta satuan di bawahnya.
     *
     * @return list<int>|null
     */
    public function visibleUnitIds(): ?array
    {
        if ($this->role->seesAllUnits()) {
            return null;
        }

        if ($this->unit_id === null) {
            return [];
        }

        return Unit::subtreeIds($this->unit_id);
    }

    public function canSeeUnit(int $unitId): bool
    {
        $ids = $this->visibleUnitIds();

        return $ids === null || in_array($unitId, $ids, true);
    }
}
