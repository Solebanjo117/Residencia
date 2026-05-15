<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationType;
use App\Enums\ReviewDecision;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceReview;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\Semester;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\TeachingLoadReview;
use App\Services\EvidenceFlowService;
use App\Services\EvidenceService;
use App\Services\FolderStructureService;
use App\Services\NotificationService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

        if (! $semester || ! $department) {
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
            ->groupBy('evidence_item_id');
        $departmentReviews = TeachingLoadReview::with('reviewer')
            ->whereIn('teaching_load_id', $teachingLoads->pluck('id'))
            ->orderByDesc('reviewed_at')
            ->get()
            ->groupBy('teaching_load_id');
        $isHistoricalSemester = $semester->status !== \App\Enums\SemesterStatus::OPEN;

        $rows = [];

        foreach ($teachingLoads as $load) {
            $loadSubmissions = ($submissions->get($load->id) ?? collect())->keyBy('evidence_item_id');
            $loadReviewTrail = $departmentReviews->get($load->id) ?? collect();
            $latestLoadReview = $loadReviewTrail->first();

            $rowData = [
                'id' => $load->id,
                'maestro' => $load->teacher->name,
                'materia' => $load->subject->name,
                'carrera' => $load->group_name,
                'clave_tecnm' => $load->subject->code,
                'modality' => $load->modality,
                'modality_label' => $load->modality === TeachingLoad::MODALITY_EN_LINEA ? 'Materia en linea' : 'Presencial',
                'semestre' => $semester->name,
                'cells' => [],
                'department_review' => [
                    'status' => $latestLoadReview?->decision ?? 'PENDING',
                    'comments' => $latestLoadReview?->comments,
                    'reviewed_at' => $latestLoadReview?->reviewed_at?->toDateTimeString(),
                    'reviewer_name' => $latestLoadReview?->reviewer?->name,
                    'can_review' => $user->isJefeDepto()
                        && $semester->status === \App\Enums\SemesterStatus::OPEN
                        && $this->canManageLoad($user, $load),
                    'trail' => $loadReviewTrail->map(fn (TeachingLoadReview $review) => [
                        'decision' => $review->decision,
                        'comments' => $review->comments,
                        'reviewed_at' => $review->reviewed_at?->toDateTimeString(),
                        'reviewer_name' => $review->reviewer?->name,
                    ])->values()->toArray(),
                ],
            ];

            foreach ($requirements as $requirement) {
                $item = $requirement->evidenceItem;
                $submission = $loadSubmissions->get($item->id);
                $stageUnlocked = $flowService->isStageUnlocked($requirement, $requirements, $loadSubmissions);
                $window = $flowService->resolveWindowForLoad($windows->get($item->id) ?? collect(), $load);
                $availability = $flowService->resolveAvailability(
                    $window,
                    $stageUnlocked,
                    $submission?->activeResubmissionUnlock !== null,
                    $submission,
                    $isHistoricalSemester
                );

                $uiStatus = $flowService->uiStatus($submission, $availability);
                $lastReview = $submission?->reviews?->first();

                $rowData['cells']['item_'.$item->id] = [
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
                        && ! $submission?->isFinalApproved(),
                    'can_mark_na' => ($user->isJefeOficina() || $user->isJefeDepto())
                        && ! $submission?->isFinalApproved(),
                    'can_reactivate' => ($user->isJefeOficina() || $user->isJefeDepto())
                        && $submission?->status === SubmissionStatus::NA,
                    'can_upload' => $this->canUploadCellFile($user, $load, $submission, $availability),
                ];
            }

            $rowData['estado_final'] = $this->resolveRowStatus(collect($rowData['cells']));
            $rows[] = $rowData;
        }

        return Inertia::render('SeguimientoDocente', [
            'rows' => $rows,
            'semesters' => Semester::pluck('name')->toArray(),
            'columns' => $evidenceItems->map(fn ($item) => [
                'key' => 'item_'.$item->id,
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
            'comments' => 'required_if:decision,REJECT|nullable|string|max:500',
        ]);

        $decision = ReviewDecision::from($request->decision);
        $comments = $request->string('comments')->trim()->toString() ?: null;

        $evidenceService->review(
            $submission,
            $request->user(),
            $decision,
            $comments
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

    public function upsertCellStatus(Request $request, EvidenceService $evidenceService, NotificationService $notificationService)
    {
        $validated = $request->validate([
            'teaching_load_id' => 'required|exists:teaching_loads,id',
            'evidence_item_id' => 'required|integer',
            'status' => 'required|in:AO,VF,REV,PA,BL,R,NE,NA,DRAFT',
            'comments' => 'required_if:status,R|nullable|string|max:500',
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

        $manualStatus = $validated['status'];
        abort_if($user->isJefeOficina() && in_array($manualStatus, ['VF', 'REV'], true), 403);

        $targetStatus = match ($manualStatus) {
            'AO', 'VF', 'REV' => SubmissionStatus::APPROVED,
            'PA' => SubmissionStatus::SUBMITTED,
            'BL' => SubmissionStatus::DRAFT,
            'R' => SubmissionStatus::REJECTED,
            'NE' => SubmissionStatus::NE,
            'NA' => SubmissionStatus::NA,
            'DRAFT' => SubmissionStatus::DRAFT,
        };

        $comments = trim((string) ($validated['comments'] ?? '')) ?: null;

        $reason = $comments
            ?: "Marcado manualmente como {$manualStatus} desde seguimiento docente.";

        $evidenceService->changeStatus($submission, $targetStatus, $user, $reason, enforceTransition: false);
        $submission->refresh();

        $approvalFields = match ($manualStatus) {
            'AO', 'REV' => [
                'office_reviewed_at' => now(),
                'office_reviewed_by_user_id' => $user->id,
                'final_approved_at' => null,
                'final_approved_by_user_id' => null,
            ],
            'VF' => [
                'office_reviewed_at' => $submission->office_reviewed_at ?? now(),
                'office_reviewed_by_user_id' => $submission->office_reviewed_by_user_id ?? $user->id,
                'final_approved_at' => now(),
                'final_approved_by_user_id' => $user->id,
            ],
            default => [
                'office_reviewed_at' => null,
                'office_reviewed_by_user_id' => null,
                'final_approved_at' => null,
                'final_approved_by_user_id' => null,
            ],
        };

        $submission->update([
            ...$approvalFields,
            'manual_ui_status' => $manualStatus === 'DRAFT' ? null : $manualStatus,
            'last_updated_at' => now(),
        ]);

        $this->notifyTeacherForManualStatusChange(
            $submission->fresh(['teacher', 'evidenceItem']),
            $manualStatus,
            $comments,
            $notificationService
        );

        return back()->with('success', $manualStatus === 'DRAFT'
            ? 'La evidencia quedo reactiva para captura.'
            : "Estado actualizado a {$manualStatus}."
        );
    }

    public function uploadCellFile(
        Request $request,
        StorageService $storageService,
        FolderStructureService $folderStructureService,
        EvidenceFlowService $flowService
    ) {
        $validated = $request->validate([
            'teaching_load_id' => 'required|exists:teaching_loads,id',
            'evidence_item_id' => 'required|exists:evidence_items,id',
            'file' => 'required|file',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $load = TeachingLoad::with(['teacher.departments', 'subject', 'semester'])
            ->findOrFail($validated['teaching_load_id']);

        abort_unless($user->isDocente() && (int) $load->teacher_user_id === (int) $user->id, 403);

        $evidenceItem = EvidenceItem::findOrFail($validated['evidence_item_id']);
        $submissionLookup = [
            'semester_id' => $load->semester_id,
            'teacher_user_id' => $load->teacher_user_id,
            'evidence_item_id' => $evidenceItem->id,
            'teaching_load_id' => $load->id,
        ];
        $submission = EvidenceSubmission::where($submissionLookup)->first();

        $availability = $this->resolveCellAvailability($load, $evidenceItem, $submission, $flowService);
        if (! $this->canUploadCellFile($user, $load, $submission, $availability)) {
            return back()->withErrors([
                'file' => 'La evidencia no esta disponible para carga: '.$availability['label'].'.',
            ]);
        }

        $submission = EvidenceSubmission::firstOrCreate(
            $submissionLookup,
            [
                'status' => SubmissionStatus::DRAFT,
                'last_updated_at' => now(),
            ]
        );

        $folder = $this->resolveEvidenceFolderForCell($load, $evidenceItem, $folderStructureService);

        try {
            $storageService->storeEvidence($request->file('file'), $folder, $user, $submission);
        } catch (\Throwable $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $submission->forceFill([
            'status' => SubmissionStatus::DRAFT,
            'manual_ui_status' => null,
            'submitted_at' => null,
            'submitted_late' => $submission->submitted_late || $availability['is_late'],
            'office_reviewed_at' => null,
            'office_reviewed_by_user_id' => null,
            'final_approved_at' => null,
            'final_approved_by_user_id' => null,
            'last_updated_at' => now(),
        ])->save();

        return back()->with('success', $availability['is_late']
            ? 'Archivo subido correctamente en periodo extemporaneo.'
            : 'Archivo subido correctamente.'
        );
    }

    public function reviewTeachingLoad(Request $request, TeachingLoad $teachingLoad)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        abort_unless($user->isJefeDepto(), 403);

        $teachingLoad->load('teacher.departments', 'semester');
        abort_unless($this->canManageLoad($user, $teachingLoad), 403);
        abort_unless($teachingLoad->semester?->status === \App\Enums\SemesterStatus::OPEN, 403);

        $validated = $request->validate([
            'decision' => 'required|in:APPROVE,REJECT',
            'comments' => 'required_if:decision,REJECT|nullable|string|max:700',
        ]);

        TeachingLoadReview::create([
            'teaching_load_id' => $teachingLoad->id,
            'reviewed_by_user_id' => $user->id,
            'decision' => $validated['decision'],
            'comments' => $validated['comments'] ?? null,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', $validated['decision'] === 'APPROVE'
            ? 'Carga aprobada por jefe de departamento.'
            : 'Carga rechazada por jefe de departamento.'
        );
    }

    private function canManageLoad($user, TeachingLoad $load): bool
    {
        if ($user->isJefeOficina()) {
            return true;
        }

        if (! $user->isJefeDepto()) {
            return false;
        }

        $departmentIds = $user->departments()->pluck('departments.id');

        return $load->teacher->departments()->whereIn('departments.id', $departmentIds)->exists();
    }

    private function notifyTeacherForManualStatusChange(
        EvidenceSubmission $submission,
        string $manualStatus,
        ?string $comments,
        NotificationService $notificationService
    ): void {
        $payload = match ($manualStatus) {
            'AO', 'REV' => [
                NotificationType::SUBMISSION_APPROVED,
                'Evidencia aprobada por oficina',
                'Tu evidencia '.$submission->evidenceItem->name.' fue aprobada por oficina.',
            ],
            'VF' => [
                NotificationType::SUBMISSION_APPROVED,
                'Evidencia con visto bueno final',
                'Tu evidencia '.$submission->evidenceItem->name.' recibio visto bueno final.',
            ],
            'R' => [
                NotificationType::SUBMISSION_REJECTED,
                'Evidencia rechazada',
                'Tu evidencia '.$submission->evidenceItem->name.' fue rechazada.',
            ],
            default => null,
        };

        if (! $payload || ! $submission->teacher) {
            return;
        }

        [$type, $title, $message] = $payload;

        $notificationService->notifyImmediate(
            $submission->teacher,
            $type,
            $title,
            $message.($comments ? ' Comentarios: '.$comments : ''),
            $submission
        );
    }

    private function canUploadCellFile($user, TeachingLoad $load, ?EvidenceSubmission $submission, array $availability): bool
    {
        if (! $user->isDocente() || (int) $load->teacher_user_id !== (int) $user->id) {
            return false;
        }

        if (! $availability['is_available']) {
            return false;
        }

        if (! $submission) {
            return true;
        }

        return in_array($submission->status, [
            SubmissionStatus::DRAFT,
            SubmissionStatus::REJECTED,
            SubmissionStatus::NE,
        ], true);
    }

    private function resolveCellAvailability(
        TeachingLoad $load,
        EvidenceItem $item,
        ?EvidenceSubmission $submission,
        EvidenceFlowService $flowService
    ): array {
        $requirements = $flowService->requirementsForDepartment($load->semester_id, $load->teacher->departments()->first()?->id);
        $requirement = $requirements->firstWhere('evidence_item_id', $item->id);
        $loadSubmissions = EvidenceSubmission::query()
            ->where('teacher_user_id', $load->teacher_user_id)
            ->where('semester_id', $load->semester_id)
            ->where('teaching_load_id', $load->id)
            ->get()
            ->keyBy('evidence_item_id');
        $windows = SubmissionWindow::query()
            ->where('semester_id', $load->semester_id)
            ->where('evidence_item_id', $item->id)
            ->where('status', 'ACTIVE')
            ->get();
        $stageUnlocked = $requirement instanceof EvidenceRequirement
            ? $flowService->isStageUnlocked($requirement, $requirements, $loadSubmissions)
            : true;

        return $flowService->resolveAvailability(
            $flowService->resolveWindowForLoad($windows, $load),
            $stageUnlocked,
            $submission?->activeResubmissionUnlock()->exists() ?? false,
            $submission,
            $load->semester?->status !== \App\Enums\SemesterStatus::OPEN
        );
    }

    private function resolveEvidenceFolderForCell(
        TeachingLoad $load,
        EvidenceItem $item,
        FolderStructureService $folderStructureService
    ): FolderNode {
        $teacherFolder = $folderStructureService->generateFullStructure($load->semester, $load->teacher);
        $subjectFolder = FolderNode::firstOrCreate(
            [
                'storage_root_id' => $teacherFolder->storage_root_id,
                'parent_id' => $teacherFolder->id,
                'name' => $load->subject->name,
            ],
            [
                'relative_path' => $teacherFolder->relative_path.'/'.Str::slug($load->subject->name),
                'owner_user_id' => $load->teacher_user_id,
                'semester_id' => $load->semester_id,
            ]
        );

        $candidates = collect($this->evidenceFolderNameCandidates($item->name))
            ->map(fn (string $name) => $this->normalizeFolderLookupName($name))
            ->all();
        $folder = $subjectFolder->children()
            ->get()
            ->first(fn (FolderNode $child) => in_array($this->normalizeFolderLookupName($child->name), $candidates, true));

        if ($folder) {
            return $folder;
        }

        $folderName = $this->defaultEvidenceFolderName($item->name);

        return FolderNode::firstOrCreate(
            [
                'storage_root_id' => $subjectFolder->storage_root_id,
                'parent_id' => $subjectFolder->id,
                'name' => $folderName,
            ],
            [
                'relative_path' => $subjectFolder->relative_path.'/'.Str::slug($folderName),
                'owner_user_id' => $load->teacher_user_id,
                'semester_id' => $load->semester_id,
            ]
        );
    }

    private function evidenceFolderNameCandidates(string $itemName): array
    {
        $name = $this->normalizeFolderLookupName($itemName);

        return match (true) {
            str_contains($name, 'HORARIO') => ['0.HORARIO OFICIAL', 'HORARIO OFICIAL', 'HORARIO'],
            str_contains($name, 'INSTRUM') => ['1.INSTRUMENTACIONES', 'INSTRUMENTACIONES'],
            str_contains($name, 'DIAGN') => ['2.EVALUACION DIAGNOSTICA', 'EVALUACION DIAGNOSTICA'],
            str_contains($name, 'ASESOR') => ['ASESORIAS'],
            str_contains($name, 'CALIF') || str_contains($name, 'PARCIAL') => ['CALIFICACIONES PARCIALES', 'CALIF. PARCIALES'],
            str_contains($name, 'EVIDENCIAS') => ['3.EVIDENCIAS DE ASIGNATURAS', 'EVIDENCIAS DE ASIGNATURAS'],
            str_contains($name, 'PROY') => ['4.PROYECTOS INDIVIDUALES', 'PROYECTOS INDIVIDUALES'],
            default => [$itemName],
        };
    }

    private function defaultEvidenceFolderName(string $itemName): string
    {
        return match (true) {
            str_contains($this->normalizeFolderLookupName($itemName), 'ASESOR') => 'ASESORIAS',
            str_contains($this->normalizeFolderLookupName($itemName), 'CALIF') => 'CALIFICACIONES PARCIALES',
            default => $itemName,
        };
    }

    private function normalizeFolderLookupName(string $name): string
    {
        $normalized = Str::ascii(mb_strtoupper($name));

        return trim(preg_replace('/\s+/', ' ', str_replace(['.', '_', '-'], ' ', $normalized)));
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

        if ($applicableStatuses->contains('REV')) {
            return 'REV';
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
