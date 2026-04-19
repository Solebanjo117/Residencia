<?php

use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createOfficeAdminForTeachingLoadIndex(): User
{
    $officeRole = Role::firstOrCreate(['name' => Role::JEFE_OFICINA]);

    return User::factory()->create([
        'role_id' => $officeRole->id,
        'email_verified_at' => now(),
    ]);
}

it('defaults teaching load filter to active semester and sorts semesters with active first', function () {
    $admin = createOfficeAdminForTeachingLoadIndex();

    $teacherRole = Role::firstOrCreate(['name' => Role::DOCENTE]);
    $teacher = User::factory()->create([
        'role_id' => $teacherRole->id,
        'email_verified_at' => now(),
    ]);

    $activeSemester = Semester::create([
        'name' => 'SEM-ACTIVE',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $closedSemester = Semester::create([
        'name' => 'SEM-CLOSED',
        'start_date' => now()->subMonths(6)->toDateString(),
        'end_date' => now()->subMonths(4)->toDateString(),
        'status' => 'CLOSED',
    ]);

    $subjectA = Subject::create([
        'code' => 'TLI-01',
        'name' => 'Materia Active',
    ]);

    $subjectB = Subject::create([
        'code' => 'TLI-02',
        'name' => 'Materia Closed',
    ]);

    TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $activeSemester->id,
        'subject_id' => $subjectA->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $closedSemester->id,
        'subject_id' => $subjectB->id,
        'group_code' => 'B',
        'hours_per_week' => 5,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.teaching-loads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TeachingLoads/Index')
            ->where('selectedSemester', (string) $activeSemester->id)
            ->where('semesters.0.id', $activeSemester->id)
            ->has('teachingLoads.data', 1)
            ->where('teachingLoads.data.0.semester.id', $activeSemester->id)
        );
});
