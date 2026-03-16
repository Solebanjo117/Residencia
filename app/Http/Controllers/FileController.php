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

        // Find a teaching load for this owner in this semester
        $load = TeachingLoad::where('teacher_user_id', $ownerId)
            ->where('semester_id', $semesterId)
            ->first();

        if (!$load) {
            return back()->withErrors(['file' => 'No se encontró carga docente para este semestre.']);
        }

        // Try to match folder name to an evidence item
        $evidenceItem = $this->matchFolderToEvidenceItem($folder->name);

        if (!$evidenceItem) {
            // Fallback: use the first evidence item available
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

        try {
            $this->storageService->storeEvidence($request->file('file'), $folder, $user, $submission);
            $submission->update(['last_updated_at' => now()]);
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
