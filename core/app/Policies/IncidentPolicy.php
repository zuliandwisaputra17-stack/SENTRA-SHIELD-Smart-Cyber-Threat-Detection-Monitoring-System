<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;
use App\Support\AccessMatrix;

class IncidentPolicy
{
    public function view(User $user, Incident $incident): bool
    {
        return AccessMatrix::allows($user->role, 'lihat-dashboard')
            && $user->canSeeUnit($incident->unit_id);
    }

    public function updateStatus(User $user, Incident $incident): bool
    {
        return AccessMatrix::allows($user->role, 'ubah-status-insiden')
            && $user->canSeeUnit($incident->unit_id);
    }

    public function proposeAction(User $user, Incident $incident): bool
    {
        return AccessMatrix::allows($user->role, 'usul-tindakan')
            && $user->canSeeUnit($incident->unit_id);
    }
}
