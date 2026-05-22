<?php

use App\Enums\NotificationType;
use App\Enums\SubmissionStatus;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\Subject;
use App\Models\SubmissionWindow;
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

it('returns a focused office review action url for office submission notifications', function () {
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
        ->assertJsonPath('notifications.0.action_url', route('oficina.revisiones.show', [
            'submission' => $submission->teacher_user_id,
            'focus_submission_id' => $submission->id,
            'teaching_load_id' => $submission->teaching_load_id,
            'evidence_item_id' => $submission->evidence_item_id,
        ], false))
        ->assertJsonPath('notifications.0.action_label', 'Revisar entrega');
});

it('returns the same focused review action url for department head submission notifications', function () {
    $submission = createNotificationActionSubmission();
    $departmentRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $departmentUser = User::factory()->create(['role_id' => $departmentRoleId]);

    Notification::create([
        'user_id' => $departmentUser->id,
        'type' => NotificationType::GENERAL,
        'title' => 'Nueva evidencia enviada',
        'message' => 'Hay una evidencia para revisar.',
        'related_entity_type' => EvidenceSubmission::class,
        'related_entity_id' => $submission->id,
        'created_at' => now(),
    ]);

    $this
        ->actingAs($departmentUser)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('notifications.0.action_url', route('oficina.revisiones.show', [
            'submission' => $submission->teacher_user_id,
            'focus_submission_id' => $submission->id,
            'teaching_load_id' => $submission->teaching_load_id,
            'evidence_item_id' => $submission->evidence_item_id,
        ], false))
        ->assertJsonPath('notifications.0.action_label', 'Revisar entrega');
});

it('returns a focused review action url for uploaded file notifications', function () {
    $submission = createNotificationActionSubmission();
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $officeUser = User::factory()->create(['role_id' => $officeRoleId]);

    $root = StorageRoot::create([
        'name' => 'root-notif-file-'.Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $folder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Folder notif file',
        'relative_path' => 'sem_'.$submission->semester_id.'/docente_'.$submission->teacher_user_id.'/item_'.$submission->evidence_item_id,
        'owner_user_id' => $submission->teacher_user_id,
        'semester_id' => $submission->semester_id,
        'parent_id' => null,
    ]);

    $file = EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $folder->id,
        'file_name' => 'evidencia.pdf',
        'stored_relative_path' => $folder->relative_path.'/evidencia.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1234,
        'file_hash' => str_repeat('c', 64),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $submission->teacher_user_id,
    ]);

    Notification::create([
        'user_id' => $officeUser->id,
        'type' => NotificationType::GENERAL,
        'title' => 'Archivo subido para revision',
        'message' => 'Hay un archivo para revisar.',
        'related_entity_type' => EvidenceFile::class,
        'related_entity_id' => $file->id,
        'created_at' => now(),
    ]);

    $this
        ->actingAs($officeUser)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('notifications.0.action_url', route('oficina.revisiones.show', [
            'submission' => $submission->teacher_user_id,
            'focus_submission_id' => $submission->id,
            'teaching_load_id' => $submission->teaching_load_id,
            'evidence_item_id' => $submission->evidence_item_id,
            'focus_file_id' => $file->id,
        ], false))
        ->assertJsonPath('notifications.0.action_label', 'Revisar entrega');
});

it('returns a focused teacher evidence action url for due soon window notifications', function () {
    $submission = createNotificationActionSubmission();
    $window = SubmissionWindow::create([
        'semester_id' => $submission->semester_id,
        'evidence_item_id' => $submission->evidence_item_id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDays(4),
        'created_by_user_id' => $submission->teacher_user_id,
        'status' => 'ACTIVE',
    ]);

    Notification::create([
        'user_id' => $submission->teacher_user_id,
        'type' => NotificationType::TASK_DUE_SOON,
        'title' => 'Tarea por vencer',
        'message' => 'Tu tarea esta por vencer.',
        'related_entity_type' => SubmissionWindow::class,
        'related_entity_id' => $window->id,
        'created_at' => now(),
    ]);

    $this
        ->actingAs($submission->teacher)
        ->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('notifications.0.action_url', route('docente.evidencias', [
            'semester_id' => $submission->semester_id,
            'evidence_item_id' => $submission->evidence_item_id,
        ], false))
        ->assertJsonPath('notifications.0.action_label', 'Ver mis evidencias');
});
