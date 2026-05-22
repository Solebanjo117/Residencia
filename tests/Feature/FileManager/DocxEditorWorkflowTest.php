<?php

use App\Enums\SubmissionStatus;
use App\Enums\WindowStatus;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\Subject;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use App\Services\DocxEditorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createDocxEditorContext(SubmissionStatus $submissionStatus = SubmissionStatus::DRAFT): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $department = Department::create(['name' => 'DEP-DOCX-'.Str::upper(Str::random(6))]);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM-DOCX-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'DOCX-'.Str::upper(Str::random(5)),
        'name' => 'Materia DOCX '.Str::upper(Str::random(3)),
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
        'name' => 'DOCX ITEM '.Str::upper(Str::random(4)),
        'description' => 'Item para pruebas del editor DOCX',
        'requires_subject' => true,
        'active' => true,
    ]);

    $submission = EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $item->id,
        'teaching_load_id' => $load->id,
        'status' => $submissionStatus,
        'last_updated_at' => now(),
        'submitted_at' => $submissionStatus === SubmissionStatus::SUBMITTED ? now() : null,
    ]);

    $root = StorageRoot::create([
        'name' => 'root-docx-'.Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $folder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'DOCX Folder',
        'relative_path' => 'sem_'.$semester->id.'/docente_'.$teacher->id.'/docx_'.Str::lower(Str::random(6)),
        'owner_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'parent_id' => null,
    ]);

    return compact('teacher', 'semester', 'submission', 'folder');
}

function makeSimpleDocx(string $bodyXml, array $options = []): string
{
    $tempPath = tempnam(sys_get_temp_dir(), 'docx-test-');
    if ($tempPath === false) {
        throw new RuntimeException('No se pudo crear un archivo temporal DOCX.');
    }

    $documentRelationships = $options['document_relationships'] ?? [];
    $mediaFiles = $options['media_files'] ?? [];
    $numberingXml = $options['numbering_xml'] ?? null;
    $extraWordParts = $options['extra_word_parts'] ?? [];
    $contentTypeOverrides = $options['content_type_overrides'] ?? [];
    $sectPrXml = $options['sect_pr_xml'] ?? '<w:sectPr/>';

    $zip = new \ZipArchive;
    $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

    $contentTypeOverridesXml = '';
    if ($numberingXml !== null) {
        $contentTypeOverridesXml .= PHP_EOL.'    <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>';
    }

    foreach ($contentTypeOverrides as $partName => $contentType) {
        $contentTypeOverridesXml .= PHP_EOL.'    <Override PartName="'.$partName.'" ContentType="'.$contentType.'"/>';
    }

    $contentTypeDefaults = '';
    foreach (array_keys($mediaFiles) as $path) {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };

        if ($mimeType === null) {
            continue;
        }

        $contentTypeDefaults .= PHP_EOL.'    <Default Extension="'.$extension.'" ContentType="'.$mimeType.'"/>';
    }

    $zip->addFromString('[Content_Types].xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
{$contentTypeDefaults}
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
{$contentTypeOverridesXml}
</Types>
XML);

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);

    $relationshipLines = '';
    foreach ($documentRelationships as $relationship) {
        $relationshipLines .= PHP_EOL.'    <Relationship Id="'.$relationship['id'].'" Type="'.$relationship['type'].'" Target="'.$relationship['target'].'"/>';
    }

    $zip->addFromString('word/_rels/document.xml.rels', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">{$relationshipLines}
</Relationships>
XML);

    $zip->addFromString('word/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>
    <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/></w:style>
    <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/></w:style>
</w:styles>
XML);

    $zip->addFromString('word/document.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <w:body>
        {$bodyXml}
        {$sectPrXml}
    </w:body>
</w:document>
XML);

    if ($numberingXml !== null) {
        $zip->addFromString('word/numbering.xml', $numberingXml);
    }

    foreach ($mediaFiles as $path => $binary) {
        $zip->addFromString($path, $binary);
    }

    foreach ($extraWordParts as $path => $xml) {
        $zip->addFromString($path, $xml);
    }

    $zip->close();

    $binary = file_get_contents($tempPath);
    @unlink($tempPath);

    if ($binary === false) {
        throw new RuntimeException('No se pudo leer el DOCX temporal.');
    }

    return $binary;
}

