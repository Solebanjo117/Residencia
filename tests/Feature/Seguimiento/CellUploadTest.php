<?php

use App\Enums\SubmissionStatus;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createSeguimientoCellUploadContext(): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $department = Department::create(['name' => 'DEP CELL '.Str::upper(Str::random(6))]);
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM CELL '.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'CELL-'.Str::upper(Str::random(6)),
        'name' => 'SISTEMAS DE INFORMACION '.Str::upper(Str::random(4)),
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
        'name' => 'ASESORIAS '.Str::upper(Str::random(4)),
        'description' => 'Evidencia de asesorias',
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

    return compact('teacher', 'semester', 'load', 'item', 'subject');
}

function readableFolderUrlForSeguimientoCellTest($folder): string
{
    $segments = [];
    $current = $folder;

    while ($current) {
        array_unshift($segments, rawurlencode($current->name));
        $current->loadMissing('parent');
        $current = $current->parent;
    }

    return '/files/folders/'.implode('/', $segments);
}

it('lets a teacher upload a seguimiento cell file and exposes it in file manager folders', function () {
    Storage::fake('local');
    $ctx = createSeguimientoCellUploadContext();

    $this
        ->from(route('asesorias', ['semester' => $ctx['semester']->name]))
        ->actingAs($ctx['teacher'])
        ->post(route('asesorias.cells.upload'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'file' => UploadedFile::fake()->create('asesoria.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('asesorias', ['semester' => $ctx['semester']->name]));

    $submission = EvidenceSubmission::query()
        ->where('teaching_load_id', $ctx['load']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->firstOrFail();
    $file = EvidenceFile::where('submission_id', $submission->id)->firstOrFail();

    expect($submission->status)->toBe(SubmissionStatus::DRAFT)
        ->and($submission->manual_ui_status)->toBeNull()
        ->and($submission->submitted_late)->toBeFalse()
        ->and($file->folderNode->name)->toBe('ASESORIAS');

    $this->assertDatabaseHas('folder_nodes', [
        'id' => $file->folder_node_id,
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('folders.show', $file->folder_node_id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contents.files', 1)
            ->where('contents.files.0.id', $file->id)
        );

    $teacherFolder = $file->folderNode->parent->parent;

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('folders.show', $teacherFolder->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contents.files', 1)
            ->where('contents.files.0.id', $file->id)
            ->where('contents.files.0.folder_path', $ctx['subject']->name.' / ASESORIAS')
        );

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('asesorias', ['semester' => $ctx['semester']->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.files.0.file_url', route('files.download', $file->id, false))
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.files.0.folder_url', readableFolderUrlForSeguimientoCellTest($file->folderNode))
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.files.0.submitted_at', $file->uploaded_at?->toDateTimeString())
        );
});

it('marks open seguimiento cells as uploadable for their teacher', function () {
    $ctx = createSeguimientoCellUploadContext();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('asesorias', ['semester' => $ctx['semester']->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.status', 'NE')
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.can_upload', true)
        );
});

it('notifies office and department heads when a teacher uploads from seguimiento', function () {
    Storage::fake('local');
    $ctx = createSeguimientoCellUploadContext();
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $departmentRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $officeUser = User::factory()->create(['role_id' => $officeRoleId, 'is_active' => true]);
    $departmentUser = User::factory()->create(['role_id' => $departmentRoleId, 'is_active' => true]);

    $this
        ->from(route('asesorias', ['semester' => $ctx['semester']->name]))
        ->actingAs($ctx['teacher'])
        ->post(route('asesorias.cells.upload'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'file' => UploadedFile::fake()->create('seguimiento.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('asesorias', ['semester' => $ctx['semester']->name]));

    $submission = EvidenceSubmission::query()
        ->where('teaching_load_id', $ctx['load']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->firstOrFail();
    $file = EvidenceFile::where('submission_id', $submission->id)->firstOrFail();

    foreach ([$officeUser, $departmentUser] as $recipient) {
        $notification = Notification::query()
            ->where('user_id', $recipient->id)
            ->where('related_entity_type', EvidenceFile::class)
            ->where('related_entity_id', $file->id)
            ->first();

        expect($notification)->not->toBeNull()
            ->and($notification->is_read)->toBeFalse()
            ->and($notification->title)->toBe('Archivo subido para revision');

        $this
            ->actingAs($recipient)
            ->getJson(route('notifications.unread'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('notifications.0.action_url', route('asesorias', [
                'semester' => $ctx['semester']->name,
                'submission_id' => $submission->id,
                'teaching_load_id' => $ctx['load']->id,
                'evidence_item_id' => $ctx['item']->id,
                'focus_file_id' => $file->id,
            ], false))
            ->assertJsonPath('notifications.0.action_label', 'Revisar entrega');
    }
});

it('exposes correction actions for rejected seguimiento files', function () {
    Storage::fake('local');
    $ctx = createSeguimientoCellUploadContext();

    $this
        ->from(route('asesorias', ['semester' => $ctx['semester']->name]))
        ->actingAs($ctx['teacher'])
        ->post(route('asesorias.cells.upload'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'file' => UploadedFile::fake()->create(
                'correccion.docx',
                200,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
        ])
        ->assertRedirect(route('asesorias', ['semester' => $ctx['semester']->name]));

    $submission = EvidenceSubmission::query()
        ->where('teaching_load_id', $ctx['load']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->firstOrFail();
    $submission->update(['status' => SubmissionStatus::REJECTED]);

    $file = EvidenceFile::where('submission_id', $submission->id)->firstOrFail();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('asesorias', [
            'semester' => $ctx['semester']->name,
            'submission_id' => $submission->id,
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.status', 'R')
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.can_upload', true)
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.files.0.is_docx', true)
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.files.0.docx_editor_url', route('files.docx.show', $file->id, false))
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.files.0.can_edit_docx', true)
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.files.0.can_replace', true)
            ->where('rows.0.cells.item_'.$ctx['item']->id.'.files.0.folder_url', readableFolderUrlForSeguimientoCellTest($file->folderNode))
        );
});

it('does not let a different teacher upload into another seguimiento row', function () {
    Storage::fake('local');
    $ctx = createSeguimientoCellUploadContext();
    $otherTeacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $otherTeacher = User::factory()->create(['role_id' => $otherTeacherRoleId]);

    $this
        ->actingAs($otherTeacher)
        ->post(route('asesorias.cells.upload'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'file' => UploadedFile::fake()->create('intruso.pdf', 200, 'application/pdf'),
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('evidence_files', 0);
});

it('does not create a draft submission when a seguimiento cell is not available', function () {
    Storage::fake('local');
    $ctx = createSeguimientoCellUploadContext();

    SubmissionWindow::where('semester_id', $ctx['semester']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->update([
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(2),
        ]);

    $this
        ->from(route('asesorias', ['semester' => $ctx['semester']->name]))
        ->actingAs($ctx['teacher'])
        ->post(route('asesorias.cells.upload'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'file' => UploadedFile::fake()->create('bloqueado.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('asesorias', ['semester' => $ctx['semester']->name]))
        ->assertSessionHasErrors('file');

    $this->assertDatabaseMissing('evidence_submissions', [
        'teaching_load_id' => $ctx['load']->id,
        'evidence_item_id' => $ctx['item']->id,
    ]);
    $this->assertDatabaseCount('evidence_files', 0);
});

it('keeps a late flag when a seguimiento cell file is uploaded after the regular window', function () {
    Storage::fake('local');
    $ctx = createSeguimientoCellUploadContext();

    SubmissionWindow::where('semester_id', $ctx['semester']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->update([
            'opens_at' => now()->subDays(3),
            'closes_at' => now()->subDay(),
        ]);

    $this
        ->from(route('asesorias', ['semester' => $ctx['semester']->name]))
        ->actingAs($ctx['teacher'])
        ->post(route('asesorias.cells.upload'), [
            'teaching_load_id' => $ctx['load']->id,
            'evidence_item_id' => $ctx['item']->id,
            'file' => UploadedFile::fake()->create('tarde.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('asesorias', ['semester' => $ctx['semester']->name]));

    $submission = EvidenceSubmission::query()
        ->where('teaching_load_id', $ctx['load']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->firstOrFail();

    expect($submission->submitted_late)->toBeTrue();
});

it('reuses one uploaded file across seg 01 through seg 04 cells', function () {
    Storage::fake('local');

    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $department = Department::create(['name' => 'DEP SEG '.Str::upper(Str::random(6))]);
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM SEG '.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'SEG-'.Str::upper(Str::random(6)),
        'name' => 'SEGUIMIENTO COMPARTIDO '.Str::upper(Str::random(4)),
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $items = collect(['SEG 01', 'SEG 02', 'SEG 03', 'SEG 04 FINAL'])->mapWithKeys(function (string $name) use ($categoryId, $semester, $department, $teacher) {
        $item = EvidenceItem::create([
            'category_id' => $categoryId,
            'name' => $name,
            'description' => 'Seguimiento compartido '.$name,
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

        return [$name => $item];
    });

    $this
        ->from(route('asesorias', ['semester' => $semester->name]))
        ->actingAs($teacher)
        ->post(route('asesorias.cells.upload'), [
            'teaching_load_id' => $load->id,
            'evidence_item_id' => $items['SEG 01']->id,
            'file' => UploadedFile::fake()->create('seg-01.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('asesorias', ['semester' => $semester->name]));

    $firstFile = EvidenceFile::firstOrFail();
    EvidenceSubmission::query()
        ->where('evidence_item_id', $items['SEG 01']->id)
        ->where('teaching_load_id', $load->id)
        ->update([
            'status' => SubmissionStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

    $this
        ->from(route('asesorias', ['semester' => $semester->name]))
        ->actingAs($teacher)
        ->post(route('asesorias.cells.upload'), [
            'teaching_load_id' => $load->id,
            'evidence_item_id' => $items['SEG 02']->id,
            'file' => UploadedFile::fake()->create('seg-02.pdf', 240, 'application/pdf'),
        ])
        ->assertRedirect(route('asesorias', ['semester' => $semester->name]));

    expect(EvidenceFile::count())->toBe(1);

    $updatedFile = $firstFile->fresh();
    expect($updatedFile->file_name)->toBe('seg-02.pdf')
        ->and($updatedFile->id)->toBe($firstFile->id);

    $this
        ->actingAs($teacher)
        ->get(route('asesorias', ['semester' => $semester->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.cells.item_'.$items['SEG 01']->id.'.files.0.id', $updatedFile->id)
            ->where('rows.0.cells.item_'.$items['SEG 01']->id.'.files.0.linked_from', null)
            ->where('rows.0.cells.item_'.$items['SEG 02']->id.'.files.0.id', $updatedFile->id)
            ->where('rows.0.cells.item_'.$items['SEG 02']->id.'.files.0.linked_from', 'SEG 01')
            ->where('rows.0.cells.item_'.$items['SEG 04 FINAL']->id.'.files.0.id', $updatedFile->id)
            ->where('rows.0.cells.item_'.$items['SEG 04 FINAL']->id.'.files.0.linked_from', 'SEG 01')
        );
});
