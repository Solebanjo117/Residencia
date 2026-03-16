<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherService
{
    /**
     * Creates a new Teacher (DOCENTE) user and assigns departments.
     */
    public function createTeacher(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $roleDocente = Role::where('name', Role::DOCENTE)->firstOrFail();

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? Str::random(12)), // Default random password
                'role_id' => $roleDocente->id,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->departments()->sync($data['department_ids'] ?? []);

            return $user;
        });
    }

    /**
     * Updates an existing Teacher's profile and department associations.
     */
    public function updateTeacher(User $teacher, array $data): User
    {
        return DB::transaction(function () use ($teacher, $data) {
            $updateData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'] ?? $teacher->is_active,
            ];

            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $teacher->update($updateData);

            $teacher->departments()->sync($data['department_ids'] ?? []);

            return $teacher;
        });
    }
}