function tinyPngBinary(): string
{
    $binary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7+q14AAAAASUVORK5CYII=', true);

    if ($binary === false) {
        throw new RuntimeException('No se pudo preparar la imagen PNG de prueba.');
    }

    return $binary;
}

function sampleDrawingXml(string $relationshipId): string
{
    return <<<XML
<w:p>
    <w:r>
        <w:drawing>
            <wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
                <wp:extent cx="952500" cy="952500"/>
                <wp:docPr id="1" name="Imagen de prueba"/>
                <a:graphic>
                    <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                        <pic:pic>
                            <pic:nvPicPr>
                                <pic:cNvPr id="0" name="imagen.png"/>
                                <pic:cNvPicPr/>
                            </pic:nvPicPr>
                            <pic:blipFill>
                                <a:blip r:embed="{$relationshipId}"/>
                                <a:stretch><a:fillRect/></a:stretch>
                            </pic:blipFill>
                            <pic:spPr>
                                <a:xfrm><a:off x="0" y="0"/><a:ext cx="952500" cy="952500"/></a:xfrm>
                                <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
                            </pic:spPr>
                        </pic:pic>
                    </a:graphicData>
                </a:graphic>
            </wp:inline>
        </w:drawing>
    </w:r>
</w:p>
XML;
}

function sampleSimpleTableXml(): string
{
    return <<<'XML'
<w:tbl>
    <w:tblPr>
        <w:tblW w:w="0" w:type="auto"/>
        <w:tblBorders>
            <w:top w:val="single" w:sz="4" w:space="0" w:color="BFC6D4"/>
            <w:left w:val="single" w:sz="4" w:space="0" w:color="BFC6D4"/>
            <w:bottom w:val="single" w:sz="4" w:space="0" w:color="BFC6D4"/>
            <w:right w:val="single" w:sz="4" w:space="0" w:color="BFC6D4"/>
            <w:insideH w:val="single" w:sz="4" w:space="0" w:color="BFC6D4"/>
            <w:insideV w:val="single" w:sz="4" w:space="0" w:color="BFC6D4"/>
        </w:tblBorders>
    </w:tblPr>
    <w:tr>
        <w:tc><w:p><w:r><w:t>Celda A1</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Celda A2</w:t></w:r></w:p></w:tc>
    </w:tr>
    <w:tr>
        <w:tc><w:p><w:r><w:t>Celda B1</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Celda B2</w:t></w:r></w:p></w:tc>
    </w:tr>
</w:tbl>
XML;
}

function sampleWordLikeTableXml(): string
{
    return <<<'XML'
<w:tbl>
    <w:tblPr>
        <w:tblW w:w="5000" w:type="pct"/>
        <w:tblLayout w:type="fixed"/>
        <w:tblCellMar>
            <w:top w:w="80" w:type="dxa"/>
            <w:left w:w="120" w:type="dxa"/>
            <w:bottom w:w="80" w:type="dxa"/>
            <w:right w:w="120" w:type="dxa"/>
        </w:tblCellMar>
        <w:tblBorders>
            <w:top w:val="single" w:sz="8" w:space="0" w:color="1F2937"/>
            <w:left w:val="single" w:sz="8" w:space="0" w:color="1F2937"/>
            <w:bottom w:val="single" w:sz="8" w:space="0" w:color="1F2937"/>
            <w:right w:val="single" w:sz="8" w:space="0" w:color="1F2937"/>
            <w:insideH w:val="single" w:sz="4" w:space="0" w:color="94A3B8"/>
            <w:insideV w:val="single" w:sz="4" w:space="0" w:color="94A3B8"/>
        </w:tblBorders>
    </w:tblPr>
    <w:tblGrid>
        <w:gridCol w:w="1800"/>
        <w:gridCol w:w="2400"/>
        <w:gridCol w:w="1800"/>
    </w:tblGrid>
    <w:tr>
        <w:tc>
            <w:tcPr>
                <w:tcW w:w="6000" w:type="dxa"/>
                <w:gridSpan w:val="3"/>
                <w:shd w:fill="D9EAF7"/>
                <w:vAlign w:val="center"/>
            </w:tcPr>
            <w:p><w:r><w:t>Encabezado combinado</w:t></w:r></w:p>
        </w:tc>
    </w:tr>
    <w:tr>
        <w:tc>
            <w:tcPr><w:tcW w:w="1800" w:type="dxa"/></w:tcPr>
            <w:p><w:r><w:t>Columna A</w:t></w:r></w:p>
        </w:tc>
        <w:tc>
            <w:tcPr><w:tcW w:w="2400" w:type="dxa"/><w:shd w:fill="F8FAFC"/></w:tcPr>
            <w:p><w:r><w:t>Columna B</w:t></w:r></w:p>
        </w:tc>
        <w:tc>
            <w:tcPr><w:tcW w:w="1800" w:type="dxa"/></w:tcPr>
            <w:p><w:r><w:t>Columna C</w:t></w:r></w:p>
        </w:tc>
    </w:tr>
</w:tbl>
XML;
}

