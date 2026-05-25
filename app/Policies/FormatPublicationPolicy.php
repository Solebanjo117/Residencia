<?php

namespace App\Policies;

use App\Models\FormatPublication;
use App\Models\User;

class FormatPublicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDocente() || $user->isAdministrativeAuthority();
    }

    public function view(User $user, FormatPublication $publication): bool
    {
        if ($user->isAdministrativeAuthority()) {
            return true;
        }

        return $user->isDocente() && $publication->isActive();
    }

    public function download(User $user, FormatPublication $publication): bool
    {
        return $this->view($user, $publication);
    }

    public function create(User $user): bool
    {
        return $user->isAdministrativeAuthority();
    }

    public function update(User $user, FormatPublication $publication): bool
    {
        return $user->isAdministrativeAuthority();
    }

    public function replaceFile(User $user, FormatPublication $publication): bool
    {
        return $user->isAdministrativeAuthority();
    }

    public function archive(User $user, FormatPublication $publication): bool
    {
        return $user->isAdministrativeAuthority();
    }

    public function restore(User $user, FormatPublication $publication): bool
    {
        return $user->isAdministrativeAuthority();
    }
}

