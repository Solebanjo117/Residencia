<?php

use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;

it('generates folder structures for active teachers when a semester is opened', function () {
    $adminRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $admin = User::factory()->create(['role_id' => $adminRoleId]);
    $activeTeacher = User::factory()->create([
        'role_id' => $teacherRoleId,
        'is_active' => true,
        'name' => 'Docente Activo ' . Str::upper(Str::random(4)),
    ]);
    $inactiveTeacher = User::factory()->create([
        'role_id' => $teacherRoleId,
        'is_active' => false,
        'name' => 'Docente Inactivo ' . Str::upper(Str::random(4)),
    ]);

    $semester = Semester::create([
        'name' => 'SEM-PROVISION-' . Str::upper(Str::random(6)),
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
        'status' => 'CLOSED',
    ]);

    $activeSubject = Subject::create([
        'code' => 'SUB-ACT-' . Str::upper(Str::random(6)),
        'name' => 'Materia Activa ' . Str::upper(Str::random(4)),
    ]);

    $inactiveSubject = Subject::create([
        'code' => 'SUB-INACT-' . Str::upper(Str::random(6)),
        'name' => 'Materia Inactiva ' . Str::upper(Str::random(4)),
    ]);

    TeachingLoad::create([
        'teacher_user_id' => $activeTeacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $activeSubject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    TeachingLoad::create([
        'teacher_user_id' => $inactiveTeacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $inactiveSubject->id,
        'group_code' => 'B',
        'hours_per_week' => 4,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.semesters.update', $semester), [
            'name' => $semester->name,
            'start_date' => $semester->start_date->toDateString(),
            'end_date' => $semester->end_date->toDateString(),
            'status' => 'OPEN',
            'academic_period_id' => null,
        ])
        ->assertRedirect(route('admin.semesters.index'));

    $semesterFolder = \App\Models\FolderNode::query()
        ->where('semester_id', $semester->id)
        ->whereNull('parent_id')
        ->first();

    expect($semesterFolder)->not->toBeNull();

    $activeTeacherFolder = \App\Models\FolderNode::query()
        ->where('semester_id', $semester->id)
        ->where('owner_user_id', $activeTeacher->id)
        ->where('parent_id', $semesterFolder->id)
        ->where('name', $activeTeacher->name)
        ->first();

    expect($activeTeacherFolder)->not->toBeNull();

    $activeSubjectFolder = \App\Models\FolderNode::query()
        ->where('semester_id', $semester->id)
        ->where('owner_user_id', $activeTeacher->id)
        ->where('parent_id', $activeTeacherFolder->id)
        ->where('name', $activeSubject->name)
        ->first();

    expect($activeSubjectFolder)->not->toBeNull();

    $this->assertDatabaseHas('folder_nodes', [
        'semester_id' => $semester->id,
        'owner_user_id' => $activeTeacher->id,
        'parent_id' => $activeSubjectFolder->id,
        'name' => '0.HORARIO OFICIAL',
    ]);

    $this->assertDatabaseMissing('folder_nodes', [
        'semester_id' => $semester->id,
        'owner_user_id' => $inactiveTeacher->id,
        'name' => $inactiveTeacher->name,
    ]);
});

it('creates the base evidence tree for active teachers when an open semester is created without teaching loads', function () {
    $adminRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $admin = User::factory()->create(['role_id' => $adminRoleId]);
    $activeTeacher = User::factory()->create([
        'role_id' => $teacherRoleId,
        'is_active' => true,
        'name' => 'Docente Base ' . Str::upper(Str::random(4)),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.semesters.store'), [
            'name' => 'SEM-BASE-' . Str::upper(Str::random(6)),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'status' => 'OPEN',
            'academic_period_id' => null,
        ])
        ->assertRedirect(route('admin.semesters.index'));

    $semester = Semester::latest('id')->firstOrFail();

    $teacherFolder = \App\Models\FolderNode::query()
        ->where('semester_id', $semester->id)
        ->where('owner_user_id', $activeTeacher->id)
        ->where('name', $activeTeacher->name)
        ->first();

    expect($teacherFolder)->not->toBeNull();

    $this->assertDatabaseHas('folder_nodes', [
        'semester_id' => $semester->id,
        'owner_user_id' => $activeTeacher->id,
        'parent_id' => $teacherFolder->id,
        'name' => '0.HORARIO OFICIAL',
    ]);

    $projectFolder = \App\Models\FolderNode::query()
        ->where('semester_id', $semester->id)
        ->where('owner_user_id', $activeTeacher->id)
        ->where('parent_id', $teacherFolder->id)
        ->where('name', '4.PROYECTOS INDIVIDUALES')
        ->first();

    expect($projectFolder)->not->toBeNull();

    $this->assertDatabaseHas('folder_nodes', [
        'semester_id' => $semester->id,
        'owner_user_id' => $activeTeacher->id,
        'parent_id' => $projectFolder->id,
        'name' => '4.1-CAPACITACION',
    ]);
});
