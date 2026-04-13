<?php

namespace App\Policies;

use App\Models\FolderNode;
use App\Models\User;

class FolderNodePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Everyone can see *some* folders
    }

    public function view(User $user, FolderNode $folderNode): bool
    {
        if ($user->isJefeOficina() || $user->isJefeDepto()) {
            return true;
        }

        if ($user->isDocente()) {
            if ($folderNode->owner_user_id === $user->id) {
                return true;
            }

            // Allow viewing if an ancestor folder is owned by the user
            $current = $folderNode->parent;
            while ($current) {
                if ($current->owner_user_id === $user->id) {
                    return true;
                }
                $current = $current->parent;
            }

            return false;
        }

        return false;
    }

    public function upload(User $user, FolderNode $folderNode): bool
    {
        return $this->view($user, $folderNode);
    }
}
