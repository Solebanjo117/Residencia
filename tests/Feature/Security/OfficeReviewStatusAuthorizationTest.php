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

function createOfficeReviewSubmission(): EvidenceSubmission
{
    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $docenteRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-REV-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-REV-' . Str::upper(Str::random(6)),
        'name' => 'Materia REV ' . Str::upper(Str::random(4)),
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
        'name' => 'ITEM-REV-' . Str::upper(Str::random(8)),
        'description' => 'Item review status auth test',
        'requires_subject' => true,
        'active' => true,
    ]);

    return EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $item->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'last_updated_at' => now(),
    ]);
}

it('forbids docente from updating submission status in office review endpoint', function () {
    $submission = createOfficeReviewSubmission();

    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $docente = User::factory()->create(['role_id' => $docenteRoleId]);

    $this
        ->actingAs($docente)
        ->post(route('oficina.revisiones.status', $submission->id), [
            'status' => 'APPROVED',
            'comments' => 'No autorizado',
        ])
        ->assertForbidden();
});

it('forbids jefe depto from updating submission status in office review endpoint', function () {
    $submission = createOfficeReviewSubmission();

    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);

    $this
        ->actingAs($jefeDepto)
        ->post(route('oficina.revisiones.status', $submission->id), [
            'status' => 'APPROVED',
            'comments' => 'No autorizado',
        ])
        ->assertForbidden();
});

it('allows jefe oficina to update submission status in office review endpoint', function () {
    $submission = createOfficeReviewSubmission();

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    $this
        ->from('/oficina/revisiones')
        ->actingAs($jefeOficina)
        ->post(route('oficina.revisiones.status', $submission->id), [
            'status' => 'APPROVED',
            'comments' => 'Revision valida',
        ])
        ->assertRedirect('/oficina/revisiones');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::APPROVED);
    $this->assertDatabaseHas('evidence_reviews', [
        'submission_id' => $submission->id,
        'reviewed_by_user_id' => $jefeOficina->id,
        'decision' => 'APPROVE',
    ]);
});
