<?php

use App\Enums\SubmissionStatus;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createTeacherFileContext(): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $department = Department::create([
        'name' => 'Dept-FILE-'.Str::upper(Str::random(4)),
    ]);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM-FILE-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-FILE-'.Str::upper(Str::random(6)),
        'name' => 'Materia File '.Str::upper(Str::random(4)),
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
        'name' => 'ITEM-FILE-'.Str::upper(Str::random(8)),
        'description' => 'Item file action test',
        'requires_subject' => true,
        'active' => true,
    ]);

    EvidenceRequirement::create([
        'semester_id' => $semester->id,
        'department_id' => $department->id,
        'evidence_item_id' => $item->id,
        'is_mandatory' => true,
    ]);

    SubmissionWindow::create([
        'semester_id' => $semester->id,
        'evidence_item_id' => $item->id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDay(),
        'created_by_user_id' => $teacher->id,
        'status' => 'ACTIVE',
    ]);

    $root = StorageRoot::create([
        'name' => 'root-file-'.Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $folder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Folder FILE',
        'relative_path' => 'sem_'.$semester->id.'/docente_'.$teacher->id.'/item_'.$item->id,
        'owner_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'parent_id' => null,
    ]);

    return compact('teacher', 'department', 'semester', 'load', 'item', 'folder', 'root');
}

it('includes docx metadata in file data for teacher evidence index', function () {
    $ctx = createTeacherFileContext();

    $submission = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::DRAFT,
        'last_updated_at' => now(),
    ]);

    $docxFile = EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'documento.docx',
        'stored_relative_path' => 'tmp/documento.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 5000,
        'file_hash' => str_repeat('a', 64),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $pdfFile = EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'evidencia.pdf',
        'stored_relative_path' => 'tmp/evidencia.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 3000,
        'file_hash' => str_repeat('b', 64),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get('/docente/evidencias');

    $response->assertOk();

    $tasks = $response->inertiaPage()['props']['tasks'];
    $taskWithFiles = collect($tasks)->first(fn ($t) => count($t['submission']['files']) > 0);

    expect($taskWithFiles)->not->toBeNull();

    $docxEntry = collect($taskWithFiles['submission']['files'])
        ->first(fn ($f) => $f['id'] === $docxFile->id);
    $pdfEntry = collect($taskWithFiles['submission']['files'])
        ->first(fn ($f) => $f['id'] === $pdfFile->id);

    expect($docxEntry['mime_type'])->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect($docxEntry['is_docx'])->toBeTrue();
    expect($docxEntry['editor_url'])->not->toBeNull();
    expect($docxEntry['can_edit_docx'])->toBeTrue();
    expect($docxEntry['can_delete'])->toBeTrue();

    expect($pdfEntry['mime_type'])->toBe('application/pdf');
    expect($pdfEntry['is_docx'])->toBeFalse();
    expect($pdfEntry['editor_url'])->toBeNull();
    expect($pdfEntry['can_edit_docx'])->toBeFalse();
    expect($pdfEntry['can_delete'])->toBeTrue();
});

it('allows deleting a file in DRAFT status and updates last_updated_at', function () {
    Storage::fake('local');

    $ctx = createTeacherFileContext();

    $submission = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::DRAFT,
        'last_updated_at' => now()->subHour(),
    ]);

    $storedPath = $ctx['folder']->relative_path.'/borrador.pdf';
    Storage::disk('local')->put($storedPath, '%PDF-1.4 draft');

    $file = EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'borrador.pdf',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 2000,
        'file_hash' => hash('sha256', '%PDF-1.4 draft'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $previousUpdatedAt = $submission->last_updated_at->toDateTimeString();

    $response = $this
        ->actingAs($ctx['teacher'])
        ->from('/docente/evidencias')
        ->delete(route('files.destroy', $file->id));

    $response->assertRedirect();

    expect(EvidenceFile::withTrashed()->find($file->id))->not->toBeNull();
    expect(EvidenceFile::find($file->id))->toBeNull();

    $freshSubmission = $submission->fresh();
    expect($freshSubmission->last_updated_at->toDateTimeString())->not->toBe($previousUpdatedAt);
});

it('allows deleting a DOCX file in SUBMITTED pending state', function () {
    Storage::fake('local');

    $ctx = createTeacherFileContext();

    $submission = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'last_updated_at' => now()->subHour(),
    ]);

    $storedPath = $ctx['folder']->relative_path.'/pendiente.docx';
    Storage::disk('local')->put($storedPath, 'PK-pending-docx');

    $file = EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'pendiente.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 4000,
        'file_hash' => hash('sha256', 'PK-pending-docx'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->from('/docente/evidencias')
        ->delete(route('files.destroy', $file->id));

    $response->assertRedirect();

    $freshSubmission = $submission->fresh();
    expect($freshSubmission->last_updated_at->toDateTimeString())->not->toBe($submission->last_updated_at->toDateTimeString());
});

it('marks can_edit_docx and can_delete as true for DOCX in SUBMITTED pending state', function () {
    $ctx = createTeacherFileContext();

    $submission = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'last_updated_at' => now(),
    ]);

    EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'pendiente.docx',
        'stored_relative_path' => 'tmp/pendiente.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 4000,
        'file_hash' => str_repeat('a', 64),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get('/docente/evidencias');

    $response->assertOk();

    $tasks = $response->inertiaPage()['props']['tasks'];
    $taskWithFiles = collect($tasks)->first(fn ($t) => count($t['submission']['files']) > 0);

    expect($taskWithFiles)->not->toBeNull();

    $fileEntry = $taskWithFiles['submission']['files'][0];
    expect($fileEntry['can_edit_docx'])->toBeTrue();
    expect($fileEntry['can_delete'])->toBeTrue();
});

