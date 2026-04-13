<?php

namespace App\Services;

use App\Enums\ReviewDecision;
use App\Enums\SubmissionStatus;
use App\Enums\NotificationType;
use App\Models\EvidenceReview;
use App\Models\EvidenceStatusHistory;
use App\Models\EvidenceSubmission;
use App\Models\ResubmissionUnlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvidenceService
{
    protected $auditService;
    protected $notificationService;

    /**
     * Valid state transitions map: from => [allowed destinations]
     */
    private const ALLOWED_TRANSITIONS = [
        // Teacher can submit, office can classify as NA/NE when applicable.
        'DRAFT'     => ['SUBMITTED', 'NA', 'NE'],
        'SUBMITTED' => ['APPROVED', 'REJECTED', 'NA', 'NE'],
        // Approved is terminal unless a dedicated reopening flow is implemented.
        'APPROVED'  => [],
        // Rejected evidence must be corrected and re-submitted; office may still mark NA/NE.
        'REJECTED'  => ['SUBMITTED', 'NA', 'NE'],
        'NA'        => ['DRAFT'],
        'NE'        => ['DRAFT'],
    ];

    public function __construct(AuditService $auditService, NotificationService $notificationService)
    {
        $this->auditService = $auditService;
        $this->notificationService = $notificationService;
    }

    public function changeStatus(EvidenceSubmission $submission, SubmissionStatus $newStatus, User $user, ?string $reason = null)
    {
        $startedAt = microtime(true);
        $oldStatus = $submission->status;

        if ($oldStatus === $newStatus) {
            Log::channel('operations')->info('evidence.status_change_skipped', [
                'actor_user_id' => $user->id,
                'actor_role_id' => $user->role_id,
                'submission_id' => $submission->id,
                'status' => $newStatus->value,
            ]);

            return $submission;
        }

        // Validate the state transition
        $allowedTargets = self::ALLOWED_TRANSITIONS[$oldStatus->value] ?? [];
        if (!in_array($newStatus->value, $allowedTargets)) {
            throw new \InvalidArgumentException(
                "Transición de estado no permitida: {$oldStatus->value} -> {$newStatus->value}"
            );
        }

        DB::transaction(function () use ($submission, $newStatus, $user, $reason, $oldStatus) {
            $submission->update([
                'status' => $newStatus,
                'last_updated_at' => now(),
                'submitted_at' => ($newStatus === SubmissionStatus::SUBMITTED) ? now() : $submission->submitted_at,
            ]);

            EvidenceStatusHistory::create([
                'submission_id' => $submission->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by_user_id' => $user->id,
                'change_reason' => $reason,
                'changed_at' => now(),
            ]);

            $this->auditService->log($user, 'CHANGE_STATUS', 'EvidenceSubmission', $submission->id, [
                'from' => $oldStatus->value,
                'to' => $newStatus->value
            ]);
        });

        Log::channel('operations')->info('evidence.status_changed', [
            'actor_user_id' => $user->id,
            'actor_role_id' => $user->role_id,
            'submission_id' => $submission->id,
            'semester_id' => $submission->semester_id,
            'evidence_item_id' => $submission->evidence_item_id,
            'teaching_load_id' => $submission->teaching_load_id,
            'from_status' => $oldStatus->value,
            'to_status' => $newStatus->value,
            'reason' => $reason,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $submission;
    }

    public function review(EvidenceSubmission $submission, User $reviewer, ReviewDecision $decision, ?string $comments)
    {
        $startedAt = microtime(true);

        $result = DB::transaction(function () use ($submission, $reviewer, $decision, $comments) {
            // Create review record
            EvidenceReview::create([
                'submission_id' => $submission->id,
                'reviewed_by_user_id' => $reviewer->id,
                'decision' => $decision,
                'comments' => $comments,
                'reviewed_at' => now(),
            ]);

            // Determine new status
            $newStatus = match ($decision) {
                ReviewDecision::APPROVE => SubmissionStatus::APPROVED,
                ReviewDecision::REJECT => SubmissionStatus::REJECTED,
            };

            // Update status
            $this->changeStatus($submission, $newStatus, $reviewer, "Review decision: " . $decision->value);

            // Notify teacher
            $type = ($decision === ReviewDecision::APPROVE) 
                ? NotificationType::SUBMISSION_APPROVED 
                : NotificationType::SUBMISSION_REJECTED;
            
            $this->notificationService->notifyImmediate(
                $submission->teacher,
                $type,
                "Evidence Reviewed: " . $submission->evidenceItem->name,
                "Your submission has been " . strtolower($decision->value) . ". Comments: " . $comments,
                $submission
            );

            return $submission;
        });

        Log::channel('operations')->info('evidence.review_completed', [
            'actor_user_id' => $reviewer->id,
            'actor_role_id' => $reviewer->role_id,
            'submission_id' => $submission->id,
            'semester_id' => $submission->semester_id,
            'evidence_item_id' => $submission->evidence_item_id,
            'decision' => $decision->value,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $result;
    }

    public function unlockForResubmission(EvidenceSubmission $submission, User $unlocker, ?Carbon $expiresAt, ?string $reason)
    {
        $startedAt = microtime(true);

        $result = DB::transaction(function () use ($submission, $unlocker, $expiresAt, $reason) {
            ResubmissionUnlock::create([
                'submission_id' => $submission->id,
                'unlocked_by_user_id' => $unlocker->id,
                'unlocked_at' => now(),
                'expires_at' => $expiresAt,
                'reason' => $reason,
            ]);
            
            $this->auditService->log($unlocker, 'UNLOCK_RESUBMISSION', 'EvidenceSubmission', $submission->id, ['reason' => $reason]);
            
            return $submission;
        });

        Log::channel('operations')->info('evidence.resubmission_unlocked', [
            'actor_user_id' => $unlocker->id,
            'actor_role_id' => $unlocker->role_id,
            'submission_id' => $submission->id,
            'semester_id' => $submission->semester_id,
            'evidence_item_id' => $submission->evidence_item_id,
            'expires_at' => $expiresAt?->toIso8601String(),
            'reason' => $reason,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $result;
    }
}
