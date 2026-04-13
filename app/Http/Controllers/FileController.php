<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\TeachingLoad;
use App\Models\EvidenceItem;
use App\Models\SubmissionWindow;
use App\Services\AuditService;
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

    public function store(Request $request, FolderNode $folder)
    {
        $this->authorize('view', $folder);

        $request->validate([
            'file' => 'required|file',
        ]);

        $user = $request->user();

        // Determine the owner (folder owner or current user for docentes)
        $ownerId = $folder->owner_user_id ?? $user->id;
        $semesterId = $folder->semester_id;

        if (!$semesterId) {
            return back()->withErrors(['file' => 'Esta carpeta no está asociada a un semestre.']);
        }

        // Navigate up to find the materia folder (parent of evidence-category folder)
        $materiaFolder = $this->findMateriaFolder($folder);

        // Find teaching load matching the materia folder name
        $load = null;
        if ($materiaFolder) {
            $load = TeachingLoad::whereHas('subject', function ($q) use ($materiaFolder) {
                    $q->where('name', $materiaFolder->name);
                })
                ->where('teacher_user_id', $ownerId)
                ->where('semester_id', $semesterId)
                ->first();
        }

        // Fallback to first teaching load if no materia match
        if (!$load) {
            $load = TeachingLoad::where('teacher_user_id', $ownerId)
                ->where('semester_id', $semesterId)
                ->first();
        }

        if (!$load) {
            return back()->withErrors(['file' => 'No se encontró carga docente para este semestre.']);
        }

        // Try to match folder name to an evidence item
        $evidenceItem = $this->matchFolderToEvidenceItem($folder->name);

        if (!$evidenceItem) {
            $evidenceItem = EvidenceItem::where('active', true)->first();
        }

        if (!$evidenceItem) {
            return back()->withErrors(['file' => 'No hay evidencias configuradas en el sistema.']);
        }

        // Find or create submission
        $submission = EvidenceSubmission::firstOrCreate(
            [
                'semester_id' => $semesterId,
                'teacher_user_id' => $ownerId,
                'evidence_item_id' => $evidenceItem->id,
                'teaching_load_id' => $load->id,
            ],
            [
                'status' => 'DRAFT',
            ]
        );

        // Only the teacher owner can upload evidence files, and only when the
        // submission is editable by business rules (policy handles status/unlock).
        $this->authorize('update', $submission);

        if (!$this->canUploadByWindowOrUnlock($submission)) {
            return back()->withErrors([
                'file' => 'La ventana de recepción está cerrada y no cuentas con prórroga activa.',
            ]);
        }

        try {
            $this->storageService->storeEvidence($request->file('file'), $folder, $user, $submission);

            // Uploading files should not implicitly submit evidence for review.
            $submission->update(['last_updated_at' => now()]);

            return back()->with('success', 'Archivo subido correctamente.');
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    public function replace(Request $request, EvidenceFile $file)
    {
        $this->authorize('delete', $file);

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $submission = $file->submission;
            $originalFileId = $file->id;
            $originalFileName = $file->file_name;

            if ($submission && !$this->canUploadByWindowOrUnlock($submission)) {
                return back()->withErrors([
                    'file' => 'La ventana de recepción está cerrada y no cuentas con prórroga activa.',
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
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, EvidenceFile $file)
    {
        $this->authorize('delete', $file);

        $this->storageService->deleteEvidence($file, $request->user());

        return back()->with('success', 'Archivo eliminado.');
    }

    /**
     * Navigate up the folder tree to find the materia-level folder.
     * Structure: SEMESTRE → DOCENTE → MATERIA → evidence folders
     * So from an evidence folder, the materia is the parent or grandparent.
     */
    private function findMateriaFolder(FolderNode $folder): ?FolderNode
    {
        $current = $folder;

        // Walk up looking for a folder whose parent is a teacher folder (whose parent is the semester root)
        while ($current && $current->parent_id) {
            $parent = FolderNode::find($current->parent_id);
            if (!$parent) break;

            // If the parent's parent is the semester root (parent_id = null), then parent is teacher and current is materia
            $grandparent = $parent->parent_id ? FolderNode::find($parent->parent_id) : null;
            if ($grandparent && $grandparent->parent_id === null) {
                // parent = teacher folder, current = materia folder
                return $current;
            }

            $current = $parent;
        }

        return null;
    }

    /**
     * Try to match a folder name to an EvidenceItem by keyword.
     */
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
                if ($item) return $item;
            }
        }

        return null;
    }

    private function canUploadByWindowOrUnlock(EvidenceSubmission $submission): bool
    {
        if ($submission->activeResubmissionUnlock()->exists()) {
            return true;
        }

        $window = SubmissionWindow::where('semester_id', $submission->semester_id)
            ->where('evidence_item_id', $submission->evidence_item_id)
            ->where('status', 'ACTIVE')
            ->first();

        if (!$window) {
            return false;
        }

        $now = now();

        return $now->greaterThanOrEqualTo($window->opens_at)
            && $now->lessThanOrEqualTo($window->closes_at);
    }
}
