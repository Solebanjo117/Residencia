<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Enums\SubmissionStatus;
use App\Models\EvidenceStatusHistory;
use App\Models\Semester;
use App\Models\TeachingLoad;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\SubmissionWindow;
use App\Models\ResubmissionUnlock;
use App\Models\FolderNode;
use App\Models\StorageRoot;
use App\Services\EvidenceService;
use App\Services\StorageService;

class EvidenceController extends Controller
{
    /**
     * WARNING: This GET endpoint previously created EvidenceSubmission records as a
     * side-effect (auto-initializing DRAFT submissions for every load+requirement pair).
     * That behavior was extracted to the dedicated POST initSubmission() action, but the
     * frontend still depends on submissions existing before files can be uploaded. If
     * submissions appear to be "missing" for a teacher, check that initSubmission() was
     * called or that a migration back-filled existing records.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $department = $user->departments()->first();

        $currentSemester = Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();

        if (!$currentSemester || !$department) {
            return Inertia::render('Teacher/Evidencias/Index', [
                'tasks' => [],
                'semester' => $currentSemester
            ]);
        }

        // 1. Get Teacher's Loads
        $loads = TeachingLoad::with('subject')
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $currentSemester->id)
            ->get();

        // 2. Get Requirements for the Dept
        $requirements = EvidenceRequirement::with('evidenceItem')
            ->where('semester_id', $currentSemester->id)
            ->where('department_id', $department->id)
            ->get();

        // 3. Get Existing Submissions (no side-effect creation here)
        $submissions = EvidenceSubmission::with(['files', 'statusHistory'])
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $currentSemester->id)
            ->get()
            ->keyBy(function($item) {
                return $item->teaching_load_id . '_' . $item->evidence_item_id;
            });

        // 4. Get Active Windows
        $windows = SubmissionWindow::where('semester_id', $currentSemester->id)
            ->where('status', 'ACTIVE')
            ->get()
            ->keyBy('evidence_item_id');

        // Build a flatten Task List
        $tasks = [];

        foreach ($loads as $load) {
            foreach ($requirements as $req) {
                $key = $load->id . '_' . $req->evidence_item_id;
                $submission = $submissions->get($key);
                $window = $windows->get($req->evidence_item_id);

                $now = now();
                $isOpen = $window ? ($now >= $window->opens_at && $now <= $window->closes_at) : false;

                $tasks[] = [
                    'id' => $submission?->id,
                    'teaching_load' => [
                        'id' => $load->id,
                        'subject_name' => $load->subject->name,
                        'group' => $load->group_name,
                    ],
                    'requirement' => [
                        'item_id' => $req->evidence_item_id,
                        'item_name' => $req->evidenceItem->name,
                        'is_mandatory' => $req->is_mandatory,
                    ],
                    'submission' => $submission ? [
                        'status' => $submission->status->value,
                        'files_count' => $submission->files->count(),
                        'files' => $submission->files->map(function($f) {
                            return [
                                'id' => $f->id,
                                'file_name' => $f->file_name,
                                'size' => $f->size_bytes,
                                'uploaded_at' => $f->uploaded_at
                            ];
                        })
                    ] : [
                        'status' => null,
                        'files_count' => 0,
                        'files' => []
                    ],
                    'window' => $window ? [
                        'opens_at' => $window->opens_at,
                        'closes_at' => $window->closes_at,
                        'is_open' => $isOpen
                    ] : null,
                ];
            }
        }

        return Inertia::render('Teacher/Evidencias/Index', [
            'tasks' => $tasks,
            'semester' => $currentSemester
        ]);
    }

    /**
     * Initialize a DRAFT submission for a specific load + evidence item.
     * Called via POST when the teacher starts working on an evidence task.
     */
    public function initSubmission(Request $request)
    {
        $request->validate([
            'teaching_load_id' => 'required|exists:teaching_loads,id',
            'evidence_item_id' => 'required|integer',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $load = TeachingLoad::findOrFail($request->teaching_load_id);

        if ($load->teacher_user_id !== $user->id) {
            abort(403);
        }

        $submission = EvidenceSubmission::firstOrCreate(
            [
                'semester_id' => $load->semester_id,
                'teacher_user_id' => $user->id,
                'evidence_item_id' => $request->evidence_item_id,
                'teaching_load_id' => $load->id,
            ],
            [
                'status' => 'DRAFT',
            ]
        );

        return back()->with('success', 'Entrega inicializada.')->with('submission_id', $submission->id);
    }

    public function submit(Request $request, EvidenceSubmission $submission, EvidenceService $evidenceService)
    {
        // Authorize teacher owns this submission
        if ($submission->teacher_user_id !== Auth::id()) {
            abort(403);
        }

        // Only DRAFT or REJECTED can be submitted
        if (!in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED])) {
            return back()->with('error', 'Esta entrega no puede ser enviada en su estado actual.');
        }

        // Verify window is open or unlocked
        $window = SubmissionWindow::where('semester_id', $submission->semester_id)
            ->where('evidence_item_id', $submission->evidence_item_id)
            ->where('status', 'ACTIVE')
            ->first();

        $now = now();
        $isWindowOpen = $window && $now >= $window->opens_at && $now <= $window->closes_at;

        $hasUnlock = ResubmissionUnlock::where('submission_id', $submission->id)
            ->where('expires_at', '>', $now)
            ->exists();

        if (!$isWindowOpen && !$hasUnlock) {
            return back()->with('error', 'La ventana de recepcion para este documento esta cerrada y no cuenta con prorroga.');
        }

        if ($submission->files()->count() === 0) {
            return back()->with('error', 'Debes adjuntar al menos un archivo para poder enviar la evidencia.');
        }

        // Use EvidenceService for the status transition (handles audit, history, validation)
        $evidenceService->changeStatus(
            $submission,
            SubmissionStatus::SUBMITTED,
            $request->user(),
            'Enviado por el Docente'
        );

        return back()->with('success', 'Evidencia enviada exitosamente para revision.');
    }

