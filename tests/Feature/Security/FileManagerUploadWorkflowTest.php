<?php

use App\Enums\SubmissionStatus;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
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

function createFileManagerContext(bool $windowOpen = true, bool $createWindow = true): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-FM-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-FM-' . Str::upper(Str::random(6)),
        'name' => 'Materia FM ' . Str::upper(Str::random(4)),
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
        'name' => 'ITEM-FM-' . Str::upper(Str::random(8)),
        'description' => 'Item test file manager',
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
        'name' => 'root-fm-' . Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $folder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Evidencias',
        'relative_path' => 'sem_' . $semester->id . '/docente_' . $teacher->id . '/' . Str::lower(Str::random(8)),
        'owner_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'parent_id' => null,
    ]);

    if ($createWindow) {
        [$opensAt, $closesAt] = $windowOpen
            ? [now()->subDay(), now()->addDay()]
            : [now()->subDays(10), now()->subDays(5)];

        SubmissionWindow::create([
            'semester_id' => $semester->id,
            'evidence_item_id' => $item->id,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'created_by_user_id' => $teacher->id,
            'status' => 'ACTIVE',
        ]);
    }

    return compact('teacher', 'semester', 'item', 'submission', 'folder');
}

it('does not auto submit evidence when uploading from file manager', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);

    $response = $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf'),
        ]);

    $response->assertRedirect('/files/manager');
    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::DRAFT);
    $this->assertDatabaseHas('evidence_files', [
        'submission_id' => $ctx['submission']->id,
    ]);
    $this->assertDatabaseMissing('evidence_status_history', [
        'submission_id' => $ctx['submission']->id,
    ]);
});

it('allows file manager upload when regular window already closed and treats it as late workflow', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: false);

    $response = $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf'),
        ]);

    $response->assertRedirect('/files/manager');
    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::DRAFT);
    $this->assertDatabaseHas('evidence_files', [
        'submission_id' => $ctx['submission']->id,
    ]);
});

it('allows docente to upload files from file manager even when no submission window is configured', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true, createWindow: false);

    $response = $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf'),
        ]);

    $response->assertRedirect('/files/manager');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('evidence_files', [
        'submission_id' => $ctx['submission']->id,
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);
});

it('normalizes legacy approved submissions without review timestamps so docente can keep using file manager', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);
    $ctx['submission']->update([
        'status' => SubmissionStatus::APPROVED,
        'submitted_at' => null,
        'office_reviewed_at' => null,
        'office_reviewed_by_user_id' => null,
        'final_approved_at' => null,
        'final_approved_by_user_id' => null,
    ]);

    $response = $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf'),
        ]);

    $response->assertRedirect('/files/manager');

    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::DRAFT);
    $this->assertDatabaseHas('evidence_files', [
        'submission_id' => $ctx['submission']->id,
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);
});

it('allows jefe oficina to upload files on teacher submission via file manager', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    $response = $this
        ->from('/files/manager')
        ->actingAs($jefeOficina)
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf'),
        ]);

    $response->assertRedirect('/files/manager');
    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::DRAFT);
    $this->assertDatabaseHas('evidence_files', [
        'submission_id' => $ctx['submission']->id,
        'uploaded_by_user_id' => $jefeOficina->id,
    ]);
});

it('allows jefe depto to upload files on teacher submission via file manager', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);

    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);

    $response = $this
        ->from('/files/manager')
        ->actingAs($jefeDepto)
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf'),
        ]);

    $response->assertRedirect('/files/manager');
    expect($ctx['submission']->fresh()->status)->toBe(SubmissionStatus::DRAFT);
    $this->assertDatabaseHas('evidence_files', [
        'submission_id' => $ctx['submission']->id,
        'uploaded_by_user_id' => $jefeDepto->id,
    ]);
});

it('forbids docente from uploading files into another teacher folder', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);
    $otherTeacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $otherTeacher = User::factory()->create(['role_id' => $otherTeacherRoleId]);

    $response = $this
        ->actingAs($otherTeacher)
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf'),
        ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('evidence_files', 0);
});

it('rejects unsupported zip upload from file manager using unified matrix', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);

    $response = $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('evidencia.zip', 200, 'application/zip'),
        ]);

    $response->assertRedirect('/files/manager');
    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('evidence_files', 0);
});

it('rejects upload when mime does not match extension', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);

    $response = $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('falso.pdf', 10, 'text/plain'),
        ]);

    $response->assertRedirect('/files/manager');
    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('evidence_files', 0);
});

it('rejects upload when filename contains path traversal separators', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);

    $tempPath = tempnam(sys_get_temp_dir(), 'malicious-upload-');
    file_put_contents($tempPath, '%PDF-1.4 content');

    // Symfony strips path segments from original names by default, so we
    // override this getter to exercise the defensive validation in StorageService.
    $maliciousFile = new class($tempPath) extends UploadedFile {
        public function __construct(string $path)
        {
            parent::__construct($path, 'malicioso.pdf', 'application/pdf', null, true);
        }

        public function getClientOriginalName(): string
        {
            return '../malicioso.pdf';
        }
    };

    $response = $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => $maliciousFile,
        ]);

    $response->assertRedirect('/files/manager');
    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('evidence_files', 0);

    @unlink($tempPath);
});

it('forbids downloading file when stored path escapes its folder scope', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);

    Storage::disk('local')->put('fuera_scope/archivo.pdf', '%PDF-1.4 escaped');

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'archivo.pdf',
        'stored_relative_path' => 'fuera_scope/archivo.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 120,
        'file_hash' => hash('sha256', '%PDF-1.4 escaped'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('files.download', $file->id))
        ->assertForbidden();
});

it('logs audit entry on successful file download', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);

    $storedPath = $ctx['folder']->relative_path . '/archivo.pdf';
    Storage::disk('local')->put($storedPath, '%PDF-1.4 ok');

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'archivo.pdf',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 120,
        'file_hash' => hash('sha256', '%PDF-1.4 ok'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('files.download', $file->id))
        ->assertOk();

    $this->assertDatabaseHas('audit_log', [
        'user_id' => $ctx['teacher']->id,
        'action' => 'DOWNLOAD_FILE',
        'entity_type' => 'EvidenceFile',
        'entity_id' => $file->id,
    ]);
});

it('allows jefe depto to preview a pdf file inside the page', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);
    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);

    $storedPath = $ctx['folder']->relative_path . '/archivo.pdf';
    Storage::disk('local')->put($storedPath, '%PDF-1.4 preview');

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'archivo.pdf',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 120,
        'file_hash' => hash('sha256', '%PDF-1.4 preview'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($jefeDepto)
        ->get(route('files.preview', $file->id))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('forbids docente from previewing a file outside their own folder', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);
    $otherTeacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $otherTeacher = User::factory()->create(['role_id' => $otherTeacherRoleId]);

    $storedPath = $ctx['folder']->relative_path . '/archivo.pdf';
    Storage::disk('local')->put($storedPath, '%PDF-1.4 private');

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'archivo.pdf',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 120,
        'file_hash' => hash('sha256', '%PDF-1.4 private'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($otherTeacher)
        ->get(route('files.preview', $file->id))
        ->assertForbidden();
});

it('forbids teacher from deleting files when submission is already submitted', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);
    $ctx['submission']->update([
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
    ]);

    $storedPath = $ctx['folder']->relative_path . '/archivo.pdf';
    Storage::disk('local')->put($storedPath, '%PDF-1.4 submitted');

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'archivo.pdf',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 120,
        'file_hash' => hash('sha256', '%PDF-1.4 submitted'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->delete(route('files.destroy', $file->id))
        ->assertForbidden();
});
