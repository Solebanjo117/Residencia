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
    protected FolderStructureService $folderStructureService;

    public function __construct(TeacherService $teacherService, FolderStructureService $folderStructureService)
    {
        $this->teacherService = $teacherService;
        $this->folderStructureService = $folderStructureService;
    }

    public function index(Request $request)
    {
        $roleDocente = Role::where('name', Role::DOCENTE)->first();
        $folderCatalog = $this->folderStructureService->folderPermissionCatalog();

        $teachers = User::where('role_id', $roleDocente->id)
            ->with('departments')
            ->orderBy('name')
            ->paginate(15);

        $teachers->getCollection()->transform(function (User $teacher) {
            $teacher->folder_permission_keys = $this->folderStructureService->resolveTeacherFolderPermissionKeys($teacher);

            return $teacher;
        });

        $departments = Department::orderBy('name')->get();

        return Inertia::render('Admin/Teachers/Index', [
            'teachers' => $teachers,
            'departments' => $departments,
            'folderCatalog' => $folderCatalog,
        ]);
    }

    public function store(Request $request)
    {
        $allowedFolderKeys = $this->folderStructureService->allFolderPermissionKeys();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|string|email|max:160|unique:users',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
            'password' => 'nullable|string|min:8',
            'folder_permission_keys' => 'nullable|array',
            'folder_permission_keys.*' => ['string', Rule::in($allowedFolderKeys)],
        ]);

        $validated['folder_permission_keys'] = array_key_exists('folder_permission_keys', $validated)
            ? $this->folderStructureService->normalizeFolderPermissionKeys($validated['folder_permission_keys'])
            : $allowedFolderKeys;

        $this->teacherService->createTeacher($validated);

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher created successfully.');
    }

    public function update(Request $request, User $teacher)
    {
        $allowedFolderKeys = $this->folderStructureService->allFolderPermissionKeys();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'string', 'email', 'max:160', Rule::unique('users')->ignore($teacher->id)],
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
            'password' => 'nullable|string|min:8',
            'is_active' => 'boolean',
            'folder_permission_keys' => 'nullable|array',
            'folder_permission_keys.*' => ['string', Rule::in($allowedFolderKeys)],
        ]);

        if (array_key_exists('folder_permission_keys', $validated)) {
            $validated['folder_permission_keys'] = $this->folderStructureService->normalizeFolderPermissionKeys($validated['folder_permission_keys']);
        } else {
            $validated['folder_permission_keys'] = $this->folderStructureService->resolveTeacherFolderPermissionKeys($teacher);
        }

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

    public function generateFolders(Request $request, User $teacher, FolderStructureService $folderStructureService)
    {
        $validated = $request->validate([
            'force' => 'nullable|boolean',
        ]);

        $force = (bool) ($validated['force'] ?? false);
        $forcedPermissionKeys = $force ? $folderStructureService->allFolderPermissionKeys() : null;

        foreach (Semester::all() as $semester) {
            $folderStructureService->regenerateTeacherStructure($semester, $teacher, $forcedPermissionKeys);
        }

        return redirect()->route('admin.teachers.index')
            ->with('success', $force
                ? "Estructura completa forzada y reconstruida para {$teacher->name} en todos los semestres."
                : "Estructura reconstruida para {$teacher->name} segun permisos configurados.");
    }
}
