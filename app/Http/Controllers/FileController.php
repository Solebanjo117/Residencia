<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\TeachingLoad;
use App\Models\EvidenceItem;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    protected $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function download(Request $request, EvidenceFile $file)
    {
        $this->authorize('download', $file);

        if (!Storage::disk('local')->exists($file->stored_relative_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($file->stored_relative_path, $file->file_name);
    }

    public function store(Request $request, FolderNode $folder)
    {
        $this->authorize('view', $folder);

        $request->validate([
            'file' => 'required|file|mimes:docx,pdf,jpg,jpeg,png,webp|max:15360',
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

        // If submission already existed, reset status to SUBMITTED so it goes through review
        if (!$submission->wasRecentlyCreated) {
            $submission->update(['status' => 'SUBMITTED']);
        }

        try {
            $this->storageService->storeEvidence($request->file('file'), $folder, $user, $submission);
            $submission->update(['last_updated_at' => now(), 'status' => 'SUBMITTED']);
            return back()->with('success', 'Archivo subido correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    public function replace(Request $request, EvidenceFile $file)
    {
        $this->authorize('delete', $file);

        $request->validate([
            'file' => 'required|file|mimes:docx,pdf,jpg,jpeg,png,webp|max:15360',
        ]);

        try {
            $this->storageService->deleteEvidence($file, $request->user());

            $submission = $file->submission;
            $folder = $file->folderNode;
            $this->storageService->storeEvidence($request->file('file'), $folder, $request->user(), $submission);

            return back()->with('success', 'Archivo reemplazado correctamente.');
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
}