beforeEach(function () {
    $this->withoutVite();
});

it('renders the docx editor with extracted editable html', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/formato.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx(
        sprintf(
            <<<'XML'
<w:p><w:r><w:rPr><w:rFonts w:ascii="Arial"/><w:sz w:val="32"/><w:color w:val="1F4E79"/></w:rPr><w:t>Titulo de prueba</w:t></w:r></w:p>
<w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Texto en negritas</w:t></w:r></w:p>
%s
XML,
            sampleDrawingXml('rIdImage1')
        ),
        [
            'document_relationships' => [[
                'id' => 'rIdImage1',
                'type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
                'target' => 'media/image1.png',
            ]],
            'media_files' => [
                'word/media/image1.png' => tinyPngBinary(),
            ],
        ]
    ));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'formato.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-content'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('files.docx.show', $file->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FileManager/DocxEditor')
            ->where('file.id', $file->id)
            ->where('file.can_edit', true)
            ->where('document.load_error', null)
            ->where('document.stats.images', 1)
            ->missing('document.version_history')
            ->where('document.html', fn (string $html) => str_contains($html, 'Titulo de prueba')
                && str_contains($html, 'Texto en negritas')
                && str_contains($html, 'data-docx-font-family="Arial"')
                && str_contains($html, 'data-docx-kind="image"'))
        );
});

it('renders onlyoffice editor config with signed document and callback urls', function () {
    config([
        'onlyoffice.enabled' => true,
        'onlyoffice.document_server_url' => 'http://documentserver.test',
    ]);

    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/onlyoffice.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx('<w:p><w:r><w:t>Texto OnlyOffice</w:t></w:r></w:p>'));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'onlyoffice.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'onlyoffice-docx'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('files.onlyoffice.show', $file->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FileManager/OnlyOfficeEditor')
            ->where('file.can_edit', true)
            ->where('onlyoffice.enabled', true)
            ->where('onlyoffice.api_url', fn (string $url) => str_starts_with($url, 'http://documentserver.test/web-apps/apps/api/documents/api.js?shardkey='))
            ->where('onlyoffice.config.document.fileType', 'docx')
            ->where('onlyoffice.config.document.title', 'onlyoffice.docx')
            ->where('onlyoffice.config.document.permissions.edit', true)
            ->where('onlyoffice.config.editorConfig.mode', 'edit')
            ->where('onlyoffice.config.document.url', fn (string $url) => str_contains($url, '/onlyoffice/files/'.$file->id.'/download') && str_contains($url, 'signature='))
            ->where('onlyoffice.config.editorConfig.callbackUrl', fn (string $url) => str_contains($url, '/onlyoffice/files/'.$file->id.'/callback/'.$ctx['teacher']->id) && str_contains($url, 'signature='))
        );
});

it('serves a signed onlyoffice document download without an authenticated browser session', function () {
    config([
        'onlyoffice.enabled' => true,
        'onlyoffice.document_server_url' => 'http://documentserver.test',
    ]);

    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/signed-download.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx('<w:p><w:r><w:t>Descarga firmada</w:t></w:r></w:p>'));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'signed-download.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'signed-download-docx'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $url = URL::temporarySignedRoute('onlyoffice.files.download', now()->addMinutes(5), ['file' => $file->id]);

    $this->get($url)->assertOk();
});

