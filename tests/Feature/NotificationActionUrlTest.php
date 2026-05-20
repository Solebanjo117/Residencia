<?php

use App\Enums\NotificationType;
use App\Enums\SubmissionStatus;
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

function createNotificationActionSubmission(): EvidenceSubmission
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-NOTIF-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-NOTIF-'.Str::upper(Str::random(6)),
        'name' => 'Materia Notif '.Str::upper(Str::random(4)),
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
        'name' => 'ITEM-NOTIF-'.Str::upper(Str::random(8)),
        'description' => 'Item notification action test',
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

it('returns a docente action url for submission notifications', function () {
    $submission = createNotificationActionSubmission();

    Notification::create([
        'user_id' => $submission->teacher_user_id,
        'type' => NotificationType::SUBMISSION_APPROVED,
        'title' => 'Evidencia aprobada',
        'message' => 'Tu evidencia fue aprobada.',
        'related_entity_type' => EvidenceSubmission::class,
        'related_entity_id' => $submission->id,
        'created_at' => now(),
    ]);

    $this
        ->actingAs($submission->teacher)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('notifications.0.action_url', route('docente.evidencias', [
            'semester_id' => $submission->semester_id,
            'teaching_load_id' => $submission->teaching_load_id,
        ], false))
        ->assertJsonPath('notifications.0.action_label', 'Ver evidencia');
});

it('returns a docente correction action url for rejected submissions', function () {
    $submission = createNotificationActionSubmission();
    $submission->update(['status' => SubmissionStatus::REJECTED]);

    Notification::create([
        'user_id' => $submission->teacher_user_id,
        'type' => NotificationType::SUBMISSION_REJECTED,
        'title' => 'Evidencia rechazada',
        'message' => 'Tu evidencia fue rechazada.',
        'related_entity_type' => EvidenceSubmission::class,
        'related_entity_id' => $submission->id,
        'created_at' => now(),
    ]);

    $this
        ->actingAs($submission->teacher)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('notifications.0.action_url', route('asesorias', [
            'semester' => $submission->semester->name,
            'submission_id' => $submission->id,
            'teaching_load_id' => $submission->teaching_load_id,
            'evidence_item_id' => $submission->evidence_item_id,
        ], false))
        ->assertJsonPath('notifications.0.action_label', 'Corregir evidencia');
});

it('returns an office review action url for submission notifications', function () {
    $submission = createNotificationActionSubmission();
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $officeUser = User::factory()->create(['role_id' => $officeRoleId]);

    Notification::create([
        'user_id' => $officeUser->id,
        'type' => NotificationType::GENERAL,
        'title' => 'Nueva evidencia enviada',
        'message' => 'Hay una evidencia para revisar.',
        'related_entity_type' => EvidenceSubmission::class,
        'related_entity_id' => $submission->id,
        'created_at' => now(),
    ]);

    $this
        ->actingAs($officeUser)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('notifications.0.action_url', route('oficina.revisiones.show', $submission->teacher_user_id, false))
        ->assertJsonPath('notifications.0.action_label', 'Abrir revision');
});
