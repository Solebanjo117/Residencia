<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\AcademicPeriod;
use App\Services\FolderStructureService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

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

        $semester = Semester::create($validated);

        // Create semester root folder
        $this->folderStructureService->ensureSemesterFolder($semester);

        if ($validated['status'] === 'OPEN') {
            $this->folderStructureService->provisionForActiveTeachers($semester);
        }

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

        $semester->update($validated);

        if ($validated['status'] === 'OPEN') {
            $this->folderStructureService->ensureSemesterFolder($semester);
            $this->folderStructureService->provisionForActiveTeachers($semester);
        }

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
}
