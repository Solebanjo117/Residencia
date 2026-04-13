<?php

use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Str;

function createTeacherInitContext(): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-INIT-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-INIT-' . Str::upper(Str::random(6)),
        'name' => 'Materia Init ' . Str::upper(Str::random(4)),
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
        'name' => 'ITEM-INIT-' . Str::upper(Str::random(8)),
        'description' => 'Item init test',
        'requires_subject' => true,
        'active' => true,
    ]);

    return compact('teacher', 'load', 'item');
}

it('initializes a draft submission for teacher evidence task', function () {
    $ctx = createTeacherInitContext();

    $response = $this
        ->from('/docente/evidencias')
        ->actingAs($ctx['teacher'])
        ->post(route('docente.evidencias.init'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
        ]);

    $response->assertRedirect('/docente/evidencias');
    $response->assertSessionHas('submission_id');

    $this->assertDatabaseHas('evidence_submissions', [
        'semester_id' => $ctx['load']->semester_id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => 'DRAFT',
    ]);
});

it('keeps init submission idempotent for same load and evidence item', function () {
    $ctx = createTeacherInitContext();

    $payload = [
        'teaching_load_id' => $ctx['load']->id,
        'evidence_item_id' => $ctx['item']->id,
    ];

    $this->actingAs($ctx['teacher'])->post(route('docente.evidencias.init'), $payload)->assertRedirect();
    $this->actingAs($ctx['teacher'])->post(route('docente.evidencias.init'), $payload)->assertRedirect();

    $count = \App\Models\EvidenceSubmission::query()
        ->where('semester_id', $ctx['load']->semester_id)
        ->where('teacher_user_id', $ctx['teacher']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->where('teaching_load_id', $ctx['load']->id)
        ->count();

    expect($count)->toBe(1);
});
