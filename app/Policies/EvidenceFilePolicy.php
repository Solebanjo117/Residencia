<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\EvidenceFile;
use App\Models\User;

class EvidenceFilePolicy
{
    public function view(User $user, EvidenceFile $file): bool
    {
        return $user->can('view', $file->folderNode);
    }

    public function download(User $user, EvidenceFile $file): bool
    {
        return $this->view($user, $file);
    }

    public function delete(User $user, EvidenceFile $file): bool
    {
        if ($user->isJefeOficina()) {
            return true;
        }

        if ($user->isDocente()) {
            // Only own files
            if ($file->uploaded_by_user_id !== $user->id) {
                return false;
            }

            // Check submission status
            $submission = $file->submission;
            if (!$submission) {
                return true; // Orphaned file? Or maybe just allow delete if no submission link (shouldn't happen per schema but safe fallback)
            }

            // Status must be DRAFT or REJECTED
            // Or if there is an active unlock
            if ($submission->activeResubmissionUnlock) {
                return true;
            }

            return in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED]);
        }

        return false;
    }
}
