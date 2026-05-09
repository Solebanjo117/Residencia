<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\EvidenceFile;
use App\Models\User;

class EvidenceFilePolicy
{
    public function view(User $user, EvidenceFile $file): bool
    {
        if (! $file->folderNode) {
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

    public function move(User $user, EvidenceFile $file): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    private function canManage(User $user, EvidenceFile $file): bool
    {
        if ($user->isJefeOficina() || $user->isJefeDepto()) {
            return true;
        }

        $submission = $file->submission;
        if (! $submission) {
            return false;
        }

        if ($user->isDocente()) {
            if ((int) $submission->teacher_user_id !== (int) $user->id) {
                return false;
            }

            if ($submission->activeResubmissionUnlock()->exists()) {
                return true;
            }

            if (in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED], true)) {
                return true;
            }

            if ($submission->status === SubmissionStatus::SUBMITTED
                && $submission->office_reviewed_at === null
                && $submission->final_approved_at === null) {
                return true;
            }

            return false;
        }

        return false;
    }
}
