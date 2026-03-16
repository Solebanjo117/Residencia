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
        if ($user->isJefeOficina()) {
            return true;
        }

        // JEFE_DEPTO can only view folders owned by teachers in their departments
        if ($user->isJefeDepto()) {
            if ($folderNode->owner_user_id === null) {
                return true; // Semester-level folders (no owner) are visible
            }
            $deptIds = $user->departments()->pluck('departments.id');
            return \App\Models\User::where('id', $folderNode->owner_user_id)
                ->whereHas('departments', fn($q) => $q->whereIn('departments.id', $deptIds))
                ->exists();
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
}
