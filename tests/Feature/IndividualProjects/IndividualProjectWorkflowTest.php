<?php

use App\Models\EvidenceFile;
use App\Models\FolderNode;
use App\Models\IndividualProject;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function makeIndividualProjectSimpleDocx(string $text = 'Formato inicial'): string
{
    $tempPath = tempnam(sys_get_temp_dir(), 'project-docx-test-');
    if ($tempPath === false) {
        throw new RuntimeException('No se pudo crear un archivo temporal DOCX.');
    }

    $zip = new ZipArchive;
    $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);
    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p><w:sectPr/></w:body></w:document>');
    $zip->close();

    $binary = file_get_contents($tempPath);
    unlink($tempPath);

    return $binary;
}

function makeIndividualProjectHyperlinkDocx(): string
{
    $tempPath = tempnam(sys_get_temp_dir(), 'project-docx-advanced-test-');
    if ($tempPath === false) {
        throw new RuntimeException('No se pudo crear un archivo temporal DOCX avanzado.');
    }

    $zip = new ZipArchive;
    $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);
    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);
    $zip->addFromString('word/_rels/document.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rIdLink1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test" TargetMode="External"/>
</Relationships>
XML);
    $zip->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>
        <w:p>
            <w:hyperlink r:id="rIdLink1">
                <w:r><w:t>Referencia institucional</w:t></w:r>
            </w:hyperlink>
        </w:p>
        <w:sectPr/>
    </w:body>
</w:document>
XML);
    $zip->close();

    $binary = file_get_contents($tempPath);
    unlink($tempPath);

    return $binary;
}

