<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Enums\ReviewDecision;
use App\Enums\SemesterStatus;
use App\Enums\SubmissionStatus;
use App\Models\Semester;
use App\Models\TeachingLoad;
use App\Models\EvidenceSubmission;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function __construct(
        protected EvidenceService $evidenceService
    ) {}

    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $departments = $user->departments()->pluck('departments.id');

        $activeSemester = Semester::where('status', SemesterStatus::OPEN)->first();

        if (!$activeSemester) {
            return Inertia::render('Oficina/PendingReviews', [
                'teachers' => [],
                'semester' => null
            ]);
        }

        $teachingLoads = TeachingLoad::with(['teacher', 'subject', 'submissions' => function($q) use ($activeSemester) {
            $q->where('semester_id', $activeSemester->id)
              ->where('status', SubmissionStatus::SUBMITTED);
        }])
        ->where('semester_id', $activeSemester->id)
        ->when($departments->count() > 0, function($q) use ($departments) {
            $q->whereHas('teacher.departments', function($sq) use ($departments) {
                $sq->whereIn('departments.id', $departments);
            });
        })
        ->get();

        // Group by user
        $teachersMap = [];

        foreach ($teachingLoads as $load) {
            $teacherId = $load->teacher_user_id;

            // Only count if they have submissions in "SUBMITTED" state awaiting review
            if ($load->submissions->count() === 0) {
                continue;
            }

            if (!isset($teachersMap[$teacherId])) {
                $teachersMap[$teacherId] = [
                    'id' => $teacherId,
                    'name' => $load->teacher->name,
                    'email' => $load->teacher->email,
                    'pending_groups' => [],
                    'total_pending' => 0
                ];
            }

            $teachersMap[$teacherId]['total_pending'] += $load->submissions->count();

            $teachersMap[$teacherId]['pending_groups'][] = [
                'load_id' => $load->id,
                'subject' => $load->subject->name,
                'group' => $load->group_name,
                'pending_count' => $load->submissions->count()
            ];
        }

        return Inertia::render('Oficina/PendingReviews', [
            'teachers' => array_values($teachersMap),
            'semester' => $activeSemester
        ]);
    }

    public function show($teacher_id)
    {
        /** @var \App\Models\User $reviewer */
        $reviewer = Auth::user();
        $activeSemester = Semester::where('status', SemesterStatus::OPEN)->first();

        if (!$activeSemester) {
            return redirect()->route('oficina.revisiones');
        }

        $teacherLoads = TeachingLoad::with([
                'subject',
                'submissions.evidenceItem',
                'submissions.files',
                'submissions.reviews' => fn ($query) => $query->with('reviewer')->orderByDesc('reviewed_at'),
                'submissions.officeReviewer',
                'submissions.finalApprover',
            ])
            ->where('teacher_user_id', $teacher_id)
            ->where('semester_id', $activeSemester->id)
            ->when($reviewer->departments()->exists(), function ($query) use ($reviewer) {
                $departmentIds = $reviewer->departments()->pluck('departments.id');

                $query->whereHas('teacher.departments', function ($teacherQuery) use ($departmentIds) {
                    $teacherQuery->whereIn('departments.id', $departmentIds);
                });
            })
            ->get();

        $teacher = User::findOrFail($teacher_id);

        return Inertia::render('Oficina/ReviewDetail', [
            'teacher' => $teacher,
            'teaching_loads' => $teacherLoads,
            'semester' => $activeSemester
        ]);
    }

    public function updateStatus(Request $request, $submission_id)
    {
        $startedAt = microtime(true);

        $request->validate([
            'status' => 'required|in:APPROVED,REJECTED,NA,NE',
            'comments' => 'nullable|string|max:500'
        ]);

        $submission = EvidenceSubmission::findOrFail($submission_id);
        $newStatus = SubmissionStatus::from($request->status);
        $oldStatus = $submission->status;

        /** @var \App\Models\User $reviewer */
        $reviewer = Auth::user();

        Log::channel('operations')->info('review.status_update_requested', [
            'actor_user_id' => $reviewer->id,
            'actor_role_id' => $reviewer->role_id,
            'submission_id' => $submission->id,
            'old_status' => $oldStatus->value,
            'requested_status' => $newStatus->value,
        ]);

        try {
            // For APPROVED/REJECTED, use the review workflow which creates
            // a review record, changes status, logs audit, and notifies teacher.
            if (in_array($newStatus, [SubmissionStatus::APPROVED, SubmissionStatus::REJECTED])) {
                $decision = $newStatus === SubmissionStatus::APPROVED
                    ? ReviewDecision::APPROVE
                    : ReviewDecision::REJECT;

                $this->evidenceService->review($submission, $reviewer, $decision, $request->comments);

                // If rejected, create an unlock record so the teacher can re-submit
                if ($newStatus === SubmissionStatus::REJECTED) {
                    $this->evidenceService->unlockForResubmission(
                        $submission,
                        $reviewer,
                        now()->addDays(3),
                        'Automatico tras rechazo de documento.'
                    );
                }
            } else {
                // For NA/NE status changes, use changeStatus directly
                $this->evidenceService->changeStatus(
                    $submission,
                    $newStatus,
                    $reviewer,
                    'Revision por Jefatura'
                );
            }

            Log::channel('operations')->info('review.status_updated', [
                'actor_user_id' => $reviewer->id,
                'actor_role_id' => $reviewer->role_id,
                'submission_id' => $submission->id,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $exception) {
            Log::channel('operations')->error('review.status_update_failed', [
                'actor_user_id' => $reviewer->id,
                'actor_role_id' => $reviewer->role_id,
                'submission_id' => $submission->id,
                'old_status' => $oldStatus->value,
                'requested_status' => $newStatus->value,
                'error' => $exception->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $exception;
        }

        return redirect()->back()->with('success', 'Estado de evidencia actualizado exitosamente.');
    }
}
