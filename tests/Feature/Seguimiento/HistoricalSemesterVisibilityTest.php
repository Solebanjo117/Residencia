<?php

use App\Enums\SubmissionStatus;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('shows historical semester evidence in seguimiento while blocking operations', function () {
    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $department = Department::create(['name' => 'DEP-HIST-' . Str::upper(Str::random(5))]);

    $admin = User::factory()->create(['role_id' => $jefeDeptoRoleId]);
    $teacher = User::factory()->create(['role_id' => $docenteRoleId]);

    $admin->departments()->attach($department->id);
    $teacher->departments()->attach($department->id);

    $activeSemester = Semester::create([
        'name' => 'SEM-ACT-' . Str::upper(Str::random(5)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
        'status' => 'OPEN',
    ]);

    $historicalSemester = Semester::create([
        'name' => 'SEM-HIST-' . Str::upper(Str::random(5)),
        'start_date' => now()->subMonths(8)->toDateString(),
        'end_date' => now()->subMonths(4)->toDateString(),
        'status' => 'CLOSED',
    ]);

    $subject = Subject::create([
        'code' => 'SUB-HIST-' . Str::upper(Str::random(6)),
        'name' => 'Materia Historica ' . Str::upper(Str::random(4)),
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');

    $approvedItem = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'INSTRUM HIST ' . Str::upper(Str::random(4)),
        'description' => 'Historico aprobado',
        'requires_subject' => true,
        'active' => true,
    ]);

    $missingItem = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'SEG 01 HIST ' . Str::upper(Str::random(4)),
        'description' => 'Historico faltante',
        'requires_subject' => true,
        'active' => true,
    ]);

    EvidenceRequirement::create([
        'semester_id' => $historicalSemester->id,
        'department_id' => $department->id,
        'evidence_item_id' => $approvedItem->id,
        'is_mandatory' => true,
    ]);

    EvidenceRequirement::create([
        'semester_id' => $historicalSemester->id,
        'department_id' => $department->id,
        'evidence_item_id' => $missingItem->id,
        'is_mandatory' => true,
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $historicalSemester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    EvidenceSubmission::create([
        'semester_id' => $historicalSemester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $approvedItem->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::APPROVED,
        'submitted_at' => now()->subMonths(5),
        'office_reviewed_at' => now()->subMonths(5),
        'office_reviewed_by_user_id' => $admin->id,
        'last_updated_at' => now()->subMonths(5),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('asesorias', ['semester' => $historicalSemester->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SeguimientoDocente')
            ->where('currentSemester', $historicalSemester->name)
            ->has('rows', 1)
            ->where('rows.0.cells.item_' . $approvedItem->id . '.status', 'AO')
            ->where('rows.0.cells.item_' . $approvedItem->id . '.availability.code', 'HISTORICAL')
            ->where('rows.0.cells.item_' . $missingItem->id . '.status', 'NE')
            ->where('rows.0.cells.item_' . $missingItem->id . '.availability.code', 'HISTORICAL')
        );
});