function individualProjectContext(): array
{
    $teacherRole = Role::firstOrCreate(['name' => Role::DOCENTE]);
    $officeRole = Role::firstOrCreate(['name' => Role::JEFE_OFICINA]);

    $teacher = User::factory()->create(['role_id' => $teacherRole->id]);
    $otherTeacher = User::factory()->create(['role_id' => $teacherRole->id]);
    $office = User::factory()->create(['role_id' => $officeRole->id]);

    $semester = Semester::create([
        'name' => 'SEM PROY IND '.Str::upper(Str::random(5)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $root = StorageRoot::create([
        'name' => 'root-proy-ind-'.Str::lower(Str::random(6)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    return compact('teacher', 'otherTeacher', 'office', 'semester', 'root');
}

function createTeacherProjectRoot(StorageRoot $root, Semester $semester, User $teacher): FolderNode
{
    $semesterFolder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => $semester->name,
        'relative_path' => Str::slug($semester->name),
        'owner_user_id' => null,
        'semester_id' => $semester->id,
    ]);

    return FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => $teacher->name,
        'relative_path' => $semesterFolder->relative_path.'/'.Str::slug($teacher->name),
        'owner_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'parent_id' => $semesterFolder->id,
    ]);
}

function createTemplateDocxFile(FolderNode $folder, User $teacher, string $fileName = 'formato.docx'): EvidenceFile
{
    $storedRelativePath = $folder->relative_path.'/'.$fileName;
    $binary = makeIndividualProjectSimpleDocx('Formato para proyecto');
    Storage::disk('local')->put($storedRelativePath, $binary);

    return EvidenceFile::create([
        'submission_id' => null,
        'individual_project_id' => null,
        'folder_node_id' => $folder->id,
        'file_name' => $fileName,
        'stored_relative_path' => $storedRelativePath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => strlen($binary),
        'file_hash' => hash('sha256', $binary),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $teacher->id,
        'is_current_version' => true,
    ]);
}

function createAdvancedTemplateDocxFile(FolderNode $folder, User $teacher, string $fileName = 'formato-avanzado.docx'): EvidenceFile
{
    $storedRelativePath = $folder->relative_path.'/'.$fileName;
    $binary = makeIndividualProjectHyperlinkDocx();
    Storage::disk('local')->put($storedRelativePath, $binary);

    return EvidenceFile::create([
        'submission_id' => null,
        'individual_project_id' => null,
        'folder_node_id' => $folder->id,
        'file_name' => $fileName,
        'stored_relative_path' => $storedRelativePath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => strlen($binary),
        'file_hash' => hash('sha256', $binary),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $teacher->id,
        'is_current_version' => true,
    ]);
}

it('creates an individual project and provisions the type folder when it does not exist', function () {
    $ctx = individualProjectContext();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_CAPACITACION,
            'title' => 'Herramientas de Gestion y Comunicacion en la Nube',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();

    expect($project->teacher_user_id)->toBe($ctx['teacher']->id)
        ->and($project->semester_id)->toBe($ctx['semester']->id)
        ->and($project->type)->toBe(IndividualProject::TYPE_CAPACITACION)
        ->and($project->status)->toBe(IndividualProject::STATUS_DRAFT)
        ->and($project->folderNode)->toBeInstanceOf(FolderNode::class)
        ->and($project->folderNode->name)->toBe('4.1-CAPACITACION')
        ->and($project->folderNode->owner_user_id)->toBe($ctx['teacher']->id);
});

it('preselects an existing compatible folder and lets the teacher switch to another owned folder', function () {
    $ctx = individualProjectContext();
    $teacherRoot = createTeacherProjectRoot($ctx['root'], $ctx['semester'], $ctx['teacher']);
    $projectsRoot = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => '4.PROYECTOS INDIVIDUALES',
        'relative_path' => $teacherRoot->relative_path.'/4.PROYECTOS INDIVIDUALES',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $teacherRoot->id,
    ]);
    $existingFolder = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => '4.4-MATERIAL DIDACTICO',
        'relative_path' => $projectsRoot->relative_path.'/4.4-MATERIAL DIDACTICO',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $projectsRoot->id,
    ]);
    $manualFolder = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => 'Material alterno',
        'relative_path' => $projectsRoot->relative_path.'/Material alterno',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $projectsRoot->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_MATERIAL_DIDACTICO,
            'title' => 'Disenar o producir materiales didacticos',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();
    expect($project->folder_node_id)->toBe($existingFolder->id);

    $this
        ->actingAs($ctx['teacher'])
        ->patch(route('docente.proyectos-individuales.folder', $project), [
            'folder_node_id' => $manualFolder->id,
        ])
        ->assertRedirect();

    expect($project->refresh()->folder_node_id)->toBe($manualFolder->id);
});

it('associates a docx file, exposes the existing editor url, and submits the project to office review', function () {
    Storage::fake('local');
    $ctx = individualProjectContext();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_ASESORIAS_DOCENTES,
            'title' => 'Prestar asesorias docentes a estudiantes',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.docx', $project), [
            'file' => UploadedFile::fake()->create(
                'asesorias.docx',
                24,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
        ])
        ->assertRedirect();

    $file = $project->refresh()->docxFile;

    expect($file)->toBeInstanceOf(EvidenceFile::class)
        ->and($file->folder_node_id)->toBe($project->folder_node_id)
        ->and($file->isDocx())->toBeTrue();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('docente.proyectos-individuales.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.docx_editor_url', route('files.docx.show', $file->id, false))
        );

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.submit', $project))
        ->assertRedirect();

    expect($project->refresh()->status)->toBe(IndividualProject::STATUS_SUBMITTED)
        ->and($project->submitted_at)->not->toBeNull();
});

