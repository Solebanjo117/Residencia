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

function createReviewableSubmission(): EvidenceSubmission
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-TEST-001',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-TEST-001',
        'name' => 'Materia Test',
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
        'name' => 'ITEM-TEST-001',
        'description' => 'Item de prueba',
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

it('forbids docente from reviewing evidence in seguimiento route', function () {
    $submission = createReviewableSubmission();

    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $docente = User::factory()->create(['role_id' => $docenteRoleId]);

    $response = $this
        ->actingAs($docente)
        ->post(route('asesorias.review', $submission->id), [
            'decision' => 'APPROVE',
            'comments' => 'No autorizado',
        ]);

    $response->assertForbidden();
});

it('forbids jefe depto from reviewing evidence in seguimiento route', function () {
    $submission = createReviewableSubmission();

    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);

    $response = $this
        ->actingAs($jefeDepto)
        ->post(route('asesorias.review', $submission->id), [
            'decision' => 'APPROVE',
            'comments' => 'No autorizado',
        ]);

    $response->assertForbidden();
});

it('allows jefe oficina to review a submitted evidence in seguimiento route', function () {
    $submission = createReviewableSubmission();

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    $response = $this
        ->from('/asesorias')
        ->actingAs($jefeOficina)
        ->post(route('asesorias.review', $submission->id), [
            'decision' => 'APPROVE',
            'comments' => 'Revisión válida',
        ]);

    $response->assertRedirect('/asesorias');
    expect($submission->fresh()->status)->toBe(SubmissionStatus::APPROVED);
    $this->assertDatabaseHas('evidence_reviews', [
        'submission_id' => $submission->id,
        'reviewed_by_user_id' => $jefeOficina->id,
        'decision' => 'APPROVE',
    ]);
});