it('saves an edited docx from the onlyoffice callback', function () {
    config([
        'onlyoffice.enabled' => true,
        'onlyoffice.document_server_url' => 'http://documentserver.test',
    ]);

    Storage::fake('local');
    Http::fake([
        'http://documentserver.test/edited.docx' => Http::response(makeSimpleDocx('<w:p><w:r><w:t>Editado en OnlyOffice</w:t></w:r></w:p>'), 200),
    ]);

    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/callback.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx('<w:p><w:r><w:t>Texto original</w:t></w:r></w:p>'));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'callback.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'callback-docx'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $url = URL::temporarySignedRoute('onlyoffice.files.callback', now()->addMinutes(5), [
        'file' => $file->id,
        'user' => $ctx['teacher']->id,
    ]);

    $this
        ->postJson($url, [
            'status' => 2,
            'key' => 'callback-key',
            'url' => 'http://documentserver.test/edited.docx',
            'users' => [(string) $ctx['teacher']->id],
        ])
        ->assertOk()
        ->assertJson(['error' => 0]);

    $file->refresh();

    expect($file->editor_source)->toBe('ONLYOFFICE');
    expect($file->last_edited_by_user_id)->toBe($ctx['teacher']->id);
    expect($file->file_hash)->not->toBe(hash('sha256', 'callback-docx'));

    /** @var DocxEditorService $service */
    $service = app(DocxEditorService::class);
    $loaded = $service->loadDocument($file);

    expect($loaded['html'])->toContain('Editado en OnlyOffice');

    $this
        ->actingAs($ctx['teacher'])
        ->getJson(route('files.history', $file->id))
        ->assertOk()
        ->assertJsonPath('file.id', $file->id)
        ->assertJsonPath('file.editor_source', 'ONLYOFFICE')
        ->assertJsonPath('history.0.label', 'Documento editado en OnlyOffice')
        ->assertJsonPath('history.0.actor_name', $ctx['teacher']->name)
        ->assertJsonPath('history.0.metadata.editor_source', 'ONLYOFFICE')
        ->assertJsonPath('history.0.metadata.old_file_name', 'callback.docx')
        ->assertJsonPath('history.0.metadata.new_file_name', 'callback.docx');
});

it('saves an edited docx in place without creating another evidence file record', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/editable.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx('<w:p><w:r><w:t>Texto base</w:t></w:r></w:p>'));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'editable.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-base'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);
    $originalId = $file->id;
    $originalPath = $file->stored_relative_path;

    $response = $this
        ->actingAs($ctx['teacher'])
        ->post(route('files.docx.store', $file->id), [
            'html' => '<h1>Documento actualizado</h1><p><strong>Contenido nuevo</strong> desde editor.</p><ul><li>Elemento uno</li></ul>',
            'save_mode' => 'replace_current',
        ]);

    $response->assertRedirect(route('files.docx.show', $originalId));

    $file->refresh();
    expect($file->id)->toBe($originalId);
    expect($file->previous_version_file_id)->toBeNull();
    expect($file->root_file_id)->toBeNull();
    expect($file->is_current_version)->toBeTrue();
    expect($file->last_edited_by_user_id)->toBe($ctx['teacher']->id);
    expect(EvidenceFile::query()->where('submission_id', $ctx['submission']->id)->count())->toBe(1);

    Storage::disk('local')->assertMissing($originalPath);
    Storage::disk('local')->assertExists($file->stored_relative_path);

    /** @var DocxEditorService $service */
    $service = app(DocxEditorService::class);
    $loaded = $service->loadDocument($file);

    expect($loaded['html'])->toContain('Documento actualizado');
    expect($loaded['html'])->toContain('Contenido nuevo');
    expect($loaded['html'])->toContain('Elemento uno');
    expect($loaded['html'])->toContain('<ul>');
});

