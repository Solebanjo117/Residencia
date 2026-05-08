<?php

use App\Http\Controllers\Admin\AdvisoryController;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('uses SeguimientoDocente as the live asesorias view', function () {
    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $docente = User::factory()->create(['role_id' => $docenteRoleId]);

    $this
        ->actingAs($docente)
        ->get(route('asesorias'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SeguimientoDocente')
        );
});

it('keeps legacy asesorias2 route unavailable', function () {
    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $docente = User::factory()->create(['role_id' => $docenteRoleId]);

    $this
        ->actingAs($docente)
        ->get('/asesorias2')
        ->assertNotFound();
});

it('keeps final row status as office approved when final evidence approval is pending', function () {
    $controller = app(AdvisoryController::class);
    $method = new ReflectionMethod($controller, 'resolveRowStatus');
    $method->setAccessible(true);

    $status = $method->invoke($controller, collect([
        ['status' => 'AO'],
        ['status' => 'VF'],
        ['status' => 'NA'],
    ]));

    expect($status)->toBe('AO');
});

it('uses online modality submission windows before the general window', function () {
    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $department = Department::create(['name' => 'DEP ONLINE TEST']);

    $admin = User::factory()->create(['role_id' => $jefeDeptoRoleId]);
    $teacher = User::factory()->create(['role_id' => $docenteRoleId]);

    $admin->departments()->attach($department->id);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM ONLINE TEST',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'ONLINE-001',
        'name' => 'Materia Online Test',
    ]);

    $category = EvidenceCategory::firstOrCreate(
        ['name' => 'I_CARGA_ACADEMICA'],
        ['description' => 'Carga academica']
    );

    $item = EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'INSTRUM ONLINE',
        'description' => 'Instrumentacion en linea',
        'requires_subject' => true,
        'active' => true,
    ]);

    EvidenceRequirement::create([
        'semester_id' => $semester->id,
        'department_id' => $department->id,
        'evidence_item_id' => $item->id,
        'is_mandatory' => true,
    ]);

    TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'OL-1',
        'hours_per_week' => 4,
        'modality' => TeachingLoad::MODALITY_EN_LINEA,
    ]);

    SubmissionWindow::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $item->id,
        'modality' => null,
        'opens_at' => now()->addMonth(),
        'closes_at' => now()->addMonths(2),
        'created_by_user_id' => $admin->id,
        'status' => 'ACTIVE',
    ]);

    SubmissionWindow::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $item->id,
        'modality' => TeachingLoad::MODALITY_EN_LINEA,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonths(5),
        'created_by_user_id' => $admin->id,
        'status' => 'ACTIVE',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('asesorias', ['semester' => $semester->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SeguimientoDocente')
            ->where('rows.0.modality', TeachingLoad::MODALITY_EN_LINEA)
            ->where('rows.0.cells.item_'.$item->id.'.availability.code', 'OPEN')
        );
});
