<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Semester;
use App\Models\TeachingLoad;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use Illuminate\Support\Facades\Auth;

class AdvisoryController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $department = $user->departments()->first();

        $semesterQuery = $request->input('semester');
        
        $semester = $semesterQuery 
            ? Semester::where('name', $semesterQuery)->first() 
            : (Semester::where('is_active', true)->first() ?? Semester::orderBy('start_date', 'desc')->first());

        if (!$semester || !$department) {
            return Inertia::render('Asesorias', [
                'rows' => [],
                'semesters' => Semester::pluck('name')->toArray(),
                'columns' => [],
                'currentSemester' => $semesterQuery ?? '',
            ]);
        }

        // 1. Get dynamically the requirements for this semester and department
        $requirements = EvidenceRequirement::with('evidenceItem')
            ->where('semester_id', $semester->id)
            ->where('department_id', $department->id)
            ->get();

        $evidenceItems = $requirements->map(function ($req) {
            return $req->evidenceItem;
        });

        // 2. Get Teaching Loads (with Teacher and Subject)
        $teachingLoads = TeachingLoad::with(['user', 'subject'])
            ->where('semester_id', $semester->id)
            ->get();

        // Filter by department if needed (assuming subject or teacher belongs to depto)
        // Here we fetch all for the semester as a broad stroke, but ideally filter by Dept
        
        // 3. Get Submissions
        $submissions = EvidenceSubmission::with('files')
            ->where('semester_id', $semester->id)
            ->get()
            ->groupBy('teaching_load_id');

        $rows = [];
        foreach ($teachingLoads as $load) {
            $loadSubmissions = $submissions->get($load->id) ?? collect([]);
            
            $rowData = [
                'id' => $load->id,
                'maestro' => $load->user->name,
                'materia' => $load->subject->name,
                'carrera' => $load->group_name, // Using Group Name as Carrera for now to match UI
                'clave_tecnm' => $load->subject->code,
                'semestre' => $semester->name,
                'reportes_docs' => [],
            ];

            $allOk = true;
            $anyNe = false;

            // Map each dynamic evidence item
            foreach ($evidenceItems as $item) {
                // Find submission for this item
                $sub = $loadSubmissions->firstWhere('evidence_item_id', $item->id);
                
                $statusKey = 'item_' . $item->id;
                
                if (!$sub) {
                    $rowData[$statusKey] = 'NE';
                    $anyNe = true;
                    $allOk = false;
                } else {
                    $val = match($sub->status->value) {
                        'APPROVED' => 'OK',
                        'NA' => 'NA',
                        default => 'NE'
                    };
                    $rowData[$statusKey] = $val;
                    
                    if ($val === 'NE') {
                        $anyNe = true;
                        $allOk = false;
                    }

                    // Map files
                    foreach($sub->files as $file) {
                        $rowData['reportes_docs'][] = [
                            'name' => $file->file_name,
                            'type' => strtoupper(pathinfo($file->file_name, PATHINFO_EXTENSION))
                        ];
                    }
                }
            }

            $rowData['estado_final'] = $allOk && $evidenceItems->count() > 0 ? 'OK' : ($anyNe ? 'NE' : 'NA');

            $rows[] = $rowData;
        }

        return Inertia::render('Asesorias', [
            'rows' => $rows,
            'semesters' => Semester::pluck('name')->toArray(),
            'columns' => $evidenceItems->map(function($item) {
                return [
                    'key' => 'item_' . $item->id,
                    'label' => $item->name,
                ];
            }),
            'currentSemester' => $semester->name,
        ]);
    }

    public function index2(Request $request)
    {
        // For Asesorias 2 (Detailed view with Followups)
        // We do a similar mapping but inject followups
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $department = $user->departments()->first();

        $semesterQuery = $request->input('semester');
        
        $semester = $semesterQuery 
            ? Semester::where('name', $semesterQuery)->first() 
            : (Semester::where('is_active', true)->first() ?? Semester::orderBy('start_date', 'desc')->first());

        if (!$semester || !$department) {
            return Inertia::render('Asesorias2', [
                'rows' => [],
                'semesters' => Semester::pluck('name')->toArray(),
                'columns' => [],
                'currentSemester' => $semesterQuery ?? '',
            ]);
        }

        // Get dynamically the requirements for this semester and department
        $requirements = EvidenceRequirement::with('evidenceItem')
            ->where('semester_id', $semester->id)
            ->where('department_id', $department->id)
            ->get();

        $evidenceItems = $requirements->map(function ($req) {
            return $req->evidenceItem;
        });

        // Get Teaching Loads
        $teachingLoads = TeachingLoad::with(['user', 'subject'])
            ->where('semester_id', $semester->id)
            ->get();

        // Get Submissions
        $submissions = EvidenceSubmission::with('files')
            ->where('semester_id', $semester->id)
            ->get()
            ->groupBy('teaching_load_id');

        $rows = [];
        foreach ($teachingLoads as $load) {
            $loadSubmissions = $submissions->get($load->id) ?? collect([]);
            
            $rowData = [
                'id' => $load->id,
                'maestro' => $load->user->name,
                'materia' => $load->subject->name,
                'carrera' => $load->group_name,
                'clave_tecnm' => $load->subject->code,
                'semestre' => $semester->name,
                'reportes_docs' => [],
                'followups' => []
            ];

            $allOk = true;
            $anyNe = false;

            foreach ($evidenceItems as $item) {
                $sub = $loadSubmissions->firstWhere('evidence_item_id', $item->id);
                $statusKey = 'item_' . $item->id;
                
                $statusVal = 'NE';
                $files = [];
                
                if ($sub) {
                    $statusVal = match($sub->status->value) {
                        'APPROVED' => 'OK',
                        'NA' => 'NA',
                        default => 'NE'
                    };
                    $files = $sub->files->map(function($f) {
                        return [
                            'name' => $f->file_name,
                            'type' => strtoupper(pathinfo($f->file_name, PATHINFO_EXTENSION))
                        ];
                    })->toArray();
                    
                    foreach($files as $file) {
                        $rowData['reportes_docs'][] = $file;
                    }
                }

                $rowData[$statusKey] = $statusVal;
                
                if ($statusVal === 'NE') {
                    $anyNe = true;
                    $allOk = false;
                }

                // Add to followups for detailed view
                $rowData['followups']['item_' . $item->id] = [
                    'status' => $statusVal,
                    'notas' => 'Evidencia requerida. ' . ($statusVal === 'NE' ? 'Pendiente.' : 'Entregada.'),
                    'evidencias' => $files,
                    'checklist' => [
                        ['id' => 'c1', 'label' => 'Subir archivo', 'done' => count($files) > 0],
                        ['id' => 'c2', 'label' => 'Aprobado por Jefatura', 'done' => $statusVal === 'OK'],
                    ]
                ];
            }

            $rowData['estado_final'] = $allOk && $evidenceItems->count() > 0 ? 'OK' : ($anyNe ? 'NE' : 'NA');

            $rows[] = $rowData;
        }

        return Inertia::render('Asesorias2', [
            'rows' => $rows,
            'semesters' => Semester::pluck('name')->toArray(),
            'columns' => $evidenceItems->map(function($item) {
                return [
                    'key' => 'item_' . $item->id,
                    'label' => $item->name,
                ];
            }),
            'currentSemester' => $semester->name,
        ]);
    }
}
