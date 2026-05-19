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
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createFolderContext(): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-FC-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $root = StorageRoot::create([
        'name' => 'root-fc-'.Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $semesterFolder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => $semester->name,
        'relative_path' => 'sem_'.$semester->id,
        'owner_user_id' => null,
        'semester_id' => $semester->id,
        'parent_id' => null,
    ]);

    $teacherFolder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => $teacher->name,
        'relative_path' => $semesterFolder->relative_path.'/'.Str::slug($teacher->name),
        'owner_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'parent_id' => $semesterFolder->id,
    ]);

    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);

    $otherTeacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $otherTeacher = User::factory()->create(['role_id' => $otherTeacherRoleId]);

    return compact(
        'teacher', 'semester', 'root',
        'semesterFolder', 'teacherFolder',
        'jefeOficina', 'jefeDepto', 'otherTeacher'
    );
}

function createFileInFolder(FolderNode $folder, User $uploader, EvidenceSubmission $submission): EvidenceFile
{
    $storedPath = $folder->relative_path.'/'.Str::uuid().'.pdf';
    Storage::disk('local')->put($storedPath, '%PDF-1.4 test');

    return EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $folder->id,
        'file_name' => 'test.pdf',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
        'file_hash' => hash('sha256', '%PDF-1.4 test'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $uploader->id,
    ]);
}

function createSubmission(User $teacher, Semester $semester): EvidenceSubmission
{
    $subject = Subject::create([
        'code' => 'SUBJ-FC-'.Str::upper(Str::random(6)),
        'name' => 'Materia FC '.Str::upper(Str::random(4)),
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
        'name' => 'ITEM-FC-'.Str::upper(Str::random(8)),
        'description' => 'Item test folder operations',
        'requires_subject' => true,
        'active' => true,
    ]);

    return EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $item->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::DRAFT,
        'last_updated_at' => now(),
    ]);
}

it('allows jefe oficina to create a subfolder', function () {
    $ctx = createFolderContext();

    $response = $this
        ->from('/files/folders/'.$ctx['teacherFolder']->id)
        ->actingAs($ctx['jefeOficina'])
        ->post(route('folders.store', $ctx['teacherFolder']->id), [
            'name' => 'Nueva Carpeta',
        ]);

    $response->assertRedirect('/files/folders/'.$ctx['teacherFolder']->id);
    $response->assertSessionHas('success');

    $newFolder = FolderNode::query()
        ->where('parent_id', $ctx['teacherFolder']->id)
        ->where('name', 'Nueva Carpeta')
        ->first();

    expect($newFolder)->not->toBeNull();
    expect($newFolder->storage_root_id)->toBe($ctx['teacherFolder']->storage_root_id);
    expect($newFolder->owner_user_id)->toBe($ctx['teacherFolder']->owner_user_id);
    expect($newFolder->semester_id)->toBe($ctx['teacherFolder']->semester_id);
    expect($newFolder->relative_path)->toBe($ctx['teacherFolder']->relative_path.'/Nueva Carpeta');
});

