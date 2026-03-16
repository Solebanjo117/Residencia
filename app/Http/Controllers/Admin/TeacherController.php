<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Models\Semester;
use App\Services\FolderStructureService;
use App\Services\TeacherService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    protected TeacherService $teacherService;

    public function __construct(TeacherService $teacherService)
    {
        $this->teacherService = $teacherService;
    }

    public function index(Request $request)
    {
        $roleDocente = Role::where('name', Role::DOCENTE)->first();

        $teachers = User::where('role_id', $roleDocente->id)
            ->with('departments')
            ->orderBy('name')
            ->paginate(15);

        $departments = Department::orderBy('name')->get();

        return Inertia::render('Admin/Teachers/Index', [
            'teachers' => $teachers,
            'departments' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|string|email|max:160|unique:users',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
            'password' => 'nullable|string|min:8',
        ]);

        $this->teacherService->createTeacher($validated);

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher created successfully.');
    }

    public function update(Request $request, User $teacher)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'string', 'email', 'max:160', Rule::unique('users')->ignore($teacher->id)],
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
            'password' => 'nullable|string|min:8',
            'is_active' => 'boolean',
        ]);

        $this->teacherService->updateTeacher($teacher, $validated);

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher updated successfully.');
    }

    public function destroy(User $teacher)
    {
        // Typically, we don't hard delete users. For now, soft delete or disable them.
        // Assuming we rely on is_active instead of hard delete to keep audit history intact.
        $teacher->update(['is_active' => false]);

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher disabled successfully.');
    }

    public function generateFolders(User $teacher, FolderStructureService $folderStructureService)
    {
        foreach (Semester::all() as $semester) {
            $folderStructureService->generateFullStructure($semester, $teacher);
        }

        return redirect()->route('admin.teachers.index')
            ->with('success', "Carpetas generadas para {$teacher->name} en todos los semestres.");
    }
}
