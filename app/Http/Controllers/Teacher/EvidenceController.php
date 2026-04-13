<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceReview;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Services\EvidenceFlowService;
use App\Services\EvidenceService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class EvidenceController extends Controller
{
    public function index(Request $request, EvidenceFlowService $flowService)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $department = $user->departments()->first();

        $currentSemester = Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();

        if (!$currentSemester || !$department) {
            return Inertia::render('Teacher/Evidencias/Index', [
                'tasks' => [],
                'semester' => $currentSemester,
                'allowedExtensions' => $this->allowedUploadExtensions(),
            ]);
        }

        $loads = TeachingLoad::with('subject')
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $currentSemester->id)
            ->get();

        $requirements = $flowService->requirementsForDepartment($currentSemester->id, $department->id);

        $submissions = EvidenceSubmission::with([
            'files',
            'statusHistory',
            'reviews' => fn ($query) => $query->with('reviewer')->orderByDesc('reviewed_at'),
            'officeReviewer',
            'finalApprover',
            'activeResubmissionUnlock',
        ])
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $currentSemester->id)
            ->get()
            ->groupBy('teaching_load_id');

        $windows = SubmissionWindow::query()
            ->where('semester_id', $currentSemester->id)
            ->where('status', 'ACTIVE')
            ->get()
            ->keyBy('evidence_item_id');

        $tasks = [];

        foreach ($loads as $load) {
            $loadSubmissions = ($submissions->get($load->id) ?? collect())->keyBy('evidence_item_id');

            foreach ($requirements as $requirement) {
                $submission = $loadSubmissions->get($requirement->evidence_item_id);
                $stageUnlocked = $flowService->isStageUnlocked($requirement, $requirements, $loadSubmissions);
                $availability = $flowService->resolveAvailability(
                    $windows->get($requirement->evidence_item_id),
                    $stageUnlocked,
                    $submission?->activeResubmissionUnlock !== null,
                    $submission
                );

                $latestReview = $submission?->reviews?->first();

                $tasks[] = [
                    'id' => $submission?->id,
                    'teaching_load' => [
                        'id' => $load->id,
                        'subject_name' => $load->subject->name,
                        'group' => $load->group_name,
                    ],
                    'requirement' => [
                        'item_id' => $requirement->evidence_item_id,
                        'item_name' => $requirement->evidenceItem->name,
                        'is_mandatory' => $requirement->is_mandatory,
                        'stage_order' => $flowService->stageOrder($requirement->evidenceItem->name),
                        'stage_label' => $flowService->stageLabel($flowService->stageOrder($requirement->evidenceItem->name)),
                    ],
                    'submission' => [
                        'status' => $submission?->status?->value,
                        'ui_status' => $flowService->uiStatus($submission, $availability),
                        'files_count' => $submission?->files->count() ?? 0,
                        'files' => $submission
                            ? $submission->files->map(fn ($file) => [
                                'id' => $file->id,
                                'file_name' => $file->file_name,
                                'size' => $file->size_bytes,
                                'uploaded_at' => $file->uploaded_at,
                                'download_url' => route('files.download', $file->id),
                            ])->values()->toArray()
                            : [],
                        'submitted_late' => (bool) $submission?->submitted_late,
                        'office_approved_at' => $submission?->office_reviewed_at?->toDateTimeString(),
                        'office_approved_by' => $submission?->officeReviewer?->name,
                        'final_approved_at' => $submission?->final_approved_at?->toDateTimeString(),
                        'final_approved_by' => $submission?->finalApprover?->name,
                        'last_review' => $latestReview ? [
                            'stage' => $latestReview->stage,
                            'decision' => $latestReview->decision->value,
                            'comments' => $latestReview->comments,
                            'reviewed_at' => $latestReview->reviewed_at?->toDateTimeString(),
                            'reviewer_name' => $latestReview->reviewer?->name,
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
                    ],
                    'availability' => $availability,
                    'window' => ($window = $windows->get($requirement->evidence_item_id)) ? [
                        'opens_at' => $window->opens_at,
                        'closes_at' => $window->closes_at,
                        'state_code' => $availability['code'],
                        'state_label' => $availability['label'],
                        'is_open' => $availability['code'] === 'OPEN',
                    ] : null,
                    'can_initialize' => !$submission && $availability['is_available'],
                    'can_upload' => $submission
                        && in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED], true)
                        && $availability['is_available'],
                    'can_submit' => $submission
                        && in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED], true)
                        && $submission->files->count() > 0
                        && $availability['is_available'],
                ];
            }
        }

        return Inertia::render('Teacher/Evidencias/Index', [
            'tasks' => $tasks,
            'semester' => $currentSemester,
            'allowedExtensions' => $this->allowedUploadExtensions(),
        ]);
    }

    public function initSubmission(Request $request, EvidenceFlowService $flowService)
    {
        $startedAt = microtime(true);

        $request->validate([
            'teaching_load_id' => 'required|exists:teaching_loads,id',
            'evidence_item_id' => 'required|integer',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $load = TeachingLoad::findOrFail($request->teaching_load_id);

        if ($load->teacher_user_id !== $user->id) {
            Log::channel('operations')->warning('evidence.init_submission_forbidden', [
                'actor_user_id' => $user->id,
                'actor_role_id' => $user->role_id,
                'teaching_load_id' => $load->id,
                'expected_teacher_id' => $load->teacher_user_id,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            abort(403);
        }

        $requirements = $this->teacherRequirementsForLoad($user, $load, $flowService);
        $requirement = $requirements->firstWhere('evidence_item_id', (int) $request->evidence_item_id);

        if (!$requirement) {
            return back()->with('error', 'La evidencia solicitada no forma parte de tu matriz activa.');
        }

        $existingSubmissions = EvidenceSubmission::query()
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $load->semester_id)
            ->where('teaching_load_id', $load->id)
            ->get()
            ->keyBy('evidence_item_id');

        $window = SubmissionWindow::query()
            ->where('semester_id', $load->semester_id)
            ->where('evidence_item_id', $requirement->evidence_item_id)
            ->where('status', 'ACTIVE')
            ->first();

        $availability = $flowService->resolveAvailability(
            $window,
            $flowService->isStageUnlocked($requirement, $requirements, $existingSubmissions),
            false
        );

        if (!$availability['is_available']) {
            return back()->with('error', 'Esta evidencia aun no se puede iniciar: ' . $availability['label'] . '.');
        }

        $submission = EvidenceSubmission::firstOrCreate(
            [
                'semester_id' => $load->semester_id,
                'teacher_user_id' => $user->id,
                'evidence_item_id' => $request->evidence_item_id,
                'teaching_load_id' => $load->id,
            ],
            [
                'status' => SubmissionStatus::DRAFT,
                'last_updated_at' => now(),
            ]
        );

        Log::channel('operations')->info('evidence.init_submission', [
            'actor_user_id' => $user->id,
            'actor_role_id' => $user->role_id,
            'submission_id' => $submission->id,
            'semester_id' => $submission->semester_id,
            'teaching_load_id' => $submission->teaching_load_id,
            'evidence_item_id' => $submission->evidence_item_id,
            'status' => $submission->status->value,
            'created' => $submission->wasRecentlyCreated,
            'duration_ms' => $this->elapsedMs($startedAt),
        ]);

        return back()->with('success', 'Entrega inicializada.')->with('submission_id', $submission->id);
    }

    public function submit(Request $request, EvidenceSubmission $submission, EvidenceService $evidenceService, EvidenceFlowService $flowService)
    {
        $startedAt = microtime(true);
        /** @var \App\Models\User|null $actor */
        $actor = $request->user();

        if ($submission->teacher_user_id !== Auth::id()) {
            Log::channel('operations')->warning('evidence.submit_forbidden', [
                'actor_user_id' => $actor?->id,
                'actor_role_id' => $actor?->role_id,
                'submission_id' => $submission->id,
                'submission_teacher_user_id' => $submission->teacher_user_id,
                'status' => $submission->status->value,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            abort(403);
        }

        if (!in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED], true)) {
            Log::channel('operations')->warning('evidence.submit_invalid_state', [
                'actor_user_id' => $actor?->id,
                'actor_role_id' => $actor?->role_id,
                'submission_id' => $submission->id,
                'status' => $submission->status->value,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return back()->with('error', 'Esta entrega no puede ser enviada en su estado actual.');
        }

        $context = $this->resolveSubmissionContext($submission, $flowService);

        if (!$context['availability']['is_available']) {
            Log::channel('operations')->warning('evidence.submit_window_closed', [
                'actor_user_id' => $actor?->id,
                'actor_role_id' => $actor?->role_id,
                'submission_id' => $submission->id,
                'semester_id' => $submission->semester_id,
                'evidence_item_id' => $submission->evidence_item_id,
                'window_id' => $context['window']?->id,
                'availability' => $context['availability']['code'],
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return back()->with('error', 'La evidencia no esta disponible para envio: ' . $context['availability']['label'] . '.');
        }

        if ($submission->files()->count() === 0) {
            Log::channel('operations')->warning('evidence.submit_without_files', [
                'actor_user_id' => $actor?->id,
                'actor_role_id' => $actor?->role_id,
                'submission_id' => $submission->id,
                'semester_id' => $submission->semester_id,
                'evidence_item_id' => $submission->evidence_item_id,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return back()->with('error', 'Debes adjuntar al menos un archivo para poder enviar la evidencia.');
        }

        $evidenceService->changeStatus(
            $submission,
            SubmissionStatus::SUBMITTED,
            $request->user(),
            'Enviado por el Docente'
        );

        $submission->update([
            'submitted_late' => $context['availability']['is_late'],
            'last_updated_at' => now(),
        ]);

        Log::channel('operations')->info('evidence.submitted', [
            'actor_user_id' => $actor?->id,
            'actor_role_id' => $actor?->role_id,
            'submission_id' => $submission->id,
            'semester_id' => $submission->semester_id,
            'evidence_item_id' => $submission->evidence_item_id,
            'teaching_load_id' => $submission->teaching_load_id,
            'window_id' => $context['window']?->id,
            'availability' => $context['availability']['code'],
            'submitted_late' => $context['availability']['is_late'],
            'duration_ms' => $this->elapsedMs($startedAt),
        ]);

        return back()->with('success', $context['availability']['is_late']
            ? 'Evidencia enviada exitosamente de forma extemporanea.'
            : 'Evidencia enviada exitosamente para revision.'
        );
    }

    public function storeFile(Request $request, EvidenceSubmission $submission, StorageService $storageService, EvidenceFlowService $flowService)
    {
        $startedAt = microtime(true);
        /** @var \App\Models\User|null $actor */
        $actor = $request->user();

        if ($submission->teacher_user_id !== Auth::id()) {
            Log::channel('operations')->warning('evidence.file_upload_forbidden', [
                'actor_user_id' => $actor?->id,
                'actor_role_id' => $actor?->role_id,
                'submission_id' => $submission->id,
                'submission_teacher_user_id' => $submission->teacher_user_id,
                'status' => $submission->status->value,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            abort(403);
        }

        if (!in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED], true)) {
            Log::channel('operations')->warning('evidence.file_upload_invalid_state', [
                'actor_user_id' => $actor?->id,
                'actor_role_id' => $actor?->role_id,
                'submission_id' => $submission->id,
                'status' => $submission->status->value,
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return back()->with('error', 'No puedes subir archivos a una entrega que ya fue enviada o aprobada.');
        }

        $context = $this->resolveSubmissionContext($submission, $flowService);

        if (!$context['availability']['is_available']) {
            Log::channel('operations')->warning('evidence.file_upload_window_closed', [
                'actor_user_id' => $actor?->id,
                'actor_role_id' => $actor?->role_id,
                'submission_id' => $submission->id,
                'semester_id' => $submission->semester_id,
                'evidence_item_id' => $submission->evidence_item_id,
                'window_id' => $context['window']?->id,
                'availability' => $context['availability']['code'],
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return back()->with('error', 'La evidencia no esta disponible para carga: ' . $context['availability']['label'] . '.');
        }

        $request->validate([
            'file' => 'required|file|mimes:' . implode(',', $this->allowedUploadExtensions()) . '|max:' . $this->maxUploadKb(),
        ]);

        $root = StorageRoot::where('is_active', true)->first();
        if (!$root) {
            return back()->with('error', 'Error del sistema: No hay una ruta de almacenamiento activa configurada.');
        }

        $semesterPath = "sem_{$submission->semester_id}";
        $teacherPath = "{$semesterPath}/docente_{$submission->teacher_user_id}";
        $itemPath = "{$teacherPath}/item_{$submission->evidence_item_id}";

        $folderNode = FolderNode::firstOrCreate(
            [
                'storage_root_id' => $root->id,
                'relative_path' => $itemPath,
            ],
            [
                'name' => 'Entregables Item ' . $submission->evidence_item_id,
                'owner_user_id' => $submission->teacher_user_id,
                'semester_id' => $submission->semester_id,
                'parent_id' => null,
            ]
        );

        try {
            $uploadedFile = $request->file('file');
            $storageService->storeEvidence($uploadedFile, $folderNode, $request->user(), $submission);

            $submission->update(['last_updated_at' => now()]);

            Log::channel('operations')->info('evidence.file_uploaded', [
                'actor_user_id' => $actor?->id,
                'actor_role_id' => $actor?->role_id,
                'submission_id' => $submission->id,
                'semester_id' => $submission->semester_id,
                'evidence_item_id' => $submission->evidence_item_id,
                'teaching_load_id' => $submission->teaching_load_id,
                'folder_node_id' => $folderNode->id,
                'window_id' => $context['window']?->id,
                'availability' => $context['availability']['code'],
                'file_name' => $uploadedFile?->getClientOriginalName(),
                'file_size_bytes' => $uploadedFile?->getSize(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return back()->with('success', $context['availability']['is_late']
                ? 'Archivo subido correctamente en periodo extemporaneo.'
                : 'Archivo subido correctamente.'
            );
        } catch (\Exception $e) {
            Log::channel('operations')->error('evidence.file_upload_failed', [
                'actor_user_id' => $actor?->id,
                'actor_role_id' => $actor?->role_id,
                'submission_id' => $submission->id,
                'semester_id' => $submission->semester_id,
                'evidence_item_id' => $submission->evidence_item_id,
                'teaching_load_id' => $submission->teaching_load_id,
                'window_id' => $context['window']?->id,
                'availability' => $context['availability']['code'],
                'error' => $e->getMessage(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);

            return back()->with('error', 'Error al guardar el archivo: ' . $e->getMessage());
        }
    }

    private function teacherRequirementsForLoad($user, TeachingLoad $load, EvidenceFlowService $flowService): Collection
    {
        $department = $user->departments()->first();

        return $flowService->requirementsForDepartment($load->semester_id, $department?->id);
    }

    private function resolveSubmissionContext(EvidenceSubmission $submission, EvidenceFlowService $flowService): array
    {
        $requirements = $flowService->requirementsForDepartment(
            $submission->semester_id,
            $submission->teacher->departments()->first()?->id
        );

        $requirement = $requirements->firstWhere('evidence_item_id', $submission->evidence_item_id);

        $loadSubmissions = EvidenceSubmission::query()
            ->where('teacher_user_id', $submission->teacher_user_id)
            ->where('semester_id', $submission->semester_id)
            ->where('teaching_load_id', $submission->teaching_load_id)
            ->get()
            ->keyBy('evidence_item_id');

        $window = SubmissionWindow::query()
            ->where('semester_id', $submission->semester_id)
            ->where('evidence_item_id', $submission->evidence_item_id)
            ->where('status', 'ACTIVE')
            ->first();

        $stageUnlocked = $requirement
            ? $flowService->isStageUnlocked($requirement, $requirements, $loadSubmissions)
            : true;

        return [
            'requirement' => $requirement,
            'window' => $window,
            'availability' => $flowService->resolveAvailability(
                $window,
                $stageUnlocked,
                $this->hasActiveUnlock($submission),
                $submission
            ),
        ];
    }

    private function hasActiveUnlock(EvidenceSubmission $submission): bool
    {
        return $submission->activeResubmissionUnlock()->exists();
    }

    private function allowedUploadExtensions(): array
    {
        return config('evidence.upload.allowed_extensions', ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp']);
    }

    private function maxUploadKb(): int
    {
        return (int) config('evidence.upload.max_kb', 15360);
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
