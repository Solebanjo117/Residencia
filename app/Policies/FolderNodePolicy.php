<?php

namespace App\Policies;

use App\Models\FolderNode;
use App\Models\User;
use App\Support\FolderOwnership;

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
            if ($this->isOwnedByOrInsideOwnedFolder($user, $folderNode)) {
                return true;
            }

            if ($this->isCommonFolderInAccessibleSemester($user, $folderNode)) {
                return true;
            }

            return false;
        }

        return false;
    }

    public function upload(User $user, FolderNode $folderNode): bool
    {
        if ($user->isJefeOficina() || $user->isJefeDepto()) {
            return true;
        }

        if ($user->isDocente()) {
            return $this->isOwnedByOrInsideOwnedFolder($user, $folderNode);
        }

        return false;
    }

    public function create(User $user, FolderNode $parent): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    public function update(User $user, FolderNode $folderNode): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    public function move(User $user, FolderNode $folderNode): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    public function delete(User $user, FolderNode $folderNode): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    private function isOwnedByOrInsideOwnedFolder(User $user, FolderNode $folderNode): bool
    {
        return FolderOwnership::isOwnedByOrInsideOwnedFolder($user, $folderNode);
    }

    private function isCommonFolderInAccessibleSemester(User $user, FolderNode $folderNode): bool
    {
        if ($folderNode->owner_user_id !== null) {
            return false;
        }

        $semesterRoot = $this->semesterRootWithoutOtherTeacherAncestor($folderNode);

        if (! $semesterRoot) {
            return false;
        }

        return FolderNode::query()
            ->where('semester_id', $semesterRoot->semester_id)
            ->where('owner_user_id', $user->id)
            ->exists()
            || FolderNode::query()
                ->where('parent_id', $semesterRoot->id)
                ->whereNull('owner_user_id')
                ->exists();
    }

    private function semesterRootWithoutOtherTeacherAncestor(FolderNode $folderNode): ?FolderNode
    {
        $current = $folderNode;

        while ($current) {
            if ($current->parent_id === null) {
                return $current->owner_user_id === null && $current->semester_id !== null
                    ? $current
                    : null;
            }

            $current->loadMissing('parent');
            $parent = $current->parent;

            if ($parent && $parent->owner_user_id !== null) {
                return null;
            }

            $current = $parent;
        }

        return null;
    }
}
