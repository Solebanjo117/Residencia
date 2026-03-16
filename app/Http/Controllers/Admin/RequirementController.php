<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvidenceRequirement;
use App\Models\Semester;
use App\Models\Department;
use App\Models\EvidenceItem;
use App\Models\EvidenceCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class RequirementController extends Controller
{
    public function index(Request $request)
    {
        $semesterId = $request->query('semester_id');
        
        $semesters = Semester::orderBy('start_date', 'desc')->get();
        $departments = Department::orderBy('name')->get();
        
        // Group items by category for nicer UI
        $categories = EvidenceCategory::with(['items' => function($q) {
            $q->where('active', true)->orderBy('name');
        }])->orderBy('name')->get();

        $requirements = [];
        if ($semesterId) {
            $requirements = EvidenceRequirement::where('semester_id', $semesterId)->get();
        }

        return Inertia::render('Admin/Requirements/Matrix', [
            'semesters' => $semesters,
            'departments' => $departments,
            'categories' => $categories,
            'requirements' => $requirements,
            'selectedSemester' => $semesterId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'requirements' => 'required|array',
            'requirements.*.department_id' => 'nullable|exists:departments,id',
            'requirements.*.evidence_item_id' => 'required|exists:evidence_items,id',
            'requirements.*.is_mandatory' => 'required|boolean',
        ]);

        DB::transaction(function () use ($validated) {
            // Because it's a matrix toggle setup, we clear existing for this semester
            // and simply re-insert what was submitted.
            EvidenceRequirement::where('semester_id', $validated['semester_id'])->delete();

            foreach ($validated['requirements'] as $req) {
                EvidenceRequirement::create([
                    'semester_id' => $validated['semester_id'],
                    'department_id' => $req['department_id'],
                    'evidence_item_id' => $req['evidence_item_id'],
                    'is_mandatory' => $req['is_mandatory'] ?? true,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Requirements matrix saved successfully.');
    }
}
