<?php

use App\Enums\SubmissionStatus;
use App\Models\Department;
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
use App\Services\DocxEditorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createDocxEditorContext(SubmissionStatus $submissionStatus = SubmissionStatus::DRAFT): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $department = Department::create(['name' => 'DEP-DOCX-' . Str::upper(Str::random(6))]);
    $teacher->departments()->attach($department->id);

    $semester = Semester::create([
        'name' => 'SEM-DOCX-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'DOCX-' . Str::upper(Str::random(5)),
        'name' => 'Materia DOCX ' . Str::upper(Str::random(3)),
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
        'name' => 'DOCX ITEM ' . Str::upper(Str::random(4)),
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
        'name' => 'root-docx-' . Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $folder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'DOCX Folder',
        'relative_path' => 'sem_' . $semester->id . '/docente_' . $teacher->id . '/docx_' . Str::lower(Str::random(6)),
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

    $zip = new \ZipArchive();
    $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

    $contentTypeOverridesXml = '';
    if ($numberingXml !== null) {
        $contentTypeOverridesXml .= PHP_EOL . '    <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>';
    }

    foreach ($contentTypeOverrides as $partName => $contentType) {
        $contentTypeOverridesXml .= PHP_EOL . '    <Override PartName="' . $partName . '" ContentType="' . $contentType . '"/>';
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

        $contentTypeDefaults .= PHP_EOL . '    <Default Extension="' . $extension . '" ContentType="' . $mimeType . '"/>';
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
        $relationshipLines .= PHP_EOL . '    <Relationship Id="' . $relationship['id'] . '" Type="' . $relationship['type'] . '" Target="' . $relationship['target'] . '"/>';
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

beforeEach(function () {
    $this->withoutVite();
});

it('renders the docx editor with extracted editable html', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path . '/formato.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx(
        sprintf(
            <<<XML
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
            ->where('document.version_history.0.id', $file->id)
            ->where('document.html', fn (string $html) => str_contains($html, 'Titulo de prueba')
                && str_contains($html, 'Texto en negritas')
                && str_contains($html, 'data-docx-font-family="Arial"')
                && str_contains($html, 'data-docx-kind="image"'))
        );
});

it('saves an edited docx as a new current revision without deleting the original', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path . '/editable.docx';
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

    $response = $this
        ->actingAs($ctx['teacher'])
        ->post(route('files.docx.store', $file->id), [
            'html' => '<h1>Documento actualizado</h1><p><strong>Contenido nuevo</strong> desde editor.</p><ul><li>Elemento uno</li></ul>',
            'save_mode' => 'replace_current',
        ]);

    $newFile = EvidenceFile::query()
        ->where('previous_version_file_id', $file->id)
        ->first();

    expect($newFile)->not->toBeNull();

    $response->assertRedirect(route('files.docx.show', $newFile->id));

    expect($file->fresh()->is_current_version)->toBeFalse();
    expect($newFile->is_current_version)->toBeTrue();
    expect($newFile->root_file_id)->toBe($file->id);
    expect($newFile->last_edited_by_user_id)->toBe($ctx['teacher']->id);

    Storage::disk('local')->assertExists($file->stored_relative_path);
    Storage::disk('local')->assertExists($newFile->stored_relative_path);

    /** @var DocxEditorService $service */
    $service = app(DocxEditorService::class);
    $loaded = $service->loadDocument($newFile);

    expect($loaded['html'])->toContain('Documento actualizado');
    expect($loaded['html'])->toContain('Contenido nuevo');
    expect($loaded['html'])->toContain('Elemento uno');
    expect($loaded['html'])->toContain('<ul>');
});

it('preserves images, explicit font metadata and real lists after saving a docx round trip', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path . '/roundtrip.docx';
    Storage::disk('local')->put($storedPath, makeSimpleDocx(
        sprintf(
            <<<XML
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
            'html' => $loaded['html'] . '<ol><li>Primer elemento</li><li>Segundo elemento</li></ol>',
            'save_mode' => 'replace_current',
        ]);

    $newFile = EvidenceFile::query()
        ->where('previous_version_file_id', $file->id)
        ->first();

    expect($newFile)->not->toBeNull();
    $response->assertRedirect(route('files.docx.show', $newFile->id));

    $reloaded = $service->loadDocument($newFile);

    expect($reloaded['html'])->toContain('data-docx-font-family="Aptos"');
    expect($reloaded['html'])->toContain('data-docx-kind="image"');
    expect($reloaded['html'])->toContain('<ol>');
    expect($reloaded['html'])->toContain('Primer elemento');

    $zip = new \ZipArchive();
    $opened = $zip->open(Storage::disk('local')->path($newFile->stored_relative_path));
    expect($opened)->toBeTrue();
    expect($zip->getFromName('word/numbering.xml'))->not->toBeFalse();
    $zip->close();
});

it('supports simple tables and paragraph presentation in a docx round trip', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path . '/tabla-estilos.docx';
    $documentXml = sprintf(<<<XML
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

    $newFile = EvidenceFile::query()
        ->where('previous_version_file_id', $file->id)
        ->first();

    expect($newFile)->not->toBeNull();
    $response->assertRedirect(route('files.docx.show', $newFile->id));

    $reloaded = $service->loadDocument($newFile);

    expect($reloaded['html'])->toContain('Celda B2 editada');
    expect($reloaded['html'])->toContain('data-docx-align="center"');
    expect($reloaded['html'])->toContain('<table class="docx-table"');

    $zip = new \ZipArchive();
    $opened = $zip->open(Storage::disk('local')->path($newFile->stored_relative_path));
    expect($opened)->toBeTrue();
    expect($zip->getFromName('word/document.xml'))->toContain('<w:tbl>');
    expect($zip->getFromName('word/document.xml'))->toContain('w:jc w:val="center"');
    $zip->close();
});

it('loads and saves editable header and footer content when the docx already defines them', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext();

    $storedPath = $ctx['folder']->relative_path . '/encabezado-pie.docx';
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

    $newFile = EvidenceFile::query()
        ->where('previous_version_file_id', $file->id)
        ->first();

    expect($newFile)->not->toBeNull();
    $response->assertRedirect(route('files.docx.show', $newFile->id));

    $zip = new \ZipArchive();
    $opened = $zip->open(Storage::disk('local')->path($newFile->stored_relative_path));
    expect($opened)->toBeTrue();
    expect($zip->getFromName('word/header1.xml'))->toContain('Encabezado editado');
    expect($zip->getFromName('word/footer1.xml'))->toContain('Pie editado');
    $zip->close();
});

it('renders the docx editor in read only mode when teacher can no longer replace the file', function () {
    Storage::fake('local');
    $ctx = createDocxEditorContext(SubmissionStatus::SUBMITTED);

    $storedPath = $ctx['folder']->relative_path . '/solo-lectura.docx';
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
