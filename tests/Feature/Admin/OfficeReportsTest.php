<?php

use App\Enums\SubmissionStatus;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createOfficeReportsContext(): array
{
    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-REP-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-REP-' . Str::upper(Str::random(6)),
        'name' => 'Materia REP ' . Str::upper(Str::random(4)),
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $itemSubmitted = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'ITEM-REP-SUB-' . Str::upper(Str::random(8)),
        'description' => 'Item reportes test',
        'requires_subject' => true,
        'active' => true,
    ]);

    $itemApproved = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'ITEM-REP-APP-' . Str::upper(Str::random(8)),
        'description' => 'Item reportes test approved',
        'requires_subject' => true,
        'active' => true,
    ]);

    EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $itemSubmitted->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'last_updated_at' => now(),
    ]);

    EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $itemApproved->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::APPROVED,
        'submitted_at' => now()->subDay(),
        'last_updated_at' => now(),
    ]);

    return compact('jefeOficina', 'teacher', 'semester');
}

it('renders office reports with aggregated teacher metrics', function () {
    $ctx = createOfficeReportsContext();

    $this
        ->actingAs($ctx['jefeOficina'])
        ->get(route('oficina.reportes', ['semester_id' => $ctx['semester']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Oficina/Reports')
            ->where('summary.teachers', 1)
            ->where('summary.submissions', 2)
            ->where('summary.submitted', 1)
            ->where('summary.approved', 1)
            ->has('rows', 1)
            ->where('rows.0.teacher_name', $ctx['teacher']->name)
        );
});

it('exports office reports as csv with current filters', function () {
    $ctx = createOfficeReportsContext();

    $response = $this
        ->actingAs($ctx['jefeOficina'])
        ->get(route('oficina.reportes', [
            'semester_id' => $ctx['semester']->id,
            'status_focus' => 'pending_review',
            'export' => 'csv',
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertHeader('content-disposition');
});