it('allows jefe depto to create a subfolder', function () {
    $ctx = createFolderContext();

    $response = $this
        ->from('/files/folders/'.$ctx['teacherFolder']->id)
        ->actingAs($ctx['jefeDepto'])
        ->post(route('folders.store', $ctx['teacherFolder']->id), [
            'name' => 'Carpeta Jefe Depto',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(
        FolderNode::query()
            ->where('parent_id', $ctx['teacherFolder']->id)
            ->where('name', 'Carpeta Jefe Depto')
            ->exists()
    )->toBeTrue();
});

it('forbids docente from creating a subfolder', function () {
    $ctx = createFolderContext();

    $response = $this
        ->from('/files/folders/'.$ctx['teacherFolder']->id)
        ->actingAs($ctx['teacher'])
        ->post(route('folders.store', $ctx['teacherFolder']->id), [
            'name' => 'No Permitida',
        ]);

    $response->assertForbidden();

    expect(
        FolderNode::query()
            ->where('parent_id', $ctx['teacherFolder']->id)
            ->where('name', 'No Permitida')
            ->exists()
    )->toBeFalse();
});

it('allows jefe oficina to rename a folder and updates relative_path', function () {
    $ctx = createFolderContext();

    $subfolder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Nombre Original',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Nombre Original',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $response = $this
        ->from('/files/folders/'.$subfolder->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.update', $subfolder->id), [
            'name' => 'Nombre Nuevo',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $subfolder->refresh();
    expect($subfolder->name)->toBe('Nombre Nuevo');
    expect($subfolder->relative_path)->toBe($ctx['teacherFolder']->relative_path.'/Nombre Nuevo');
});

it('allows jefe oficina to edit folder appearance without renaming path', function () {
    $ctx = createFolderContext();

    $subfolder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Apariencia',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Apariencia',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $response = $this
        ->from('/files/folders/'.$subfolder->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.update', $subfolder->id), [
            'name' => 'Apariencia',
            'icon_key' => 'book',
            'color_key' => 'blue',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $subfolder->refresh();
    expect($subfolder->name)->toBe('Apariencia');
    expect($subfolder->relative_path)->toBe($ctx['teacherFolder']->relative_path.'/Apariencia');
    expect($subfolder->icon_key)->toBe('book');
    expect($subfolder->color_key)->toBe('blue');
});

it('rejects unsupported folder icon and color keys', function () {
    $ctx = createFolderContext();

    $response = $this
        ->from('/files/folders/'.$ctx['teacherFolder']->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.update', $ctx['teacherFolder']->id), [
            'name' => $ctx['teacherFolder']->name,
            'icon_key' => 'rocket',
            'color_key' => 'black',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['icon_key', 'color_key']);
});

it('blocks renaming to a duplicate name under the same parent', function () {
    $ctx = createFolderContext();

    FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Existente',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Existente',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $subfolder2 = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Otra',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Otra',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $response = $this
        ->from('/files/folders/'.$subfolder2->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.update', $subfolder2->id), [
            'name' => 'Existente',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();

    $subfolder2->refresh();
    expect($subfolder2->name)->toBe('Otra');
});

it('allows jefe oficina to move a folder within same docente and semester', function () {
    $ctx = createFolderContext();

    $parentA = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'PadreA',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/PadreA',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $parentB = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'PadreB',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/PadreB',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $child = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Hijo',
        'relative_path' => $parentA->relative_path.'/Hijo',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $parentA->id,
    ]);

    $response = $this
        ->from('/files/folders/'.$child->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.move', $child->id), [
            'target_folder_id' => $parentB->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $child->refresh();
    expect($child->parent_id)->toBe($parentB->id);
    expect($child->relative_path)->toBe($parentB->relative_path.'/Hijo');
});

it('updates descendant relative_paths when moving a folder', function () {
    $ctx = createFolderContext();

    $parentA = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Origen',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Origen',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $parentB = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Destino',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Destino',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $child = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Sub',
        'relative_path' => $parentA->relative_path.'/Sub',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $parentA->id,
    ]);

    $grandchild = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Nieto',
        'relative_path' => $child->relative_path.'/Nieto',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $child->id,
    ]);

    $this
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.move', $child->id), [
            'target_folder_id' => $parentB->id,
        ]);

    $grandchild->refresh();
    expect($grandchild->relative_path)->toBe($parentB->relative_path.'/Sub/Nieto');
});

it('blocks moving a folder to a different docente', function () {
    $ctx = createFolderContext();

    $otherTeacherFolder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => $ctx['otherTeacher']->name,
        'relative_path' => $ctx['semesterFolder']->relative_path.'/'.Str::slug($ctx['otherTeacher']->name),
        'owner_user_id' => $ctx['otherTeacher']->id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['semesterFolder']->id,
    ]);

    $folderToMove = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Mover',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Mover',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $response = $this
        ->from('/files/folders/'.$folderToMove->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.move', $folderToMove->id), [
            'target_folder_id' => $otherTeacherFolder->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();

    $folderToMove->refresh();
    expect($folderToMove->parent_id)->toBe($ctx['teacherFolder']->id);
});

