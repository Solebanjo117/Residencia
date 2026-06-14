<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DocxEditorService
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    private const DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const WORD_DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    private const PIC_NS = 'http://schemas.openxmlformats.org/drawingml/2006/picture';

    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const CONTENT_TYPES_NS = 'http://schemas.openxmlformats.org/package/2006/content-types';

    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const BULLET_NUM_ID = 9101;

    private const BULLET_ABSTRACT_NUM_ID = 9100;

    private const ORDERED_NUM_ID = 9201;

    private const ORDERED_ABSTRACT_NUM_ID = 9200;

    public function __construct(
        protected StorageService $storageService,
    ) {}

    public function loadDocument(EvidenceFile $file): array
    {
        $this->ensureEditableDocx($file);
        $absolutePath = $this->ensureFileExists($file);
        $package = $this->readPackage($absolutePath);
        $rendered = $this->documentXmlToHtml(
            $package['document_xml'],
            $package['numbering_xml'],
            $package['has_extra_parts'],
            $package['image_relationships']
        );
        $headerRendered = $package['header_part']['xml'] !== null
            ? $this->wordPartXmlToHtml($package['header_part']['xml'], $package['numbering_xml'], $package['header_part']['image_relationships'], 'header')
            : ['html' => '', 'warnings' => [], 'stats' => null];
        $footerRendered = $package['footer_part']['xml'] !== null
            ? $this->wordPartXmlToHtml($package['footer_part']['xml'], $package['numbering_xml'], $package['footer_part']['image_relationships'], 'footer')
            : ['html' => '', 'warnings' => [], 'stats' => null];
        $rewriteSafety = $this->analyzePackageRewriteSafety($package, [$rendered, $headerRendered, $footerRendered]);

        return [
            'html' => $rendered['html'],
            'header_html' => $headerRendered['html'],
            'footer_html' => $footerRendered['html'],
            'warnings' => array_values(array_unique(array_merge(
                $rendered['warnings'],
                $headerRendered['warnings'],
                $footerRendered['warnings'],
                $rewriteSafety['warnings']
            ))),
            'stats' => $rendered['stats'],
            'safe_to_save' => $rewriteSafety['safe_to_save'],
            'blocking_features' => $rewriteSafety['blocking_features'],
            'sections' => [
                'has_header' => $package['header_part']['xml'] !== null,
                'has_footer' => $package['footer_part']['xml'] !== null,
            ],
        ];
    }

    public function saveDocument(
        EvidenceFile $file,
        string $html,
        User $user,
        ?string $headerHtml = null,
        ?string $footerHtml = null
    ): EvidenceFile {
        return $this->saveDocumentWithRewritePolicy($file, $html, $user, $headerHtml, $footerHtml, false);
    }

    public function saveProjectCopyAllowingUnsafeRewrite(
        EvidenceFile $file,
        string $html,
        User $user,
        ?string $headerHtml = null,
        ?string $footerHtml = null
    ): EvidenceFile {
        return $this->saveDocumentWithRewritePolicy($file, $html, $user, $headerHtml, $footerHtml, true);
    }

    public function saveDocumentAllowingUnsafeRewrite(
        EvidenceFile $file,
        string $html,
        User $user,
        ?string $headerHtml = null,
        ?string $footerHtml = null
    ): EvidenceFile {
        return $this->saveDocumentWithRewritePolicy($file, $html, $user, $headerHtml, $footerHtml, true);
    }

    private function saveDocumentWithRewritePolicy(
        EvidenceFile $file,
        string $html,
        User $user,
        ?string $headerHtml,
        ?string $footerHtml,
        bool $allowUnsafeRewrite
    ): EvidenceFile {
        $this->ensureEditableDocx($file);
        $absolutePath = $this->ensureFileExists($file);
        $package = $this->readPackage($absolutePath);
        $rewriteSafety = $this->analyzePackageRewriteSafety($package);
        if (! $rewriteSafety['safe_to_save'] && ! $allowUnsafeRewrite) {
            throw new RuntimeException(
                'Este DOCX contiene estructura avanzada de Word que el editor web no puede preservar al 100%. Para evitar corrupcion o cambios de formato, descarga y editalo en Word/Google Docs, o reemplaza el archivo desde el gestor.'
            );
        }

        $parsed = $this->htmlToBlocks($html);
        $compiled = $this->blocksToDocumentXml($parsed['blocks'], $package['sect_pr_xml']);
        $extraParts = [];
        $needsNumbering = $compiled['needs_numbering'];

        if ($package['header_part']['xml'] !== null && $headerHtml !== null) {
            $headerParsed = $this->htmlToBlocks($headerHtml);
            $headerCompiled = $this->blocksToWordPartXml($headerParsed['blocks'], 'hdr');
            $extraParts[$package['header_part']['path']] = $headerCompiled;
            $needsNumbering = $needsNumbering || $this->blocksRequireNumbering($headerParsed['blocks']);
        }

        if ($package['footer_part']['xml'] !== null && $footerHtml !== null) {
            $footerParsed = $this->htmlToBlocks($footerHtml);
            $footerCompiled = $this->blocksToWordPartXml($footerParsed['blocks'], 'ftr');
            $extraParts[$package['footer_part']['path']] = $footerCompiled;
            $needsNumbering = $needsNumbering || $this->blocksRequireNumbering($footerParsed['blocks']);
        }

        $binary = $this->buildDocxBinary(
            $absolutePath,
            $compiled['document_xml'],
            $needsNumbering,
            $extraParts
        );

        return $this->storageService->overwriteGeneratedEvidence(
            $file,
            $binary,
            $file->file_name,
            self::DOCX_MIME,
            $user,
            'DOCX_EDITOR',
            [
                'source_file_id' => $file->id,
                'save_mode' => 'replace_current',
                'warnings' => $parsed['warnings'],
                'unsafe_rewrite_acknowledged' => $allowUnsafeRewrite && ! $rewriteSafety['safe_to_save'],
                'blocking_features' => $rewriteSafety['blocking_features'],
                'normalized_block_count' => count($parsed['blocks']),
            ]
        );
    }

    private function ensureEditableDocx(EvidenceFile $file): void
    {
        if (! $file->isDocx()) {
            throw new RuntimeException('Este archivo no es compatible con el editor DOCX.');
        }
    }

    private function ensureFileExists(EvidenceFile $file): string
    {
        $this->storageService->assertEvidenceFilePath($file);

        if (! Storage::disk('local')->exists($file->stored_relative_path)) {
            throw new RuntimeException('No se encontro el archivo DOCX en almacenamiento.');
        }

        return Storage::disk('local')->path($file->stored_relative_path);
    }

    private function readPackage(string $absolutePath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('No se pudo abrir el paquete DOCX.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();

            throw new RuntimeException('El archivo DOCX no contiene word/document.xml.');
        }

        $numberingXml = $zip->getFromName('word/numbering.xml') ?: null;
        $documentRelsXml = $zip->getFromName('word/_rels/document.xml.rels') ?: null;
        $hasExtraParts = $this->zipHasEntryPrefix($zip, 'word/header') || $this->zipHasEntryPrefix($zip, 'word/footer');
        $imageRelationships = $this->extractImageRelationships($zip, $documentRelsXml);
        $headerFooterParts = $this->extractHeaderFooterParts($zip, $documentXml, $documentRelsXml);
        $entries = $this->zipEntryNames($zip);
        $zip->close();

        return [
            'document_xml' => $documentXml,
            'numbering_xml' => $numberingXml,
            'sect_pr_xml' => $this->extractSectPrXml($documentXml),
            'has_extra_parts' => $hasExtraParts,
            'image_relationships' => $imageRelationships,
            'header_part' => $headerFooterParts['header'],
            'footer_part' => $headerFooterParts['footer'],
            'entries' => $entries,
        ];
    }

    private function zipEntryNames(ZipArchive $zip): array
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if ($name !== false) {
                $entries[] = (string) $name;
            }
        }

        return $entries;
    }

    private function zipHasEntryPrefix(ZipArchive $zip, string $prefix): bool
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function extractImageRelationships(ZipArchive $zip, ?string $documentRelsXml): array
    {
        if (! $documentRelsXml) {
            return [];
        }

        return $this->extractImageRelationshipsForPart($zip, $documentRelsXml, 'word/document.xml');
    }

    private function extractImageRelationshipsForPart(ZipArchive $zip, ?string $relsXml, string $basePart): array
    {
        if (! $relsXml) {
            return [];
        }

        $dom = $this->loadPackageXml($relsXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pr', self::PACKAGE_REL_NS);
        $relationships = [];

        foreach ($xpath->query('/pr:Relationships/pr:Relationship') as $relationship) {
            if (! $relationship instanceof DOMElement) {
                continue;
            }

            $type = (string) $relationship->getAttribute('Type');
            if (! str_ends_with($type, '/image')) {
                continue;
            }

            $id = trim((string) $relationship->getAttribute('Id'));
            $target = trim((string) $relationship->getAttribute('Target'));
            if ($id === '' || $target === '') {
                continue;
            }

            $zipPath = $this->resolveZipTargetPath($basePart, $target);
            $binary = $zip->getFromName($zipPath);
            if ($binary === false) {
                continue;
            }

            $mimeType = $this->mimeTypeForExtension((string) pathinfo($zipPath, PATHINFO_EXTENSION));

            $relationships[$id] = [
                'target' => $target,
                'zip_path' => $zipPath,
                'mime_type' => $mimeType,
                'data_uri' => 'data:'.$mimeType.';base64,'.base64_encode($binary),
            ];
        }

        return $relationships;
    }

    private function extractHeaderFooterParts(ZipArchive $zip, string $documentXml, ?string $documentRelsXml): array
    {
        $emptyPart = [
            'xml' => null,
            'path' => null,
            'relationship_id' => null,
            'image_relationships' => [],
        ];

        if (! $documentRelsXml) {
            return [
                'header' => $emptyPart,
                'footer' => $emptyPart,
            ];
        }

        $relsMap = $this->extractPackageRelationshipsMap($documentRelsXml);
        $dom = $this->loadWordXml($documentXml);
        $xpath = $this->wordXPath($dom);

        $headerReference = $this->selectHeaderFooterReference($xpath, 'headerReference');
        $footerReference = $this->selectHeaderFooterReference($xpath, 'footerReference');

        return [
            'header' => $this->resolveWordPartReference($zip, $relsMap, $headerReference, 'word/document.xml'),
            'footer' => $this->resolveWordPartReference($zip, $relsMap, $footerReference, 'word/document.xml'),
        ];
    }

    private function extractPackageRelationshipsMap(string $relsXml): array
    {
        $dom = $this->loadPackageXml($relsXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pr', self::PACKAGE_REL_NS);
        $relationships = [];

        foreach ($xpath->query('/pr:Relationships/pr:Relationship') as $relationship) {
            if (! $relationship instanceof DOMElement) {
                continue;
            }

            $id = trim((string) $relationship->getAttribute('Id'));
            $target = trim((string) $relationship->getAttribute('Target'));
            if ($id === '' || $target === '') {
                continue;
            }

            $relationships[$id] = [
                'type' => (string) $relationship->getAttribute('Type'),
                'target' => $target,
            ];
        }

        return $relationships;
    }

    private function selectHeaderFooterReference(DOMXPath $xpath, string $localName): ?string
    {
        $references = $xpath->query('/w:document/w:body/w:sectPr/w:'.$localName);
        if ($references === false) {
            return null;
        }

        $selected = null;
        foreach ($references as $reference) {
            if (! $reference instanceof DOMElement) {
                continue;
            }

            $type = strtolower(trim((string) $reference->getAttributeNS(self::WORD_NS, 'type')));
            $relationshipId = trim((string) $reference->getAttributeNS(self::REL_NS, 'id'));
            if ($relationshipId === '') {
                continue;
            }

            if ($type === 'default' || $type === '') {
                return $relationshipId;
            }

            $selected ??= $relationshipId;
        }

        return $selected;
    }

    private function resolveWordPartReference(ZipArchive $zip, array $relsMap, ?string $relationshipId, string $basePart): array
    {
        if ($relationshipId === null || ! isset($relsMap[$relationshipId])) {
            return [
                'xml' => null,
                'path' => null,
                'relationship_id' => null,
                'image_relationships' => [],
            ];
        }

        $path = $this->resolveZipTargetPath($basePart, (string) $relsMap[$relationshipId]['target']);
        $xml = $zip->getFromName($path);
        if ($xml === false) {
            return [
                'xml' => null,
                'path' => null,
                'relationship_id' => null,
                'image_relationships' => [],
            ];
        }

        $relsPath = trim((string) dirname($path), '/')
            .'/_rels/'
            .basename($path)
            .'.rels';
        $partRelsXml = $zip->getFromName($relsPath) ?: null;

        return [
            'xml' => $xml,
            'path' => $path,
            'relationship_id' => $relationshipId,
            'image_relationships' => $this->extractImageRelationshipsForPart($zip, $partRelsXml, $path),
        ];
    }

    private function resolveZipTargetPath(string $basePart, string $target): string
    {
        $normalizedTarget = str_replace('\\', '/', trim($target));

        if ($normalizedTarget === '') {
            return '';
        }

        if (str_starts_with($normalizedTarget, '/')) {
            return ltrim($normalizedTarget, '/');
        }

        $segments = explode('/', trim((string) dirname($basePart), '/'));
        foreach (explode('/', $normalizedTarget) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function mimeTypeForExtension(string $extension): string
    {
        return match (strtolower($extension)) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }

    private function extractSectPrXml(string $documentXml): ?string
    {
        $dom = $this->loadWordXml($documentXml);
        $xpath = $this->wordXPath($dom);
        $node = $xpath->query('/w:document/w:body/w:sectPr')->item(0);

        return $node instanceof DOMElement ? $dom->saveXML($node) : null;
    }

    private function analyzePackageRewriteSafety(array $package, array $renderedParts = []): array
    {
        $blockingFeatures = [];
        $warnings = [];

        foreach ($this->advancedPackageParts($package['entries'] ?? []) as $feature) {
            $blockingFeatures[] = $feature;
        }

        foreach ([
            'documento principal' => $package['document_xml'] ?? null,
            'encabezado' => $package['header_part']['xml'] ?? null,
            'pie de pagina' => $package['footer_part']['xml'] ?? null,
        ] as $label => $xml) {
            if (! is_string($xml) || trim($xml) === '') {
                continue;
            }

            $blockingFeatures = array_merge($blockingFeatures, $this->advancedWordXmlFeatures($xml, $label));
        }

        foreach ($renderedParts as $part) {
            if ((int) ($part['stats']['unsupported_blocks'] ?? 0) > 0) {
                $blockingFeatures[] = 'bloques no soportados durante la conversion HTML';
            }
        }

        $blockingFeatures = array_values(array_unique($blockingFeatures));

        if ($blockingFeatures !== []) {
            $warnings[] = 'Este DOCX se abre en modo protegido: contiene estructura avanzada que el editor web no puede reescribir sin riesgo de cambiar formato o estructura.';
        }

        return [
            'safe_to_save' => $blockingFeatures === [],
            'blocking_features' => $blockingFeatures,
            'warnings' => $warnings,
        ];
    }

    private function advancedPackageParts(array $entries): array
    {
        $features = [];
        $patterns = [
            '#^word/comments#' => 'comentarios de Word',
            '#^word/footnotes\.xml$#' => 'notas al pie',
            '#^word/endnotes\.xml$#' => 'notas finales',
            '#^word/charts/#' => 'graficas incrustadas',
            '#^word/diagrams/#' => 'SmartArt o diagramas',
            '#^word/embeddings/#' => 'objetos OLE incrustados',
            '#^word/glossary/#' => 'bloques rapidos de Word',
        ];

        foreach ($entries as $entry) {
            foreach ($patterns as $pattern => $label) {
                if (preg_match($pattern, (string) $entry) === 1) {
                    $features[] = $label;
                }
            }
        }

        return array_values(array_unique($features));
    }

    private function advancedWordXmlFeatures(string $xml, string $label): array
    {
        $dom = $this->loadWordXml($xml);
        $features = [];
        $advancedElements = [
            'altChunk' => 'contenido HTML/externo importado',
            'sdt' => 'controles de contenido',
            'fldSimple' => 'campos dinamicos',
            'instrText' => 'campos dinamicos',
            'hyperlink' => 'hipervinculos nativos',
            'commentRangeStart' => 'comentarios de Word',
            'commentRangeEnd' => 'comentarios de Word',
            'commentReference' => 'comentarios de Word',
            'footnoteReference' => 'notas al pie',
            'endnoteReference' => 'notas finales',
            'ins' => 'control de cambios',
            'del' => 'control de cambios',
            'moveFrom' => 'control de cambios',
            'moveTo' => 'control de cambios',
            'smartTag' => 'etiquetas inteligentes',
            'customXml' => 'XML personalizado',
            'object' => 'objetos incrustados',
            'pict' => 'imagenes o formas heredadas',
            'txbxContent' => 'cuadros de texto',
            'oMath' => 'ecuaciones',
            'oMathPara' => 'ecuaciones',
        ];

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if (isset($advancedElements[$element->localName])) {
                $features[] = $label.': '.$advancedElements[$element->localName];
            }

            if ($element->localName === 'anchor') {
                $features[] = $label.': imagenes o formas flotantes';
            }
        }

        $xpath = $this->wordXPath($dom);
        if ($xpath->query('//w:tc/w:tbl')->length > 0) {
            $features[] = $label.': tablas anidadas';
        }

        if ($xpath->query('/w:document/w:body/w:p/w:pPr/w:sectPr')->length > 0) {
            $features[] = $label.': multiples secciones de pagina';
        }

        return array_values(array_unique($features));
    }

    private function documentXmlToHtml(
        string $documentXml,
        ?string $numberingXml,
        bool $hasExtraParts,
        array $imageRelationships
    ): array {
        $dom = $this->loadWordXml($documentXml);
        $xpath = $this->wordXPath($dom);
        $body = $xpath->query('/w:document/w:body')->item(0);

        if (! $body instanceof DOMElement) {
            throw new RuntimeException('El documento DOCX no contiene un cuerpo valido.');
        }

        return $this->renderWordContainerToHtml($body, $xpath, $numberingXml, $imageRelationships);
    }

    private function wordPartXmlToHtml(
        string $partXml,
        ?string $numberingXml,
        array $imageRelationships,
        string $partType
    ): array {
        $dom = $this->loadWordXml($partXml);
        $xpath = $this->wordXPath($dom);
        $container = $xpath->query('/w:'.($partType === 'header' ? 'hdr' : 'ftr'))->item(0);

        if (! $container instanceof DOMElement) {
            throw new RuntimeException('No se pudo interpretar el '.($partType === 'header' ? 'encabezado' : 'pie de pagina').' del DOCX.');
        }

        return $this->renderWordContainerToHtml($container, $xpath, $numberingXml, $imageRelationships);
    }

    private function renderWordContainerToHtml(
        DOMElement $container,
        DOMXPath $xpath,
        ?string $numberingXml,
        array $imageRelationships,
        array $initialWarnings = []
    ): array {
        $numberingFormats = $this->extractNumberingFormats($numberingXml);
        $warnings = $initialWarnings;
        $blocks = [];
        $listBuffer = [];
        $listType = null;
        $stats = [
            'paragraphs' => 0,
            'headings' => 0,
            'list_items' => 0,
            'images' => 0,
            'tables' => 0,
            'unsupported_blocks' => 0,
        ];

        foreach ($container->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === 'sectPr') {
                continue;
            }

            if ($child->localName === 'tbl') {
                $this->flushListBuffer($blocks, $listBuffer, $listType);
                $tableHtml = $this->renderTableElementToHtml($child, $xpath, $numberingFormats, $imageRelationships, $warnings);
                if ($tableHtml === null) {
                    $warnings[] = 'Se detecto una tabla DOCX demasiado compleja y se dejo como referencia no editable.';
                    $blocks[] = '<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" contenteditable="false" data-docx-unsupported="table">Tabla detectada: se muestra como referencia, pero no es editable en esta fase.</div>';
                    $stats['unsupported_blocks']++;

                    continue;
                }

                $blocks[] = $tableHtml;
                $stats['tables']++;

                continue;
            }

            if ($child->localName !== 'p') {
                $this->flushListBuffer($blocks, $listBuffer, $listType);
                $warnings[] = 'Se detectaron elementos DOCX no soportados que podrian simplificarse al guardar.';
                $stats['unsupported_blocks']++;

                continue;
            }

            $parsed = $this->parseParagraphElement($child, $xpath, $numberingFormats, $imageRelationships, $warnings);
            if ($parsed === null) {
                continue;
            }

            $stats['images'] += $parsed['image_count'];

            if ($parsed['type'] === 'ul-item' || $parsed['type'] === 'ol-item') {
                $itemListType = $parsed['type'] === 'ol-item' ? 'ol' : 'ul';

                if ($listType !== null && $listType !== $itemListType) {
                    $this->flushListBuffer($blocks, $listBuffer, $listType);
                }

                $listType = $itemListType;
                $listBuffer[] = $this->buildHtmlBlockElement(
                    'li',
                    $parsed['html'],
                    $parsed['presentation'],
                    [
                        'data-docx-list-level' => (string) $parsed['level'],
                    ],
                    $this->listLevelAttribute($parsed['level'])
                );
                $stats['list_items']++;

                continue;
            }

            $this->flushListBuffer($blocks, $listBuffer, $listType);
            $blocks[] = $this->buildHtmlBlockElement($parsed['type'], $parsed['html'], $parsed['presentation']);

            if (str_starts_with($parsed['type'], 'h')) {
                $stats['headings']++;
            } else {
                $stats['paragraphs']++;
            }
        }

        $this->flushListBuffer($blocks, $listBuffer, $listType);

        if ($blocks === []) {
            $blocks[] = '<p><br></p>';
        }

        return [
            'html' => implode("\n", $blocks),
            'warnings' => array_values(array_unique($warnings)),
            'stats' => $stats,
        ];
    }

    private function listLevelAttribute(int $level): string
    {
        if ($level <= 0) {
            return '';
        }

        return ' style="margin-left:'.(1.5 * $level).'rem"';
    }

    private function flushListBuffer(array &$blocks, array &$listBuffer, ?string &$listType): void
    {
        if ($listBuffer === [] || $listType === null) {
            $listBuffer = [];
            $listType = null;

            return;
        }

        $blocks[] = '<'.$listType.'>'.implode('', $listBuffer).'</'.$listType.'>';
        $listBuffer = [];
        $listType = null;
    }

    private function parseParagraphElement(
        DOMElement $paragraph,
        DOMXPath $xpath,
        array $numberingFormats,
        array $imageRelationships,
        array &$warnings
    ): ?array {
        $html = $this->extractInlineHtml($paragraph, $imageRelationships, $warnings);
        $html = trim($html);

        if ($html === '') {
            $html = '<br>';
        }

        $styleValue = (string) $xpath->evaluate('string(./w:pPr/w:pStyle/@w:val)', $paragraph);
        $numId = (string) $xpath->evaluate('string(./w:pPr/w:numPr/w:numId/@w:val)', $paragraph);
        $level = max(0, (int) $xpath->evaluate('string(./w:pPr/w:numPr/w:ilvl/@w:val)', $paragraph));
        $imageCount = substr_count($html, 'data-docx-kind="image"');

        if ($numId !== '') {
            $format = $numberingFormats[$numId][$level] ?? $numberingFormats[$numId][0] ?? 'bullet';

            return [
                'type' => $format === 'decimal' ? 'ol-item' : 'ul-item',
                'html' => $html,
                'level' => $level,
                'image_count' => $imageCount,
                'presentation' => $this->extractParagraphPresentation($paragraph, $xpath),
            ];
        }

        $normalizedStyle = strtolower($styleValue);

        return [
            'type' => match (true) {
                str_contains($normalizedStyle, 'heading1'), str_contains($normalizedStyle, 'title') => 'h1',
                str_contains($normalizedStyle, 'heading2') => 'h2',
                str_contains($normalizedStyle, 'heading3') => 'h3',
                default => 'p',
            },
            'html' => $html,
            'level' => 0,
            'image_count' => $imageCount,
            'presentation' => $this->extractParagraphPresentation($paragraph, $xpath),
        ];
    }

    private function extractParagraphPresentation(DOMElement $paragraph, DOMXPath $xpath): array
    {
        $alignment = trim((string) $xpath->evaluate('string(./w:pPr/w:jc/@w:val)', $paragraph));
        $indentLeft = (int) $xpath->evaluate('string(./w:pPr/w:ind/@w:left)', $paragraph);
        $spacingBefore = (int) $xpath->evaluate('string(./w:pPr/w:spacing/@w:before)', $paragraph);
        $spacingAfter = (int) $xpath->evaluate('string(./w:pPr/w:spacing/@w:after)', $paragraph);

        return [
            'alignment' => $alignment !== '' ? $alignment : null,
            'indent_left' => $indentLeft > 0 ? $indentLeft : null,
            'spacing_before' => $spacingBefore > 0 ? $spacingBefore : null,
            'spacing_after' => $spacingAfter > 0 ? $spacingAfter : null,
        ];
    }

    private function buildHtmlBlockElement(
        string $tag,
        string $html,
        array $presentation = [],
        array $extraAttributes = [],
        ?string $extraStyle = null
    ): string {
        $attributes = [];
        $styleParts = [];

        if (($presentation['alignment'] ?? null) !== null) {
            $alignment = (string) $presentation['alignment'];
            $attributes[] = 'data-docx-align="'.htmlspecialchars($alignment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
            $styleParts[] = 'text-align:'.$this->cssTextAlignValue($alignment);
        }

        if (($presentation['indent_left'] ?? null) !== null) {
            $indentLeft = (int) $presentation['indent_left'];
            $attributes[] = 'data-docx-indent-left="'.$indentLeft.'"';
            $styleParts[] = 'margin-left:'.($indentLeft / 20).'pt';
        }

        if (($presentation['spacing_before'] ?? null) !== null) {
            $spacingBefore = (int) $presentation['spacing_before'];
            $attributes[] = 'data-docx-spacing-before="'.$spacingBefore.'"';
            $styleParts[] = 'margin-top:'.($spacingBefore / 20).'pt';
        }

        if (($presentation['spacing_after'] ?? null) !== null) {
            $spacingAfter = (int) $presentation['spacing_after'];
            $attributes[] = 'data-docx-spacing-after="'.$spacingAfter.'"';
            $styleParts[] = 'margin-bottom:'.($spacingAfter / 20).'pt';
        }

        foreach ($extraAttributes as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $attributes[] = $name.'="'.htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        if ($extraStyle !== null && trim($extraStyle) !== '') {
            $styleParts[] = trim($extraStyle);
        }

        if ($styleParts !== []) {
            $attributes[] = 'style="'.htmlspecialchars(implode('; ', $styleParts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return '<'.$tag.($attributes !== [] ? ' '.implode(' ', $attributes) : '').'>'.$html.'</'.$tag.'>';
    }

    private function cssTextAlignValue(string $alignment): string
    {
        return match (strtolower($alignment)) {
            'center' => 'center',
            'right' => 'right',
            'both', 'justify' => 'justify',
            default => 'left',
        };
    }

    private function renderTableElementToHtml(
        DOMElement $table,
        DOMXPath $xpath,
        array $numberingFormats,
        array $imageRelationships,
        array &$warnings
    ): ?string {
        $tablePresentation = $this->extractTablePresentation($table, $xpath);
        $columnWidths = $this->extractTableGridWidths($table, $xpath);
        $tableAttributes = [
            'class' => 'docx-table',
            'data-docx-kind' => 'table',
        ];
        $tableStyles = ['border-collapse:collapse'];

        if (($tablePresentation['width'] ?? null) !== null) {
            $tableAttributes['data-docx-width'] = (string) $tablePresentation['width'];
            $tableAttributes['data-docx-width-type'] = (string) ($tablePresentation['width_type'] ?? 'dxa');
            $tableStyles[] = 'width:'.$this->docxTableWidthToCss(
                (int) $tablePresentation['width'],
                (string) ($tablePresentation['width_type'] ?? 'dxa')
            );
        }

        if (($tablePresentation['layout'] ?? null) === 'fixed') {
            $tableAttributes['data-docx-layout'] = 'fixed';
            $tableStyles[] = 'table-layout:fixed';
        }

        if (($tablePresentation['indent'] ?? null) !== null) {
            $tableAttributes['data-docx-indent'] = (string) $tablePresentation['indent'];
            $tableStyles[] = 'margin-left:'.(((int) $tablePresentation['indent']) / 20).'pt';
        }

        if (($tablePresentation['alignment'] ?? null) !== null) {
            $tableAttributes['data-docx-align'] = (string) $tablePresentation['alignment'];
            $tableStyles[] = $this->tableAlignmentCss((string) $tablePresentation['alignment']);
        }

        foreach (($tablePresentation['cell_margins'] ?? []) as $side => $twips) {
            $tableAttributes['data-docx-cell-margin-'.$side] = (string) $twips;
        }

        foreach (($tablePresentation['borders'] ?? []) as $name => $border) {
            foreach ($border as $property => $value) {
                $tableAttributes['data-docx-border-'.$name.'-'.$property] = (string) $value;
            }
        }

        if ($columnWidths !== []) {
            $tableAttributes['data-docx-grid'] = implode(',', $columnWidths);
        }

        $rowsHtml = [];
        $rowCount = 0;
        $verticalMergeOrigins = [];

        foreach ($xpath->query('./w:tr', $table) as $rowNode) {
            if (! $rowNode instanceof DOMElement) {
                continue;
            }

            $rowAttributes = [];
            $rowStyles = [];
            $rowHeight = $this->extractTableRowHeight($rowNode, $xpath);
            if ($rowHeight !== null) {
                $rowAttributes['data-docx-height'] = (string) $rowHeight;
                $rowStyles[] = 'height:'.($rowHeight / 20).'pt';
            }

            $cellHtml = [];
            $gridColumn = 0;
            foreach ($xpath->query('./w:tc', $rowNode) as $cellNode) {
                if (! $cellNode instanceof DOMElement) {
                    continue;
                }

                $cellPresentation = $this->extractTableCellPresentation($cellNode, $xpath);
                $colspan = max(1, (int) ($cellPresentation['grid_span'] ?? 1));
                $vMerge = $cellPresentation['v_merge'] ?? null;

                if ($vMerge === 'continue' && isset($verticalMergeOrigins[$gridColumn])) {
                    [$originRow, $originCell] = $verticalMergeOrigins[$gridColumn];
                    $rowsHtml[$originRow]['cells'][$originCell]['rowspan']++;
                    $gridColumn += $colspan;

                    continue;
                }

                $renderedCell = $this->renderTableCellBlocksToHtml(
                    $cellNode,
                    $xpath,
                    $numberingFormats,
                    $imageRelationships,
                    $warnings
                );

                $cellDescriptor = [
                    'html' => $renderedCell,
                    'attributes' => $this->tableCellAttributes($cellPresentation, $gridColumn, $colspan),
                    'styles' => $this->tableCellStyles($cellPresentation, $tablePresentation),
                    'colspan' => $colspan,
                    'rowspan' => 1,
                ];

                $cellHtml[] = $cellDescriptor;
                $cellIndex = array_key_last($cellHtml);

                if ($vMerge === 'restart') {
                    for ($offset = 0; $offset < $colspan; $offset++) {
                        $verticalMergeOrigins[$gridColumn + $offset] = [$rowCount, $cellIndex];
                    }
                } else {
                    for ($offset = 0; $offset < $colspan; $offset++) {
                        unset($verticalMergeOrigins[$gridColumn + $offset]);
                    }
                }

                $gridColumn += $colspan;
            }

            if ($cellHtml === []) {
                continue;
            }

            $rowsHtml[] = [
                'attributes' => $rowAttributes,
                'styles' => $rowStyles,
                'cells' => $cellHtml,
            ];
            $rowCount++;
        }

        if ($rowsHtml === []) {
            return null;
        }

        if ($rowCount > 20) {
            $warnings[] = 'La tabla tiene muchas filas; puedes editarla, pero la experiencia puede simplificarse en esta fase.';
        }

        $colgroup = '';
        if ($columnWidths !== []) {
            $columns = array_map(
                fn (int $width) => '<col style="width:'.($width / 20).'pt">',
                $columnWidths
            );
            $colgroup = '<colgroup>'.implode('', $columns).'</colgroup>';
        }

        $renderedRows = array_map(function (array $row): string {
            $cells = array_map(function (array $cell): string {
                $attributes = $cell['attributes'];
                if (($cell['colspan'] ?? 1) > 1) {
                    $attributes['colspan'] = (string) $cell['colspan'];
                }
                if (($cell['rowspan'] ?? 1) > 1) {
                    $attributes['rowspan'] = (string) $cell['rowspan'];
                    $attributes['data-docx-rowspan'] = (string) $cell['rowspan'];
                }

                return '<td'.$this->htmlAttributes($attributes, $cell['styles']).'>'.$cell['html'].'</td>';
            }, $row['cells']);

            return '<tr'.$this->htmlAttributes($row['attributes'], $row['styles']).'>'.implode('', $cells).'</tr>';
        }, $rowsHtml);

        return '<table'.$this->htmlAttributes($tableAttributes, $tableStyles).'>'.$colgroup.'<tbody>'.implode('', $renderedRows).'</tbody></table>';
    }

    private function extractTablePresentation(DOMElement $table, DOMXPath $xpath): array
    {
        $width = trim((string) $xpath->evaluate('string(./w:tblPr/w:tblW/@w:w)', $table));
        $widthType = trim((string) $xpath->evaluate('string(./w:tblPr/w:tblW/@w:type)', $table));
        $layout = trim((string) $xpath->evaluate('string(./w:tblPr/w:tblLayout/@w:type)', $table));
        $indent = trim((string) $xpath->evaluate('string(./w:tblPr/w:tblInd/@w:w)', $table));
        $alignment = trim((string) $xpath->evaluate('string(./w:tblPr/w:jc/@w:val)', $table));
        $cellMargins = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $margin = trim((string) $xpath->evaluate('string(./w:tblPr/w:tblCellMar/w:'.$side.'/@w:w)', $table));
            if ($margin !== '' && ctype_digit($margin)) {
                $cellMargins[$side] = (int) $margin;
            }
        }

        $borders = [];
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $name) {
            $border = $this->extractBorderPresentation($xpath, './w:tblPr/w:tblBorders/w:'.$name, $table);
            if ($border !== null) {
                $borders[$name] = $border;
            }
        }

        return array_filter([
            'width' => $width !== '' && ctype_digit($width) ? (int) $width : null,
            'width_type' => $widthType !== '' ? $widthType : null,
            'layout' => $layout !== '' ? $layout : null,
            'indent' => $indent !== '' && ctype_digit($indent) ? (int) $indent : null,
            'alignment' => $alignment !== '' ? $alignment : null,
            'cell_margins' => $cellMargins,
            'borders' => $borders,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    private function extractTableGridWidths(DOMElement $table, DOMXPath $xpath): array
    {
        $widths = [];

        foreach ($xpath->query('./w:tblGrid/w:gridCol', $table) as $column) {
            if (! $column instanceof DOMElement) {
                continue;
            }

            $width = trim((string) $column->getAttributeNS(self::WORD_NS, 'w'));
            if ($width !== '' && ctype_digit($width)) {
                $widths[] = (int) $width;
            }
        }

        return $widths;
    }

    private function extractTableRowHeight(DOMElement $row, DOMXPath $xpath): ?int
    {
        $height = trim((string) $xpath->evaluate('string(./w:trPr/w:trHeight/@w:val)', $row));

        return $height !== '' && ctype_digit($height) ? (int) $height : null;
    }

    private function extractTableCellPresentation(DOMElement $cell, DOMXPath $xpath): array
    {
        $width = trim((string) $xpath->evaluate('string(./w:tcPr/w:tcW/@w:w)', $cell));
        $widthType = trim((string) $xpath->evaluate('string(./w:tcPr/w:tcW/@w:type)', $cell));
        $gridSpan = trim((string) $xpath->evaluate('string(./w:tcPr/w:gridSpan/@w:val)', $cell));
        $shading = trim((string) $xpath->evaluate('string(./w:tcPr/w:shd/@w:fill)', $cell));
        $verticalAlign = trim((string) $xpath->evaluate('string(./w:tcPr/w:vAlign/@w:val)', $cell));
        $vMergeNode = $xpath->query('./w:tcPr/w:vMerge', $cell)->item(0);
        $vMerge = null;

        if ($vMergeNode instanceof DOMElement) {
            $value = trim((string) $vMergeNode->getAttributeNS(self::WORD_NS, 'val'));
            $vMerge = $value === '' || strtolower($value) === 'continue' ? 'continue' : strtolower($value);
        }

        $margins = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $margin = trim((string) $xpath->evaluate('string(./w:tcPr/w:tcMar/w:'.$side.'/@w:w)', $cell));
            if ($margin !== '' && ctype_digit($margin)) {
                $margins[$side] = (int) $margin;
            }
        }

        $borders = [];
        foreach (['top', 'left', 'bottom', 'right'] as $name) {
            $border = $this->extractBorderPresentation($xpath, './w:tcPr/w:tcBorders/w:'.$name, $cell);
            if ($border !== null) {
                $borders[$name] = $border;
            }
        }

        return array_filter([
            'width' => $width !== '' && ctype_digit($width) ? (int) $width : null,
            'width_type' => $widthType !== '' ? $widthType : null,
            'grid_span' => $gridSpan !== '' && ctype_digit($gridSpan) ? (int) $gridSpan : null,
            'shading' => $this->normalizeColorValue($shading),
            'vertical_align' => $verticalAlign !== '' ? $verticalAlign : null,
            'v_merge' => $vMerge,
            'margins' => $margins,
            'borders' => $borders,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    private function tableCellAttributes(array $presentation, int $gridColumn, int $colspan): array
    {
        $attributes = [
            'data-docx-grid-col' => (string) $gridColumn,
        ];

        if (($presentation['width'] ?? null) !== null) {
            $attributes['data-docx-width'] = (string) $presentation['width'];
            $attributes['data-docx-width-type'] = (string) ($presentation['width_type'] ?? 'dxa');
        }

        if ($colspan > 1) {
            $attributes['data-docx-grid-span'] = (string) $colspan;
        }

        if (($presentation['v_merge'] ?? null) !== null) {
            $attributes['data-docx-v-merge'] = (string) $presentation['v_merge'];
        }

        if (($presentation['shading'] ?? null) !== null) {
            $attributes['data-docx-bg'] = (string) $presentation['shading'];
        }

        if (($presentation['vertical_align'] ?? null) !== null) {
            $attributes['data-docx-valign'] = (string) $presentation['vertical_align'];
        }

        foreach (($presentation['margins'] ?? []) as $side => $twips) {
            $attributes['data-docx-margin-'.$side] = (string) $twips;
        }

        foreach (($presentation['borders'] ?? []) as $name => $border) {
            foreach ($border as $property => $value) {
                $attributes['data-docx-border-'.$name.'-'.$property] = (string) $value;
            }
        }

        return $attributes;
    }

    private function tableCellStyles(array $presentation, array $tablePresentation): array
    {
        $styles = [];

        if (($presentation['width'] ?? null) !== null) {
            $styles[] = 'width:'.$this->docxTableWidthToCss(
                (int) $presentation['width'],
                (string) ($presentation['width_type'] ?? 'dxa')
            );
        }

        if (($presentation['shading'] ?? null) !== null) {
            $styles[] = 'background-color:#'.$presentation['shading'];
        }

        if (($presentation['vertical_align'] ?? null) !== null) {
            $styles[] = 'vertical-align:'.$this->cssVerticalAlignValue((string) $presentation['vertical_align']);
        }

        $margins = $presentation['margins'] ?? $tablePresentation['cell_margins'] ?? [];
        if ($margins !== []) {
            $styles[] = 'padding:'.$this->cellMarginCss($margins);
        }

        foreach (($presentation['borders'] ?? []) as $side => $border) {
            $css = $this->borderPresentationToCss($border);
            if ($css !== null) {
                $styles[] = 'border-'.$side.':'.$css;
            }
        }

        return $styles;
    }

    private function docxTableWidthToCss(int $width, string $type): string
    {
        $normalizedType = strtolower($type);

        if ($normalizedType === 'pct') {
            return max(0.1, $width / 50).'%';
        }

        if ($normalizedType === 'nil') {
            return 'auto';
        }

        return max(0.1, $width / 20).'pt';
    }

    private function tableAlignmentCss(string $alignment): string
    {
        return match (strtolower($alignment)) {
            'center' => 'margin-left:auto; margin-right:auto',
            'right', 'end' => 'margin-left:auto; margin-right:0',
            default => 'margin-left:0; margin-right:auto',
        };
    }

    private function cssVerticalAlignValue(string $value): string
    {
        return match (strtolower($value)) {
            'center' => 'middle',
            'bottom' => 'bottom',
            default => 'top',
        };
    }

    private function cellMarginCss(array $margins): string
    {
        $top = (int) ($margins['top'] ?? 0);
        $right = (int) ($margins['right'] ?? 0);
        $bottom = (int) ($margins['bottom'] ?? 0);
        $left = (int) ($margins['left'] ?? 0);

        return ($top / 20).'pt '.($right / 20).'pt '.($bottom / 20).'pt '.($left / 20).'pt';
    }

    private function extractBorderPresentation(DOMXPath $xpath, string $query, DOMElement $context): ?array
    {
        $border = $xpath->query($query, $context)->item(0);
        if (! $border instanceof DOMElement) {
            return null;
        }

        $value = trim((string) $border->getAttributeNS(self::WORD_NS, 'val'));
        if ($value === '') {
            return null;
        }

        $size = trim((string) $border->getAttributeNS(self::WORD_NS, 'sz'));
        $space = trim((string) $border->getAttributeNS(self::WORD_NS, 'space'));
        $color = $this->normalizeColorValue((string) $border->getAttributeNS(self::WORD_NS, 'color'));

        return array_filter([
            'val' => $value,
            'sz' => $size !== '' && ctype_digit($size) ? (int) $size : null,
            'space' => $space !== '' && ctype_digit($space) ? (int) $space : null,
            'color' => $color,
        ], static fn ($item) => $item !== null && $item !== '');
    }

    private function borderPresentationToCss(array $border): ?string
    {
        $value = strtolower((string) ($border['val'] ?? 'single'));
        if (in_array($value, ['nil', 'none'], true)) {
            return '0 none transparent';
        }

        $size = max(1, (int) round(((int) ($border['sz'] ?? 4)) / 8 * 1.333));
        $style = match ($value) {
            'dashed', 'dashSmallGap', 'dotDash', 'dotDotDash' => 'dashed',
            'dotted' => 'dotted',
            'double' => 'double',
            default => 'solid',
        };
        $color = (string) ($border['color'] ?? 'BFC6D4');

        return $size.'px '.$style.' #'.$color;
    }

    private function htmlAttributes(array $attributes, array $styles = []): string
    {
        if ($styles !== []) {
            $attributes['style'] = implode('; ', array_filter($styles));
        }

        $html = [];
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $html[] = $name.'="'.htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return $html !== [] ? ' '.implode(' ', $html) : '';
    }

    private function renderTableCellBlocksToHtml(
        DOMElement $cell,
        DOMXPath $xpath,
        array $numberingFormats,
        array $imageRelationships,
        array &$warnings
    ): string {
        $blocks = [];
        $listBuffer = [];
        $listType = null;

        foreach ($cell->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === 'tbl') {
                $warnings[] = 'Las tablas anidadas se muestran como referencia dentro de la celda y no se reescriben en esta fase.';
                $this->flushListBuffer($blocks, $listBuffer, $listType);
                $blocks[] = '<div class="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800" contenteditable="false" data-docx-unsupported="nested-table">Tabla anidada no editable</div>';

                continue;
            }

            if ($child->localName !== 'p') {
                continue;
            }

            $parsed = $this->parseParagraphElement($child, $xpath, $numberingFormats, $imageRelationships, $warnings);
            if ($parsed === null) {
                continue;
            }

            if (in_array($parsed['type'], ['ul-item', 'ol-item'], true)) {
                $itemListType = $parsed['type'] === 'ol-item' ? 'ol' : 'ul';

                if ($listType !== null && $listType !== $itemListType) {
                    $this->flushListBuffer($blocks, $listBuffer, $listType);
                }

                $listType = $itemListType;
                $listBuffer[] = $this->buildHtmlBlockElement(
                    'li',
                    $parsed['html'],
                    $parsed['presentation'],
                    ['data-docx-list-level' => (string) $parsed['level']],
                    $this->listLevelAttribute($parsed['level'])
                );

                continue;
            }

            $this->flushListBuffer($blocks, $listBuffer, $listType);
            $blocks[] = $this->buildHtmlBlockElement($parsed['type'], $parsed['html'], $parsed['presentation']);
        }

        $this->flushListBuffer($blocks, $listBuffer, $listType);

        return $blocks !== [] ? implode('', $blocks) : '<p><br></p>';
    }

    private function extractNumberingFormats(?string $numberingXml): array
    {
        if (! $numberingXml) {
            return [];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($numberingXml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);

        $abstractMap = [];
        foreach ($xpath->query('/w:numbering/w:abstractNum') as $abstractNode) {
            if (! $abstractNode instanceof DOMElement) {
                continue;
            }

            $abstractId = $abstractNode->getAttributeNS(self::WORD_NS, 'abstractNumId');
            $abstractMap[$abstractId] = [];

            foreach ($xpath->query('./w:lvl', $abstractNode) as $levelNode) {
                if (! $levelNode instanceof DOMElement) {
                    continue;
                }

                $level = (int) $levelNode->getAttributeNS(self::WORD_NS, 'ilvl');
                $format = (string) $xpath->evaluate('string(./w:numFmt/@w:val)', $levelNode);
                $abstractMap[$abstractId][$level] = $format ?: 'bullet';
            }
        }

        $map = [];
        foreach ($xpath->query('/w:numbering/w:num') as $numNode) {
            if (! $numNode instanceof DOMElement) {
                continue;
            }

            $numId = $numNode->getAttributeNS(self::WORD_NS, 'numId');
            $abstractId = (string) $xpath->evaluate('string(./w:abstractNumId/@w:val)', $numNode);
            $map[$numId] = $abstractMap[$abstractId] ?? [0 => 'bullet'];
        }

        return $map;
    }

    private function extractInlineHtml(DOMNode $node, array $imageRelationships, array &$warnings): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === 'r') {
                $html .= $this->runToHtml($child, $imageRelationships, $warnings);

                continue;
            }

            if ($child->localName === 'hyperlink') {
                $html .= $this->extractInlineHtml($child, $imageRelationships, $warnings);

                continue;
            }

            if (in_array($child->localName, ['fldSimple', 'smartTag'], true)) {
                $warnings[] = 'Se detectaron campos o etiquetas especiales de Word que no se editan de forma nativa en esta fase.';
                $html .= $this->extractInlineHtml($child, $imageRelationships, $warnings);
            }
        }

        return $html;
    }

    private function runToHtml(DOMElement $run, array $imageRelationships, array &$warnings): string
    {
        $formatting = $this->extractRunFormatting($run);
        $html = '';

        foreach ($run->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === 't') {
                $html .= $this->wrapStyledText($child->textContent, $formatting);

                continue;
            }

            if (in_array($child->localName, ['br', 'cr'], true)) {
                $html .= '<br>';

                continue;
            }

            if ($child->localName === 'tab') {
                $html .= $this->wrapStyledText('    ', $formatting);

                continue;
            }

            if (in_array($child->localName, ['drawing', 'object', 'pict'], true)) {
                $html .= $this->renderEmbeddedObjectToHtml($child, $imageRelationships, $warnings);
            }
        }

        return $html;
    }

    private function extractRunFormatting(DOMElement $run): array
    {
        $formatting = [
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'font_family' => null,
            'font_size_half_points' => null,
            'color' => null,
        ];

        $rPr = null;
        foreach ($run->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'rPr') {
                $rPr = $child;
                break;
            }
        }

        if (! $rPr instanceof DOMElement) {
            return $formatting;
        }

        $formatting['bold'] = $this->elementExists($rPr, ['b', 'bCs']);
        $formatting['italic'] = $this->elementExists($rPr, ['i', 'iCs']);
        $formatting['underline'] = $this->hasUnderline($rPr);
        $formatting['font_family'] = $this->extractRunFontFamily($rPr);
        $formatting['font_size_half_points'] = $this->extractRunFontSize($rPr);
        $formatting['color'] = $this->extractRunColor($rPr);

        return $formatting;
    }

    private function extractRunFontFamily(DOMElement $rPr): ?string
    {
        foreach ($rPr->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'rFonts') {
                continue;
            }

            foreach (['ascii', 'hAnsi', 'cs', 'eastAsia'] as $attribute) {
                $value = trim((string) $child->getAttributeNS(self::WORD_NS, $attribute));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractRunFontSize(DOMElement $rPr): ?int
    {
        foreach ($rPr->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'sz') {
                continue;
            }

            $value = (int) $child->getAttributeNS(self::WORD_NS, 'val');
            if ($value > 0) {
                return $value;
            }
        }

        return null;
    }

    private function extractRunColor(DOMElement $rPr): ?string
    {
        foreach ($rPr->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'color') {
                continue;
            }

            $value = strtoupper(trim((string) $child->getAttributeNS(self::WORD_NS, 'val')));
            if ($value !== '' && $value !== 'AUTO' && preg_match('/^[0-9A-F]{6}$/', $value) === 1) {
                return $value;
            }
        }

        return null;
    }

    private function wrapStyledText(string $text, array $formatting): string
    {
        if ($text === '') {
            return '';
        }

        $escapedText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $styleParts = [];
        $dataAttributes = [];

        if (! empty($formatting['bold'])) {
            $styleParts[] = 'font-weight:700';
        }

        if (! empty($formatting['italic'])) {
            $styleParts[] = 'font-style:italic';
        }

        if (! empty($formatting['underline'])) {
            $styleParts[] = 'text-decoration:underline';
        }

        $fontFamily = $this->normalizeFontFamily($formatting['font_family'] ?? null);
        if ($fontFamily !== null) {
            $styleParts[] = 'font-family:'.$this->cssFontFamilyValue($fontFamily);
            $dataAttributes[] = 'data-docx-font-family="'.htmlspecialchars($fontFamily, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        $fontSize = (int) ($formatting['font_size_half_points'] ?? 0);
        if ($fontSize > 0) {
            $styleParts[] = 'font-size:'.($fontSize / 2).'pt';
            $dataAttributes[] = 'data-docx-font-size="'.$fontSize.'"';
        }

        $color = $this->normalizeColorValue($formatting['color'] ?? null);
        if ($color !== null) {
            $styleParts[] = 'color:#'.$color;
            $dataAttributes[] = 'data-docx-color="'.$color.'"';
        }

        if ($styleParts === [] && $dataAttributes === []) {
            return $escapedText;
        }

        $attributes = $dataAttributes;
        $attributes[] = 'style="'.htmlspecialchars(implode('; ', $styleParts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';

        return '<span '.implode(' ', $attributes).'>'.$escapedText.'</span>';
    }

    private function normalizeFontFamily(?string $fontFamily): ?string
    {
        if ($fontFamily === null) {
            return null;
        }

        $family = trim($fontFamily, " \t\n\r\0\x0B'\"");
        if ($family === '') {
            return null;
        }

        $primary = explode(',', $family)[0] ?? $family;
        $primary = trim($primary, " \t\n\r\0\x0B'\"");

        return $primary !== '' ? $primary : null;
    }

    private function cssFontFamilyValue(string $fontFamily): string
    {
        return '"'.addcslashes($fontFamily, '"\\').'"';
    }

    private function renderEmbeddedObjectToHtml(DOMElement $node, array $imageRelationships, array &$warnings): string
    {
        $relationshipId = $this->findEmbeddedRelationshipId($node);
        if ($relationshipId === null) {
            $warnings[] = 'Se detecto un objeto incrustado que no se pudo vincular a una imagen visible.';

            return '<span class="inline-flex rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800" contenteditable="false" data-docx-unsupported="image">Imagen no soportada</span>';
        }

        $image = $imageRelationships[$relationshipId] ?? null;
        if ($image === null) {
            $warnings[] = 'Se detecto una imagen incrustada, pero no se pudo resolver su contenido binario.';

            return '<span class="inline-flex rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800" contenteditable="false" data-docx-unsupported="image">Imagen no disponible</span>';
        }

        $drawingXml = $node->ownerDocument?->saveXML($node);
        if ($drawingXml === false || $drawingXml === null) {
            return '<img src="'.htmlspecialchars($image['data_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" alt="Imagen incrustada" contenteditable="false" style="max-width:100%" />';
        }

        $size = $this->extractDrawingSize($node);
        $alt = $this->extractDrawingAltText($node);
        $style = ['max-width:100%', 'height:auto'];

        if ($size['width_px'] !== null) {
            $style[] = 'width:'.$size['width_px'].'px';
        }

        return '<img src="'
            .htmlspecialchars($image['data_uri'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            .'" alt="'
            .htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            .'" contenteditable="false" data-docx-kind="image" data-docx-rel-id="'
            .htmlspecialchars($relationshipId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            .'" data-docx-drawing="'
            .base64_encode($drawingXml)
            .'"'
            .($size['cx'] !== null ? ' data-docx-cx="'.$size['cx'].'"' : '')
            .($size['cy'] !== null ? ' data-docx-cy="'.$size['cy'].'"' : '')
            .' style="'
            .htmlspecialchars(implode('; ', $style), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            .'" />';
    }

    private function findEmbeddedRelationshipId(DOMElement $node): ?string
    {
        $queue = [$node];

        while ($queue !== []) {
            /** @var DOMElement $current */
            $current = array_shift($queue);
            $embed = trim((string) $current->getAttributeNS(self::REL_NS, 'embed'));
            if ($embed !== '') {
                return $embed;
            }

            foreach ($current->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $queue[] = $child;
                }
            }
        }

        return null;
    }

    private function extractDrawingSize(DOMElement $node): array
    {
        $queue = [$node];

        while ($queue !== []) {
            /** @var DOMElement $current */
            $current = array_shift($queue);
            if ($current->localName === 'extent') {
                $cx = (int) $current->getAttribute('cx');
                $cy = (int) $current->getAttribute('cy');

                return [
                    'cx' => $cx > 0 ? $cx : null,
                    'cy' => $cy > 0 ? $cy : null,
                    'width_px' => $cx > 0 ? (int) round($cx / 9525) : null,
                    'height_px' => $cy > 0 ? (int) round($cy / 9525) : null,
                ];
            }

            foreach ($current->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $queue[] = $child;
                }
            }
        }

        return [
            'cx' => null,
            'cy' => null,
            'width_px' => null,
            'height_px' => null,
        ];
    }

    private function extractDrawingAltText(DOMElement $node): string
    {
        $queue = [$node];

        while ($queue !== []) {
            /** @var DOMElement $current */
            $current = array_shift($queue);
            if ($current->localName === 'docPr') {
                $description = trim((string) $current->getAttribute('descr'));
                $name = trim((string) $current->getAttribute('name'));

                return $description !== '' ? $description : ($name !== '' ? $name : 'Imagen incrustada');
            }

            foreach ($current->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $queue[] = $child;
                }
            }
        }

        return 'Imagen incrustada';
    }

    private function elementExists(DOMElement $element, array $localNames): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && in_array($child->localName, $localNames, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasUnderline(DOMElement $rPr): bool
    {
        foreach ($rPr->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'u') {
                continue;
            }

            $value = strtolower((string) $child->getAttributeNS(self::WORD_NS, 'val'));

            return $value === '' || $value !== 'none';
        }

        return false;
    }

    private function htmlToBlocks(string $html): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<!DOCTYPE html><html><body>'.$html.'</body></html>';
        $dom->loadHTML(mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body instanceof DOMElement) {
            throw new RuntimeException('No se pudo interpretar el contenido del editor DOCX.');
        }

        $blocks = [];
        $warnings = [];

        foreach ($body->childNodes as $child) {
            $this->collectBlocksFromHtmlNode($child, $blocks, $warnings);
        }

        if ($blocks === []) {
            $blocks[] = [
                'type' => 'p',
                'segments' => [],
            ];
        }

        return [
            'blocks' => $blocks,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function collectBlocksFromHtmlNode(DOMNode $node, array &$blocks, array &$warnings): void
    {
        if ($node instanceof DOMText) {
            $text = trim($node->wholeText);
            if ($text !== '') {
                $blocks[] = [
                    'type' => 'p',
                    'segments' => $this->mergeSegments([[
                        'type' => 'text',
                        'text' => $text,
                        'bold' => false,
                        'italic' => false,
                        'underline' => false,
                        'font_family' => null,
                        'font_size_half_points' => null,
                        'color' => null,
                    ]]),
                ];
            }

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        if ($node->hasAttribute('data-docx-unsupported')) {
            $warnings[] = 'Se conservaron advertencias visuales de partes no soportadas del DOCX original. Esas partes no se reescriben en la nueva version.';

            return;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, ['p', 'div', 'section', 'article'], true)) {
            $blocks[] = [
                'type' => 'p',
                'segments' => $this->extractSegmentsFromHtmlNode($node),
                'presentation' => $this->extractBlockPresentationFromHtmlElement($node),
            ];

            return;
        }

        if (in_array($tag, ['h1', 'h2', 'h3'], true)) {
            $blocks[] = [
                'type' => $tag,
                'segments' => $this->extractSegmentsFromHtmlNode($node),
                'presentation' => $this->extractBlockPresentationFromHtmlElement($node),
            ];

            return;
        }

        if ($tag === 'table') {
            $tableBlock = $this->extractTableBlockFromHtmlElement($node, $warnings);
            if ($tableBlock !== null) {
                $blocks[] = $tableBlock;
            }

            return;
        }

        if (in_array($tag, ['ul', 'ol'], true)) {
            $this->collectListBlocks($node, $blocks, 0);

            return;
        }

        if ($tag === 'img') {
            $blocks[] = [
                'type' => 'p',
                'segments' => $this->extractSegmentsFromHtmlNode($node),
                'presentation' => $this->extractBlockPresentationFromHtmlElement($node),
            ];

            return;
        }

        foreach ($node->childNodes as $child) {
            $this->collectBlocksFromHtmlNode($child, $blocks, $warnings);
        }
    }

    private function extractSegmentsFromHtmlNode(DOMNode $node, array $styles = []): array
    {
        $styles = array_merge([
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'font_family' => null,
            'font_size_half_points' => null,
            'color' => null,
        ], $styles);

        if ($node instanceof DOMText) {
            if ($node->wholeText === '') {
                return [];
            }

            return [[
                'type' => 'text',
                'text' => $node->wholeText,
                'bold' => $styles['bold'],
                'italic' => $styles['italic'],
                'underline' => $styles['underline'],
                'font_family' => $styles['font_family'],
                'font_size_half_points' => $styles['font_size_half_points'],
                'color' => $styles['color'],
            ]];
        }

        if (! $node instanceof DOMElement) {
            return [];
        }

        if ($node->hasAttribute('data-docx-unsupported')) {
            return [];
        }

        $tag = strtolower($node->tagName);

        if ($tag === 'img') {
            $drawingXml = trim((string) $node->getAttribute('data-docx-drawing'));
            if ($drawingXml === '') {
                return [];
            }

            return [[
                'type' => 'image',
                'drawing_xml' => $drawingXml,
                'alt' => $node->getAttribute('alt'),
                'cx' => $node->getAttribute('data-docx-cx'),
                'cy' => $node->getAttribute('data-docx-cy'),
            ]];
        }

        if ($tag === 'br') {
            return [[
                'type' => 'break',
            ]];
        }

        if (in_array($tag, ['ul', 'ol'], true)) {
            return [];
        }

        $nextStyles = $this->mergeStyleState($styles, $node);

        $segments = [];

        foreach ($node->childNodes as $child) {
            $segments = array_merge($segments, $this->extractSegmentsFromHtmlNode($child, $nextStyles));
        }

        return $this->mergeSegments($segments);
    }

    private function collectListBlocks(DOMElement $listNode, array &$blocks, int $level): void
    {
        $tag = strtolower($listNode->tagName);

        foreach ($listNode->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $segments = [];
            $nestedLists = [];

            foreach ($child->childNodes as $liChild) {
                if ($liChild instanceof DOMElement && in_array(strtolower($liChild->tagName), ['ul', 'ol'], true)) {
                    $nestedLists[] = $liChild;

                    continue;
                }

                $segments = array_merge($segments, $this->extractSegmentsFromHtmlNode($liChild));
            }

            $segments = $this->mergeSegments($segments);

            if ($segments === []) {
                $segments[] = [
                    'type' => 'text',
                    'text' => '',
                    'bold' => false,
                    'italic' => false,
                    'underline' => false,
                    'font_family' => null,
                    'font_size_half_points' => null,
                    'color' => null,
                ];
            }

            $itemLevel = max($level, (int) $child->getAttribute('data-docx-list-level'));

            $blocks[] = [
                'type' => $tag === 'ol' ? 'ol-item' : 'ul-item',
                'level' => $itemLevel,
                'segments' => $segments,
                'presentation' => $this->extractBlockPresentationFromHtmlElement($child),
            ];

            foreach ($nestedLists as $nestedList) {
                $this->collectListBlocks($nestedList, $blocks, $itemLevel + 1);
            }
        }
    }

    private function extractTableBlockFromHtmlElement(DOMElement $table, array &$warnings): ?array
    {
        $rows = [];

        foreach ($table->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['tbody', 'thead', 'tfoot'], true)) {
                foreach ($child->childNodes as $rowNode) {
                    if ($rowNode instanceof DOMElement && strtolower($rowNode->tagName) === 'tr') {
                        $parsedRow = $this->extractTableRowFromHtmlElement($rowNode, $warnings);
                        if ($parsedRow !== null) {
                            $rows[] = $parsedRow;
                        }
                    }
                }

                continue;
            }

            if ($child instanceof DOMElement && strtolower($child->tagName) === 'tr') {
                $parsedRow = $this->extractTableRowFromHtmlElement($child, $warnings);
                if ($parsedRow !== null) {
                    $rows[] = $parsedRow;
                }
            }
        }

        return $rows !== [] ? [
            'type' => 'table',
            'rows' => $rows,
            'presentation' => $this->extractTablePresentationFromHtmlElement($table),
        ] : null;
    }

    private function extractTableRowFromHtmlElement(DOMElement $rowNode, array &$warnings): ?array
    {
        $cells = [];

        foreach ($rowNode->childNodes as $cellNode) {
            if (! $cellNode instanceof DOMElement || ! in_array(strtolower($cellNode->tagName), ['td', 'th'], true)) {
                continue;
            }

            $cellBlocks = [];
            foreach ($cellNode->childNodes as $child) {
                $this->collectBlocksFromHtmlNode($child, $cellBlocks, $warnings);
            }

            if ($cellBlocks === []) {
                $cellBlocks[] = [
                    'type' => 'p',
                    'segments' => [[
                        'type' => 'text',
                        'text' => trim($cellNode->textContent),
                        'bold' => false,
                        'italic' => false,
                        'underline' => false,
                        'font_family' => null,
                        'font_size_half_points' => null,
                        'color' => null,
                    ]],
                    'presentation' => [],
                ];
            }

            $cells[] = [
                'blocks' => $cellBlocks,
                'presentation' => $this->extractTableCellPresentationFromHtmlElement($cellNode),
            ];
        }

        return $cells !== [] ? [
            'cells' => $cells,
            'presentation' => $this->extractTableRowPresentationFromHtmlElement($rowNode),
        ] : null;
    }

    private function extractTablePresentationFromHtmlElement(DOMElement $table): array
    {
        $styleMap = $this->parseInlineStyleMap((string) $table->getAttribute('style'));
        $grid = array_values(array_filter(array_map(
            static fn (string $value): ?int => ctype_digit(trim($value)) ? (int) trim($value) : null,
            explode(',', (string) $table->getAttribute('data-docx-grid'))
        ), static fn (?int $value): bool => $value !== null && $value > 0));

        return array_filter([
            'width' => $this->normalizeTwipsValue($table->getAttribute('data-docx-width') ?: ($styleMap['width'] ?? null)),
            'width_type' => $table->getAttribute('data-docx-width-type') ?: null,
            'layout' => $table->getAttribute('data-docx-layout') ?: null,
            'indent' => $this->normalizeTwipsValue($table->getAttribute('data-docx-indent') ?: ($styleMap['margin-left'] ?? null)),
            'alignment' => $table->getAttribute('data-docx-align') ?: null,
            'cell_margins' => $this->extractDirectionalTwipsAttributes($table, 'data-docx-cell-margin-'),
            'borders' => $this->extractBorderPresentationFromHtmlElement($table),
            'grid' => $grid,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    private function extractTableRowPresentationFromHtmlElement(DOMElement $row): array
    {
        $styleMap = $this->parseInlineStyleMap((string) $row->getAttribute('style'));

        return array_filter([
            'height' => $this->normalizeTwipsValue($row->getAttribute('data-docx-height') ?: ($styleMap['height'] ?? null)),
        ], static fn ($value) => $value !== null);
    }

    private function extractTableCellPresentationFromHtmlElement(DOMElement $cell): array
    {
        $styleMap = $this->parseInlineStyleMap((string) $cell->getAttribute('style'));
        $colspan = $cell->getAttribute('data-docx-grid-span') ?: $cell->getAttribute('colspan');
        $rowspan = $cell->getAttribute('data-docx-rowspan') ?: $cell->getAttribute('rowspan');
        $background = $cell->getAttribute('data-docx-bg');
        if ($background === '') {
            $background = $styleMap['background-color'] ?? null;
        }

        $verticalAlign = $cell->getAttribute('data-docx-valign');
        if ($verticalAlign === '') {
            $verticalAlign = $styleMap['vertical-align'] ?? null;
        }

        return array_filter([
            'width' => $this->normalizeTwipsValue($cell->getAttribute('data-docx-width') ?: ($styleMap['width'] ?? null)),
            'width_type' => $cell->getAttribute('data-docx-width-type') ?: null,
            'grid_span' => $colspan !== '' && ctype_digit($colspan) ? max(1, (int) $colspan) : null,
            'row_span' => $rowspan !== '' && ctype_digit($rowspan) ? max(1, (int) $rowspan) : null,
            'grid_col' => $cell->getAttribute('data-docx-grid-col') !== '' && ctype_digit($cell->getAttribute('data-docx-grid-col'))
                ? (int) $cell->getAttribute('data-docx-grid-col')
                : null,
            'v_merge' => $cell->getAttribute('data-docx-v-merge') ?: null,
            'shading' => $this->normalizeColorValue($background),
            'vertical_align' => $this->normalizeTableVerticalAlignment($verticalAlign !== '' ? $verticalAlign : null),
            'margins' => $this->extractDirectionalTwipsAttributes($cell, 'data-docx-margin-'),
            'borders' => $this->extractBorderPresentationFromHtmlElement($cell),
        ], static fn ($value) => $value !== null && $value !== []);
    }

    private function extractDirectionalTwipsAttributes(DOMElement $element, string $prefix): array
    {
        $values = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $value = $this->normalizeTwipsValue($element->getAttribute($prefix.$side) ?: null);
            if ($value !== null) {
                $values[$side] = $value;
            }
        }

        return $values;
    }

    private function extractBorderPresentationFromHtmlElement(DOMElement $element): array
    {
        $borders = [];

        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
            $value = $element->getAttribute('data-docx-border-'.$side.'-val');
            if ($value === '') {
                continue;
            }

            $border = [
                'val' => $value,
            ];

            foreach (['sz', 'space'] as $numeric) {
                $attribute = $element->getAttribute('data-docx-border-'.$side.'-'.$numeric);
                if ($attribute !== '' && ctype_digit($attribute)) {
                    $border[$numeric] = (int) $attribute;
                }
            }

            $color = $this->normalizeColorValue($element->getAttribute('data-docx-border-'.$side.'-color') ?: null);
            if ($color !== null) {
                $border['color'] = $color;
            }

            $borders[$side] = $border;
        }

        return $borders;
    }

    private function normalizeTableVerticalAlignment(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'middle', 'center' => 'center',
            'bottom' => 'bottom',
            'top' => 'top',
            default => null,
        };
    }

    private function extractBlockPresentationFromHtmlElement(DOMElement $element): array
    {
        $styleMap = $this->parseInlineStyleMap((string) $element->getAttribute('style'));
        $alignment = trim((string) $element->getAttribute('data-docx-align'));
        if ($alignment === '' && isset($styleMap['text-align'])) {
            $alignment = strtolower(trim((string) $styleMap['text-align']));
        }

        $indentLeft = $element->getAttribute('data-docx-indent-left');
        if ($indentLeft === '' && isset($styleMap['margin-left'])) {
            $indentLeft = (string) $styleMap['margin-left'];
        }

        $spacingBefore = $element->getAttribute('data-docx-spacing-before');
        if ($spacingBefore === '' && isset($styleMap['margin-top'])) {
            $spacingBefore = (string) $styleMap['margin-top'];
        }

        $spacingAfter = $element->getAttribute('data-docx-spacing-after');
        if ($spacingAfter === '' && isset($styleMap['margin-bottom'])) {
            $spacingAfter = (string) $styleMap['margin-bottom'];
        }

        return array_filter([
            'alignment' => $this->normalizeParagraphAlignment($alignment !== '' ? $alignment : null),
            'indent_left' => $this->normalizeTwipsValue($indentLeft !== '' ? $indentLeft : null),
            'spacing_before' => $this->normalizeTwipsValue($spacingBefore !== '' ? $spacingBefore : null),
            'spacing_after' => $this->normalizeTwipsValue($spacingAfter !== '' ? $spacingAfter : null),
        ], static fn ($value) => $value !== null);
    }

    private function normalizeParagraphAlignment(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'center' => 'center',
            'right' => 'right',
            'justify', 'both' => 'both',
            'left', 'start' => 'left',
            default => null,
        };
    }

    private function normalizeTwipsValue(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $normalized) === 1) {
            $twips = (int) $normalized;

            return $twips > 0 ? $twips : null;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)pt$/', $normalized, $matches) === 1) {
            return max(1, (int) round(((float) $matches[1]) * 20));
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)px$/', $normalized, $matches) === 1) {
            return max(1, (int) round(((float) $matches[1]) * 15));
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)rem$/', $normalized, $matches) === 1) {
            return max(1, (int) round(((float) $matches[1]) * 240));
        }

        return null;
    }

    private function mergeStyleState(array $styles, DOMElement $element): array
    {
        $styleMap = $this->parseInlineStyleMap((string) $element->getAttribute('style'));
        $tag = strtolower($element->tagName);

        if (in_array($tag, ['strong', 'b'], true)) {
            $styles['bold'] = true;
        }

        if (in_array($tag, ['em', 'i'], true)) {
            $styles['italic'] = true;
        }

        if ($tag === 'u') {
            $styles['underline'] = true;
        }

        if (($styleMap['font-weight'] ?? null) !== null) {
            $styles['bold'] = $styles['bold'] || $this->isBoldCssValue($styleMap['font-weight']);
        }

        if (($styleMap['font-style'] ?? null) !== null) {
            $styles['italic'] = $styles['italic'] || str_contains(strtolower($styleMap['font-style']), 'italic');
        }

        if (($styleMap['text-decoration'] ?? null) !== null) {
            $styles['underline'] = $styles['underline'] || str_contains(strtolower($styleMap['text-decoration']), 'underline');
        }

        $fontFamily = $element->getAttribute('data-docx-font-family');
        if ($fontFamily === '' && isset($styleMap['font-family'])) {
            $fontFamily = (string) $styleMap['font-family'];
        }

        $normalizedFontFamily = $this->normalizeFontFamily($fontFamily !== '' ? $fontFamily : null);
        if ($normalizedFontFamily !== null) {
            $styles['font_family'] = $normalizedFontFamily;
        }

        $fontSize = $element->getAttribute('data-docx-font-size');
        if ($fontSize === '' && isset($styleMap['font-size'])) {
            $fontSize = (string) $styleMap['font-size'];
        }

        $normalizedFontSize = $this->normalizeFontSizeHalfPoints($fontSize !== '' ? $fontSize : null);
        if ($normalizedFontSize !== null) {
            $styles['font_size_half_points'] = $normalizedFontSize;
        }

        $color = $element->getAttribute('data-docx-color');
        if ($color === '' && isset($styleMap['color'])) {
            $color = (string) $styleMap['color'];
        }

        $normalizedColor = $this->normalizeColorValue($color !== '' ? $color : null);
        if ($normalizedColor !== null) {
            $styles['color'] = $normalizedColor;
        }

        return $styles;
    }

    private function parseInlineStyleMap(string $style): array
    {
        $map = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            if ($property === '' || $value === '') {
                continue;
            }

            $map[strtolower($property)] = $value;
        }

        return $map;
    }

    private function isBoldCssValue(string $value): bool
    {
        $normalized = strtolower(trim($value));

        if ($normalized === 'bold') {
            return true;
        }

        return ctype_digit($normalized) && (int) $normalized >= 600;
    }

    private function normalizeFontSizeHalfPoints(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (ctype_digit($normalized)) {
            $halfPoints = (int) $normalized;

            return $halfPoints > 0 ? $halfPoints : null;
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)pt$/', $normalized, $matches) === 1) {
            return max(1, (int) round(((float) $matches[1]) * 2));
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)px$/', $normalized, $matches) === 1) {
            return max(1, (int) round(((float) $matches[1]) * 1.5));
        }

        return null;
    }

    private function normalizeColorValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^#?([0-9A-F]{6})$/', $normalized, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^#?([0-9A-F]{3})$/', $normalized, $matches) === 1) {
            return strtoupper(preg_replace('/(.)/', '$1$1', $matches[1]));
        }

        if (preg_match('/^RGB\((\d+),\s*(\d+),\s*(\d+)\)$/', $normalized, $matches) === 1) {
            return sprintf('%02X%02X%02X', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    private function mergeSegments(array $segments): array
    {
        $merged = [];

        foreach ($segments as $segment) {
            if (in_array(($segment['type'] ?? null), ['break', 'image'], true)) {
                $merged[] = $segment;

                continue;
            }

            $text = (string) ($segment['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $lastIndex = array_key_last($merged);
            if (
                $lastIndex !== null
                && ($merged[$lastIndex]['type'] ?? null) === 'text'
                && $merged[$lastIndex]['bold'] === ($segment['bold'] ?? false)
                && $merged[$lastIndex]['italic'] === ($segment['italic'] ?? false)
                && $merged[$lastIndex]['underline'] === ($segment['underline'] ?? false)
                && $merged[$lastIndex]['font_family'] === ($segment['font_family'] ?? null)
                && $merged[$lastIndex]['font_size_half_points'] === ($segment['font_size_half_points'] ?? null)
                && $merged[$lastIndex]['color'] === ($segment['color'] ?? null)
            ) {
                $merged[$lastIndex]['text'] .= $text;

                continue;
            }

            $merged[] = [
                'type' => 'text',
                'text' => $text,
                'bold' => (bool) ($segment['bold'] ?? false),
                'italic' => (bool) ($segment['italic'] ?? false),
                'underline' => (bool) ($segment['underline'] ?? false),
                'font_family' => $segment['font_family'] ?? null,
                'font_size_half_points' => $segment['font_size_half_points'] ?? null,
                'color' => $segment['color'] ?? null,
            ];
        }

        return $merged;
    }

    private function blocksToDocumentXml(array $blocks, ?string $sectPrXml): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $document = $dom->createElementNS(self::WORD_NS, 'w:document');
        $document->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:w', self::WORD_NS);
        $document->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:r', self::REL_NS);
        $document->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wp', self::WORD_DRAWING_NS);
        $document->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:a', self::DRAWING_NS);
        $document->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:pic', self::PIC_NS);
        $dom->appendChild($document);

        $body = $dom->createElementNS(self::WORD_NS, 'w:body');
        $document->appendChild($body);

        $needsNumbering = false;

        foreach ($blocks as $block) {
            if (in_array($block['type'] ?? 'p', ['ul-item', 'ol-item'], true)) {
                $needsNumbering = true;
            }

            if (($block['type'] ?? 'p') === 'table') {
                $body->appendChild($this->buildTableNode($dom, $block));

                continue;
            }

            $body->appendChild($this->buildParagraphNode($dom, $block));
        }

        if ($sectPrXml) {
            $sectPrDom = $this->loadWordXml($sectPrXml);
            if ($sectPrDom->documentElement instanceof DOMElement) {
                $body->appendChild($dom->importNode($sectPrDom->documentElement, true));
            }
        } else {
            $body->appendChild($dom->createElementNS(self::WORD_NS, 'w:sectPr'));
        }

        return [
            'document_xml' => $dom->saveXML(),
            'needs_numbering' => $needsNumbering,
        ];
    }

    private function blocksToWordPartXml(array $blocks, string $partTag): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $rootTag = $partTag === 'hdr' ? 'w:hdr' : 'w:ftr';
        $root = $dom->createElementNS(self::WORD_NS, $rootTag);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:w', self::WORD_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:r', self::REL_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wp', self::WORD_DRAWING_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:a', self::DRAWING_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:pic', self::PIC_NS);
        $dom->appendChild($root);

        if ($blocks === []) {
            $blocks[] = [
                'type' => 'p',
                'segments' => [],
                'presentation' => [],
            ];
        }

        foreach ($blocks as $block) {
            if (($block['type'] ?? 'p') === 'table') {
                $root->appendChild($this->buildTableNode($dom, $block));

                continue;
            }

            $root->appendChild($this->buildParagraphNode($dom, $block));
        }

        return $dom->saveXML();
    }

    private function blocksRequireNumbering(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (in_array($block['type'] ?? 'p', ['ul-item', 'ol-item'], true)) {
                return true;
            }
        }

        return false;
    }

    private function buildParagraphNode(DOMDocument $dom, array $block): DOMElement
    {
        $type = $block['type'] ?? 'p';
        $paragraph = $dom->createElementNS(self::WORD_NS, 'w:p');
        $paragraphProperties = $dom->createElementNS(self::WORD_NS, 'w:pPr');
        $presentation = $block['presentation'] ?? [];

        if (in_array($type, ['h1', 'h2', 'h3'], true)) {
            $style = $dom->createElementNS(self::WORD_NS, 'w:pStyle');
            $style->setAttributeNS(self::WORD_NS, 'w:val', match ($type) {
                'h1' => 'Heading1',
                'h2' => 'Heading2',
                default => 'Heading3',
            });
            $paragraphProperties->appendChild($style);
        }

        if (in_array($type, ['ul-item', 'ol-item'], true)) {
            $numPr = $dom->createElementNS(self::WORD_NS, 'w:numPr');
            $level = max(0, min(8, (int) ($block['level'] ?? 0)));

            $ilvl = $dom->createElementNS(self::WORD_NS, 'w:ilvl');
            $ilvl->setAttributeNS(self::WORD_NS, 'w:val', (string) $level);
            $numPr->appendChild($ilvl);

            $numId = $dom->createElementNS(self::WORD_NS, 'w:numId');
            $numId->setAttributeNS(
                self::WORD_NS,
                'w:val',
                (string) ($type === 'ol-item' ? self::ORDERED_NUM_ID : self::BULLET_NUM_ID)
            );
            $numPr->appendChild($numId);
            $paragraphProperties->appendChild($numPr);
        }

        $alignment = $this->normalizeParagraphAlignment($presentation['alignment'] ?? null);
        if ($alignment !== null) {
            $jc = $dom->createElementNS(self::WORD_NS, 'w:jc');
            $jc->setAttributeNS(self::WORD_NS, 'w:val', $alignment);
            $paragraphProperties->appendChild($jc);
        }

        $indentLeft = (int) ($presentation['indent_left'] ?? 0);
        if ($indentLeft > 0) {
            $indent = $dom->createElementNS(self::WORD_NS, 'w:ind');
            $indent->setAttributeNS(self::WORD_NS, 'w:left', (string) $indentLeft);
            $paragraphProperties->appendChild($indent);
        }

        $spacingBefore = (int) ($presentation['spacing_before'] ?? 0);
        $spacingAfter = (int) ($presentation['spacing_after'] ?? 0);
        if ($spacingBefore > 0 || $spacingAfter > 0) {
            $spacing = $dom->createElementNS(self::WORD_NS, 'w:spacing');
            if ($spacingBefore > 0) {
                $spacing->setAttributeNS(self::WORD_NS, 'w:before', (string) $spacingBefore);
            }
            if ($spacingAfter > 0) {
                $spacing->setAttributeNS(self::WORD_NS, 'w:after', (string) $spacingAfter);
            }
            $paragraphProperties->appendChild($spacing);
        }

        if ($paragraphProperties->childNodes->length > 0) {
            $paragraph->appendChild($paragraphProperties);
        }

        $segments = $block['segments'] ?? [];

        if ($segments === []) {
            $segments = [[
                'type' => 'text',
                'text' => '',
                'bold' => false,
                'italic' => false,
                'underline' => false,
                'font_family' => null,
                'font_size_half_points' => null,
                'color' => null,
            ]];
        }

        foreach ($segments as $segment) {
            if (($segment['type'] ?? null) === 'break') {
                $run = $dom->createElementNS(self::WORD_NS, 'w:r');
                $run->appendChild($dom->createElementNS(self::WORD_NS, 'w:br'));
                $paragraph->appendChild($run);

                continue;
            }

            if (($segment['type'] ?? null) === 'image') {
                $run = $dom->createElementNS(self::WORD_NS, 'w:r');
                $drawing = $this->buildDrawingNode($dom, (string) ($segment['drawing_xml'] ?? ''));
                if ($drawing !== null) {
                    $run->appendChild($drawing);
                    $paragraph->appendChild($run);
                }

                continue;
            }

            $run = $dom->createElementNS(self::WORD_NS, 'w:r');
            $runProperties = $dom->createElementNS(self::WORD_NS, 'w:rPr');

            if (! empty($segment['bold'])) {
                $runProperties->appendChild($dom->createElementNS(self::WORD_NS, 'w:b'));
            }

            if (! empty($segment['italic'])) {
                $runProperties->appendChild($dom->createElementNS(self::WORD_NS, 'w:i'));
            }

            if (! empty($segment['underline'])) {
                $underline = $dom->createElementNS(self::WORD_NS, 'w:u');
                $underline->setAttributeNS(self::WORD_NS, 'w:val', 'single');
                $runProperties->appendChild($underline);
            }

            $fontFamily = $this->normalizeFontFamily($segment['font_family'] ?? null);
            if ($fontFamily !== null) {
                $rFonts = $dom->createElementNS(self::WORD_NS, 'w:rFonts');
                $rFonts->setAttributeNS(self::WORD_NS, 'w:ascii', $fontFamily);
                $rFonts->setAttributeNS(self::WORD_NS, 'w:hAnsi', $fontFamily);
                $runProperties->appendChild($rFonts);
            }

            $fontSize = (int) ($segment['font_size_half_points'] ?? 0);
            if ($fontSize > 0) {
                $size = $dom->createElementNS(self::WORD_NS, 'w:sz');
                $size->setAttributeNS(self::WORD_NS, 'w:val', (string) $fontSize);
                $runProperties->appendChild($size);

                $sizeCs = $dom->createElementNS(self::WORD_NS, 'w:szCs');
                $sizeCs->setAttributeNS(self::WORD_NS, 'w:val', (string) $fontSize);
                $runProperties->appendChild($sizeCs);
            }

            $color = $this->normalizeColorValue($segment['color'] ?? null);
            if ($color !== null) {
                $colorNode = $dom->createElementNS(self::WORD_NS, 'w:color');
                $colorNode->setAttributeNS(self::WORD_NS, 'w:val', $color);
                $runProperties->appendChild($colorNode);
            }

            if ($runProperties->childNodes->length > 0) {
                $run->appendChild($runProperties);
            }

            $text = $dom->createElementNS(self::WORD_NS, 'w:t');
            $text->appendChild($dom->createTextNode((string) ($segment['text'] ?? '')));

            if ($this->needsXmlSpacePreserve((string) ($segment['text'] ?? ''))) {
                $text->setAttributeNS(self::XML_NS, 'xml:space', 'preserve');
            }

            $run->appendChild($text);
            $paragraph->appendChild($run);
        }

        return $paragraph;
    }

    private function buildTableNode(DOMDocument $dom, array $block): DOMElement
    {
        $table = $dom->createElementNS(self::WORD_NS, 'w:tbl');
        $presentation = $block['presentation'] ?? [];

        $tableProperties = $dom->createElementNS(self::WORD_NS, 'w:tblPr');
        $tableWidth = $dom->createElementNS(self::WORD_NS, 'w:tblW');
        $tableWidth->setAttributeNS(self::WORD_NS, 'w:w', (string) ((int) ($presentation['width'] ?? 0)));
        $tableWidth->setAttributeNS(self::WORD_NS, 'w:type', (string) ($presentation['width_type'] ?? 'auto'));
        $tableProperties->appendChild($tableWidth);

        if (($presentation['layout'] ?? null) === 'fixed') {
            $layout = $dom->createElementNS(self::WORD_NS, 'w:tblLayout');
            $layout->setAttributeNS(self::WORD_NS, 'w:type', 'fixed');
            $tableProperties->appendChild($layout);
        }

        if (($presentation['indent'] ?? null) !== null) {
            $indent = $dom->createElementNS(self::WORD_NS, 'w:tblInd');
            $indent->setAttributeNS(self::WORD_NS, 'w:w', (string) ((int) $presentation['indent']));
            $indent->setAttributeNS(self::WORD_NS, 'w:type', 'dxa');
            $tableProperties->appendChild($indent);
        }

        if (($presentation['alignment'] ?? null) !== null) {
            $alignment = $dom->createElementNS(self::WORD_NS, 'w:jc');
            $alignment->setAttributeNS(self::WORD_NS, 'w:val', (string) $presentation['alignment']);
            $tableProperties->appendChild($alignment);
        }

        if (($presentation['cell_margins'] ?? []) !== []) {
            $cellMargins = $dom->createElementNS(self::WORD_NS, 'w:tblCellMar');
            foreach (['top', 'left', 'bottom', 'right'] as $side) {
                if (! isset($presentation['cell_margins'][$side])) {
                    continue;
                }

                $margin = $dom->createElementNS(self::WORD_NS, 'w:'.$side);
                $margin->setAttributeNS(self::WORD_NS, 'w:w', (string) ((int) $presentation['cell_margins'][$side]));
                $margin->setAttributeNS(self::WORD_NS, 'w:type', 'dxa');
                $cellMargins->appendChild($margin);
            }
            $tableProperties->appendChild($cellMargins);
        }

        $tableBorders = $dom->createElementNS(self::WORD_NS, 'w:tblBorders');
        $presentationBorders = $presentation['borders'] ?? [];
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $borderName) {
            $border = $dom->createElementNS(self::WORD_NS, 'w:'.$borderName);
            $borderPresentation = $presentationBorders[$borderName] ?? [];
            $border->setAttributeNS(self::WORD_NS, 'w:val', (string) ($borderPresentation['val'] ?? 'single'));
            $border->setAttributeNS(self::WORD_NS, 'w:sz', (string) ((int) ($borderPresentation['sz'] ?? 4)));
            $border->setAttributeNS(self::WORD_NS, 'w:space', (string) ((int) ($borderPresentation['space'] ?? 0)));
            $border->setAttributeNS(self::WORD_NS, 'w:color', (string) ($borderPresentation['color'] ?? 'BFC6D4'));
            $tableBorders->appendChild($border);
        }
        $tableProperties->appendChild($tableBorders);
        $table->appendChild($tableProperties);

        if (($presentation['grid'] ?? []) !== []) {
            $grid = $dom->createElementNS(self::WORD_NS, 'w:tblGrid');
            foreach ($presentation['grid'] as $width) {
                $column = $dom->createElementNS(self::WORD_NS, 'w:gridCol');
                $column->setAttributeNS(self::WORD_NS, 'w:w', (string) ((int) $width));
                $grid->appendChild($column);
            }
            $table->appendChild($grid);
        }

        $pendingRowspans = [];
        foreach (($block['rows'] ?? []) as $row) {
            $rowNode = $dom->createElementNS(self::WORD_NS, 'w:tr');
            $rowPresentation = $row['presentation'] ?? [];

            if (($rowPresentation['height'] ?? null) !== null) {
                $rowProperties = $dom->createElementNS(self::WORD_NS, 'w:trPr');
                $height = $dom->createElementNS(self::WORD_NS, 'w:trHeight');
                $height->setAttributeNS(self::WORD_NS, 'w:val', (string) ((int) $rowPresentation['height']));
                $rowProperties->appendChild($height);
                $rowNode->appendChild($rowProperties);
            }

            $gridColumn = 0;
            foreach (($row['cells'] ?? []) as $cell) {
                $cellPresentation = $cell['presentation'] ?? [];
                $targetColumn = (int) ($cellPresentation['grid_col'] ?? $gridColumn);
                while ($gridColumn < $targetColumn) {
                    if (($pendingRowspans[$gridColumn] ?? 0) > 0) {
                        $rowNode->appendChild($this->buildTableContinuationCellNode($dom));
                        $pendingRowspans[$gridColumn]--;
                    }
                    $gridColumn++;
                }

                $cellNode = $dom->createElementNS(self::WORD_NS, 'w:tc');
                $cellProperties = $dom->createElementNS(self::WORD_NS, 'w:tcPr');
                $cellWidth = $dom->createElementNS(self::WORD_NS, 'w:tcW');
                $cellWidth->setAttributeNS(self::WORD_NS, 'w:w', (string) ((int) ($cellPresentation['width'] ?? 2400)));
                $cellWidth->setAttributeNS(self::WORD_NS, 'w:type', (string) ($cellPresentation['width_type'] ?? 'dxa'));
                $cellProperties->appendChild($cellWidth);

                $gridSpan = max(1, (int) ($cellPresentation['grid_span'] ?? 1));
                if ($gridSpan > 1) {
                    $span = $dom->createElementNS(self::WORD_NS, 'w:gridSpan');
                    $span->setAttributeNS(self::WORD_NS, 'w:val', (string) $gridSpan);
                    $cellProperties->appendChild($span);
                }

                $rowSpan = max(1, (int) ($cellPresentation['row_span'] ?? 1));
                if ($rowSpan > 1 || ($cellPresentation['v_merge'] ?? null) === 'restart') {
                    $vMerge = $dom->createElementNS(self::WORD_NS, 'w:vMerge');
                    $vMerge->setAttributeNS(self::WORD_NS, 'w:val', 'restart');
                    $cellProperties->appendChild($vMerge);

                    for ($offset = 0; $offset < $gridSpan; $offset++) {
                        $pendingRowspans[$gridColumn + $offset] = $rowSpan - 1;
                    }
                }

                if (($cellPresentation['shading'] ?? null) !== null) {
                    $shading = $dom->createElementNS(self::WORD_NS, 'w:shd');
                    $shading->setAttributeNS(self::WORD_NS, 'w:fill', (string) $cellPresentation['shading']);
                    $cellProperties->appendChild($shading);
                }

                if (($cellPresentation['vertical_align'] ?? null) !== null) {
                    $verticalAlign = $dom->createElementNS(self::WORD_NS, 'w:vAlign');
                    $verticalAlign->setAttributeNS(self::WORD_NS, 'w:val', (string) $cellPresentation['vertical_align']);
                    $cellProperties->appendChild($verticalAlign);
                }

                if (($cellPresentation['margins'] ?? []) !== []) {
                    $cellMargins = $dom->createElementNS(self::WORD_NS, 'w:tcMar');
                    foreach (['top', 'left', 'bottom', 'right'] as $side) {
                        if (! isset($cellPresentation['margins'][$side])) {
                            continue;
                        }

                        $margin = $dom->createElementNS(self::WORD_NS, 'w:'.$side);
                        $margin->setAttributeNS(self::WORD_NS, 'w:w', (string) ((int) $cellPresentation['margins'][$side]));
                        $margin->setAttributeNS(self::WORD_NS, 'w:type', 'dxa');
                        $cellMargins->appendChild($margin);
                    }
                    $cellProperties->appendChild($cellMargins);
                }

                if (($cellPresentation['borders'] ?? []) !== []) {
                    $cellBorders = $dom->createElementNS(self::WORD_NS, 'w:tcBorders');
                    foreach (['top', 'left', 'bottom', 'right'] as $side) {
                        if (! isset($cellPresentation['borders'][$side])) {
                            continue;
                        }

                        $borderPresentation = $cellPresentation['borders'][$side];
                        $border = $dom->createElementNS(self::WORD_NS, 'w:'.$side);
                        $border->setAttributeNS(self::WORD_NS, 'w:val', (string) ($borderPresentation['val'] ?? 'single'));
                        $border->setAttributeNS(self::WORD_NS, 'w:sz', (string) ((int) ($borderPresentation['sz'] ?? 4)));
                        $border->setAttributeNS(self::WORD_NS, 'w:space', (string) ((int) ($borderPresentation['space'] ?? 0)));
                        $border->setAttributeNS(self::WORD_NS, 'w:color', (string) ($borderPresentation['color'] ?? 'BFC6D4'));
                        $cellBorders->appendChild($border);
                    }
                    $cellProperties->appendChild($cellBorders);
                }

                $cellNode->appendChild($cellProperties);

                $cellBlocks = $cell['blocks'] ?? [];
                if ($cellBlocks === []) {
                    $cellBlocks[] = [
                        'type' => 'p',
                        'segments' => [],
                        'presentation' => [],
                    ];
                }

                foreach ($cellBlocks as $cellBlock) {
                    if (($cellBlock['type'] ?? 'p') === 'table') {
                        continue;
                    }

                    $cellNode->appendChild($this->buildParagraphNode($dom, $cellBlock));
                }

                $rowNode->appendChild($cellNode);
                $gridColumn += $gridSpan;
            }

            while (array_sum($pendingRowspans) > 0 && $gridColumn <= max(array_keys($pendingRowspans))) {
                if (($pendingRowspans[$gridColumn] ?? 0) > 0) {
                    $rowNode->appendChild($this->buildTableContinuationCellNode($dom));
                    $pendingRowspans[$gridColumn]--;
                }
                $gridColumn++;
            }

            $table->appendChild($rowNode);
        }

        return $table;
    }

    private function buildTableContinuationCellNode(DOMDocument $dom): DOMElement
    {
        $cellNode = $dom->createElementNS(self::WORD_NS, 'w:tc');
        $cellProperties = $dom->createElementNS(self::WORD_NS, 'w:tcPr');
        $vMerge = $dom->createElementNS(self::WORD_NS, 'w:vMerge');
        $cellProperties->appendChild($vMerge);
        $cellNode->appendChild($cellProperties);
        $cellNode->appendChild($this->buildParagraphNode($dom, [
            'type' => 'p',
            'segments' => [],
            'presentation' => [],
        ]));

        return $cellNode;
    }

    private function needsXmlSpacePreserve(string $text): bool
    {
        return $text !== trim($text) || str_contains($text, '  ');
    }

    private function buildDrawingNode(DOMDocument $dom, string $encodedDrawing): ?DOMElement
    {
        if ($encodedDrawing === '') {
            return null;
        }

        $drawingXml = base64_decode($encodedDrawing, true);
        if ($drawingXml === false || trim($drawingXml) === '') {
            return null;
        }

        $drawingDom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $drawingDom->loadXML($drawingXml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $drawingDom->documentElement instanceof DOMElement) {
            return null;
        }

        return $dom->importNode($drawingDom->documentElement, true);
    }

    private function buildDocxBinary(string $absolutePath, string $documentXml, bool $needsNumbering, array $extraParts = []): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'residencia-docx-');
        if ($tempPath === false) {
            throw new RuntimeException('No se pudo preparar un archivo temporal para guardar la version DOCX.');
        }

        if (! copy($absolutePath, $tempPath)) {
            @unlink($tempPath);

            throw new RuntimeException('No se pudo copiar el DOCX base para generar una nueva version.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);

            throw new RuntimeException('No se pudo abrir el DOCX temporal para escritura.');
        }

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $documentXml);

        foreach ($extraParts as $path => $xml) {
            $zip->deleteName((string) $path);
            $zip->addFromString((string) $path, (string) $xml);
        }

        if ($needsNumbering) {
            $zip->deleteName('word/numbering.xml');
            $zip->addFromString('word/numbering.xml', $this->defaultNumberingXml());
            $this->ensureDocumentRelationshipsHaveNumbering($zip);
            $this->ensureContentTypesHaveNumbering($zip);
        }

        $zip->close();

        $binary = file_get_contents($tempPath);
        @unlink($tempPath);

        if ($binary === false) {
            throw new RuntimeException('No se pudo leer la nueva version DOCX generada.');
        }

        return $binary;
    }

    private function ensureDocumentRelationshipsHaveNumbering(ZipArchive $zip): void
    {
        $xml = $zip->getFromName('word/_rels/document.xml.rels');

        if ($xml === false) {
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="'.self::PACKAGE_REL_NS.'"></Relationships>';
        }

        $dom = $this->loadPackageXml($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pr', self::PACKAGE_REL_NS);

        $existing = $xpath->query('/pr:Relationships/pr:Relationship[@Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering"]')->item(0);
        if (! $existing instanceof DOMElement) {
            $root = $dom->documentElement;
            if ($root instanceof DOMElement) {
                $relationship = $dom->createElementNS(self::PACKAGE_REL_NS, 'Relationship');
                $relationship->setAttribute('Id', $this->nextRelationshipId($dom));
                $relationship->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering');
                $relationship->setAttribute('Target', 'numbering.xml');
                $root->appendChild($relationship);
            }
        }

        $zip->deleteName('word/_rels/document.xml.rels');
        $zip->addFromString('word/_rels/document.xml.rels', $dom->saveXML());
    }

    private function nextRelationshipId(DOMDocument $dom): string
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('pr', self::PACKAGE_REL_NS);
        $max = 0;

        foreach ($xpath->query('/pr:Relationships/pr:Relationship') as $relationship) {
            if (! $relationship instanceof DOMElement) {
                continue;
            }

            if (preg_match('/^rId(\d+)$/i', $relationship->getAttribute('Id'), $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'rId'.($max + 1);
    }

    private function ensureContentTypesHaveNumbering(ZipArchive $zip): void
    {
        $xml = $zip->getFromName('[Content_Types].xml');
        if ($xml === false) {
            throw new RuntimeException('El paquete DOCX no contiene [Content_Types].xml.');
        }

        $dom = $this->loadContentTypesXml($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ct', self::CONTENT_TYPES_NS);

        $existing = $xpath->query('/ct:Types/ct:Override[@PartName="/word/numbering.xml"]')->item(0);
        if (! $existing instanceof DOMElement) {
            $root = $dom->documentElement;
            if ($root instanceof DOMElement) {
                $override = $dom->createElementNS(self::CONTENT_TYPES_NS, 'Override');
                $override->setAttribute('PartName', '/word/numbering.xml');
                $override->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml');
                $root->appendChild($override);
            }
        }

        $zip->deleteName('[Content_Types].xml');
        $zip->addFromString('[Content_Types].xml', $dom->saveXML());
    }

    private function defaultNumberingXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:abstractNum w:abstractNumId="9100">
        <w:multiLevelType w:val="hybridMultilevel"/>
        <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="&#x2022;"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr><w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol"/></w:rPr></w:lvl>
        <w:lvl w:ilvl="1"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="o"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="1440" w:hanging="360"/></w:pPr></w:lvl>
        <w:lvl w:ilvl="2"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="&#x25AA;"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="2160" w:hanging="360"/></w:pPr></w:lvl>
    </w:abstractNum>
    <w:abstractNum w:abstractNumId="9200">
        <w:multiLevelType w:val="multilevel"/>
        <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr></w:lvl>
        <w:lvl w:ilvl="1"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%2."/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="1440" w:hanging="360"/></w:pPr></w:lvl>
        <w:lvl w:ilvl="2"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%3."/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="2160" w:hanging="360"/></w:pPr></w:lvl>
    </w:abstractNum>
    <w:num w:numId="9101"><w:abstractNumId w:val="9100"/></w:num>
    <w:num w:numId="9201"><w:abstractNumId w:val="9200"/></w:num>
</w:numbering>
XML;
    }

    private function loadWordXml(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('No se pudo interpretar el XML interno del archivo DOCX.');
        }

        return $dom;
    }

    private function loadPackageXml(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('No se pudo interpretar el XML interno del paquete DOCX.');
        }

        return $dom;
    }

    private function loadContentTypesXml(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('No se pudo interpretar [Content_Types].xml del DOCX.');
        }

        return $dom;
    }

    private function wordXPath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);
        $xpath->registerNamespace('r', self::REL_NS);

        return $xpath;
    }
}
