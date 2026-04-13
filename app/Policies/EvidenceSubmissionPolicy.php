<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\EvidenceSubmission;
use App\Models\User;

class EvidenceSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    public function view(User $user, EvidenceSubmission $submission): bool
    {
        if ($user->id === $submission->teacher_user_id || $user->isJefeOficina()) {
            return true;
        }

        // JEFE_DEPTO can only view submissions from teachers in their departments
        if ($user->isJefeDepto()) {
            $deptIds = $user->departments()->pluck('departments.id');
            return $submission->teacher->departments()->whereIn('departments.id', $deptIds)->exists();
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
        return $user->isJefeOficina() && $submission->status === SubmissionStatus::SUBMITTED;
    }

    public function finalApprove(User $user, EvidenceSubmission $submission): bool
    {
        return $user->isJefeDepto()
            && $submission->status === SubmissionStatus::APPROVED
            && $submission->office_reviewed_at !== null
            && $submission->final_approved_at === null
            && $this->isDepartmentScoped($user, $submission);
    }
    
    public function unlock(User $user, EvidenceSubmission $submission): bool
    {
        return $user->isJefeOficina();
    }

    public function markAsNA(User $user, EvidenceSubmission $submission): bool
    {
        if ($user->isJefeOficina()) {
            return true;
        }

        return $user->isJefeDepto() && $this->isDepartmentScoped($user, $submission);
    }

    private function isDepartmentScoped(User $user, EvidenceSubmission $submission): bool
    {
        $deptIds = $user->departments()->pluck('departments.id');

        if ($deptIds->isEmpty()) {
            return false;
        }

        return $submission->teacher->departments()->whereIn('departments.id', $deptIds)->exists();
    }
}