it('blocks moving a folder to a different semester', function () {
    $ctx = createFolderContext();

    $otherSemester = Semester::create([
        'name' => 'SEM-OTRO-'.Str::upper(Str::random(6)),
        'start_date' => now()->addMonths(6)->toDateString(),
        'end_date' => now()->addMonths(12)->toDateString(),
        'status' => 'OPEN',
    ]);

    $otherSemesterFolder = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => $otherSemester->name,
        'relative_path' => 'sem2_'.$otherSemester->id,
        'owner_user_id' => null,
        'semester_id' => $otherSemester->id,
        'parent_id' => null,
    ]);

    $folderToMove = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Mover',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Mover',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $response = $this
        ->from('/files/folders/'.$folderToMove->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.move', $folderToMove->id), [
            'target_folder_id' => $otherSemesterFolder->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

it('blocks moving a folder into itself', function () {
    $ctx = createFolderContext();

    $folder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Auto',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Auto',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $response = $this
        ->from('/files/folders/'.$folder->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.move', $folder->id), [
            'target_folder_id' => $folder->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

it('blocks moving a folder into its own descendant', function () {
    $ctx = createFolderContext();

    $parent = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Padre',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Padre',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $child = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Hijo',
        'relative_path' => $parent->relative_path.'/Hijo',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $parent->id,
    ]);

    $response = $this
        ->from('/files/folders/'.$parent->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.move', $parent->id), [
            'target_folder_id' => $child->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

it('blocks deleting a folder with children', function () {
    $ctx = createFolderContext();

    $parentWithChild = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'ConHijos',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/ConHijos',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Hijo',
        'relative_path' => $parentWithChild->relative_path.'/Hijo',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $parentWithChild->id,
    ]);

    $response = $this
        ->from('/files/folders/'.$parentWithChild->id)
        ->actingAs($ctx['jefeOficina'])
        ->delete(route('folders.destroy', $parentWithChild->id));

    $response->assertRedirect();
    $response->assertSessionHasErrors();

    expect(FolderNode::find($parentWithChild->id))->not->toBeNull();
});

it('blocks deleting a folder with files', function () {
    Storage::fake('local');
    $ctx = createFolderContext();
    $submission = createSubmission($ctx['teacher'], $ctx['semester']);

    $folderWithFiles = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'ConArchivos',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/ConArchivos',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    createFileInFolder($folderWithFiles, $ctx['teacher'], $submission);

    $response = $this
        ->from('/files/folders/'.$folderWithFiles->id)
        ->actingAs($ctx['jefeOficina'])
        ->delete(route('folders.destroy', $folderWithFiles->id));

    $response->assertRedirect();
    $response->assertSessionHasErrors();

    expect(FolderNode::find($folderWithFiles->id))->not->toBeNull();
});

it('allows deleting an empty folder', function () {
    $ctx = createFolderContext();

    $emptyFolder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Vacia',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Vacia',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $response = $this
        ->actingAs($ctx['jefeOficina'])
        ->delete(route('folders.destroy', $emptyFolder->id));

    $response->assertRedirect(route('folders.index'));
    $response->assertSessionHas('success');

    expect(FolderNode::find($emptyFolder->id))->toBeNull();
});

it('forbids docente from deleting a folder', function () {
    $ctx = createFolderContext();

    $emptyFolder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'VaciaDocente',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/VaciaDocente',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->delete(route('folders.destroy', $emptyFolder->id));

    $response->assertForbidden();
    expect(FolderNode::find($emptyFolder->id))->not->toBeNull();
});

it('allows jefe oficina to move a file to another valid folder', function () {
    Storage::fake('local');
    $ctx = createFolderContext();
    $submission = createSubmission($ctx['teacher'], $ctx['semester']);

    $folderA = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'CarpetaA',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/CarpetaA',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $folderB = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'CarpetaB',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/CarpetaB',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $file = createFileInFolder($folderA, $ctx['teacher'], $submission);

    $oldPath = $file->stored_relative_path;
    $fileName = basename($oldPath);

    $response = $this
        ->from('/files/folders/'.$folderA->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('files.move', $file->id), [
            'target_folder_id' => $folderB->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $file->refresh();
    expect($file->folder_node_id)->toBe($folderB->id);
    expect($file->stored_relative_path)->toBe($folderB->relative_path.'/'.$fileName);
    expect($file->submission_id)->toBe($submission->id);

    Storage::disk('local')->assertExists($file->stored_relative_path);
    Storage::disk('local')->assertMissing($oldPath);
});

it('forbids docente from moving a file', function () {
    Storage::fake('local');
    $ctx = createFolderContext();
    $submission = createSubmission($ctx['teacher'], $ctx['semester']);

    $folderA = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'CarpetaA',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/CarpetaA',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $folderB = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'CarpetaB',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/CarpetaB',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $file = createFileInFolder($folderA, $ctx['teacher'], $submission);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->patch(route('files.move', $file->id), [
            'target_folder_id' => $folderB->id,
        ]);

    $response->assertForbidden();

    $file->refresh();
    expect($file->folder_node_id)->toBe($folderA->id);
});

it('preserves submission_id when moving a file', function () {
    Storage::fake('local');
    $ctx = createFolderContext();
    $submission = createSubmission($ctx['teacher'], $ctx['semester']);

    $folderA = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'OrigenFile',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/OrigenFile',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $folderB = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'DestinoFile',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/DestinoFile',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $file = createFileInFolder($folderA, $ctx['teacher'], $submission);

    $this
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('files.move', $file->id), [
            'target_folder_id' => $folderB->id,
        ]);

    $file->refresh();
    expect($file->submission_id)->toBe($submission->id);
    expect($file->is_current_version)->toBeTrue();
});

it('replaces a file in place without creating another evidence file record', function () {
    Storage::fake('local');
    $ctx = createFolderContext();
    $submission = createSubmission($ctx['teacher'], $ctx['semester']);
    $file = createFileInFolder($ctx['teacherFolder'], $ctx['teacher'], $submission);
    $originalId = $file->id;
    $originalPath = $file->stored_relative_path;
    $originalHash = $file->file_hash;

    $response = $this
        ->from(route('folders.show', $ctx['teacherFolder']->id))
        ->actingAs($ctx['jefeOficina'])
        ->post(route('files.replace', $file->id), [
            'file' => UploadedFile::fake()->create('actualizado.pdf', 300, 'application/pdf'),
        ]);

    $response->assertRedirect(route('folders.show', $ctx['teacherFolder']->id));

    $file->refresh();
    expect($file->id)->toBe($originalId);
    expect($file->file_name)->toBe('actualizado.pdf');
    expect($file->stored_relative_path)->not->toBe($originalPath);
    expect($file->file_hash)->not->toBe($originalHash);
    expect($file->previous_version_file_id)->toBeNull();
    expect($file->root_file_id)->toBeNull();
    expect($file->is_current_version)->toBeTrue();
    expect(EvidenceFile::query()->where('submission_id', $submission->id)->count())->toBe(1);

    Storage::disk('local')->assertMissing($originalPath);
    Storage::disk('local')->assertExists($file->stored_relative_path);
});

it('preview and download still work after moving a file', function () {
    Storage::fake('local');
    $ctx = createFolderContext();
    $submission = createSubmission($ctx['teacher'], $ctx['semester']);

    $folderA = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'PreviewA',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/PreviewA',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $folderB = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'PreviewB',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/PreviewB',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $file = createFileInFolder($folderA, $ctx['teacher'], $submission);

    $this
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('files.move', $file->id), [
            'target_folder_id' => $folderB->id,
        ]);

    $file->refresh();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('files.download', $file->id))
        ->assertOk();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('files.preview', $file->id))
        ->assertOk();
});

it('logs CREATE_FOLDER audit entry', function () {
    $ctx = createFolderContext();

    $this
        ->actingAs($ctx['jefeOficina'])
        ->post(route('folders.store', $ctx['teacherFolder']->id), [
            'name' => 'AuditTest',
        ]);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'CREATE_FOLDER',
        'user_id' => $ctx['jefeOficina']->id,
    ]);
});

