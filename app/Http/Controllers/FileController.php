<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\Role;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EvidenceFlowService;
use App\Services\FolderManagerService;
use App\Services\NotificationService;
use App\Services\SeguimientoSharedFileService;
use App\Services\StorageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FileController extends Controller
{
    private const HISTORY_ACTIONS = [
        'UPLOAD_FILE',
        'REPLACE_FILE',
        'SAVE_DOCX_VERSION',
        'DELETE_FILE',
    ];

    protected $storageService;

    protected $auditService;

    protected $folderManagerService;

    protected $seguimientoSharedFiles;

    public function __construct(
        StorageService $storageService,
        AuditService $auditService,
        FolderManagerService $folderManagerService,
        SeguimientoSharedFileService $seguimientoSharedFiles
    ) {
        $this->storageService = $storageService;
        $this->auditService = $auditService;
        $this->folderManagerService = $folderManagerService;
        $this->seguimientoSharedFiles = $seguimientoSharedFiles;
    }

    public function download(Request $request, EvidenceFile $file)
    {
        $this->authorize('download', $file);
        $this->storageService->assertEvidenceFilePath($file);

        if (! Storage::disk('local')->exists($file->stored_relative_path)) {
            abort(404);
        }

        $this->auditService->log($request->user(), 'DOWNLOAD_FILE', 'EvidenceFile', $file->id, [
            'file_name' => $file->file_name,
            'stored_relative_path' => $file->stored_relative_path,
        ]);

        return Storage::disk('local')->download($file->stored_relative_path, $file->file_name);
    }

    public function preview(Request $request, EvidenceFile $file)
    {
        $this->authorize('preview', $file);
        $this->storageService->assertEvidenceFilePath($file);

        if (! Storage::disk('local')->exists($file->stored_relative_path)) {
            abort(404);
        }

        $this->auditService->log($request->user(), 'PREVIEW_FILE', 'EvidenceFile', $file->id, [
            'file_name' => $file->file_name,
            'stored_relative_path' => $file->stored_relative_path,
        ]);

        return response()->file(
            Storage::disk('local')->path($file->stored_relative_path),
            [
                'Content-Type' => $file->mime_type ?? 'application/octet-stream',
                'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE.'; filename="'.addslashes($file->file_name).'"',
            ]
        );
    }

    public function history(EvidenceFile $file)
    {
        $this->authorize('view', $file);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->where('entity_type', 'EvidenceFile')
            ->where('entity_id', $file->id)
            ->whereIn('action', self::HISTORY_ACTIONS)
            ->orderByDesc('at')
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'label' => $this->historyActionLabel($log),
                'at' => $log->at?->timezone(config('app.timezone'))->toDateTimeString(),
                'actor_name' => $log->user?->name ?? 'Usuario no disponible',
                'actor_email' => $log->user?->email,
                'metadata' => $this->historyMetadata($log),
            ]);

        return response()->json([
            'file' => [
                'id' => $file->id,
                'name' => $file->file_name,
                'last_edited_at' => $file->last_edited_at?->timezone(config('app.timezone'))->toDateTimeString(),
                'last_edited_by' => $file->editedBy?->name,
                'editor_source' => $file->editor_source,
            ],
            'history' => $logs,
        ]);
    }

    public function store(Request $request, FolderNode $folder, EvidenceFlowService $flowService)
    {
        $this->authorize('upload', $folder);

        $request->validate([
            'file' => 'required|file',
        ]);

        $user = $request->user();
        $ownerId = $folder->owner_user_id ?? $user->id;
        $semesterId = $folder->semester_id;

        if (! $semesterId) {
            return back()->withErrors(['file' => 'Esta carpeta no esta asociada a un semestre.']);
        }

        if ($folder->owner_user_id === null && $this->canBypassAvailability($user)) {
            try {
                $this->storageService->storeStandaloneFolderFile($request->file('file'), $folder, $user);

                return back()->with('success', 'Archivo subido correctamente.');
            } catch (AuthorizationException $exception) {
                throw $exception;
            } catch (\Exception $exception) {
                return back()->withErrors(['file' => $exception->getMessage()]);
            }
        }

        $materiaFolder = $this->findMateriaFolder($folder);

        $load = null;
        if ($materiaFolder) {
            $load = TeachingLoad::whereHas('subject', function ($query) use ($materiaFolder) {
                $query->where('name', $materiaFolder->name);
            })
                ->where('teacher_user_id', $ownerId)
                ->where('semester_id', $semesterId)
                ->first();
        }

        if (! $load) {
            $load = TeachingLoad::where('teacher_user_id', $ownerId)
                ->where('semester_id', $semesterId)
                ->first();
        }

        if (! $load) {
            return back()->withErrors(['file' => 'No se encontro carga docente para este semestre.']);
        }

        $evidenceItem = $this->matchFolderToEvidenceItem($folder->name);

        if (! $evidenceItem) {
            $evidenceItem = EvidenceItem::where('active', true)->first();
        }

        if (! $evidenceItem) {
            return back()->withErrors(['file' => 'No hay evidencias configuradas en el sistema.']);
        }

        $submission = EvidenceSubmission::firstOrCreate(
            [
                'semester_id' => $semesterId,
                'teacher_user_id' => $ownerId,
                'evidence_item_id' => $evidenceItem->id,
                'teaching_load_id' => $load->id,
            ],
            [
                'status' => 'DRAFT',
                'last_updated_at' => now(),
            ]
        );

        if (! $this->canManageSubmissionFiles($user, $submission)) {
            abort(403);
        }

        $availability = $this->fileManagerAvailability($submission, $flowService);
        if (! $this->canBypassAvailability($user) && ! $availability['is_available']) {
            return back()->withErrors([
                'file' => 'La evidencia no esta disponible para carga: '.$availability['label'].'.',
            ]);
        }

        try {
            $existingSharedFile = $this->seguimientoSharedFiles->currentSharedFileForSubmission($submission);
            $file = $existingSharedFile
                ? $this->storageService->overwriteEvidence($existingSharedFile, $request->file('file'), $user)
                : $this->storageService->storeEvidence($request->file('file'), $folder, $user, $submission);

            $submission->update(['last_updated_at' => now()]);
            $this->notifyAdministratorsAboutFileActivity($user, 'subio', $file, $submission);

            return back()->with('success', $this->uploadSuccessMessage($availability));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }
    }

    public function replace(Request $request, EvidenceFile $file, EvidenceFlowService $flowService)
    {
        $this->authorize('replace', $file);

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $submission = $file->submission;

            if (
                $submission
                && ! $this->canBypassAvailability($request->user())
                && ! $this->fileManagerAvailability($submission, $flowService)['is_available']
            ) {
                return back()->withErrors([
                    'file' => 'La evidencia no esta disponible para carga en este momento.',
                ]);
            }

            $updatedFile = $this->storageService->overwriteEvidence($file, $request->file('file'), $request->user());

            if ($submission) {
                $submission->update(['last_updated_at' => now()]);
            }
            $this->notifyAdministratorsAboutFileActivity($request->user(), 'reemplazo', $updatedFile, $submission);

            return back()->with('success', 'Archivo reemplazado correctamente.');
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }
    }

    public function destroy(Request $request, EvidenceFile $file)
    {
        $this->authorize('delete', $file);

        $submission = $file->submission;
        $fileName = $file->file_name;

        $this->storageService->deleteEvidence($file, $request->user());

        if ($submission) {
            $submission->update(['last_updated_at' => now()]);
        }
        $this->notifyAdministratorsAboutFileActivity($request->user(), 'elimino', null, $submission, $fileName);

        return back()->with('success', 'Archivo eliminado.');
    }

    public function move(Request $request, EvidenceFile $file)
    {
        $this->authorize('move', $file);

        $request->validate([
            'target_folder_id' => 'required|exists:folder_nodes,id',
        ]);

        $target = FolderNode::findOrFail($request->input('target_folder_id'));

        try {
            $this->folderManagerService->moveFile($request->user(), $file, $target);
            $this->notifyAdministratorsAboutFileActivity($request->user(), 'movio', $file, $file->submission);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['target_folder_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Archivo movido correctamente.');
    }

    private function canManageSubmissionFiles($user, EvidenceSubmission $submission): bool
    {
        if ($this->canBypassAvailability($user)) {
            return true;
        }

        if (! $this->isTeacherManagingOwnSubmission($user, $submission)) {
            return false;
        }

        if ($submission->status === SubmissionStatus::NA) {
            return false;
        }

        return true;
    }

    private function historyActionLabel(AuditLog $log): string
    {
        $editorSource = strtoupper((string) data_get($log->metadata, 'editor_source'));

        return match ($log->action) {
            'UPLOAD_FILE' => 'Archivo subido',
            'REPLACE_FILE' => $editorSource === 'ONLYOFFICE' ? 'Documento editado en OnlyOffice' : 'Archivo reemplazado',
            'SAVE_DOCX_VERSION' => 'Version DOCX guardada',
            'DELETE_FILE' => 'Archivo eliminado',
            default => $log->action,
        };
    }

    private function historyMetadata(AuditLog $log): array
    {
        $metadata = $log->metadata ?? [];

        return [
            'old_file_name' => data_get($metadata, 'old_file_name'),
            'new_file_name' => data_get($metadata, 'new_file_name') ?? data_get($metadata, 'stored_filename') ?? data_get($metadata, 'file_name'),
            'old_size_bytes' => data_get($metadata, 'old_size_bytes'),
            'new_size_bytes' => data_get($metadata, 'new_size_bytes') ?? data_get($metadata, 'size_bytes'),
            'old_file_hash' => data_get($metadata, 'old_file_hash'),
            'new_file_hash' => data_get($metadata, 'new_file_hash'),
            'editor_source' => data_get($metadata, 'editor_source'),
            'mime_type' => data_get($metadata, 'mime_type'),
        ];
    }

    private function canBypassAvailability($user): bool
    {
        return $user->isJefeOficina() || $user->isJefeDepto();
    }

    private function isTeacherManagingOwnSubmission($user, EvidenceSubmission $submission): bool
    {
        return $user->isDocente() && (int) $submission->teacher_user_id === (int) $user->id;
    }

    private function notifyAdministratorsAboutFileActivity(
        User $actor,
        string $action,
        ?EvidenceFile $file,
        ?EvidenceSubmission $submission,
        ?string $fileName = null
    ): void {
        if (! $actor->isDocente()) {
            return;
        }

        $relatedEntity = $file ?? $submission;
        if (! $relatedEntity) {
            return;
        }

        $fileName = $fileName ?: $file?->file_name ?: 'archivo';
        $itemName = $submission?->evidenceItem?->name ?: 'evidencia';
        $title = 'Movimiento en expediente docente';
        $message = "{$actor->name} {$action} {$fileName} en {$itemName}.";
        $notificationService = app(NotificationService::class);

        User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('name', [Role::JEFE_OFICINA, Role::JEFE_DEPTO]))
            ->where('is_active', true)
            ->get()
            ->each(fn (User $recipient) => $notificationService->notifyImmediate(
                $recipient,
                NotificationType::GENERAL,
                $title,
                $message,
                $relatedEntity
            ));
    }

    private function findMateriaFolder(FolderNode $folder): ?FolderNode
    {
        $current = $folder;

        while ($current && $current->parent_id) {
            $parent = FolderNode::find($current->parent_id);
            if (! $parent) {
                break;
            }

            $grandparent = $parent->parent_id ? FolderNode::find($parent->parent_id) : null;
            if ($grandparent && $grandparent->parent_id === null) {
                return $current;
            }

            $current = $parent;
        }

        return null;
    }

    private function matchFolderToEvidenceItem(string $folderName): ?EvidenceItem
    {
        $name = mb_strtoupper($folderName);

        $mappings = [
            'SD2-AVANCE' => 'SEG 02',
            'AVANCE-50' => 'SEG 02',
            'SD4-AVANCE' => 'SEG 04 FINAL',
            'AVANCE-100' => 'SEG 04 FINAL',
            'HORARIO' => 'HORARIO',
            'INSTRUMENTACION' => 'INSTRUM',
            'EVALUACION DIAGNOSTICA' => 'EV.DIAGN',
            'EVIDENCIAS DE ASIGNATURA' => 'REPORTES EVIDENCIAS ASIGNATURAS',
            'PROYECTOS INDIVIDUALES' => 'SEG 02',
            'CAPACITACION' => 'SEG 02',
            'MATERIAL DIDACTICO' => 'SEG 02',
            'ASESORIAS' => 'ASESORIAS',
            'ACTAS' => 'ACTAS FINALES',
            'REPORTE FINAL' => 'REP FINAL',
            'SEG 01' => 'SEG 01',
            'SEG 02' => 'SEG 02',
            'SEG 03' => 'SEG 03',
            'SEG 04' => 'SEG 04 FINAL',
            'SD2' => 'SEG 02',
            'SD4' => 'SEG 04 FINAL',
            'PARCIAL' => 'CALIF. PARCIALES',
        ];

        foreach ($mappings as $keyword => $itemName) {
            if (str_contains($name, $keyword)) {
                $item = EvidenceItem::where('name', $itemName)->first();
                if ($item) {
                    return $item;
                }
            }
        }

        return null;
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

        $windows = SubmissionWindow::where('semester_id', $submission->semester_id)
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

    private function uploadSuccessMessage(array $availability): string
    {
        return match ($availability['code'] ?? null) {
            'FILE_MANAGER_DRAFT' => 'Archivo subido correctamente en borrador. Aun no existe una ventana de entrega configurada.',
            default => $availability['is_late']
                ? 'Archivo subido correctamente en periodo extemporaneo.'
                : 'Archivo subido correctamente.',
        };
    }
}
