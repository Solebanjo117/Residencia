<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    private const DEFAULT_ALLOWED_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function storeEvidence(UploadedFile $file, FolderNode $folderNode, User $user, EvidenceSubmission $submission)
    {
        $validatedUpload = $this->validateUpload($file);

        // Check root path
        $root = $folderNode->root;
        if (!$root || !$root->is_active) {
            throw new \Exception("Storage root not active or defined.");
        }

        $normalizedFolderPath = $this->normalizeRelativePath($folderNode->relative_path);
        $storedName = (string) Str::uuid() . '.' . $validatedUpload['extension'];

        // Store file physically using a normalized folder path to prevent traversal vectors.
        $path = $file->storeAs($normalizedFolderPath, $storedName, 'local');
        if (!$path) {
            throw new \RuntimeException('No se pudo guardar el archivo en disco.');
        }

        $normalizedStoredPath = $this->normalizeRelativePath($path);
        $this->assertPathInsideFolder($folderNode, $normalizedStoredPath);

        $hashSourcePath = $file->getRealPath();
        $fileHash = $hashSourcePath ? hash_file('sha256', $hashSourcePath) : null;

        // Create EvidenceFile record
        $evidenceFile = EvidenceFile::create([
            'submission_id' => $submission->id,
            'folder_node_id' => $folderNode->id,
            'file_name' => $validatedUpload['safe_file_name'],
            'stored_relative_path' => $normalizedStoredPath,
            'mime_type' => $validatedUpload['mime_type'],
            'size_bytes' => $validatedUpload['size_bytes'],
            'file_hash' => $fileHash,
            'uploaded_at' => now(),
            'uploaded_by_user_id' => $user->id,
        ]);

        $this->auditService->log($user, 'UPLOAD_FILE', 'EvidenceFile', $evidenceFile->id, [
            'original_filename' => $validatedUpload['original_name'],
            'stored_filename' => $validatedUpload['safe_file_name'],
            'stored_relative_path' => $normalizedStoredPath,
            'mime_type' => $validatedUpload['mime_type'],
            'size_bytes' => $validatedUpload['size_bytes'],
            'extension' => $validatedUpload['extension'],
        ]);

        return $evidenceFile;
    }

    public function deleteEvidence(EvidenceFile $file, User $user)
    {
        $this->assertEvidenceFilePath($file);

        if (Storage::disk('local')->exists($file->stored_relative_path)) {
            Storage::disk('local')->delete($file->stored_relative_path);
        }

        // Set who deleted it, then use Laravel's SoftDeletes properly
        $file->deleted_by_user_id = $user->id;
        $file->save();
        $file->delete();

        $this->auditService->log($user, 'DELETE_FILE', 'EvidenceFile', $file->id, [
            'file_name' => $file->file_name,
            'stored_relative_path' => $file->stored_relative_path,
        ]);

        return $file;
    }

    public function assertEvidenceFilePath(EvidenceFile $file): void
    {
        $folderNode = $file->folderNode;
        if (!$folderNode) {
            throw new AuthorizationException('El archivo no tiene carpeta asociada.');
        }

        $this->assertPathInsideFolder($folderNode, $file->stored_relative_path);
    }

    public function getAccessibleRoots(User $user)
    {
        if ($user->isJefeOficina() || $user->isJefeDepto()) {
            return $this->buildTree(FolderNode::all());
        }

        if ($user->isDocente()) {
            $nodes = FolderNode::where('owner_user_id', $user->id)->get();
            return $this->buildTree($nodes);
        }

        return [];
    }

    private function buildTree($nodes)
    {
        $grouped = $nodes->groupBy('parent_id');
        
        // Find roots within the collection (nodes whose parent is not in the collection)
        $ids = $nodes->pluck('id');
        $roots = $nodes->filter(function ($node) use ($ids) {
            return is_null($node->parent_id) || !$ids->contains($node->parent_id);
        });

        foreach ($nodes as $node) {
            $children = $grouped->get($node->id) ?? collect([]);
            $node->setRelation('children', $children);
        }

        return $roots->values();
    }
    private function validateUpload(UploadedFile $file): array
    {
        $originalName = (string) $file->getClientOriginalName();
        if ($originalName === '') {
            throw new \InvalidArgumentException('Nombre de archivo inválido.');
        }

        if (preg_match('/[\\\\\/]/', $originalName) === 1) {
            throw new \InvalidArgumentException('El nombre del archivo no puede contener rutas.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $originalName) === 1) {
            throw new \InvalidArgumentException('El nombre del archivo contiene caracteres no permitidos.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            throw new \InvalidArgumentException('El archivo debe incluir una extensión válida.');
        }

        $allowedExtensions = $this->allowedExtensions();
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException('Formato no permitido. Formatos válidos: ' . implode(', ', $allowedExtensions));
        }

        $sizeBytes = (int) ($file->getSize() ?? 0);
        if ($sizeBytes <= 0) {
            throw new \InvalidArgumentException('El archivo está vacío o no se pudo leer su tamaño.');
        }

        $maxBytes = $this->maxUploadKb() * 1024;
        if ($sizeBytes > $maxBytes) {
            throw new \InvalidArgumentException('El archivo excede el tamaño máximo permitido de ' . $this->maxUploadKb() . ' KB.');
        }

        $mimeType = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        $allowedMimeTypes = $this->allowedMimeTypes();
        $expectedMimeTypes = array_map('strtolower', $allowedMimeTypes[$extension] ?? []);

        if (!empty($expectedMimeTypes) && !in_array($mimeType, $expectedMimeTypes, true)) {
            throw new \InvalidArgumentException('El MIME detectado no corresponde con la extensión del archivo.');
        }

        $baseName = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $safeBaseName = preg_replace('/[^\pL\pN\-_ ]/u', '_', $baseName);
        $safeBaseName = preg_replace('/\s+/', ' ', (string) $safeBaseName);
        $safeBaseName = trim((string) $safeBaseName, " ._\t\n\r\0\x0B");

        if ($safeBaseName === '') {
            $safeBaseName = 'archivo';
        }

        $safeBaseName = mb_substr($safeBaseName, 0, $this->maxFilenameChars());

        return [
            'original_name' => $originalName,
            'safe_file_name' => $safeBaseName . '.' . $extension,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ];
    }

    private function assertPathInsideFolder(FolderNode $folderNode, string $storedRelativePath): void
    {
        $folderPath = $this->normalizeRelativePath($folderNode->relative_path);
        $filePath = $this->normalizeRelativePath($storedRelativePath);

        if (!str_starts_with($filePath, $folderPath . '/')) {
            throw new AuthorizationException('Ruta de archivo fuera del alcance de su carpeta.');
        }
    }

    private function normalizeRelativePath(string $path): string
    {
        $normalized = str_replace('\\\\', '/', trim($path));
        $normalized = preg_replace('#/+#', '/', $normalized);
        $normalized = ltrim((string) $normalized, '/');

        if ($normalized === '') {
            throw new \InvalidArgumentException('Ruta relativa inválida.');
        }

        if (str_contains($normalized, "\0")) {
            throw new \InvalidArgumentException('Ruta relativa inválida.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Ruta relativa inválida.');
            }
        }

        return $normalized;
    }

    private function allowedExtensions(): array
    {
        return config('evidence.upload.allowed_extensions', ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp']);
    }

    private function allowedMimeTypes(): array
    {
        return config('evidence.upload.allowed_mime_types', self::DEFAULT_ALLOWED_MIME_TYPES);
    }

    private function maxUploadKb(): int
    {
        return (int) config('evidence.upload.max_kb', 15360);
    }

    private function maxFilenameChars(): int
    {
        return (int) config('evidence.upload.max_filename_chars', 120);
    }
}