it('logs RENAME_FOLDER audit entry', function () {
    $ctx = createFolderContext();

    $folder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'BeforeRename',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/BeforeRename',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $this
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.update', $folder->id), [
            'name' => 'AfterRename',
        ]);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'RENAME_FOLDER',
        'user_id' => $ctx['jefeOficina']->id,
        'entity_type' => 'FolderNode',
        'entity_id' => $folder->id,
    ]);
});

it('logs MOVE_FOLDER audit entry', function () {
    $ctx = createFolderContext();

    $parent = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'TargetFolder',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/TargetFolder',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $child = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'MoverAudit',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/MoverAudit',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $this
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.move', $child->id), [
            'target_folder_id' => $parent->id,
        ]);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'MOVE_FOLDER',
        'user_id' => $ctx['jefeOficina']->id,
        'entity_type' => 'FolderNode',
        'entity_id' => $child->id,
    ]);
});

it('logs DELETE_FOLDER audit entry', function () {
    $ctx = createFolderContext();

    $emptyFolder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'DeleteAudit',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/DeleteAudit',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $this
        ->actingAs($ctx['jefeOficina'])
        ->delete(route('folders.destroy', $emptyFolder->id));

    $this->assertDatabaseHas('audit_log', [
        'action' => 'DELETE_FOLDER',
        'user_id' => $ctx['jefeOficina']->id,
        'entity_type' => 'FolderNode',
        'entity_id' => $emptyFolder->id,
    ]);
});

