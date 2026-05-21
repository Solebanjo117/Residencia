<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\SubmissionWindow;
use App\Models\User;
use App\Services\EvidenceFlowService;
use App\Services\OnlyOfficeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class OnlyOfficeController extends Controller
{
    public function show(Request $request, EvidenceFile $file, OnlyOfficeService $onlyOfficeService, EvidenceFlowService $flowService)
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

        try {
            $config = $onlyOfficeService->editorConfig($file, $request->user(), $canEdit);
            $loadError = null;
        } catch (RuntimeException $exception) {
            $config = null;
            $loadError = $exception->getMessage();
        }

        return Inertia::render('FileManager/OnlyOfficeEditor', [
            'file' => [
                'id' => $file->id,
                'name' => $file->file_name,
                'download_url' => route('files.download', $file->id),
                'folder_url' => $file->folderNode
                    ? $this->readableFolderUrl($file->folderNode)
                    : route('folders.show', $file->folder_node_id),
                'can_edit' => $canEdit,
            ],
            'onlyoffice' => [
                'enabled' => $onlyOfficeService->isEnabled(),
                'api_url' => $onlyOfficeService->isEnabled() ? $onlyOfficeService->apiUrl($file) : null,
                'config' => $config,
                'load_error' => $loadError,
            ],
        ]);
    }

    public function download(EvidenceFile $file, OnlyOfficeService $onlyOfficeService)
    {
        $onlyOfficeService->ensureReady($file);

        return response()->file(
            Storage::disk('local')->path($file->stored_relative_path),
            [
                'Content-Type' => $file->mime_type ?: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE.'; filename="'.addslashes($file->file_name).'"',
            ]
        );
    }

    public function callback(Request $request, EvidenceFile $file, User $user, OnlyOfficeService $onlyOfficeService): JsonResponse
    {
        $payload = $request->json()->all();
        $status = (int) ($payload['status'] ?? 0);

        try {
            if (in_array($status, [2, 6], true)) {
                $url = trim((string) ($payload['url'] ?? ''));
                if ($url === '') {
                    throw new RuntimeException('OnlyOffice no incluyo URL de guardado.');
                }

                $binary = $onlyOfficeService->downloadEditedDocument($url);
                $savedFile = $onlyOfficeService->saveEditedDocument($file, $user, $binary, $payload);
                $savedFile->submission?->update(['last_updated_at' => now()]);
            }

            return response()->json(['error' => 0]);
        } catch (\Throwable $exception) {
            Log::error('OnlyOffice callback failed', [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 1]);
        }
    }

    private function canBypassAvailability($user): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    private function readableFolderUrl($folder): string
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
