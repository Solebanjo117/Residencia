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
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createDashboardBaseContext(): array
{
    $semester = Semester::create([
        'name' => 'SEM-DB-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-DB-' . Str::upper(Str::random(6)),
        'name' => 'Materia DB ' . Str::upper(Str::random(4)),
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'ITEM-DB-' . Str::upper(Str::random(8)),
        'description' => 'Item dashboard',
        'requires_subject' => true,
        'active' => true,
    ]);

    return compact('semester', 'subject', 'item');
}

it('renders role dashboard with docente quick actions and deadlines', function () {
    $ctx = createDashboardBaseContext();

    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $docenteRoleId]);

    $department = Department::create(['name' => 'DEP-DB-' . Str::upper(Str::random(6))]);
    $teacher->departments()->attach($department->id);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $ctx['semester']->id,
        'subject_id' => $ctx['subject']->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    EvidenceRequirement::create([
        'semester_id' => $ctx['semester']->id,
        'department_id' => $department->id,
        'evidence_item_id' => $ctx['item']->id,
        'is_mandatory' => true,
    ]);

    EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'last_updated_at' => now(),
    ]);

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(3),
        'created_by_user_id' => $teacher->id,
        'status' => 'ACTIVE',
    ]);

    $this
        ->actingAs($teacher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('semester.name', $ctx['semester']->name)
            ->has('overview', 7)
            ->where('quickActions.0.href', '/docente/evidencias')
            ->has('upcomingDeadlines', 1)
        );
});

it('renders role dashboard with oficina quick actions and pending review metrics', function () {
    $ctx = createDashboardBaseContext();

    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $docenteRoleId]);

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $ctx['semester']->id,
        'subject_id' => $ctx['subject']->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'last_updated_at' => now(),
    ]);

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(2),
        'created_by_user_id' => $jefeOficina->id,
        'status' => 'ACTIVE',
    ]);

    $this
        ->actingAs($jefeOficina)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('semester.name', $ctx['semester']->name)
            ->has('overview', 7)
            ->where('quickActions.0.href', '/oficina/revisiones')
            ->has('upcomingDeadlines', 1)
        );
});