it('copies a selected template docx into the project and preserves the original template file', function () {
    Storage::fake('local');
    $ctx = individualProjectContext();
    $teacherRoot = createTeacherProjectRoot($ctx['root'], $ctx['semester'], $ctx['teacher']);
    $projectsRoot = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => '4.PROYECTOS INDIVIDUALES',
        'relative_path' => $teacherRoot->relative_path.'/4.PROYECTOS INDIVIDUALES',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $teacherRoot->id,
    ]);
    $templateFolder = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => 'FORMATOS',
        'relative_path' => $teacherRoot->relative_path.'/FORMATOS',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $teacherRoot->id,
    ]);
    $templateFile = createTemplateDocxFile($templateFolder, $ctx['teacher']);

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_CAPACITACION,
            'title' => 'Proyecto con formato',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.template', $project), [
            'template_file_id' => $templateFile->id,
        ])
        ->assertRedirect();

    $project->refresh()->load(['docxFile']);
    $copiedFile = $project->docxFile;

    expect($copiedFile)->toBeInstanceOf(EvidenceFile::class)
        ->and($copiedFile->id)->not->toBe($templateFile->id)
        ->and($copiedFile->individual_project_id)->toBe($project->id)
        ->and($copiedFile->folder_node_id)->toBe($project->folder_node_id)
        ->and($copiedFile->file_name)->toBe($templateFile->file_name);

    expect(Storage::disk('local')->exists($templateFile->stored_relative_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($copiedFile->stored_relative_path))->toBeTrue();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('docente.proyectos-individuales.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.docx_editor_url', route('files.docx.show', $copiedFile->id, false))
            ->where('project.docx_editor.store_url', route('docente.proyectos-individuales.docx-editor', $project, false))
            ->where('project.docx_editor.file.id', $copiedFile->id)
            ->where('project.docx_editor.document.load_error', null)
            ->where('project.docx_editor.capabilities.can_edit', true)
            ->where('project.docx_editor.document.html', fn (string $html) => str_contains($html, 'Formato para proyecto'))
        );
});

it('saves an inline edited project docx and returns to the project screen', function () {
    Storage::fake('local');
    $ctx = individualProjectContext();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_CAPACITACION,
            'title' => 'Proyecto editable inline',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();
    $storedRelativePath = $project->folderNode->relative_path.'/formato.docx';
    Storage::disk('local')->put($storedRelativePath, makeIndividualProjectSimpleDocx('Texto base'));

    $file = EvidenceFile::create([
        'submission_id' => null,
        'individual_project_id' => $project->id,
        'folder_node_id' => $project->folder_node_id,
        'file_name' => 'formato.docx',
        'stored_relative_path' => $storedRelativePath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => Storage::disk('local')->size($storedRelativePath),
        'file_hash' => hash('sha256', Storage::disk('local')->get($storedRelativePath)),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);
    $project->forceFill(['docx_file_id' => $file->id])->save();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.docx-editor', $project), [
            'html' => '<p>Texto editado inline</p>',
            'header_html' => '<p><br></p>',
            'footer_html' => '<p><br></p>',
            'save_mode' => 'replace_current',
        ])
        ->assertRedirect(route('docente.proyectos-individuales.show', $project));

    $file->refresh();
    expect($file->last_edited_by_user_id)->toBe($ctx['teacher']->id)
        ->and($file->last_edited_at)->not->toBeNull()
        ->and(app(\App\Services\DocxEditorService::class)->loadDocument($file)['html'])->toContain('Texto editado inline');
});

it('rejects inline docx saves for files that do not belong to the project', function () {
    Storage::fake('local');
    $ctx = individualProjectContext();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_CAPACITACION,
            'title' => 'Proyecto sin docx propio',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();
    $otherProject = IndividualProject::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'type' => IndividualProject::TYPE_CAPACITACION,
        'title' => 'Otro proyecto',
        'folder_node_id' => $project->folder_node_id,
        'status' => IndividualProject::STATUS_DRAFT,
    ]);
    $storedRelativePath = $project->folderNode->relative_path.'/otro.docx';
    Storage::disk('local')->put($storedRelativePath, makeIndividualProjectSimpleDocx('Otro proyecto'));
    $foreignFile = EvidenceFile::create([
        'submission_id' => null,
        'individual_project_id' => $otherProject->id,
        'folder_node_id' => $project->folder_node_id,
        'file_name' => 'otro.docx',
        'stored_relative_path' => $storedRelativePath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => Storage::disk('local')->size($storedRelativePath),
        'file_hash' => hash('sha256', Storage::disk('local')->get($storedRelativePath)),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);
    $project->forceFill(['docx_file_id' => $foreignFile->id])->save();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('docente.proyectos-individuales.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.docx_editor_url', null)
            ->where('project.docx_editor', null)
        );

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.docx-editor', $project), [
            'html' => '<p>No debe guardar</p>',
            'save_mode' => 'replace_current',
        ])
        ->assertNotFound();
});

