<?php

use App\Enums\SubmissionStatus;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;

function createApplicabilityContext(): array
{
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $office = User::factory()->create(['role_id' => $officeRoleId]);
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $department = Department::create([
        'name' => 'Dept APP ' . Str::upper(Str::random(4)),
    ]);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM-APP-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-APP-' . Str::upper(Str::random(6)),
        'name' => 'Materia APP ' . Str::upper(Str::random(4)),
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
        'name' => 'ITEM-APP-' . Str::upper(Str::random(8)),
        'description' => 'Item applicability test',
        'requires_subject' => true,
        'active' => true,
    ]);

    return compact('office', 'teacher', 'load', 'item');
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
