<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;

it('allows administrative users to create a user linked to an existing teacher', function () {
    $adminRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $department = Department::firstOrCreate(['name' => 'Dept Users Test']);

    $admin = User::factory()->create([
        'role_id' => $adminRoleId,
        'is_active' => true,
    ]);
    $admin->departments()->attach($department->id);

    $teacher = User::factory()->create([
        'role_id' => $teacherRoleId,
        'is_active' => true,
    ]);
    $teacher->departments()->attach($department->id);

    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');

    $this
        ->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Usuario Vinculado',
            'email' => 'usuario.vinculado@example.com',
            'password' => 'password123',
            'role_id' => $officeRoleId,
            'linked_teacher_user_id' => $teacher->id,
            'department_ids' => [$department->id],
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'usuario.vinculado@example.com',
        'role_id' => $officeRoleId,
        'linked_teacher_user_id' => $teacher->id,
    ]);
});

it('prevents docente users from being linked to another teacher account', function () {
    $adminRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $admin = User::factory()->create([
        'role_id' => $adminRoleId,
        'is_active' => true,
    ]);

    $teacher = User::factory()->create([
        'role_id' => $teacherRoleId,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Docente Incorrecto',
            'email' => 'docente.incorrecto@example.com',
            'password' => 'password123',
            'role_id' => $teacherRoleId,
            'linked_teacher_user_id' => $teacher->id,
            'department_ids' => [],
        ])
        ->assertSessionHasErrors('linked_teacher_user_id');
});