it('renders the inline project docx editor as read only when the project cannot be edited', function () {
    Storage::fake('local');
    $ctx = individualProjectContext();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_CAPACITACION,
            'title' => 'Proyecto enviado',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();
    $storedRelativePath = $project->folderNode->relative_path.'/enviado.docx';
    Storage::disk('local')->put($storedRelativePath, makeIndividualProjectSimpleDocx('Proyecto enviado'));
    $file = EvidenceFile::create([
        'submission_id' => null,
        'individual_project_id' => $project->id,
        'folder_node_id' => $project->folder_node_id,
        'file_name' => 'enviado.docx',
        'stored_relative_path' => $storedRelativePath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => Storage::disk('local')->size($storedRelativePath),
        'file_hash' => hash('sha256', Storage::disk('local')->get($storedRelativePath)),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);
    $project->forceFill([
        'docx_file_id' => $file->id,
        'status' => IndividualProject::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ])->save();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('docente.proyectos-individuales.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.can_edit', false)
            ->where('project.docx_editor.capabilities.can_edit', false)
            ->where('project.docx_editor.file.can_edit', false)
        );
});

it('preserves the existing project docx as previous version when a template replaces it', function () {
    Storage::fake('local');
    $ctx = individualProjectContext();
    $teacherRoot = createTeacherProjectRoot($ctx['root'], $ctx['semester'], $ctx['teacher']);
    $templateFolder = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => 'FORMATOS',
        'relative_path' => $teacherRoot->relative_path.'/FORMATOS',
        'owner_user_id' => $ctx['teacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $teacherRoot->id,
    ]);
    $templateFile = createTemplateDocxFile($templateFolder, $ctx['teacher']);

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_MATERIAL_DIDACTICO,
            'title' => 'Proyecto con borrador previo',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.docx', $project), [
            'file' => UploadedFile::fake()->create(
                'borrador.docx',
                24,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ),
        ])
        ->assertRedirect();

    $existingDocxId = $project->refresh()->docxFile->id;

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.template', $project), [
            'template_file_id' => $templateFile->id,
        ])
        ->assertRedirect();

    $project->refresh()->load(['docxFile']);
    $replacedDocx = $project->docxFile;

    expect($replacedDocx->id)->not->toBe($existingDocxId)
        ->and($replacedDocx->previous_version_file_id)->toBe($existingDocxId)
        ->and($replacedDocx->individual_project_id)->toBe($project->id);
});

