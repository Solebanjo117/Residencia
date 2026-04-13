<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Services\AuditService;
use App\Services\EvidenceFlowService;
use App\Services\StorageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    protected $storageService;
    protected $auditService;

    public function __construct(StorageService $storageService, AuditService $auditService)
    {
        $this->storageService = $storageService;
        $this->auditService = $auditService;
    }

    public function download(Request $request, EvidenceFile $file)
    {
        $this->authorize('download', $file);
        $this->storageService->assertEvidenceFilePath($file);

        if (!Storage::disk('local')->exists($file->stored_relative_path)) {
            abort(404);
        }

        $this->auditService->log($request->user(), 'DOWNLOAD_FILE', 'EvidenceFile', $file->id, [
            'file_name' => $file->file_name,
            'stored_relative_path' => $file->stored_relative_path,
        ]);

        return Storage::disk('local')->download($file->stored_relative_path, $file->file_name);
    }

    public function store(Request $request, FolderNode $folder, EvidenceFlowService $flowService)
    {
        $this->authorize('view', $folder);

        $request->validate([
            'file' => 'required|file',
        ]);

        $user = $request->user();
        $ownerId = $folder->owner_user_id ?? $user->id;
        $semesterId = $folder->semester_id;

        if (!$semesterId) {
            return back()->withErrors(['file' => 'Esta carpeta no esta asociada a un semestre.']);
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

        if (!$load) {
            $load = TeachingLoad::where('teacher_user_id', $ownerId)
                ->where('semester_id', $semesterId)
                ->first();
        }

        if (!$load) {
            return back()->withErrors(['file' => 'No se encontro carga docente para este semestre.']);
        }

        $evidenceItem = $this->matchFolderToEvidenceItem($folder->name);

        if (!$evidenceItem) {
            $evidenceItem = EvidenceItem::where('active', true)->first();
        }

        if (!$evidenceItem) {
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

        $this->authorize('update', $submission);

        $availability = $this->submissionAvailability($submission, $flowService);
        if (!$availability['is_available']) {
            return back()->withErrors([
                'file' => 'La evidencia no esta disponible para carga: ' . $availability['label'] . '.',
            ]);
        }

        try {
            $this->storageService->storeEvidence($request->file('file'), $folder, $user, $submission);
            $submission->update(['last_updated_at' => now()]);

            return back()->with('success', $availability['is_late']
                ? 'Archivo subido correctamente en periodo extemporaneo.'
                : 'Archivo subido correctamente.'
            );
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }
    }

    public function replace(Request $request, EvidenceFile $file, EvidenceFlowService $flowService)
    {
        $this->authorize('delete', $file);

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $submission = $file->submission;
            $originalFileId = $file->id;
            $originalFileName = $file->file_name;

            if ($submission && !$this->submissionAvailability($submission, $flowService)['is_available']) {
                return back()->withErrors([
                    'file' => 'La evidencia no esta disponible para carga en este momento.',
                ]);
            }

            $this->storageService->deleteEvidence($file, $request->user());

            $folder = $file->folderNode;
            $newFile = $this->storageService->storeEvidence($request->file('file'), $folder, $request->user(), $submission);

            $this->auditService->log($request->user(), 'REPLACE_FILE', 'EvidenceFile', $newFile->id, [
                'replaced_file_id' => $originalFileId,
                'replaced_file_name' => $originalFileName,
                'new_file_name' => $newFile->file_name,
            ]);

            if ($submission) {
                $submission->update(['last_updated_at' => now()]);
            }

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

        $this->storageService->deleteEvidence($file, $request->user());

        return back()->with('success', 'Archivo eliminado.');
    }

    private function findMateriaFolder(FolderNode $folder): ?FolderNode
    {
        $current = $folder;

        while ($current && $current->parent_id) {
            $parent = FolderNode::find($current->parent_id);
            if (!$parent) {
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
            'HORARIO' => 'HORARIO',
            'INSTRUMENTACION' => 'INSTRUM',
            'EVALUACION DIAGNOSTICA' => 'EV.DIAGN',
            'EVIDENCIAS DE ASIGNATURA' => 'REPORTES EVIDENCIAS ASIGNATURAS',
            'PROYECTOS INDIVIDUALES' => 'PROY IND',
            'CAPACITACION' => 'PROY IND',
            'MATERIAL DIDACTICO' => 'PROY IND',
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

        $window = SubmissionWindow::where('semester_id', $submission->semester_id)
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
}
