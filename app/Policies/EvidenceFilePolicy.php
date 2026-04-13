<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\EvidenceFile;
use App\Models\User;

class EvidenceFilePolicy
{
    public function view(User $user, EvidenceFile $file): bool
    {
        if (!$file->folderNode) {
            return false;
        }

        return $user->can('view', $file->folderNode);
    }

    public function download(User $user, EvidenceFile $file): bool
    {
        return $this->view($user, $file);
    }

    public function preview(User $user, EvidenceFile $file): bool
    {
        return $this->view($user, $file);
    }

    public function replace(User $user, EvidenceFile $file): bool
    {
        return $this->canManage($user, $file);
    }

    public function delete(User $user, EvidenceFile $file): bool
    {
        return $this->canManage($user, $file);
    }

    private function canManage(User $user, EvidenceFile $file): bool
    {
        if ($user->isJefeOficina() || $user->isJefeDepto()) {
            return true;
        }

        $submission = $file->submission;
        if (!$submission) {
            return false;
        }

        if ($user->isDocente()) {
            // Teachers can only delete files from their own submission.
            if ((int) $submission->teacher_user_id !== (int) $user->id) {
                return false;
            }

            // Status must be DRAFT or REJECTED
            // Or if there is an active unlock
            if ($submission->activeResubmissionUnlock()->exists()) {
                return true;
            }

            return in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED], true);
        }

        return false;
    }
}
