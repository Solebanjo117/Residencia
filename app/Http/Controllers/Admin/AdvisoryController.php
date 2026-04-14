<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewDecision;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\EvidenceReview;
use App\Models\Semester;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Services\EvidenceFlowService;
use App\Services\EvidenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdvisoryController extends Controller
{
    public function index(Request $request, EvidenceFlowService $flowService)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $department = $user->departments()->first();

        $semesterQuery = $request->input('semester');

        $semester = $semesterQuery
            ? Semester::where('name', $semesterQuery)->first()
            : Semester::activeOrLatest();

        if (!$semester || !$department) {
            return Inertia::render('SeguimientoDocente', [
                'rows' => [],
                'semesters' => Semester::pluck('name')->toArray(),
                'columns' => [],
                'currentSemester' => $semesterQuery ?? '',
                'userRole' => $user->role->name ?? '',
            ]);
        }

        $requirements = $flowService->requirementsForDepartment($semester->id, $department->id);
        $evidenceItems = $requirements->map(fn (EvidenceRequirement $requirement) => $requirement->evidenceItem);

        $loadsQuery = TeachingLoad::with(['teacher.departments', 'subject'])
            ->where('semester_id', $semester->id);

        if ($user->isDocente()) {
            $loadsQuery->where('teacher_user_id', $user->id);
        } elseif ($user->isJefeDepto()) {
            $teacherIds = $department->teachers()->pluck('users.id');

            $loadsQuery->whereIn('teacher_user_id', $teacherIds);
        }

        $teachingLoads = $loadsQuery->get();

        $submissions = EvidenceSubmission::with([
            'files',
            'reviews' => fn ($query) => $query->with('reviewer')->orderByDesc('reviewed_at'),
            'officeReviewer',
            'finalApprover',
            'activeResubmissionUnlock',
        ])
            ->where('semester_id', $semester->id)
            ->whereIn('teaching_load_id', $teachingLoads->pluck('id'))
            ->get()
            ->groupBy('teaching_load_id');

        $windows = SubmissionWindow::query()
            ->where('semester_id', $semester->id)
            ->where('status', 'ACTIVE')
            ->get()
            ->keyBy('evidence_item_id');
        $isHistoricalSemester = $semester->status !== \App\Enums\SemesterStatus::OPEN;

        $rows = [];

        foreach ($teachingLoads as $load) {
            $loadSubmissions = ($submissions->get($load->id) ?? collect())->keyBy('evidence_item_id');

            $rowData = [
                'id' => $load->id,
                'maestro' => $load->teacher->name,
                'materia' => $load->subject->name,
                'carrera' => $load->group_name,
                'clave_tecnm' => $load->subject->code,
                'semestre' => $semester->name,
                'cells' => [],
            ];

            foreach ($requirements as $requirement) {
                $item = $requirement->evidenceItem;
                $submission = $loadSubmissions->get($item->id);
                $stageUnlocked = $flowService->isStageUnlocked($requirement, $requirements, $loadSubmissions);
                $availability = $flowService->resolveAvailability(
                    $windows->get($item->id),
                    $stageUnlocked,
                    $submission?->activeResubmissionUnlock !== null,
                    $submission,
                    $isHistoricalSemester
                );

                $uiStatus = $flowService->uiStatus($submission, $availability);
                $lastReview = $submission?->reviews?->first();

                $rowData['cells']['item_' . $item->id] = [
                    'status' => $uiStatus,
                    'db_status' => $submission?->status?->value,
                    'submission_id' => $submission?->id,
                    'teaching_load_id' => $load->id,
                    'evidence_item_id' => $item->id,
                    'stage_order' => $flowService->stageOrder($item->name),
                    'stage_label' => $flowService->stageLabel($flowService->stageOrder($item->name)),
                    'availability' => $availability,
                    'is_late' => (bool) $submission?->submitted_late || (bool) $availability['is_late'],
                    'office_approved_at' => $submission?->office_reviewed_at?->toDateTimeString(),
                    'office_approved_by' => $submission?->officeReviewer?->name,
                    'final_approved_at' => $submission?->final_approved_at?->toDateTimeString(),
                    'final_approved_by' => $submission?->finalApprover?->name,
                    'files' => $submission ? $submission->files->map(fn ($file) => [
                        'id' => $file->id,
                        'file_name' => $file->file_name,
                        'size' => $file->size_bytes,
                        'uploaded_at' => $file->uploaded_at?->toDateTimeString(),
                    ])->values()->toArray() : [],
                    'last_review' => $lastReview ? [
                        'stage' => $lastReview->stage,
                        'decision' => $lastReview->decision->value,
                        'comments' => $lastReview->comments,
                        'reviewed_at' => $lastReview->reviewed_at?->toDateTimeString(),
                        'reviewer_name' => $lastReview->reviewer?->name,
                    ] : null,
                    'review_trail' => $submission
                        ? $submission->reviews->map(fn (EvidenceReview $review) => [
                            'stage' => $review->stage,
                            'decision' => $review->decision->value,
                            'comments' => $review->comments,
                            'reviewed_at' => $review->reviewed_at?->toDateTimeString(),
                            'reviewer_name' => $review->reviewer?->name,
                        ])->values()->toArray()
                        : [],
                    'can_office_review' => $user->isJefeOficina()
                        && $submission?->status === SubmissionStatus::SUBMITTED,
                    'can_final_approve' => $user->isJefeDepto()
                        && $submission?->isOfficeApproved()
                        && !$submission?->isFinalApproved(),
                    'can_mark_na' => ($user->isJefeOficina() || $user->isJefeDepto())
                        && !$submission?->isFinalApproved(),
                    'can_reactivate' => ($user->isJefeOficina() || $user->isJefeDepto())
                        && $submission?->status === SubmissionStatus::NA,
                ];
            }

            $rowData['estado_final'] = $this->resolveRowStatus(collect($rowData['cells']));
            $rows[] = $rowData;
        }

        return Inertia::render('SeguimientoDocente', [
            'rows' => $rows,
            'semesters' => Semester::pluck('name')->toArray(),
            'columns' => $evidenceItems->map(fn ($item) => [
                'key' => 'item_' . $item->id,
                'label' => $item->name,
                'item_id' => $item->id,
                'stage_order' => $flowService->stageOrder($item->name),
                'stage_label' => $flowService->stageLabel($flowService->stageOrder($item->name)),
            ])->values()->toArray(),
            'currentSemester' => $semester->name,
            'userRole' => $user->role->name ?? '',
        ]);
    }

    public function reviewEvidence(Request $request, EvidenceSubmission $submission, EvidenceService $evidenceService)
    {
        $this->authorize('review', $submission);

        $request->validate([
            'decision' => 'required|in:APPROVE,REJECT',
            'comments' => 'nullable|string|max:500',
        ]);

        $decision = ReviewDecision::from($request->decision);

        $evidenceService->review(
            $submission,
            $request->user(),
            $decision,
            $request->comments
        );

        return back()->with('success', $decision === ReviewDecision::APPROVE
            ? 'Evidencia aprobada por oficina.'
            : 'Evidencia rechazada y devuelta al docente.'
        );
    }

    public function finalApprove(Request $request, EvidenceSubmission $submission, EvidenceService $evidenceService)
    {
        $this->authorize('finalApprove', $submission);

        $request->validate([
            'comments' => 'nullable|string|max:500',
        ]);

        $evidenceService->finalApprove($submission, $request->user(), $request->string('comments')->trim()->toString() ?: null);

        return back()->with('success', 'Visto bueno final registrado correctamente.');
    }

    public function upsertCellStatus(Request $request, EvidenceService $evidenceService)
    {
        $validated = $request->validate([
            'teaching_load_id' => 'required|exists:teaching_loads,id',
            'evidence_item_id' => 'required|integer',
            'status' => 'required|in:NA,DRAFT',
            'comments' => 'nullable|string|max:500',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $load = TeachingLoad::with('teacher.departments')->findOrFail($validated['teaching_load_id']);

        abort_unless($this->canManageLoad($user, $load), 403);

        $submission = EvidenceSubmission::firstOrCreate(
            [
                'semester_id' => $load->semester_id,
                'teacher_user_id' => $load->teacher_user_id,
                'evidence_item_id' => $validated['evidence_item_id'],
                'teaching_load_id' => $load->id,
            ],
            [
                'status' => SubmissionStatus::DRAFT,
                'last_updated_at' => now(),
            ]
        );

        abort_unless($user->can('markAsNA', $submission), 403);

        if ($submission->isFinalApproved()) {
            return back()->with('error', 'No puedes cambiar la aplicabilidad de una evidencia con visto bueno final.');
        }

        $targetStatus = SubmissionStatus::from($validated['status']);
        $reason = $validated['comments'] ?: ($targetStatus === SubmissionStatus::NA
            ? 'Marcado manualmente como no aplica.'
            : 'Reactivado para captura y envio.');

        $evidenceService->changeStatus($submission, $targetStatus, $user, $reason);

        return back()->with('success', $targetStatus === SubmissionStatus::NA
            ? 'La evidencia quedo marcada como no aplica.'
            : 'La evidencia quedo reactiva para captura.'
        );
    }

    private function canManageLoad($user, TeachingLoad $load): bool
    {
        if ($user->isJefeOficina()) {
            return true;
        }

        if (!$user->isJefeDepto()) {
            return false;
        }

        $departmentIds = $user->departments()->pluck('departments.id');

        return $load->teacher->departments()->whereIn('departments.id', $departmentIds)->exists();
    }

    private function resolveRowStatus(Collection $cells): string
    {
        $statuses = $cells->pluck('status');
        $applicableStatuses = $statuses->reject(fn (string $status) => $status === 'NA')->values();

        if ($applicableStatuses->isEmpty()) {
            return 'NA';
        }

        if ($applicableStatuses->contains('R')) {
            return 'R';
        }

        if ($applicableStatuses->every(fn (string $status) => $status === 'VF')) {
            return 'VF';
        }

        if ($applicableStatuses->every(fn (string $status) => in_array($status, ['AO', 'VF'], true))) {
            return 'AO';
        }

        if ($applicableStatuses->contains('NE')) {
            return 'NE';
        }

        if ($applicableStatuses->contains('PA')) {
            return 'PA';
        }

        if ($applicableStatuses->every(fn (string $status) => $status === 'BL')) {
            return 'BL';
        }

        return 'PA';
    }
}
