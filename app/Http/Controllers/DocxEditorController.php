<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\EvidenceFile;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\Role;
use App\Models\SubmissionWindow;
use App\Models\User;
use App\Services\DocxEditorService;
use App\Services\EvidenceFlowService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class DocxEditorController extends Controller
{
    public function show(Request $request, EvidenceFile $file, DocxEditorService $docxEditorService, EvidenceFlowService $flowService)
    {
        $this->authorize('view', $file);
        abort_unless($file->isDocx(), 404);

        $canEdit = $request->user()->can('replace', $file);
        $submission = $file->submission;

        if ($canEdit && $submission && ! $this->canBypassAvailability($request->user())) {
            $availability = $this->fileManagerAvailability($submission, $flowService);

            if (! $availability['is_available']) {
                $canEdit = false;
            }
        }
        $payload = null;
        $loadError = null;

        try {
            $payload = $docxEditorService->loadDocument($file);
        } catch (RuntimeException $exception) {
            $loadError = $exception->getMessage();
        }

        return Inertia::render('FileManager/DocxEditor', [
            'file' => [
                'id' => $file->id,
                'name' => $file->file_name,
                'mime_type' => $file->mime_type,
                'uploaded_at' => $file->uploaded_at?->toDateTimeString(),
                'uploaded_by' => $file->uploadedBy?->name,
                'last_edited_at' => $file->last_edited_at?->toDateTimeString(),
                'last_edited_by' => $file->editedBy?->name,
                'download_url' => route('files.download', $file->id),
                'folder_url' => $file->folderNode
                    ? $this->readableFolderUrl($file->folderNode)
                    : route('folders.show', $file->folder_node_id),
                'is_current_version' => (bool) $file->is_current_version,
                'can_edit' => $canEdit,
            ],
            'document' => [
                'html' => $payload['html'] ?? '',
                'header_html' => $payload['header_html'] ?? '',
                'footer_html' => $payload['footer_html'] ?? '',
                'warnings' => $payload['warnings'] ?? [],
                'stats' => $payload['stats'] ?? null,
                'load_error' => $loadError,
                'sections' => $payload['sections'] ?? [
                    'has_header' => false,
                    'has_footer' => false,
                ],
            ],
            'capabilities' => [
                'can_edit' => $canEdit,
            ],
        ]);
    }

    public function store(Request $request, EvidenceFile $file, DocxEditorService $docxEditorService, EvidenceFlowService $flowService)
    {
        $this->authorize('replace', $file);
        abort_unless($file->isDocx(), 404);

        $submission = $file->submission;
        if ($submission && ! $this->canBypassAvailability($request->user())) {
            $availability = $this->fileManagerAvailability($submission, $flowService);

            if (! $availability['is_available']) {
                return back()->withErrors([
                    'docx' => 'La evidencia no esta disponible para carga en este momento.',
                ]);
            }
        }

        $validated = $request->validate([
            'html' => 'required|string',
            'header_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'save_mode' => 'required|in:replace_current',
        ]);

        try {
            $savedFile = $docxEditorService->saveDocument(
                $file,
                $validated['html'],
                $request->user(),
                $validated['header_html'] ?? null,
                $validated['footer_html'] ?? null
            );

            $savedFile->submission?->update([
                'last_updated_at' => now(),
            ]);
            $this->notifyAdministratorsAboutDocxEdit($request->user(), $savedFile);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'docx' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('files.docx.show', $savedFile->id)
            ->with('success', 'Documento DOCX guardado correctamente.');
    }

    private function canBypassAvailability($user): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    private function readableFolderUrl(FolderNode $folder): string
    {
        $segments = [];
        $current = $folder;

        while ($current) {
            array_unshift($segments, rawurlencode($current->name));
            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return '/files/folders/'.implode('/', $segments);
    }

    private function notifyAdministratorsAboutDocxEdit(User $actor, EvidenceFile $file): void
    {
        if (! $actor->isDocente()) {
            return;
        }

        $submission = $file->submission;
        $itemName = $submission?->evidenceItem?->name ?: 'evidencia';
        $message = "{$actor->name} modifico {$file->file_name} en {$itemName}.";
        $notificationService = app(NotificationService::class);

        User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('name', [Role::JEFE_OFICINA, Role::JEFE_DEPTO]))
            ->where('is_active', true)
            ->get()
            ->each(fn (User $recipient) => $notificationService->notifyImmediate(
                $recipient,
                NotificationType::GENERAL,
                'Movimiento en expediente docente',
                $message,
                $file
            ));
    }

    private function submissionAvailability(EvidenceSubmission $submission, EvidenceFlowService $flowService): array
    {
        $baseRequirements = $flowService->requirementsForDepartment(
            $submission->semester_id,
            $submission->teacher->departments()->first()?->id
        );
        $requirements = $flowService->requirementsForDepartment(
            $submission->semester_id,
            $submission->teacher->departments()->first()?->id,
            $submission->teachingLoad
        );

        $requirement = $requirements->firstWhere('evidence_item_id', $submission->evidence_item_id);

        if (! $requirement instanceof EvidenceRequirement) {
            if ($baseRequirements->contains('evidence_item_id', $submission->evidence_item_id)) {
                return $flowService->notApplicableAvailability();
            }
        }

        $loadSubmissions = EvidenceSubmission::query()
            ->where('teacher_user_id', $submission->teacher_user_id)
            ->where('semester_id', $submission->semester_id)
            ->where('teaching_load_id', $submission->teaching_load_id)
            ->get()
            ->keyBy('evidence_item_id');

        $windows = SubmissionWindow::query()
            ->where('semester_id', $submission->semester_id)
            ->where('evidence_item_id', $submission->evidence_item_id)
            ->where('status', 'ACTIVE')
            ->get();
        $window = $flowService->resolveWindowForLoad($windows, $submission->teachingLoad);

        $stageUnlocked = $requirement instanceof EvidenceRequirement
            ? $flowService->isStageUnlocked($requirement, $requirements, $loadSubmissions)
            : true;

        return $flowService->resolveAvailability(
            $window,
            $stageUnlocked,
            $submission->activeResubmissionUnlock()->exists(),
            $submission
        );
    }

    private function fileManagerAvailability(EvidenceSubmission $submission, EvidenceFlowService $flowService): array
    {
        $availability = $this->submissionAvailability($submission, $flowService);

        if ($availability['code'] === 'NOT_CONFIGURED') {
            return [
                ...$availability,
                'code' => 'FILE_MANAGER_DRAFT',
                'label' => 'Disponible en borrador dentro del gestor (sin ventana configurada)',
                'is_available' => true,
                'is_late' => false,
                'is_future' => false,
                'tone' => 'amber',
            ];
        }

        return $availability;
    }
}
