<?php

use App\Enums\SubmissionStatus;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;

function createApplicabilityContext(): array
{
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $deptHeadRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $office = User::factory()->create(['role_id' => $officeRoleId]);
    $deptHead = User::factory()->create(['role_id' => $deptHeadRoleId]);
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $department = Department::create([
        'name' => 'Dept APP '.Str::upper(Str::random(4)),
    ]);
    $deptHead->departments()->attach($department->id);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM-APP-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-APP-'.Str::upper(Str::random(6)),
        'name' => 'Materia APP '.Str::upper(Str::random(4)),
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
        'name' => 'ITEM-APP-'.Str::upper(Str::random(8)),
        'description' => 'Item applicability test',
        'requires_subject' => true,
        'active' => true,
    ]);

    return compact('office', 'deptHead', 'teacher', 'load', 'item');
}

it('allows office to mark a load evidence as no aplica and reactivate it later', function () {
    $ctx = createApplicabilityContext();

    $this
        ->from('/asesorias')
        ->actingAs($ctx['office'])
        ->post(route('asesorias.cells.status'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'status' => 'NA',
            'comments' => 'No corresponde a esta carga',
        ])
        ->assertRedirect('/asesorias');

    $submission = EvidenceSubmission::query()
        ->where('teaching_load_id', $ctx['load']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->first();

    expect($submission)->not->toBeNull();
    expect($submission->status)->toBe(SubmissionStatus::NA);

    $this
        ->from('/asesorias')
        ->actingAs($ctx['office'])
        ->post(route('asesorias.cells.status'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'status' => 'DRAFT',
            'comments' => 'Se reactiva por correccion administrativa',
        ])
        ->assertRedirect('/asesorias');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::DRAFT);
});

it('allows office to manually mark seguimiento statuses', function () {
    $ctx = createApplicabilityContext();

    $this
        ->from('/asesorias')
        ->actingAs($ctx['office'])
        ->post(route('asesorias.cells.status'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'status' => 'REV',
            'comments' => 'Lista para revision del jefe de departamento',
        ])
        ->assertRedirect('/asesorias');

    $submission = EvidenceSubmission::query()
        ->where('teaching_load_id', $ctx['load']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->firstOrFail();

    expect($submission->status)->toBe(SubmissionStatus::APPROVED);
    expect($submission->manual_ui_status)->toBe('REV');
    expect($submission->office_reviewed_at)->not->toBeNull();
    expect($submission->office_reviewed_by_user_id)->toBe($ctx['office']->id);
    expect($submission->final_approved_at)->toBeNull();
});

it('notifies the teacher when seguimiento status is manually approved', function () {
    $ctx = createApplicabilityContext();

    $this
        ->from('/asesorias')
        ->actingAs($ctx['deptHead'])
        ->post(route('asesorias.cells.status'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'status' => 'VF',
            'comments' => 'Visto bueno final',
        ])
        ->assertRedirect('/asesorias');

    $submission = EvidenceSubmission::query()
        ->where('teaching_load_id', $ctx['load']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->firstOrFail();

    expect($submission->status)->toBe(SubmissionStatus::APPROVED);
    expect($submission->final_approved_at)->not->toBeNull();

    $notification = Notification::where('user_id', $ctx['teacher']->id)->first();

    expect($notification)->not->toBeNull();
    expect($notification->type->value)->toBe('SUBMISSION_APPROVED');
    expect($notification->related_entity_id)->toBe($submission->id);
});

it('forbids docente from marking a load evidence as no aplica', function () {
    $ctx = createApplicabilityContext();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('asesorias.cells.status'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'status' => 'NA',
            'comments' => 'No autorizado',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('evidence_submissions', [
        'teaching_load_id' => $ctx['load']->id,
        'evidence_item_id' => $ctx['item']->id,
        'status' => 'NA',
    ]);
});