it('logs MOVE_FILE audit entry', function () {
    Storage::fake('local');
    $ctx = createFolderContext();
    $submission = createSubmission($ctx['teacher'], $ctx['semester']);

    $folderA = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'FileSource',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/FileSource',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $folderB = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'FileDest',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/FileDest',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $file = createFileInFolder($folderA, $ctx['teacher'], $submission);

    $this
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('files.move', $file->id), [
            'target_folder_id' => $folderB->id,
        ]);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'MOVE_FILE',
        'user_id' => $ctx['jefeOficina']->id,
        'entity_type' => 'EvidenceFile',
        'entity_id' => $file->id,
    ]);
});

it('blocks renaming a semester root folder', function () {
    $ctx = createFolderContext();

    $response = $this
        ->from('/files/folders/'.$ctx['semesterFolder']->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.update', $ctx['semesterFolder']->id), [
            'name' => 'Nuevo Nombre',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

it('blocks moving a semester root folder', function () {
    $ctx = createFolderContext();

    $response = $this
        ->from('/files/folders/'.$ctx['semesterFolder']->id)
        ->actingAs($ctx['jefeOficina'])
        ->patch(route('folders.move', $ctx['semesterFolder']->id), [
            'target_folder_id' => $ctx['teacherFolder']->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

it('blocks deleting a semester root folder', function () {
    $ctx = createFolderContext();

    $response = $this
        ->actingAs($ctx['jefeOficina'])
        ->delete(route('folders.destroy', $ctx['semesterFolder']->id));

    $response->assertRedirect();
    $response->assertSessionHasErrors();
});

it('exposes permission flags on current folder in show response', function () {
    $ctx = createFolderContext();

    $response = $this
        ->actingAs($ctx['jefeOficina'])
        ->get(route('folders.show', $ctx['teacherFolder']->id));

    $response->assertInertia(fn ($page) => $page
        ->component('FileManager/Index')
        ->where('currentFolder.can_create_folder', true)
        ->where('currentFolder.can_rename', true)
        ->where('currentFolder.can_move', true)
        ->where('currentFolder.can_delete', true)
    );
});

it('hides permission flags for docente on current folder', function () {
    $ctx = createFolderContext();

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get(route('folders.show', $ctx['teacherFolder']->id));

    $response->assertInertia(fn ($page) => $page
        ->component('FileManager/Index')
        ->where('currentFolder.can_create_folder', false)
        ->where('currentFolder.can_rename', false)
        ->where('currentFolder.can_move', false)
        ->where('currentFolder.can_delete', false)
    );
});

it('exposes can_move on file entries for jefe oficina', function () {
    Storage::fake('local');
    $ctx = createFolderContext();
    $submission = createSubmission($ctx['teacher'], $ctx['semester']);

    createFileInFolder($ctx['teacherFolder'], $ctx['teacher'], $submission);

    $response = $this
        ->actingAs($ctx['jefeOficina'])
        ->get(route('folders.show', $ctx['teacherFolder']->id));

    $response->assertInertia(fn ($page) => $page
        ->component('FileManager/Index')
        ->where('contents.files.0.can_move', true)
    );
});

it('does not expose can_move on file entries for docente', function () {
    Storage::fake('local');
    $ctx = createFolderContext();
    $submission = createSubmission($ctx['teacher'], $ctx['semester']);

    createFileInFolder($ctx['teacherFolder'], $ctx['teacher'], $submission);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get(route('folders.show', $ctx['teacherFolder']->id));

    $response->assertInertia(fn ($page) => $page
        ->component('FileManager/Index')
        ->where('contents.files.0.can_move', false)
    );
});

it('exposes can_rename on subfolder entries for jefe oficina', function () {
    $ctx = createFolderContext();

    FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'SubCarpeta',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/SubCarpeta',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $response = $this
        ->actingAs($ctx['jefeOficina'])
        ->get(route('folders.show', $ctx['teacherFolder']->id));

    $response->assertInertia(fn ($page) => $page
        ->component('FileManager/Index')
        ->where('contents.folders.0.can_rename', true)
        ->where('contents.folders.0.can_move', true)
        ->where('contents.folders.0.can_delete', true)
    );
});

it('exposes folder appearance keys on current folder and subfolder entries', function () {
    $ctx = createFolderContext();

    $ctx['teacherFolder']->update([
        'icon_key' => 'users',
        'color_key' => 'purple',
    ]);

    FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Con Color',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Con Color',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
        'icon_key' => 'calendar',
        'color_key' => 'green',
    ]);

    $response = $this
        ->actingAs($ctx['jefeOficina'])
        ->get(route('folders.show', $ctx['teacherFolder']->id));

    $response->assertInertia(fn ($page) => $page
        ->component('FileManager/Index')
        ->where('currentFolder.icon_key', 'users')
        ->where('currentFolder.color_key', 'purple')
        ->where('contents.folders.0.icon_key', 'calendar')
        ->where('contents.folders.0.color_key', 'green')
    );
});

it('opens a folder using its readable folder path', function () {
    $ctx = createFolderContext();

    $subfolder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => 'Instrumentacion Didactica',
        'relative_path' => $ctx['teacherFolder']->relative_path.'/Instrumentacion Didactica',
        'owner_user_id' => $ctx['teacherFolder']->owner_user_id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['teacherFolder']->id,
    ]);

    $readablePath = implode('/', [
        $ctx['semesterFolder']->name,
        $ctx['teacherFolder']->name,
        $subfolder->name,
    ]);
    $readableUrl = '/files/folders/'.implode('/', array_map('rawurlencode', explode('/', $readablePath)));

    $response = $this
        ->actingAs($ctx['jefeOficina'])
        ->get($readableUrl);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('FileManager/Index')
        ->where('currentFolder.id', $subfolder->id)
        ->where('currentFolder.display_path', str_replace('/', ' / ', $readablePath))
        ->where('currentFolder.readable_url', $readableUrl)
    );
});

it('keeps docentes from opening readable paths outside their folders', function () {
    $ctx = createFolderContext();

    $otherTeacherFolder = FolderNode::create([
        'storage_root_id' => $ctx['teacherFolder']->storage_root_id,
        'name' => $ctx['otherTeacher']->name,
        'relative_path' => $ctx['semesterFolder']->relative_path.'/'.Str::slug($ctx['otherTeacher']->name),
        'owner_user_id' => $ctx['otherTeacher']->id,
        'semester_id' => $ctx['teacherFolder']->semester_id,
        'parent_id' => $ctx['semesterFolder']->id,
    ]);

    $readablePath = implode('/', [
        $ctx['semesterFolder']->name,
        $otherTeacherFolder->name,
    ]);
    $readableUrl = '/files/folders/'.implode('/', array_map('rawurlencode', explode('/', $readablePath)));

    $response = $this
        ->actingAs($ctx['teacher'])
        ->get($readableUrl);

    $response->assertForbidden();
});
