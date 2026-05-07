<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\EvidenceSubmission;
use App\Models\User;

class EvidenceSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdministrativeAuthority();
    }

    public function view(User $user, EvidenceSubmission $submission): bool
    {
        if ($user->id === $submission->teacher_user_id || $user->isAdministrativeAuthority()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isDocente();
    }

    public function update(User $user, EvidenceSubmission $submission): bool
    {
        // Teacher can update if DRAFT or REJECTED or Unlocked
        if ($user->id === $submission->teacher_user_id) {
            // Check unlock
            if ($submission->activeResubmissionUnlock) {
                return true;
            }

            // Standard rules
            return in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED]);
        }

        return false;
    }

    public function review(User $user, EvidenceSubmission $submission): bool
    {
        return $user->isAdministrativeAuthority() && $submission->status === SubmissionStatus::SUBMITTED;
    }

    public function finalApprove(User $user, EvidenceSubmission $submission): bool
    {
        return $user->isAdministrativeAuthority()
            && $submission->status === SubmissionStatus::APPROVED
            && $submission->office_reviewed_at !== null
            && $submission->final_approved_at === null;
    }

    public function unlock(User $user, EvidenceSubmission $submission): bool
    {
        return $user->isAdministrativeAuthority();
    }

    public function markAsNA(User $user, EvidenceSubmission $submission): bool
    {
        return $user->isAdministrativeAuthority();
    }
}