it('marks can_edit_docx and can_delete as false when SUBMITTED with office_reviewed_at', function () {
    $ctx = createTeacherFileContext();

    $submission = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'office_reviewed_at' => now(),
        'last_updated_at' => now(),
    ]);

    EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'aprobado_oficina.docx',
        'stored_relative_path' => 'tmp/aprobado_oficina.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 4000,
        'file_hash' => str_repeat('b', 64),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get('/docente/evidencias');

    $response->assertOk();

    $tasks = $response->inertiaPage()['props']['tasks'];
    $taskWithFiles = collect($tasks)->first(fn ($t) => count($t['submission']['files']) > 0);

    expect($taskWithFiles)->not->toBeNull();

    $fileEntry = $taskWithFiles['submission']['files'][0];
    expect($fileEntry['can_edit_docx'])->toBeFalse();
    expect($fileEntry['can_delete'])->toBeFalse();
});

it('blocks file deletion when SUBMITTED with office_reviewed_at set', function () {
    Storage::fake('local');

    $ctx = createTeacherFileContext();

    $submission = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'office_reviewed_at' => now(),
        'last_updated_at' => now(),
    ]);

    $storedPath = $ctx['folder']->relative_path.'/aprobado.pdf';
    Storage::disk('local')->put($storedPath, '%PDF-1.4 office-approved');

    $file = EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'aprobado.pdf',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 2000,
        'file_hash' => hash('sha256', '%PDF-1.4 office-approved'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->from('/docente/evidencias')
        ->delete(route('files.destroy', $file->id));

    $response->assertForbidden();
    expect(EvidenceFile::find($file->id))->not->toBeNull();
});

it('shows can_upload as true for SUBMITTED pending submission', function () {
    $ctx = createTeacherFileContext();

    $submission = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::SUBMITTED,
        'submitted_at' => now(),
        'last_updated_at' => now(),
    ]);

    EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'pendiente.pdf',
        'stored_relative_path' => 'tmp/pendiente.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1000,
        'file_hash' => str_repeat('c', 64),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get('/docente/evidencias');

    $response->assertOk();

    $tasks = $response->inertiaPage()['props']['tasks'];
    $task = collect($tasks)->first(fn ($t) => $t['submission']['status'] === 'SUBMITTED');

    expect($task)->not->toBeNull();
    expect($task['can_upload'])->toBeTrue();
    expect($task['can_submit'])->toBeFalse();
});

it('allows file deletion when submission has active resubmission unlock', function () {
    Storage::fake('local');

    $ctx = createTeacherFileContext();

    $submission = EvidenceSubmission::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'evidence_item_id' => $ctx['item']->id,
        'teaching_load_id' => $ctx['load']->id,
        'status' => SubmissionStatus::REJECTED,
        'last_updated_at' => now(),
    ]);

    ResubmissionUnlock::create([
        'submission_id' => $submission->id,
        'unlocked_by_user_id' => $ctx['teacher']->id,
        'unlocked_at' => now(),
        'expires_at' => now()->addDays(3),
        'reason' => 'Corrección requerida',
    ]);

    $storedPath = $ctx['folder']->relative_path.'/correccion.docx';
    Storage::disk('local')->put($storedPath, 'PK-docx-content');

    $file = EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'correccion.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 4000,
        'file_hash' => hash('sha256', 'PK-docx-content'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->from('/docente/evidencias')
        ->delete(route('files.destroy', $file->id));

    $response->assertRedirect();
    expect(EvidenceFile::find($file->id))->toBeNull();
});

it('filters tasks by semester_id query parameter', function () {
    $ctx = createTeacherFileContext();

    $otherSemester = Semester::create([
        'name' => 'SEM-OTHER-'.Str::upper(Str::random(4)),
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->subMonths(6)->toDateString(),
        'status' => 'CLOSED',
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get('/docente/evidencias?semester_id='.$ctx['semester']->id);

    $response->assertOk();
    $tasks = $response->inertiaPage()['props']['tasks'];
    expect(count($tasks))->toBeGreaterThanOrEqual(1);

    $props = $response->inertiaPage()['props'];
    expect($props['selectedSemesterId'])->toBe($ctx['semester']->id);
});

it('prefers the open semester even if a newer closed semester exists', function () {
    $ctx = createTeacherFileContext();

    $closedSemester = Semester::create([
        'name' => 'SEM-CLOSED-'.Str::upper(Str::random(4)),
        'start_date' => now()->addMonths(2)->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
        'status' => 'CLOSED',
    ]);

    $subject = Subject::create([
        'code' => 'SUBJ-CLOSED-'.Str::upper(Str::random(5)),
        'name' => 'Materia Cerrada '.Str::upper(Str::random(3)),
    ]);

    TeachingLoad::create([
        'teacher_user_id' => $ctx['teacher']->id,
        'semester_id' => $closedSemester->id,
        'subject_id' => $subject->id,
        'group_code' => 'B',
        'hours_per_week' => 4,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get('/docente/evidencias');

    $response->assertOk();

    $props = $response->inertiaPage()['props'];
    expect($props['selectedSemesterId'])->toBe($ctx['semester']->id);
});

it('filters tasks by teaching_load_id query parameter', function () {
    $ctx = createTeacherFileContext();

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get('/docente/evidencias?semester_id='.$ctx['semester']->id.'&teaching_load_id='.$ctx['load']->id);

    $response->assertOk();

    $tasks = $response->inertiaPage()['props']['tasks'];
    expect(count($tasks))->toBeGreaterThanOrEqual(1);

    foreach ($tasks as $task) {
        expect($task['teaching_load']['id'])->toBe($ctx['load']->id);
    }
});
