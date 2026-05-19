<?php

use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\TeachingLoadReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SeguimientoSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('uses existing seguimiento windows for project moments and keeps initial evidence open through sd2', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15 09:00:00'));

    $jefeRole = Role::firstOrCreate(['name' => Role::JEFE_OFICINA]);
    User::factory()->create(['role_id' => $jefeRole->id, 'is_active' => true]);

    $this->seed(SeguimientoSeeder::class);

    $semester = Semester::where('name', 'ENE-JUN 2026')->firstOrFail();
    $items = EvidenceItem::whereIn('name', [
        'INSTRUM',
        'EV.DIAGN',
        'SEG 02',
        'SEG 04 FINAL',
        'PROY IND',
        'PROY IND SD2',
        'PROY IND SD4',
    ])->get()->keyBy('name');

    expect($items->has('SEG 02'))->toBeTrue()
        ->and($items->has('SEG 04 FINAL'))->toBeTrue()
        ->and($items->has('PROY IND SD2'))->toBeFalse()
        ->and($items->has('PROY IND SD4'))->toBeFalse()
        ->and($items->has('PROY IND'))->toBeFalse();

    $sd2Window = SubmissionWindow::where('semester_id', $semester->id)
        ->where('evidence_item_id', $items['SEG 02']->id)
        ->whereNull('modality')
        ->firstOrFail();
    $sd4Window = SubmissionWindow::where('semester_id', $semester->id)
        ->where('evidence_item_id', $items['SEG 04 FINAL']->id)
        ->whereNull('modality')
        ->firstOrFail();

    foreach (['INSTRUM', 'EV.DIAGN'] as $itemName) {
        $window = SubmissionWindow::where('semester_id', $semester->id)
            ->where('evidence_item_id', $items[$itemName]->id)
            ->whereNull('modality')
            ->firstOrFail();

        expect($window->closes_at->toDateTimeString())->toBe($sd2Window->closes_at->toDateTimeString());
    }
    expect($sd4Window->opens_at->greaterThan($sd2Window->closes_at))->toBeTrue();
});

it('shows existing sd2 and sd4 seguimiento columns for project moments', function () {
    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $department = Department::create(['name' => 'DEP PROY '.Str::upper(Str::random(6))]);
    $teacher = User::factory()->create(['role_id' => $docenteRoleId]);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM PROY '.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'PROY-'.Str::upper(Str::random(6)),
        'name' => 'PROYECTOS DE INNOVACION '.Str::upper(Str::random(4)),
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $seg2Item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'SEG 02',
        'description' => 'Segundo seguimiento',
        'requires_subject' => true,
        'active' => true,
    ]);
    $seg4Item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'SEG 04 FINAL',
        'description' => 'Cuarto seguimiento final',
        'requires_subject' => true,
        'active' => true,
    ]);

    foreach ([$seg2Item, $seg4Item] as $item) {
        EvidenceRequirement::create([
            'semester_id' => $semester->id,
            'department_id' => $department->id,
            'evidence_item_id' => $item->id,
            'is_mandatory' => true,
        ]);

        SubmissionWindow::create([
            'semester_id' => $semester->id,
            'evidence_item_id' => $item->id,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'created_by_user_id' => $teacher->id,
            'status' => 'ACTIVE',
        ]);
    }

    $this
        ->actingAs($teacher)
        ->get(route('asesorias', ['semester' => $semester->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.label', 'SEG 02')
            ->where('columns.0.stage_label', 'SD2')
            ->where('columns.1.label', 'SEG 04 FINAL')
            ->where('columns.1.stage_label', 'SD4')
            ->where('rows.0.id', $load->id)
            ->where('rows.0.cells.item_'.$seg2Item->id.'.stage_label', 'SD2')
            ->where('rows.0.cells.item_'.$seg4Item->id.'.stage_label', 'SD4')
        );
});

it('marks the active seguimiento stage and exposes manual completion labels', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-05 09:00:00'));

    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $department = Department::create(['name' => 'DEP ACTUAL '.Str::upper(Str::random(6))]);
    $teacher = User::factory()->create(['role_id' => $docenteRoleId]);
    $teacher->departments()->attach($department->id);
    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);
    $jefeDepto->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM ACTUAL '.Str::upper(Str::random(6)),
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'ACT-'.Str::upper(Str::random(6)),
        'name' => 'CONTROL ACTUAL '.Str::upper(Str::random(4)),
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $seg2Item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'SEG 02',
        'description' => 'Segundo seguimiento activo',
        'requires_subject' => true,
        'active' => true,
    ]);
    $seg4Item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'SEG 04 FINAL',
        'description' => 'Cuarto seguimiento futuro',
        'requires_subject' => true,
        'active' => true,
    ]);

    foreach ([$seg2Item, $seg4Item] as $item) {
        EvidenceRequirement::create([
            'semester_id' => $semester->id,
            'department_id' => $department->id,
            'evidence_item_id' => $item->id,
            'is_mandatory' => true,
        ]);
    }

    SubmissionWindow::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $seg2Item->id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDay(),
        'created_by_user_id' => $jefeDepto->id,
        'status' => 'ACTIVE',
    ]);
    SubmissionWindow::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $seg4Item->id,
        'opens_at' => now()->addMonth(),
        'closes_at' => now()->addMonth()->addDays(3),
        'created_by_user_id' => $jefeDepto->id,
        'status' => 'ACTIVE',
    ]);

    TeachingLoadReview::create([
        'teaching_load_id' => $load->id,
        'reviewed_by_user_id' => $jefeDepto->id,
        'decision' => 'APPROVE',
        'comments' => 'Expediente completo.',
        'reviewed_at' => now(),
    ]);

    $this
        ->actingAs($teacher)
        ->get(route('asesorias', ['semester' => $semester->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_stage', 'SD2')
            ->where('columns.0.is_current_stage', true)
            ->where('columns.1.is_current_stage', false)
            ->where('rows.0.efficiency_label', 'Nivel de cumplimiento')
            ->where('rows.0.final_completion_status', 'Completo')
        );
});
