<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use App\Services\FolderStructureService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeachingLoadController extends Controller
{
    public function index(Request $request)
    {
        $roleDocente = Role::where('name', Role::DOCENTE)->first();

        $requestedSemesterId = $request->query('semester_id');
        $activeSemesterId = Semester::active()?->id;
        $semesterId = $requestedSemesterId;

        if ($semesterId === null && $activeSemesterId) {
            $semesterId = (string) $activeSemesterId;
        }

        $query = TeachingLoad::with(['teacher', 'semester', 'subject'])
            ->orderBy('created_at', 'desc');

        if ($semesterId !== null && $semesterId !== '') {
            $query->where('semester_id', $semesterId);
        }

        $teachingLoads = $query->paginate(15)->withQueryString();

        $semesters = Semester::query()
            ->orderByRaw("CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END")
            ->orderBy('start_date', 'desc')
            ->get();
        $teachers = User::where('role_id', $roleDocente->id)->orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();

        return Inertia::render('Admin/TeachingLoads/Index', [
            'teachingLoads' => $teachingLoads,
            'semesters' => $semesters,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'selectedSemester' => $semesterId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'teacher_user_id' => 'required|exists:users,id',
            'semester_id' => 'required|exists:semesters,id',
            'subject_id' => 'required|exists:subjects,id',
            'group_code' => 'required|string|max:40',
            'hours_per_week' => 'nullable|integer|min:1|max:40',
            'modality' => ['required', Rule::in([TeachingLoad::MODALITY_PRESENCIAL, TeachingLoad::MODALITY_EN_LINEA])],
        ]);

        // Prevent duplicate exact assignments
        $exists = TeachingLoad::where('teacher_user_id', $validated['teacher_user_id'])
            ->where('semester_id', $validated['semester_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('group_code', $validated['group_code'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['error' => 'Esta carga académica ya está asignada al docente.']);
        }

        $load = TeachingLoad::create($validated);

        // Auto-generate full folder structure for the assigned teacher
        app(FolderStructureService::class)->generateFullStructure($load->semester, $load->teacher);

        return redirect()->back()->with('success', 'Carga académica asignada correctamente.');
    }

    public function update(Request $request, TeachingLoad $teachingLoad)
    {
        $validated = $request->validate([
            'teacher_user_id' => 'required|exists:users,id',
            'semester_id' => 'required|exists:semesters,id',
            'subject_id' => 'required|exists:subjects,id',
            'group_code' => 'required|string|max:40',
            'hours_per_week' => 'nullable|integer|min:1|max:40',
            'modality' => ['required', Rule::in([TeachingLoad::MODALITY_PRESENCIAL, TeachingLoad::MODALITY_EN_LINEA])],
        ]);

        $teachingLoad->update($validated);

        return redirect()->back()->with('success', 'Carga académica actualizada correctamente.');
    }

    public function destroy(TeachingLoad $teachingLoad)
    {
        $teachingLoad->delete();

        return redirect()->back()->with('success', 'Carga académica eliminada correctamente.');
    }
}
