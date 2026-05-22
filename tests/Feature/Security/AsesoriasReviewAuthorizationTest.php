<?php

use App\Enums\ReviewDecision;
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
use App\Models\TeachingLoadReview;
use App\Models\User;
use App\Services\EvidenceService;

function createReviewableSubmission(): EvidenceSubmission
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $department = Department::create([
        'name' => 'Dept REV '.fake()->unique()->lexify('????'),
    ]);
    $teacher->departments()->attach($department->id);

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

it('requires a reason when rejecting evidence in seguimiento route', function () {
    $submission = createReviewableSubmission();

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    $response = $this
        ->from('/asesorias')
        ->actingAs($jefeOficina)
        ->post(route('asesorias.review', $submission->id), [
            'decision' => 'REJECT',
            'comments' => '',
        ]);

    $response
        ->assertRedirect('/asesorias')
        ->assertSessionHasErrors('comments');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::SUBMITTED);
});

it('forbids jefe oficina from registering final approval', function () {
    $submission = createReviewableSubmission();

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    /** @var EvidenceService $service */
    $service = app(EvidenceService::class);
    $service->review($submission, $jefeOficina, ReviewDecision::APPROVE, 'Aprobado por oficina');

    $this
        ->actingAs($jefeOficina)
        ->post(route('asesorias.final-approval', $submission->id), [
            'comments' => 'No autorizado',
        ])
        ->assertForbidden();

    expect($submission->fresh()->final_approved_at)->toBeNull();
});

it('forbids jefe oficina from setting final review statuses manually', function () {
    $submission = createReviewableSubmission();

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    $this
        ->actingAs($jefeOficina)
        ->post(route('asesorias.cells.status'), [
            'teaching_load_id' => $submission->teaching_load_id,
            'evidence_item_id' => $submission->evidence_item_id,
            'status' => 'VF',
            'comments' => 'No autorizado',
        ])
        ->assertForbidden();

    expect($submission->fresh()->final_approved_at)->toBeNull();
});

it('allows jefe depto to register final approval after office approval', function () {
    $submission = createReviewableSubmission();

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    /** @var EvidenceService $service */
    $service = app(EvidenceService::class);
    $service->review($submission, $jefeOficina, ReviewDecision::APPROVE, 'Aprobado por oficina');

    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);
    $jefeDepto->departments()->attach($submission->teacher->departments->pluck('id'));

    $response = $this
        ->from('/asesorias')
        ->actingAs($jefeDepto)
        ->post(route('asesorias.final-approval', $submission->id), [
            'comments' => 'Liberado por jefatura',
        ]);

    $response->assertRedirect('/asesorias');
    expect($submission->fresh()->final_approved_at)->not->toBeNull();
    $this->assertDatabaseHas('evidence_reviews', [
        'submission_id' => $submission->id,
        'reviewed_by_user_id' => $jefeDepto->id,
        'decision' => 'APPROVE',
        'stage' => 'FINAL',
    ]);
});

it('forbids non jefe depto users from registering final approval', function () {
    $submission = createReviewableSubmission();

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    /** @var EvidenceService $service */
    $service = app(EvidenceService::class);
    $service->review($submission, $jefeOficina, ReviewDecision::APPROVE, 'Aprobado por oficina');

    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $docente = User::factory()->create(['role_id' => $docenteRoleId]);

    $this
        ->actingAs($jefeOficina)
        ->post(route('asesorias.final-approval', $submission->id), [
            'comments' => 'No autorizado',
        ])
        ->assertForbidden();

    $this
        ->actingAs($docente)
        ->post(route('asesorias.final-approval', $submission->id), [
            'comments' => 'No autorizado',
        ])
        ->assertForbidden();

    expect($submission->fresh()->final_approved_at)->toBeNull();
});

it('allows only jefe depto to approve or reject the teaching load department review', function () {
    $submission = createReviewableSubmission();
    $load = $submission->teachingLoad;

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    $this
        ->actingAs($jefeOficina)
        ->post(route('asesorias.loads.department-review', $load->id), [
            'decision' => 'APPROVE',
            'comments' => 'No autorizado',
        ])
        ->assertForbidden();

    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);
    $jefeDepto->departments()->attach($submission->teacher->departments->pluck('id'));

    $this
        ->from('/asesorias')
        ->actingAs($jefeDepto)
        ->post(route('asesorias.loads.department-review', $load->id), [
            'decision' => 'REJECT',
            'comments' => 'Faltan correcciones generales',
        ])
        ->assertRedirect('/asesorias');

    $this->assertDatabaseHas('teaching_load_reviews', [
        'teaching_load_id' => $load->id,
        'reviewed_by_user_id' => $jefeDepto->id,
        'decision' => 'REJECT',
        'comments' => 'Faltan correcciones generales',
    ]);

    $review = TeachingLoadReview::where('teaching_load_id', $load->id)->firstOrFail();
    $notification = Notification::query()
        ->where('user_id', $submission->teacher_user_id)
        ->where('related_entity_type', TeachingLoadReview::class)
        ->where('related_entity_id', $review->id)
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->type->value)->toBe('SUBMISSION_REJECTED');

    $this
        ->actingAs($submission->teacher)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('notifications.0.action_url', route('docente.evidencias', [
            'semester_id' => $load->semester_id,
            'teaching_load_id' => $load->id,
        ], false))
        ->assertJsonPath('notifications.0.action_label', 'Ver asignatura');
});

it('notifies the teacher when jefe depto approves a teaching load review', function () {
    $submission = createReviewableSubmission();
    $load = $submission->teachingLoad;

    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);
    $jefeDepto->departments()->attach($submission->teacher->departments->pluck('id'));

    $this
        ->from('/asesorias')
        ->actingAs($jefeDepto)
        ->post(route('asesorias.loads.department-review', $load->id), [
            'decision' => 'APPROVE',
            'comments' => 'Asignatura completa',
        ])
        ->assertRedirect('/asesorias');

    $review = TeachingLoadReview::where('teaching_load_id', $load->id)->firstOrFail();

    $notification = Notification::query()
        ->where('user_id', $submission->teacher_user_id)
        ->where('related_entity_type', TeachingLoadReview::class)
        ->where('related_entity_id', $review->id)
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->type->value)->toBe('SUBMISSION_APPROVED');

    $this
        ->actingAs($submission->teacher)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('notifications.0.action_url', route('docente.evidencias', [
            'semester_id' => $load->semester_id,
            'teaching_load_id' => $load->id,
        ], false))
        ->assertJsonPath('notifications.0.action_label', 'Ver asignatura');
});