    public function storeFile(Request $request, EvidenceSubmission $submission, StorageService $storageService)
    {
        // 1. Authorization
        if ($submission->teacher_user_id !== Auth::id()) {
            abort(403);
        }

        if (!in_array($submission->status, [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED])) {
            return back()->with('error', 'No puedes subir archivos a una entrega que ya fue enviada o aprobada.');
        }

        // 2. Validate Window or Unlock
        $window = SubmissionWindow::where('semester_id', $submission->semester_id)
            ->where('evidence_item_id', $submission->evidence_item_id)
            ->where('status', 'ACTIVE')
            ->first();

        $now = now();
        $isWindowOpen = $window && $now >= $window->opens_at && $now <= $window->closes_at;

        $hasUnlock = ResubmissionUnlock::where('submission_id', $submission->id)
            ->where('expires_at', '>', $now)
            ->exists();

        if (!$isWindowOpen && !$hasUnlock) {
            return back()->with('error', 'La ventana de recepcion para este documento esta cerrada y no cuenta con permisos de re-subida.');
        }

        $request->validate([
            'file' => 'required|file|mimes:docx,pdf,zip,rar|max:15360', // 15MB
        ]);

        // 3. Find or Create Folder Node
        $root = StorageRoot::where('is_active', true)->first();
        if (!$root) {
            return back()->with('error', 'Error del sistema: No hay una ruta de almacenamiento activa configurada.');
        }

        $semesterPath = "sem_{$submission->semester_id}";
        $teacherPath = "{$semesterPath}/docente_{$submission->teacher_user_id}";
        $itemPath = "{$teacherPath}/item_{$submission->evidence_item_id}";

        // We could just create the single terminal node. For simplicity, we ensure it exists.
        $folderNode = FolderNode::firstOrCreate(
            [
                'storage_root_id' => $root->id,
                'relative_path' => $itemPath,
            ],
            [
                // We fake a generic parent_id NULL or build parents if we strictly want a clean tree.
                // Given the File Manager just queries nodes, we just provide the basic fields.
                'name' => 'Entregables Item ' . $submission->evidence_item_id,
                'owner_user_id' => $submission->teacher_user_id,
                'semester_id' => $submission->semester_id,
                'parent_id' => null, // Standalone node in the DB tree logic for this direct upload
            ]
        );

        // 4. Store using StorageService
        try {
            $storageService->storeEvidence($request->file('file'), $folderNode, $request->user(), $submission);

            // Mark last_updated_at
            $submission->touch();

            return back()->with('success', 'Archivo subido correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al guardar el archivo: ' . $e->getMessage());
        }
    }
}
