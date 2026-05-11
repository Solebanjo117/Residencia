<?php

use App\Enums\SubmissionStatus;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
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