it('copies a read only common template into the project before editing and preserves the original on save', function () {
    Storage::fake('local');
    $ctx = individualProjectContext();
    $teacherRoot = createTeacherProjectRoot($ctx['root'], $ctx['semester'], $ctx['teacher']);
    $semesterFolder = $teacherRoot->parent;
    $templateFolder = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => 'FORMATOS',
        'relative_path' => $semesterFolder->relative_path.'/FORMATOS',
        'owner_user_id' => null,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $semesterFolder->id,
    ]);
    $templateFile = createTemplateDocxFile($templateFolder, $ctx['teacher']);
    $originalTemplateHash = $templateFile->file_hash;
    $originalTemplatePath = $templateFile->stored_relative_path;
    $originalTemplateBinary = Storage::disk('local')->get($originalTemplatePath);

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_CAPACITACION,
            'title' => 'Proyecto con formato comun',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.template', $project), [
            'template_file_id' => $templateFile->id,
        ])
        ->assertRedirect();

    $project->refresh()->load(['docxFile']);
    $copiedFile = $project->docxFile;

    expect($copiedFile)->toBeInstanceOf(EvidenceFile::class)
        ->and($copiedFile->id)->not->toBe($templateFile->id)
        ->and($copiedFile->individual_project_id)->toBe($project->id)
        ->and($copiedFile->folder_node_id)->toBe($project->folder_node_id)
        ->and($copiedFile->file_name)->toBe($templateFile->file_name)
        ->and($ctx['teacher']->can('replace', $templateFile))->toBeFalse()
        ->and($ctx['teacher']->can('replace', $copiedFile))->toBeTrue();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('docente.proyectos-individuales.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.docx_editor_url', route('files.docx.show', $copiedFile->id, false))
            ->where('project.docx_editor.file.id', $copiedFile->id)
            ->where('project.docx_editor.capabilities.can_edit', true)
            ->where('project.docx_editor.store_url', route('docente.proyectos-individuales.docx-editor', $project, false))
        );

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.docx-editor', $project), [
            'html' => '<p>Contenido editado desde copia del proyecto</p>',
            'header_html' => '<p><br></p>',
            'footer_html' => '<p><br></p>',
            'save_mode' => 'replace_current',
        ])
        ->assertRedirect(route('docente.proyectos-individuales.show', $project));

    $templateFile->refresh();
    $copiedFile->refresh();

    expect($templateFile->file_hash)->toBe($originalTemplateHash)
        ->and($templateFile->stored_relative_path)->toBe($originalTemplatePath)
        ->and(Storage::disk('local')->get($templateFile->stored_relative_path))->toBe($originalTemplateBinary)
        ->and($copiedFile->file_hash)->not->toBe($originalTemplateHash)
        ->and(app(\App\Services\DocxEditorService::class)->loadDocument($copiedFile)['html'])->toContain('Contenido editado desde copia del proyecto');
});

it('allows editing an advanced common template copy while preserving the original source docx', function () {
    Storage::fake('local');
    $ctx = individualProjectContext();
    $teacherRoot = createTeacherProjectRoot($ctx['root'], $ctx['semester'], $ctx['teacher']);
    $semesterFolder = $teacherRoot->parent;
    $templateFolder = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => 'FORMATOS AVANZADOS',
        'relative_path' => $semesterFolder->relative_path.'/FORMATOS AVANZADOS',
        'owner_user_id' => null,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $semesterFolder->id,
    ]);
    $templateFile = createAdvancedTemplateDocxFile($templateFolder, $ctx['teacher']);
    $originalTemplateHash = $templateFile->file_hash;
    $originalTemplateBinary = Storage::disk('local')->get($templateFile->stored_relative_path);

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_CAPACITACION,
            'title' => 'Proyecto con formato avanzado',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.template', $project), [
            'template_file_id' => $templateFile->id,
        ])
        ->assertRedirect();

    $project->refresh()->load(['docxFile']);
    $copiedFile = $project->docxFile;

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('docente.proyectos-individuales.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.docx_editor.file.id', $copiedFile->id)
            ->where('project.docx_editor.document.safe_to_save', false)
            ->where('project.docx_editor.document.blocking_features.0', 'documento principal: hipervinculos nativos')
            ->where('project.docx_editor.capabilities.can_edit', true)
            ->where('project.docx_editor.file.can_edit', true)
        );

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.docx-editor', $project), [
            'html' => '<p>Contenido simplificado desde copia avanzada</p>',
            'header_html' => '<p><br></p>',
            'footer_html' => '<p><br></p>',
            'save_mode' => 'replace_current',
        ])
        ->assertRedirect(route('docente.proyectos-individuales.show', $project));

    $templateFile->refresh();
    $copiedFile->refresh();

    expect($templateFile->file_hash)->toBe($originalTemplateHash)
        ->and(Storage::disk('local')->get($templateFile->stored_relative_path))->toBe($originalTemplateBinary)
        ->and($copiedFile->file_hash)->not->toBe($originalTemplateHash)
        ->and($copiedFile->editor_meta['unsafe_rewrite_acknowledged'] ?? null)->toBeTrue()
        ->and(app(\App\Services\DocxEditorService::class)->loadDocument($copiedFile)['html'])->toContain('Contenido simplificado desde copia avanzada');
});

