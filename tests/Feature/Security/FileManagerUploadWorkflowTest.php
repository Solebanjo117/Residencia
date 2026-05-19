<?php

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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createFileManagerContext(bool $windowOpen = true, bool $createWindow = true): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-FM-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-FM-'.Str::upper(Str::random(6)),
        'name' => 'Materia FM '.Str::upper(Str::random(4)),
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
        'name' => 'ITEM-FM-'.Str::upper(Str::random(8)),
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
        'name' => 'root-fm-'.Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $folder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Evidencias',
        'relative_path' => 'sem_'.$semester->id.'/docente_'.$teacher->id.'/'.Str::lower(Str::random(8)),
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

it('notifies office and department when a teacher uploads from file manager', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $departmentRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $officeUser = User::factory()->create(['role_id' => $officeRoleId]);
    $departmentUser = User::factory()->create(['role_id' => $departmentRoleId]);

    $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('aviso.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect('/files/manager');

    $file = EvidenceFile::where('submission_id', $ctx['submission']->id)->firstOrFail();

    foreach ([$officeUser, $departmentUser] as $recipient) {
        $notification = Notification::query()
            ->where('user_id', $recipient->id)
            ->where('related_entity_type', EvidenceFile::class)
            ->where('related_entity_id', $file->id)
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->message)->toContain($ctx['teacher']->name);
    }
});

it('opens the active teacher folder by default in file manager', function () {
    $ctx = createFileManagerContext(windowOpen: true);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('folders.index'))
        ->assertRedirect(route('folders.show', $ctx['folder']->id));
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

it('allows docente to add files from file manager without changing an approved submission', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);
    $ctx['submission']->update([
        'status' => SubmissionStatus::APPROVED,
        'submitted_at' => now()->subDay(),
        'office_reviewed_at' => now()->subHours(3),
        'office_reviewed_by_user_id' => $ctx['teacher']->id,
        'final_approved_at' => now()->subHour(),
        'final_approved_by_user_id' => $ctx['teacher']->id,
    ]);

    $response = $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $ctx['folder']->id), [
            'file' => UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf'),
        ]);

    $response->assertRedirect('/files/manager');

    $ctx['submission']->refresh();
    expect($ctx['submission']->status)->toBe(SubmissionStatus::APPROVED);
    expect($ctx['submission']->final_approved_at)->not->toBeNull();
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
    $maliciousFile = new class($tempPath) extends UploadedFile
    {
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

    $storedPath = $ctx['folder']->relative_path.'/archivo.pdf';
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

    $storedPath = $ctx['folder']->relative_path.'/archivo.pdf';
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

it('shows sd2 advance files inside the matching sd4 advance folder so they can be reused and edited', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);

    $parent = FolderNode::create([
        'storage_root_id' => $ctx['folder']->storage_root_id,
        'name' => '4.1-CAPACITACION',
        'relative_path' => $ctx['folder']->relative_path.'/4.1-CAPACITACION',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $ctx['folder']->id,
    ]);

    $sd2Folder = FolderNode::create([
        'storage_root_id' => $ctx['folder']->storage_root_id,
        'name' => 'SD2-AVANCE-50%',
        'relative_path' => $parent->relative_path.'/SD2-AVANCE-50%',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $parent->id,
    ]);

    $sd4Folder = FolderNode::create([
        'storage_root_id' => $ctx['folder']->storage_root_id,
        'name' => 'SD4-AVANCE-100%',
        'relative_path' => $parent->relative_path.'/SD4-AVANCE-100%',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $parent->id,
    ]);

    $storedPath = $sd2Folder->relative_path.'/avance.docx';
    Storage::disk('local')->put($storedPath, 'docx-content');

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $sd2Folder->id,
        'file_name' => 'avance.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 120,
        'file_hash' => hash('sha256', 'docx-content'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('folders.show', $sd4Folder->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FileManager/Index')
            ->where('contents.files.0.id', $file->id)
            ->where('contents.files.0.linked_from', 'SD2-AVANCE-50%')
            ->where('contents.files.0.docx_editor_url', route('files.docx.show', $file->id))
            ->where('contents.files.0.can_edit_docx', true)
        );
});

it('links sd2 and sd4 project folders to their matching seguimiento evidence items', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true, createWindow: false);
    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $seg2 = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'SEG 02',
        'description' => 'Segundo seguimiento',
        'requires_subject' => true,
        'active' => true,
    ]);
    $seg4 = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'SEG 04 FINAL',
        'description' => 'Cuarto seguimiento final',
        'requires_subject' => true,
        'active' => true,
    ]);

    foreach ([$seg2, $seg4] as $item) {
        SubmissionWindow::create([
            'semester_id' => $ctx['semester']->id,
            'evidence_item_id' => $item->id,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'created_by_user_id' => $ctx['teacher']->id,
            'status' => 'ACTIVE',
        ]);
    }

    $parent = FolderNode::create([
        'storage_root_id' => $ctx['folder']->storage_root_id,
        'name' => '4.1-CAPACITACION',
        'relative_path' => $ctx['folder']->relative_path.'/4.1-CAPACITACION',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $ctx['folder']->id,
    ]);

    $sd2Folder = FolderNode::create([
        'storage_root_id' => $ctx['folder']->storage_root_id,
        'name' => 'SD2-AVANCE-50%',
        'relative_path' => $parent->relative_path.'/SD2-AVANCE-50%',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $parent->id,
    ]);
    $sd4Folder = FolderNode::create([
        'storage_root_id' => $ctx['folder']->storage_root_id,
        'name' => 'SD4-AVANCE-100%',
        'relative_path' => $parent->relative_path.'/SD4-AVANCE-100%',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $parent->id,
    ]);

    $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $sd2Folder->id), [
            'file' => UploadedFile::fake()->create('avance-50.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect('/files/manager');

    $this
        ->from('/files/manager')
        ->actingAs($ctx['teacher'])
        ->post(route('files.store', $sd4Folder->id), [
            'file' => UploadedFile::fake()->create('avance-100.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect('/files/manager');

    $this->assertDatabaseHas('evidence_submissions', [
        'teacher_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $seg2->id,
    ]);
    $this->assertDatabaseHas('evidence_submissions', [
        'teacher_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $seg4->id,
    ]);
});

it('forbids docente from previewing a file outside their own folder', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);
    $otherTeacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $otherTeacher = User::factory()->create(['role_id' => $otherTeacherRoleId]);

    $storedPath = $ctx['folder']->relative_path.'/archivo.pdf';
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

it('forbids teacher from deleting files when submission has been reviewed by office', function () {
    Storage::fake('local');

    $ctx = createFileManagerContext(windowOpen: true);
    $ctx['submission']->update([
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'office_reviewed_at' => now(),
    ]);

    $storedPath = $ctx['folder']->relative_path.'/archivo.pdf';
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
