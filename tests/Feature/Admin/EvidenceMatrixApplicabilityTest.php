<?php

use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use App\Services\EvidenceFlowService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createEvidenceMatrixApplicabilityContext(): array
{
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $office = User::factory()->create(['role_id' => $officeRoleId]);
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $department = Department::create(['name' => 'DEP MATRIX '.Str::upper(Str::random(5))]);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM MATRIX '.Str::upper(Str::random(5)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'MAT-'.Str::upper(Str::random(5)),
        'name' => 'MATRIZ '.Str::upper(Str::random(5)),
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'PROYECTOS INDIVIDUALES '.Str::upper(Str::random(4)),
        'description' => 'Proyecto individual configurable',
        'requires_subject' => true,
        'active' => true,
    ]);

    return compact('office', 'teacher', 'department', 'semester', 'load', 'item');
}

it('stores teacher and teaching load applicability overrides in the evidence matrix', function () {
    $ctx = createEvidenceMatrixApplicabilityContext();

    $this
        ->actingAs($ctx['office'])
        ->post(route('admin.requirements.store'), [
            'semester_id' => $ctx['semester']->id,
            'requirements' => [[
                'department_id' => $ctx['department']->id,
                'evidence_item_id' => $ctx['item']->id,
                'is_mandatory' => true,
                'applies_condition' => [
                    'teacher_overrides' => [
                        (string) $ctx['teacher']->id => false,
                    ],
                    'teaching_load_overrides' => [
                        (string) $ctx['load']->id => true,
                    ],
                ],
            ]],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('evidence_requirements', [
        'semester_id' => $ctx['semester']->id,
        'department_id' => $ctx['department']->id,
        'evidence_item_id' => $ctx['item']->id,
    ]);

    $requirement = EvidenceRequirement::firstWhere('evidence_item_id', $ctx['item']->id);

    expect($requirement->applies_condition['teacher_overrides'][(string) $ctx['teacher']->id])->toBeFalse()
        ->and($requirement->applies_condition['teaching_load_overrides'][(string) $ctx['load']->id])->toBeTrue();
});

it('hides non applicable matrix evidence from the teacher evidence list', function () {
    $ctx = createEvidenceMatrixApplicabilityContext();

    EvidenceRequirement::create([
        'semester_id' => $ctx['semester']->id,
        'department_id' => $ctx['department']->id,
        'evidence_item_id' => $ctx['item']->id,
        'is_mandatory' => true,
        'applies_condition' => [
            'teacher_overrides' => [
                (string) $ctx['teacher']->id => false,
            ],
        ],
    ]);

    expect(app(EvidenceFlowService::class)
        ->requirementsForDepartment($ctx['semester']->id, $ctx['department']->id, $ctx['load'])
        ->pluck('evidence_item_id')
        ->contains($ctx['item']->id))->toBeFalse();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('docente.evidencias', ['semester_id' => $ctx['semester']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tasks', [])
        );
});