it('lets office approve and reject submitted individual projects with review comments', function () {
    $ctx = individualProjectContext();

    $project = IndividualProject::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'type' => IndividualProject::TYPE_CAPACITACION,
        'title' => 'Capacitacion docente',
        'status' => IndividualProject::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($ctx['office'])
        ->post(route('oficina.proyectos-individuales.reject', $project), [
            'review_comment' => '',
        ])
        ->assertSessionHasErrors('review_comment');

    $this
        ->actingAs($ctx['office'])
        ->post(route('oficina.proyectos-individuales.reject', $project), [
            'review_comment' => 'Falta evidencia de plataforma.',
        ])
        ->assertRedirect();

    expect($project->refresh()->status)->toBe(IndividualProject::STATUS_REJECTED)
        ->and($project->review_comment)->toBe('Falta evidencia de plataforma.')
        ->and($project->reviewed_by_user_id)->toBe($ctx['office']->id);

    $this->assertDatabaseHas('individual_project_reviews', [
        'individual_project_id' => $project->id,
        'reviewed_by_user_id' => $ctx['office']->id,
        'decision' => 'REJECT',
        'comments' => 'Falta evidencia de plataforma.',
    ]);

    $project->forceFill(['status' => IndividualProject::STATUS_SUBMITTED])->save();

    $this
        ->actingAs($ctx['office'])
        ->post(route('oficina.proyectos-individuales.approve', $project), [
            'review_comment' => 'Completo.',
        ])
        ->assertRedirect();

    expect($project->refresh()->status)->toBe(IndividualProject::STATUS_APPROVED)
        ->and($project->review_comment)->toBe('Completo.');

    $this->assertDatabaseHas('individual_project_reviews', [
        'individual_project_id' => $project->id,
        'reviewed_by_user_id' => $ctx['office']->id,
        'decision' => 'APPROVE',
        'comments' => 'Completo.',
    ]);
});

it('prevents teachers from accessing projects owned by another teacher', function () {
    $ctx = individualProjectContext();

    $project = IndividualProject::create([
        'semester_id' => $ctx['semester']->id,
        'teacher_user_id' => $ctx['teacher']->id,
        'type' => IndividualProject::TYPE_CAPACITACION,
        'title' => 'Capacitacion privada',
        'status' => IndividualProject::STATUS_DRAFT,
    ]);

    $this
        ->actingAs($ctx['otherTeacher'])
        ->get(route('docente.proyectos-individuales.show', $project))
        ->assertForbidden();

    $this
        ->actingAs($ctx['otherTeacher'])
        ->patch(route('docente.proyectos-individuales.folder', $project), [
            'folder_node_id' => 999999,
        ])
        ->assertForbidden();
});

it('rejects template files outside the teacher scope', function () {
    Storage::fake('local');
    $ctx = individualProjectContext();
    $otherRoot = createTeacherProjectRoot($ctx['root'], $ctx['semester'], $ctx['otherTeacher']);
    $otherTemplateFolder = FolderNode::create([
        'storage_root_id' => $ctx['root']->id,
        'name' => 'FORMATOS',
        'relative_path' => $otherRoot->relative_path.'/FORMATOS',
        'owner_user_id' => $ctx['otherTeacher']->id,
        'semester_id' => $ctx['semester']->id,
        'parent_id' => $otherRoot->id,
    ]);
    $templateFile = createTemplateDocxFile($otherTemplateFolder, $ctx['otherTeacher']);

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.store'), [
            'semester_id' => $ctx['semester']->id,
            'type' => IndividualProject::TYPE_CAPACITACION,
            'title' => 'Proyecto restringido',
        ])
        ->assertRedirect();

    $project = IndividualProject::query()->firstOrFail();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('docente.proyectos-individuales.template', $project), [
            'template_file_id' => $templateFile->id,
        ])
        ->assertForbidden();
});
