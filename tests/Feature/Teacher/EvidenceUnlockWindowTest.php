<?php

use App\Enums\SubmissionStatus;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\ResubmissionUnlock;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\Subject;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createTeacherEvidenceContext(): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-UNL-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-UNL-' . Str::upper(Str::random(6)),
        'name' => 'Materia UNL ' . Str::upper(Str::random(4)),
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
        'name' => 'ITEM-UNL-' . Str::upper(Str::random(8)),
        'description' => 'Item unlock test',
        'requires_subject' => true,
        'active' => true,
    ]);

    $submission = EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $item->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::DRAFT,
        'last_updated_at' => now(),
    ]);

    $root = StorageRoot::create([
        'name' => 'root-unl-' . Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $folder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Folder UNL',
        'relative_path' => 'sem_' . $semester->id . '/docente_' . $teacher->id . '/item_' . $item->id,
        'owner_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'parent_id' => null,
    ]);

    return compact('teacher', 'submission', 'folder');
}

it('allows submitting evidence when unlock has no expiration date', function () {
    $ctx = createTeacherEvidenceContext();

    EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'evidencia.pdf',
        'stored_relative_path' => 'tmp/evidencia.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1234,
        'file_hash' => str_repeat('a', 64),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    ResubmissionUnlock::create([
        'submission_id' => $ctx['submission']->id,
        'unlocked_by_user_id' => $ctx['teacher']->id,
        'unlocked_at' => now(),
        'expires_at' => null,
        'reason' => 'Prórroga sin fecha',
    ]);

    $response = $this
        ->from('/docente/evidencias')
        ->actingAs($ctx['teacher'])
        ->post(route('docente.evidencias.submit', $ctx['submission']->id));

    $response->assertRedirect('/docente/evidencias');
    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::SUBMITTED);
});

it('allows uploading supported image evidence when unlock has no expiration date and window is closed', function () {
    Storage::fake('local');

    $ctx = createTeacherEvidenceContext();

    ResubmissionUnlock::create([
        'submission_id' => $ctx['submission']->id,
        'unlocked_by_user_id' => $ctx['teacher']->id,
        'unlocked_at' => now(),
        'expires_at' => null,
        'reason' => 'Prórroga sin fecha',
    ]);

    $response = $this
        ->from('/docente/evidencias')
        ->actingAs($ctx['teacher'])
        ->post(route('docente.evidencias.upload', $ctx['submission']->id), [
            'file' => UploadedFile::fake()->create('evidencia.png', 200, 'image/png'),
        ]);

    $response->assertRedirect('/docente/evidencias');
    $this->assertDatabaseHas('evidence_files', [
        'submission_id' => $ctx['submission']->id,
    ]);
});

it('rejects unsupported extensions using the unified upload matrix', function () {
    Storage::fake('local');

    $ctx = createTeacherEvidenceContext();

    ResubmissionUnlock::create([
        'submission_id' => $ctx['submission']->id,
        'unlocked_by_user_id' => $ctx['teacher']->id,
        'unlocked_at' => now(),
        'expires_at' => null,
        'reason' => 'Prórroga sin fecha',
    ]);

    $response = $this
        ->from('/docente/evidencias')
        ->actingAs($ctx['teacher'])
        ->post(route('docente.evidencias.upload', $ctx['submission']->id), [
            'file' => UploadedFile::fake()->create('evidencia.zip', 200, 'application/zip'),
        ]);

    $response->assertRedirect('/docente/evidencias');
    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('evidence_files', 0);
});

it('marks submission as late when teacher submits after regular window closes', function () {
    $ctx = createTeacherEvidenceContext();

    SubmissionWindow::create([
        'semester_id' => $ctx['submission']->semester_id,
        'evidence_item_id' => $ctx['submission']->evidence_item_id,
        'opens_at' => now()->subDays(10),
        'closes_at' => now()->subDay(),
        'created_by_user_id' => $ctx['teacher']->id,
        'status' => 'ACTIVE',
    ]);

    EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'evidencia.pdf',
        'stored_relative_path' => 'tmp/evidencia.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1234,
        'file_hash' => str_repeat('b', 64),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $response = $this
        ->from('/docente/evidencias')
        ->actingAs($ctx['teacher'])
        ->post(route('docente.evidencias.submit', $ctx['submission']->id));

    $response->assertRedirect('/docente/evidencias');
    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::SUBMITTED);
    expect($ctx['submission']->fresh()->submitted_late)->toBeTrue();
});