it('preserves images, explicit font metadata and real lists after saving a docx round trip', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/roundtrip.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx(
        sprintf(
            <<<'XML'
<w:p><w:r><w:rPr><w:rFonts w:ascii="Aptos"/><w:sz w:val="28"/><w:color w:val="2F5597"/></w:rPr><w:t>Texto base con fuente</w:t></w:r></w:p>
%s
XML,
            sampleDrawingXml('rIdImage1')
        ),
        [
            'document_relationships' => [[
                'id' => 'rIdImage1',
                'type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
                'target' => 'media/image1.png',
            ]],
            'media_files' => [
                'word/media/image1.png' => tinyPngBinary(),
            ],
        ]
    ));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'roundtrip.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-roundtrip'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    /** @var DocxEditorService $service */
    $service = app(DocxEditorService::class);
    $loaded = $service->loadDocument($file);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->post(route('files.docx.store', $file->id), [
            'html' => $loaded['html'].'<ol><li>Primer elemento</li><li>Segundo elemento</li></ol>',
            'save_mode' => 'replace_current',
        ]);

    $response->assertRedirect(route('files.docx.show', $file->id));
    $file->refresh();
    expect(EvidenceFile::query()->where('submission_id', $ctx['submission']->id)->count())->toBe(1);

    $reloaded = $service->loadDocument($file);

    expect($reloaded['html'])->toContain('data-docx-font-family="Aptos"');
    expect($reloaded['html'])->toContain('data-docx-kind="image"');
    expect($reloaded['html'])->toContain('<ol>');
    expect($reloaded['html'])->toContain('Primer elemento');

    $zip = new \ZipArchive;
    $opened = $zip->open(Storage::disk('local')->path($file->stored_relative_path));
    expect($opened)->toBeTrue();
    expect($zip->getFromName('word/numbering.xml'))->not->toBeFalse();
    $zip->close();
});

it('supports simple tables and paragraph presentation in a docx round trip', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/tabla-estilos.docx';
    $documentXml = sprintf(<<<'XML'
<w:p>
    <w:pPr>
        <w:jc w:val="center"/>
        <w:ind w:left="720"/>
        <w:spacing w:before="120" w:after="240"/>
    </w:pPr>
    <w:r><w:t>Parrafo centrado</w:t></w:r>
</w:p>
%s
XML, sampleSimpleTableXml());
    Storage::disk('local')->put($storedPath, makeSimpleDocx($documentXml));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'tabla-estilos.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-table-style'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    /** @var DocxEditorService $service */
    $service = app(DocxEditorService::class);
    $loaded = $service->loadDocument($file);

    expect($loaded['html'])->toContain('data-docx-align="center"');
    expect($loaded['html'])->toContain('data-docx-indent-left="720"');
    expect($loaded['html'])->toContain('data-docx-spacing-after="240"');
    expect($loaded['html'])->toContain('<table class="docx-table"');
    expect($loaded['stats']['tables'])->toBe(1);

    $editedHtml = str_replace('Celda B2', 'Celda B2 editada', $loaded['html']);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->post(route('files.docx.store', $file->id), [
            'html' => $editedHtml,
            'save_mode' => 'replace_current',
        ]);

    $response->assertRedirect(route('files.docx.show', $file->id));
    $file->refresh();
    expect(EvidenceFile::query()->where('submission_id', $ctx['submission']->id)->count())->toBe(1);

    $reloaded = $service->loadDocument($file);

    expect($reloaded['html'])->toContain('Celda B2 editada');
    expect($reloaded['html'])->toContain('data-docx-align="center"');
    expect($reloaded['html'])->toContain('<table class="docx-table"');

    $zip = new \ZipArchive;
    $opened = $zip->open(Storage::disk('local')->path($file->stored_relative_path));
    expect($opened)->toBeTrue();
    expect($zip->getFromName('word/document.xml'))->toContain('<w:tbl>');
    expect($zip->getFromName('word/document.xml'))->toContain('w:jc w:val="center"');
    $zip->close();
});

