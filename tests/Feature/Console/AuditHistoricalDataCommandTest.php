<?php

use App\Enums\SubmissionStatus;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceStatusHistory;
use App\Models\EvidenceSubmission;
use App\Models\NotificationSchedule;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\Subject;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createHistoricalAuditBase(): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');

    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $office = User::factory()->create(['role_id' => $officeRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-HIST-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-HIST-' . Str::upper(Str::random(6)),
        'name' => 'Materia Hist ' . Str::upper(Str::random(4)),
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $category = EvidenceCategory::create([
        'name' => 'CAT-HIST-' . Str::upper(Str::random(6)),
        'description' => 'Categoria para pruebas de saneamiento historico',
    ]);

    return compact('teacher', 'office', 'semester', 'load', 'category');
}

function createEvidenceItem(EvidenceCategory $category, string $suffix): EvidenceItem
{
    return EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'ITEM-HIST-' . $suffix . '-' . Str::upper(Str::random(4)),
        'description' => 'Item de prueba historica ' . $suffix,
        'requires_subject' => true,
        'active' => true,
    ]);
}

it('fails audit when historical inconsistencies are found', function () {
    $ctx = createHistoricalAuditBase();
    $itemA = createEvidenceItem($ctx['category'], 'A');
    $itemB = createEvidenceItem($ctx['category'], 'B');

    $submission = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $itemA->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::APPROVED,
        'submitted_at' => now()->subDays(2),
        'last_updated_at' => now()->subDay(),
    ]);

    EvidenceStatusHistory::create([
        'submission_id' => $submission->id,
        'old_status' => SubmissionStatus::DRAFT,
        'new_status' => SubmissionStatus::APPROVED,
        'changed_by_user_id' => $ctx['office']->id,
        'change_reason' => 'Dato heredado invalido',
        'changed_at' => now()->subDay(),
    ]);

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $itemA->id,
        'opens_at' => now()->addDay(),
        'closes_at' => now()->addDays(5),
        'created_by_user_id' => $ctx['office']->id,
        'status' => 'ACTIVE',
    ]);

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $itemA->id,
        'opens_at' => now()->addDays(3),
        'closes_at' => now()->addDays(7),
        'created_by_user_id' => $ctx['office']->id,
        'status' => 'ACTIVE',
    ]);

    NotificationSchedule::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $itemB->id,
        'notify_at' => now()->subHour(),
        'notification_type' => 'WINDOW_OPEN',
        'is_sent' => false,
    ]);

    StorageRoot::create([
        'name' => 'ROOT-HIST-' . Str::upper(Str::random(4)),
        'base_path' => storage_path('app/private'),
        'is_active' => true,
    ]);

    $this->artisan('asad:audit-historical-data')
        ->expectsOutputToContain('Transiciones invalidas de historial')
        ->expectsOutputToContain('Ventanas activas solapadas')
        ->expectsOutputToContain('APPROVED/REJECTED sin revision')
        ->assertExitCode(1);
});

