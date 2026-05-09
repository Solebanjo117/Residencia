<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\SubmissionWindow;
use App\Services\DocxEditorService;
use App\Services\EvidenceFlowService;
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
                'folder_url' => route('folders.show', $file->folder_node_id),
                'is_current_version' => (bool) $file->is_current_version,
                'can_edit' => $canEdit,
            ],
            'document' => [
                'html' => $payload['html'] ?? '',
                'header_html' => $payload['header_html'] ?? '',
                'footer_html' => $payload['footer_html'] ?? '',
                'warnings' => $payload['warnings'] ?? [],
                'stats' => $payload['stats'] ?? null,
                'version_history' => $payload['version_history'] ?? [],
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
            'save_mode' => 'required|in:replace_current,new_version',
        ]);

        try {
            $savedFile = $docxEditorService->saveDocument(
                $file,
                $validated['html'],
                $request->user(),
                $validated['save_mode'] === 'new_version',
                $validated['header_html'] ?? null,
                $validated['footer_html'] ?? null
            );

            $savedFile->submission?->update([
                'last_updated_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'docx' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('files.docx.show', $savedFile->id)
            ->with('success', $validated['save_mode'] === 'new_version'
                ? 'Nueva version DOCX guardada correctamente.'
                : 'Documento DOCX guardado correctamente.'
            );
    }

    private function canBypassAvailability($user): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    private function submissionAvailability(EvidenceSubmission $submission, EvidenceFlowService $flowService): array
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
