<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('allows an administrative user to add a teacher manually', function () {
    $adminRole = Role::firstOrCreate(['name' => Role::JEFE_DEPTO]);
    Role::firstOrCreate(['name' => Role::DOCENTE]);
    $admin = User::factory()->create(['role_id' => $adminRole->id]);

    $this
        ->actingAs($admin)
        ->post(route('admin.teachers.store'), [
            'name' => 'Docente Manual',
            'email' => 'docente.manual@example.com',
            'password' => 'password123',
            'department_ids' => [],
            'folder_permission_keys' => [],
        ])
        ->assertRedirect(route('admin.teachers.index'));

    $teacher = User::where('email', 'docente.manual@example.com')->firstOrFail();

    expect($teacher->role?->name)->toBe(Role::DOCENTE);
    expect(Hash::check('password123', $teacher->password))->toBeTrue();
});
