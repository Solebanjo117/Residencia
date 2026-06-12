<?php

use App\Models\FolderNode;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;

function createOfficeAdminForTeacherFolders(): User
{
    $officeRole = Role::firstOrCreate(['name' => Role::JEFE_OFICINA]);

    return User::factory()->create([
        'role_id' => $officeRole->id,
        'email_verified_at' => now(),
    ]);
}

function createTeacherWithFolderKeys(array $keys): User
{
    $teacherRole = Role::firstOrCreate(['name' => Role::DOCENTE]);

    return User::factory()->create([
        'role_id' => $teacherRole->id,
        'email_verified_at' => now(),
        'folder_permission_keys' => $keys,
    ]);
}

it('stores normalized folder permission keys when creating a teacher', function () {
    $admin = createOfficeAdminForTeacherFolders();

    $this
        ->actingAs($admin)
        ->post(route('admin.teachers.store'), [
            'name' => 'Docente Permisos',
            'email' => 'docente-permisos@example.com',
            'password' => 'password123',
            'folder_permission_keys' => [
                '4.PROYECTOS INDIVIDUALES/4.1-CAPACITACION/SD2-AVANCE-50%',
            ],
        ])
        ->assertRedirect(route('admin.teachers.index'));

    $teacher = User::where('email', 'docente-permisos@example.com')->firstOrFail();

    expect($teacher->folder_permission_keys)->toContain('4.PROYECTOS INDIVIDUALES');
    expect($teacher->folder_permission_keys)->toContain('4.PROYECTOS INDIVIDUALES/4.1-CAPACITACION');
    expect($teacher->folder_permission_keys)->toContain('4.PROYECTOS INDIVIDUALES/4.1-CAPACITACION/SD2-AVANCE-50%');
});

it('keeps existing folder permission keys when updating teacher without folder payload', function () {
    $admin = createOfficeAdminForTeacherFolders();
    $teacher = createTeacherWithFolderKeys([
        '0.HORARIO OFICIAL',
        '4.PROYECTOS INDIVIDUALES',
        '4.PROYECTOS INDIVIDUALES/4.1-CAPACITACION',
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.teachers.update', $teacher->id), [
            'name' => $teacher->name,
            'email' => $teacher->email,
            'is_active' => false,
            'department_ids' => [],
        ])
        ->assertRedirect(route('admin.teachers.index'));

    $updated = $teacher->fresh();

    expect((bool) $updated->is_active)->toBeFalse();
    expect($updated->folder_permission_keys)->toBe([
        '0.HORARIO OFICIAL',
        '4.PROYECTOS INDIVIDUALES',
        '4.PROYECTOS INDIVIDUALES/4.1-CAPACITACION',
    ]);
});

it('applies folder permissions when creating a teaching load and can force full regeneration', function () {
    $admin = createOfficeAdminForTeacherFolders();
    $teacher = createTeacherWithFolderKeys(['0.HORARIO OFICIAL']);

    $semester = Semester::create([
        'name' => 'SEM-PERM-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUB-PERM-'.Str::upper(Str::random(5)),
        'name' => 'Materia Permisos',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.teaching-loads.store'), [
            'teacher_user_id' => $teacher->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'group_code' => 'A',
            'hours_per_week' => 4,
            'modality' => TeachingLoad::MODALITY_PRESENCIAL,
        ])
        ->assertRedirect();

    $subjectFolder = FolderNode::query()
        ->where('name', $subject->name)
        ->where('semester_id', $semester->id)
        ->where('owner_user_id', $teacher->id)
        ->firstOrFail();

    expect(FolderNode::query()
        ->where('parent_id', $subjectFolder->id)
        ->where('name', '0.HORARIO OFICIAL')
        ->exists())->toBeTrue();

    expect(FolderNode::query()
        ->where('parent_id', $subjectFolder->id)
        ->where('name', '1.INSTRUMENTACIONES')
        ->exists())->toBeFalse();

    $this
        ->actingAs($admin)
        ->post(route('admin.teachers.generate-folders', $teacher->id), [
            'force' => true,
        ])
        ->assertRedirect(route('admin.teachers.index'));

    $subjectFolderAfterForce = FolderNode::query()
        ->where('name', $subject->name)
        ->where('semester_id', $semester->id)
        ->where('owner_user_id', $teacher->id)
        ->firstOrFail();

    expect(FolderNode::query()
        ->where('parent_id', $subjectFolderAfterForce->id)
        ->where('name', '1.INSTRUMENTACIONES')
        ->exists())->toBeTrue();

    expect(TeachingLoad::where('teacher_user_id', $teacher->id)->count())->toBe(1);
});

it('rebuilds teacher structure and removes folders that are no longer permitted', function () {
    $admin = createOfficeAdminForTeacherFolders();
    $teacher = createTeacherWithFolderKeys([
        '0.HORARIO OFICIAL',
        '1.INSTRUMENTACIONES',
    ]);

    $semester = Semester::create([
        'name' => 'SEM-REBUILD-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUB-REBUILD-'.Str::upper(Str::random(5)),
        'name' => 'Materia Rebuild',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.teaching-loads.store'), [
            'teacher_user_id' => $teacher->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'group_code' => 'B',
            'hours_per_week' => 5,
            'modality' => TeachingLoad::MODALITY_PRESENCIAL,
        ])
        ->assertRedirect();

    $initialSubjectFolder = FolderNode::query()
        ->where('name', $subject->name)
        ->where('semester_id', $semester->id)
        ->where('owner_user_id', $teacher->id)
        ->firstOrFail();

    expect(FolderNode::query()
        ->where('parent_id', $initialSubjectFolder->id)
        ->where('name', '1.INSTRUMENTACIONES')
        ->exists())->toBeTrue();

    $this
        ->actingAs($admin)
        ->put(route('admin.teachers.update', $teacher->id), [
            'name' => $teacher->name,
            'email' => $teacher->email,
            'department_ids' => [],
            'is_active' => true,
            'folder_permission_keys' => ['0.HORARIO OFICIAL'],
        ])
        ->assertRedirect(route('admin.teachers.index'));

    $this
        ->actingAs($admin)
        ->post(route('admin.teachers.generate-folders', $teacher->id), [
            'force' => false,
        ])
        ->assertRedirect(route('admin.teachers.index'));

    $rebuiltSubjectFolder = FolderNode::query()
        ->where('name', $subject->name)
        ->where('semester_id', $semester->id)
        ->where('owner_user_id', $teacher->id)
        ->firstOrFail();

    expect(FolderNode::query()
        ->where('parent_id', $rebuiltSubjectFolder->id)
        ->where('name', '0.HORARIO OFICIAL')
        ->exists())->toBeTrue();

    expect(FolderNode::query()
        ->where('parent_id', $rebuiltSubjectFolder->id)
        ->where('name', '1.INSTRUMENTACIONES')
        ->exists())->toBeFalse();
});
