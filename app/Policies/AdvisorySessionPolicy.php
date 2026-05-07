<?php

namespace App\Policies;

use App\Models\AdvisorySession;
use App\Models\User;

class AdvisorySessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrativeAuthority();
    }

    public function view(User $user, AdvisorySession $session): bool
    {
        if ($user->id === $session->created_by_user_id || $user->isAdministrativeAuthority()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isDocente();
    }
}
