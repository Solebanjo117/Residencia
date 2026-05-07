<?php

use App\Models\Role;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;

function adminForSubjectManagement(): User
{
    $role = Role::firstOrCreate(['name' => Role::JEFE_DEPTO]);

    return User::factory()->create(['role_id' => $role->id]);
}

it('allows an administrative user to create and update subjects manually', function () {
    $admin = adminForSubjectManagement();

    $this
        ->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'code' => 'AED-1015',
            'name' => 'Diseno Organizacional',
        ])
        ->assertRedirect(route('admin.subjects.index'));

    $subject = Subject::where('code', 'AED-1015')->firstOrFail();

    $this
        ->actingAs($admin)
        ->put(route('admin.subjects.update', $subject), [
            'code' => 'AED-1015',
            'name' => 'Diseno Organizacional Actualizado',
        ])
        ->assertRedirect(route('admin.subjects.index'));

    expect($subject->fresh()->name)->toBe('Diseno Organizacional Actualizado');
});

it('blocks deleting subjects that already have teaching loads', function () {
    $admin = adminForSubjectManagement();
    $teacherRole = Role::firstOrCreate(['name' => Role::DOCENTE]);
    $teacher = User::factory()->create(['role_id' => $teacherRole->id]);
    $semester = \App\Models\Semester::create([
        'name' => 'SEM-SUBJECT-LOCK',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);
    $subject = Subject::create([
        'code' => 'LOCK-1',
        'name' => 'Materia protegida',
    ]);

    TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.subjects.destroy', $subject))
        ->assertSessionHasErrors('error');

    $this->assertDatabaseHas('subjects', [
        'id' => $subject->id,
    ]);
});
