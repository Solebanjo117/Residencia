<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\FolderStructureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(private FolderStructureService $folderStructureService) {}

    public function index()
    {
        $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');

        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->with(['role', 'departments', 'linkedTeacher'])
                ->orderBy('name')
                ->paginate(15),
            'roles' => Role::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'teachers' => User::query()
                ->where('role_id', $docenteRoleId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateUser($request);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'linked_teacher_user_id' => $validated['linked_teacher_user_id'] ?? null,
            'email_verified_at' => now(),
            'is_active' => true,
            'folder_permission_keys' => $this->isDocenteRole((int) $validated['role_id'])
                ? $this->folderStructureService->allFolderPermissionKeys()
                : null,
        ]);

        $user->departments()->sync($validated['department_ids'] ?? []);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $this->validateUser($request, $user);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
            'linked_teacher_user_id' => $validated['linked_teacher_user_id'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'folder_permission_keys' => $this->isDocenteRole((int) $validated['role_id'])
                ? ($user->folder_permission_keys ?: $this->folderStructureService->allFolderPermissionKeys())
                : null,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);
        $user->departments()->sync($validated['department_ids'] ?? []);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        $user->update(['is_active' => false]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario desactivado correctamente.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'linked_teacher_user_id' => [
                'nullable',
                'exists:users,id',
                Rule::when(
                    (int) $request->input('role_id') === (int) $docenteRoleId,
                    ['prohibited'],
                    []
                ),
                Rule::when(
                    (int) $request->input('role_id') !== (int) $docenteRoleId,
                    [Rule::exists('users', 'id')->where('role_id', $docenteRoleId)],
                    []
                ),
            ],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function isDocenteRole(int $roleId): bool
    {
        return Role::whereKey($roleId)->where('name', Role::DOCENTE)->exists();
    }
}
