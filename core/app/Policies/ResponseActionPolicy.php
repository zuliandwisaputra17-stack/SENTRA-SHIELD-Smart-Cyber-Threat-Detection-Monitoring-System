<?php

namespace App\Policies;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Models\ResponseAction;
use App\Models\User;
use App\Support\AccessMatrix;

/**
 * Two-person rule: pengusul tidak pernah bisa memutuskan tindakannya sendiri,
 * apa pun perannya.
 */
class ResponseActionPolicy
{
    public function decide(User $user, ResponseAction $action): bool
    {
        return AccessMatrix::allows($user->role, 'putuskan-tindakan')
            && $action->status === ActionStatus::Diusulkan
            && $action->proposed_by !== $user->id;
    }

    /** Pengusul membatalkan usulannya sendiri selama belum diputuskan. */
    public function cancel(User $user, ResponseAction $action): bool
    {
        return $action->status === ActionStatus::Diusulkan && $action->proposed_by === $user->id;
    }

    /** Rollback (buka blokir) hanya oleh Komandan untuk blokir yang sudah dieksekusi. */
    public function rollback(User $user, ResponseAction $action): bool
    {
        return AccessMatrix::allows($user->role, 'putuskan-tindakan')
            && $action->type === ActionType::BlockIp
            && $action->status === ActionStatus::Dieksekusi;
    }
}
