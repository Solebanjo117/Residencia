<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\AcademicPeriod;
use App\Models\EvidenceRequirement;
use App\Models\TeachingLoad;
use App\Services\FolderStructureService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class SemesterController extends Controller
{
    public function __construct(private FolderStructureService $folderStructureService) {}

    public function index()
    {
        $semesters = Semester::with('academicPeriod')
            ->orderBy('start_date', 'desc')
            ->paginate(15);
            
        $academicPeriods = AcademicPeriod::orderBy('start_date', 'desc')->get();

        return Inertia::render('Admin/Semesters/Index', [
            'semesters' => $semesters,
            'academicPeriods' => $academicPeriods,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:40|unique:semesters',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => ['required', Rule::in(['OPEN', 'CLOSED'])],
            'academic_period_id' => 'nullable|exists:academic_periods,id',
        ]);

        $semester = DB::transaction(function () use ($validated) {
            if ($validated['status'] === 'OPEN') {
                $this->closeOtherOpenSemesters();
            }

            $semester = Semester::create($validated);
            $this->bootstrapSemesterFromLatestReference($semester);

            $this->folderStructureService->ensureSemesterFolder($semester);

            if ($validated['status'] === 'OPEN') {
                $this->folderStructureService->provisionForActiveTeachers($semester);
            }

            return $semester;
        });

        return redirect()->route('admin.semesters.index')->with('success', 'Semester created successfully.');
    }

    public function update(Request $request, Semester $semester)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('semesters')->ignore($semester->id)],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => ['required', Rule::in(['OPEN', 'CLOSED'])],
            'academic_period_id' => 'nullable|exists:academic_periods,id',
        ]);

        DB::transaction(function () use ($semester, $validated) {
            if ($validated['status'] === 'OPEN') {
                $this->closeOtherOpenSemesters($semester->id);
            }

            $semester->update($validated);
            $this->bootstrapSemesterFromLatestReference($semester);

            if ($validated['status'] === 'OPEN') {
                $this->folderStructureService->ensureSemesterFolder($semester);
                $this->folderStructureService->provisionForActiveTeachers($semester);
            }
        });

        return redirect()->route('admin.semesters.index')->with('success', 'Semester updated successfully.');
    }

    public function destroy(Semester $semester)
    {
        // Add protection to prevent deleting if there are teaching loads etc.
        if ($semester->teachingLoads()->exists() || $semester->submissionWindows()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete semester with associated records. Close it instead.']);
        }

        $semester->delete();

        return redirect()->route('admin.semesters.index')->with('success', 'Semester deleted successfully.');
    }

    private function closeOtherOpenSemesters(?int $ignoreSemesterId = null): void
    {
        Semester::query()
            ->where('status', 'OPEN')
            ->when($ignoreSemesterId, fn ($query) => $query->where('id', '!=', $ignoreSemesterId))
            ->update(['status' => 'CLOSED']);
    }

    private function bootstrapSemesterFromLatestReference(Semester $semester): void
    {
        if (!$semester->requirements()->exists()) {
            $referenceForRequirements = Semester::query()
                ->where('id', '!=', $semester->id)
                ->whereHas('requirements')
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

            if ($referenceForRequirements) {
                $referenceForRequirements->requirements()
                    ->orderBy('id')
                    ->get()
                    ->each(function (EvidenceRequirement $requirement) use ($semester) {
                        EvidenceRequirement::create([
                            'semester_id' => $semester->id,
                            'department_id' => $requirement->department_id,
                            'evidence_item_id' => $requirement->evidence_item_id,
                            'is_mandatory' => $requirement->is_mandatory,
                            'applies_condition' => $requirement->applies_condition,
                        ]);
                    });
            }
        }

        if (!$semester->teachingLoads()->exists()) {
            $referenceForLoads = Semester::query()
                ->where('id', '!=', $semester->id)
                ->whereHas('teachingLoads')
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

            if ($referenceForLoads) {
                $referenceForLoads->teachingLoads()
                    ->orderBy('id')
                    ->get()
                    ->each(function (TeachingLoad $load) use ($semester) {
                        TeachingLoad::create([
                            'teacher_user_id' => $load->teacher_user_id,
                            'semester_id' => $semester->id,
                            'subject_id' => $load->subject_id,
                            'group_code' => $load->group_code,
                            'hours_per_week' => $load->hours_per_week,
                        ]);
                    });
            }
        }
    }
}