it('preserves word-like table sizing shading and merged cells in the docx editor', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/tabla-word.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx(sampleWordLikeTableXml()));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'tabla-word.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-word-table'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    /** @var DocxEditorService $service */
    $service = app(DocxEditorService::class);
    $loaded = $service->loadDocument($file);

    expect($loaded['html'])->toContain('data-docx-layout="fixed"');
    expect($loaded['html'])->toContain('data-docx-grid="1800,2400,1800"');
    expect($loaded['html'])->toContain('colspan="3"');
    expect($loaded['html'])->toContain('data-docx-bg="D9EAF7"');
    expect($loaded['html'])->toContain('background-color:#D9EAF7');
    expect($loaded['html'])->toContain('data-docx-cell-margin-left="120"');

    $editedHtml = str_replace('Columna B', 'Columna B editada', $loaded['html']);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->post(route('files.docx.store', $file->id), [
            'html' => $editedHtml,
            'save_mode' => 'replace_current',
        ]);

    $response->assertRedirect(route('files.docx.show', $file->id));
    $file->refresh();

    $zip = new \ZipArchive;
    $opened = $zip->open(Storage::disk('local')->path($file->stored_relative_path));
    expect($opened)->toBeTrue();
    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();

    expect($documentXml)->toContain('<w:tblGrid>');
    expect($documentXml)->toContain('w:gridSpan w:val="3"');
    expect($documentXml)->toContain('w:shd w:fill="D9EAF7"');
    expect($documentXml)->toContain('Columna B editada');
});

it('opens advanced word documents in protected read only mode', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/documento-avanzado.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx(
        <<<'XML'
<w:p>
    <w:hyperlink r:id="rIdLink1">
        <w:r><w:t>Referencia institucional</w:t></w:r>
    </w:hyperlink>
</w:p>
XML,
        [
            'document_relationships' => [[
                'id' => 'rIdLink1',
                'type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
                'target' => 'https://example.test',
            ]],
        ]
    ));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'documento-avanzado.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-advanced'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('files.docx.show', $file->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FileManager/DocxEditor')
            ->where('file.can_edit', false)
            ->where('capabilities.can_edit', false)
            ->where('document.safe_to_save', false)
            ->where('document.blocking_features.0', 'documento principal: hipervinculos nativos')
            ->where('document.html', fn (string $html) => str_contains($html, 'Referencia institucional'))
        );
});

it('blocks saving advanced word documents to avoid destructive rewrites', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/documento-con-comentarios.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx(
        <<<'XML'
<w:p>
    <w:r><w:t>Texto con comentario</w:t></w:r>
    <w:commentReference w:id="1"/>
</w:p>
XML,
        [
            'extra_word_parts' => [
                'word/comments.xml' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>',
            ],
            'content_type_overrides' => [
                '/word/comments.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml',
            ],
        ]
    ));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'documento-con-comentarios.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-comments'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->from(route('files.docx.show', $file->id))
        ->post(route('files.docx.store', $file->id), [
            'html' => '<p>Texto editado</p>',
            'save_mode' => 'replace_current',
        ]);

    $response
        ->assertRedirect(route('files.docx.show', $file->id))
        ->assertSessionHasErrors('docx');

    $file->refresh();
    expect($file->file_hash)->toBe(hash('sha256', 'docx-comments'));

    $zip = new \ZipArchive;
    $opened = $zip->open(Storage::disk('local')->path($storedPath));
    expect($opened)->toBeTrue();
    expect($zip->getFromName('word/document.xml'))->toContain('Texto con comentario');
    $zip->close();
});

