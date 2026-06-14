<?php

namespace App\Support;

use App\Models\FolderNode;
use App\Models\User;

class FolderOwnership
{
    public static function isOwnedByOrInsideOwnedFolder(User $user, ?FolderNode $folderNode): bool
    {
        $current = $folderNode;

        while ($current) {
            if ((int) $current->owner_user_id === (int) $user->id) {
                return true;
            }

            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return false;
    }
}
