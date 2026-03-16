<?php

namespace App\Policies;

use App\Models\AdvisorySession;
use App\Models\User;

class AdvisorySessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    public function view(User $user, AdvisorySession $session): bool
    {
        if ($user->id === $session->created_by_user_id || $user->isJefeOficina()) {
            return true;
        }

        // JEFE_DEPTO can only view sessions from teachers in their departments
        if ($user->isJefeDepto()) {
            $deptIds = $user->departments()->pluck('departments.id');
            return $session->creator->departments()->whereIn('departments.id', $deptIds)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isDocente();
    }
}