it('loads and saves editable header and footer content when the docx already defines them', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path.'/encabezado-pie.docx';
    $bodyXml = '<w:p><w:r><w:t>Cuerpo principal</w:t></w:r></w:p>';

    Storage::disk('local')->put($storedPath, makeSimpleDocx(
        $bodyXml,
        [
            'document_relationships' => [
                [
                    'id' => 'rIdHeader1',
                    'type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header',
                    'target' => 'header1.xml',
                ],
                [
                    'id' => 'rIdFooter1',
                    'type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer',
                    'target' => 'footer1.xml',
                ],
            ],
            'extra_word_parts' => [
                'word/header1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Encabezado actual</w:t></w:r></w:p></w:hdr>',
                'word/footer1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Pie actual</w:t></w:r></w:p></w:ftr>',
            ],
            'content_type_overrides' => [
                '/word/header1.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml',
                '/word/footer1.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml',
            ],
            'sect_pr_xml' => '<w:sectPr xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:headerReference w:type="default" r:id="rIdHeader1"/><w:footerReference w:type="default" r:id="rIdFooter1"/></w:sectPr>',
        ]
    ));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'encabezado-pie.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-header-footer'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('files.docx.show', $file->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FileManager/DocxEditor')
            ->where('document.sections.has_header', true)
            ->where('document.sections.has_footer', true)
            ->where('document.header_html', fn (string $html) => str_contains($html, 'Encabezado actual'))
            ->where('document.footer_html', fn (string $html) => str_contains($html, 'Pie actual'))
        );

    $response = $this
        ->actingAs($ctx['teacher'])
        ->post(route('files.docx.store', $file->id), [
            'html' => '<p>Cuerpo principal actualizado</p>',
            'header_html' => '<p><strong>Encabezado editado</strong></p>',
            'footer_html' => '<p>Pie editado</p>',
            'save_mode' => 'replace_current',
        ]);

    $response->assertRedirect(route('files.docx.show', $file->id));
    $file->refresh();
    expect(EvidenceFile::query()->where('submission_id', $ctx['submission']->id)->count())->toBe(1);

    $zip = new \ZipArchive;
    $opened = $zip->open(Storage::disk('local')->path($file->stored_relative_path));
    expect($opened)->toBeTrue();
    expect($zip->getFromName('word/header1.xml'))->toContain('Encabezado editado');
    expect($zip->getFromName('word/footer1.xml'))->toContain('Pie editado');
    $zip->close();
});

it('blocks saving a docx when submission is pending and window is not open', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext(SubmissionStatus::SUBMITTED);

    $departmentId = $ctx['teacher']->departments()->first()->id;

    EvidenceRequirement::create([
        'semester_id' => $ctx['semester']->id,
        'department_id' => $departmentId,
        'evidence_item_id' => $ctx['submission']->evidence_item_id,
        'is_mandatory' => true,
    ]);

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['submission']->evidence_item_id,
        'opens_at' => now()->addDays(2),
        'closes_at' => now()->addDays(5),
        'created_by_user_id' => $ctx['teacher']->id,
        'status' => WindowStatus::ACTIVE,
    ]);

    $storedPath = $ctx['folder']->relative_path.'/pendiente.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx('<w:p><w:r><w:t>Base</w:t></w:r></w:p>'));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'pendiente.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-pending'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->post(route('files.docx.store', $file->id), [
            'html' => '<p>Actualizacion</p>',
            'save_mode' => 'replace_current',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('docx');

    $this->assertDatabaseMissing('evidence_files', [
        'previous_version_file_id' => $file->id,
    ]);
});

it('allows saving a docx when submission is pending and window is open', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext(SubmissionStatus::SUBMITTED);

    $departmentId = $ctx['teacher']->departments()->first()->id;

    EvidenceRequirement::create([
        'semester_id' => $ctx['semester']->id,
        'department_id' => $departmentId,
        'evidence_item_id' => $ctx['submission']->evidence_item_id,
        'is_mandatory' => true,
    ]);

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['submission']->evidence_item_id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDay(),
        'created_by_user_id' => $ctx['teacher']->id,
        'status' => WindowStatus::ACTIVE,
    ]);

    $storedPath = $ctx['folder']->relative_path.'/pendiente.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx('<w:p><w:r><w:t>Base</w:t></w:r></w:p>'));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'pendiente.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-pending-open'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    $response = $this
        ->actingAs($ctx['teacher'])
        ->post(route('files.docx.store', $file->id), [
            'html' => '<p>Actualizacion</p>',
            'save_mode' => 'replace_current',
        ]);

    $response->assertRedirect(route('files.docx.show', $file->id));
    expect(EvidenceFile::query()->where('submission_id', $ctx['submission']->id)->count())->toBe(1);
});

it('renders the docx editor in read only mode when teacher can no longer replace the file', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext(SubmissionStatus::SUBMITTED);
    $ctx['submission']->update([
        'office_reviewed_at' => now(),
    ]);

    $storedPath = $ctx['folder']->relative_path.'/solo-lectura.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx('<w:p><w:r><w:t>Solo lectura</w:t></w:r></w:p>'));

    $file = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'solo-lectura.docx',
        'stored_relative_path' => $storedPath,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 1024,
        'file_hash' => hash('sha256', 'docx-read-only'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
    ]);

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('files.docx.show', $file->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FileManager/DocxEditor')
            ->where('file.can_edit', false)
        );
});