it('applies safe fixes with --fix and leaves data consistent', function () {
    Storage::fake('local');

    $ctx = createHistoricalAuditBase();
    $itemSubmitted = createEvidenceItem($ctx['category'], 'SUB');
    $itemApproved = createEvidenceItem($ctx['category'], 'APR');
    $itemMismatch = createEvidenceItem($ctx['category'], 'MIS');
    $itemOrphanSchedule = createEvidenceItem($ctx['category'], 'ORP');

    $submitted = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $itemSubmitted->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => null,
        'last_updated_at' => now()->subHours(3),
    ]);

    EvidenceStatusHistory::create([
        'submission_id' => $submitted->id,
        'old_status' => SubmissionStatus::DRAFT,
        'new_status' => SubmissionStatus::SUBMITTED,
        'changed_by_user_id' => $ctx['teacher']->id,
        'change_reason' => 'Envio inicial',
        'changed_at' => now()->subHours(4),
    ]);

    $approved = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $itemApproved->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::APPROVED,
        'submitted_at' => now()->subDays(2),
        'last_updated_at' => now()->subDay(),
    ]);

    EvidenceStatusHistory::create([
        'submission_id' => $approved->id,
        'old_status' => SubmissionStatus::DRAFT,
        'new_status' => SubmissionStatus::SUBMITTED,
        'changed_by_user_id' => $ctx['teacher']->id,
        'change_reason' => 'Envio inicial',
        'changed_at' => now()->subDays(2),
    ]);

    EvidenceStatusHistory::create([
        'submission_id' => $approved->id,
        'old_status' => SubmissionStatus::SUBMITTED,
        'new_status' => SubmissionStatus::APPROVED,
        'changed_by_user_id' => $ctx['office']->id,
        'change_reason' => 'Revision aprobatoria',
        'changed_at' => now()->subDay(),
    ]);

    $mismatch = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $itemMismatch->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::APPROVED,
        'submitted_at' => now()->subDays(2),
        'last_updated_at' => now()->subHours(5),
    ]);

    EvidenceStatusHistory::create([
        'submission_id' => $mismatch->id,
        'old_status' => SubmissionStatus::DRAFT,
        'new_status' => SubmissionStatus::SUBMITTED,
        'changed_by_user_id' => $ctx['teacher']->id,
        'change_reason' => 'Envio inicial',
        'changed_at' => now()->subDays(2),
    ]);

    $windowA = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $itemSubmitted->id,
        'opens_at' => now()->addDay(),
        'closes_at' => now()->addDays(6),
        'created_by_user_id' => $ctx['office']->id,
        'status' => 'ACTIVE',
    ]);

    $windowB = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $itemSubmitted->id,
        'opens_at' => now()->addDays(3),
        'closes_at' => now()->addDays(8),
        'created_by_user_id' => $ctx['office']->id,
        'status' => 'ACTIVE',
    ]);

    $orphanSchedule = NotificationSchedule::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $itemOrphanSchedule->id,
        'notify_at' => now()->subHour(),
        'notification_type' => 'WINDOW_OPEN',
        'is_sent' => false,
    ]);

    $duplicateNotifyAt = now()->addHours(6);
    $scheduleKeep = NotificationSchedule::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $itemSubmitted->id,
        'notify_at' => $duplicateNotifyAt,
        'notification_type' => 'WINDOW_CLOSING',
        'is_sent' => false,
    ]);

    $scheduleDuplicate = NotificationSchedule::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $itemSubmitted->id,
        'notify_at' => $duplicateNotifyAt,
        'notification_type' => 'WINDOW_CLOSING',
        'is_sent' => false,
    ]);

    $root = StorageRoot::create([
        'name' => 'ROOT-HIST-' . Str::upper(Str::random(4)),
        'base_path' => storage_path('app/private'),
        'is_active' => true,
    ]);

    $folder = \App\Models\FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Carpeta Evidencia',
        'relative_path' => 'sem_' . $ctx['semester']->id . '/docente_' . $ctx['teacher']->id,
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => null,
    ]);

    $originalPath = 'fuera_de_carpeta/prueba.pdf';
    Storage::disk('local')->put($originalPath, 'contenido prueba');

    $file = EvidenceFile::create([
        'submission_id' => $submitted->id,
        'folder_node_id' => $folder->id,
        'file_name' => 'prueba.pdf',
        'stored_relative_path' => $originalPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 1200,
        'file_hash' => hash('sha256', 'contenido prueba'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this->artisan('asad:audit-historical-data --fix')
        ->expectsOutputToContain('Hallazgos posteriores al saneamiento')
        ->assertExitCode(0);

    expect($submitted->fresh()->submitted_at)->not->toBeNull();

    $this->assertDatabaseHas('evidence_reviews', [
        'submission_id' => $approved->id,
        'decision' => 'APPROVE',
    ]);

    $this->assertDatabaseHas('evidence_status_history', [
        'submission_id' => $mismatch->id,
        'old_status' => 'SUBMITTED',
        'new_status' => 'APPROVED',
    ]);

    expect($windowA->fresh()->status->value)->toBe('ACTIVE');
    expect($windowB->fresh()->status->value)->toBe('INACTIVE');

    expect($orphanSchedule->fresh()->is_sent)->toBeTrue();
    expect($scheduleKeep->fresh()->is_sent)->toBeFalse();
    expect($scheduleDuplicate->fresh()->is_sent)->toBeTrue();

    $updatedFile = $file->fresh();
    expect($updatedFile->stored_relative_path)->toBe($folder->relative_path . '/prueba.pdf');
    expect(Storage::disk('local')->exists($updatedFile->stored_relative_path))->toBeTrue();
    expect(Storage::disk('local')->exists($originalPath))->toBeFalse();
});
