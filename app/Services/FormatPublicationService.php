<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\FormatPublication;
use App\Models\FormatPublicationFile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormatPublicationService
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

    public function __construct(
        private readonly AuditService $auditService,
        private readonly NotificationService $notificationService
    ) {}

    public function publish(array $attributes, UploadedFile $upload, User $actor): FormatPublication
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($attributes, $upload, $actor, &$storedPath) {
                $publication = FormatPublication::create([
                    'evidence_item_id' => $attributes['evidence_item_id'],
                    'title' => $attributes['title'],
                    'body' => $attributes['body'] ?? null,
                    'status' => FormatPublication::STATUS_ACTIVE,
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                    'published_at' => now(),
                ]);

                $file = $this->storeFile($publication, $upload, $actor, $storedPath);
                $publication->forceFill([
                    'current_format_publication_file_id' => $file->id,
                ])->save();

                $this->auditService->log($actor, 'PUBLISH_FORMAT', 'FormatPublication', $publication->id, [
                    'title' => $publication->title,
                    'evidence_item_id' => $publication->evidence_item_id,
                    'file_id' => $file->id,
                    'file_name' => $file->file_name,
                ]);

                $this->notifyTeachers($publication, 'Nuevo formato publicado', 'Se publico el formato: '.$publication->title);

                return $publication->fresh(['evidenceItem', 'currentFile', 'author', 'updatedBy']);
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function update(FormatPublication $publication, array $attributes, User $actor): FormatPublication
    {
        $publication->fill([
            'title' => $attributes['title'] ?? $publication->title,
            'body' => array_key_exists('body', $attributes) ? $attributes['body'] : $publication->body,
            'evidence_item_id' => $attributes['evidence_item_id'] ?? $publication->evidence_item_id,
            'updated_by_user_id' => $actor->id,
        ])->save();

        $this->auditService->log($actor, 'UPDATE_FORMAT', 'FormatPublication', $publication->id, [
            'title' => $publication->title,
            'evidence_item_id' => $publication->evidence_item_id,
        ]);

        return $publication->fresh(['evidenceItem', 'currentFile', 'author', 'updatedBy']);
    }

    public function replaceFile(FormatPublication $publication, UploadedFile $upload, User $actor): FormatPublication
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($publication, $upload, $actor, &$storedPath) {
                FormatPublicationFile::query()
                    ->where('format_publication_id', $publication->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                $file = $this->storeFile($publication, $upload, $actor, $storedPath);
                $publication->forceFill([
                    'current_format_publication_file_id' => $file->id,
                    'updated_by_user_id' => $actor->id,
                    'updated_at' => now(),
                ])->save();

                $this->auditService->log($actor, 'REPLACE_FORMAT_FILE', 'FormatPublication', $publication->id, [
                    'file_id' => $file->id,
                    'file_name' => $file->file_name,
                ]);

                $this->notifyTeachers($publication, 'Formato actualizado', 'Se actualizo el formato: '.$publication->title);

                return $publication->fresh(['evidenceItem', 'currentFile', 'author', 'updatedBy']);
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function archive(FormatPublication $publication, User $actor): FormatPublication
    {
        $publication->forceFill([
            'status' => FormatPublication::STATUS_ARCHIVED,
            'updated_by_user_id' => $actor->id,
        ])->save();

        $this->auditService->log($actor, 'ARCHIVE_FORMAT', 'FormatPublication', $publication->id, [
            'title' => $publication->title,
        ]);

        return $publication->fresh(['evidenceItem', 'currentFile', 'author', 'updatedBy']);
    }

    public function restore(FormatPublication $publication, User $actor): FormatPublication
    {
        $publication->forceFill([
            'status' => FormatPublication::STATUS_ACTIVE,
            'updated_by_user_id' => $actor->id,
        ])->save();

        $this->auditService->log($actor, 'RESTORE_FORMAT', 'FormatPublication', $publication->id, [
            'title' => $publication->title,
        ]);

        return $publication->fresh(['evidenceItem', 'currentFile', 'author', 'updatedBy']);
    }

    public function assertDownloadablePath(FormatPublication $publication, FormatPublicationFile $file): void
    {
        $expectedPrefix = $this->publicationDirectory($publication).'/';
        try {
            $path = $this->normalizeRelativePath($file->stored_relative_path);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        if (! str_starts_with($path, $expectedPrefix)) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }
    }

    public function allowedExtensions(): array
    {
        return config('evidence.upload.allowed_extensions', ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp']);
    }

    public function maxUploadKb(): int
    {
        return (int) config('evidence.upload.max_kb', 15360);
    }

    private function storeFile(
        FormatPublication $publication,
        UploadedFile $upload,
        User $actor,
        ?string &$storedPath
    ): FormatPublicationFile {
        $validated = $this->validateUpload($upload);
        $directory = $this->publicationDirectory($publication);
        $storedName = (string) Str::uuid().'.'.$validated['extension'];
        $path = $upload->storeAs($directory, $storedName, 'local');

        if (! $path) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        $storedPath = $this->normalizeRelativePath($path);
        $hashSourcePath = $upload->getRealPath();

        return FormatPublicationFile::create([
            'format_publication_id' => $publication->id,
            'file_name' => $validated['safe_file_name'],
            'stored_relative_path' => $storedPath,
            'mime_type' => $validated['mime_type'],
            'size_bytes' => $validated['size_bytes'],
            'file_hash' => $hashSourcePath ? hash_file('sha256', $hashSourcePath) : null,
            'is_current' => true,
            'uploaded_by_user_id' => $actor->id,
            'uploaded_at' => now(),
        ]);
    }

    private function validateUpload(UploadedFile $file): array
    {
        $originalName = (string) $file->getClientOriginalName();
        if ($originalName === '') {
            throw new \InvalidArgumentException('Nombre de archivo invalido.');
        }

        if (preg_match('/[\\\\\/]/', $originalName) === 1) {
            throw new \InvalidArgumentException('El nombre del archivo no puede contener rutas.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $originalName) === 1) {
            throw new \InvalidArgumentException('El nombre del archivo contiene caracteres no permitidos.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowedExtensions = $this->allowedExtensions();
        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException('Formato no permitido. Formatos validos: '.implode(', ', $allowedExtensions));
        }

        $sizeBytes = (int) ($file->getSize() ?? 0);
        if ($sizeBytes <= 0) {
            throw new \InvalidArgumentException('El archivo esta vacio o no se pudo leer su tamano.');
        }

        if ($sizeBytes > $this->maxUploadKb() * 1024) {
            throw new \InvalidArgumentException('El archivo excede el tamano maximo permitido de '.$this->maxUploadKb().' KB.');
        }

        $mimeType = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        $expectedMimeTypes = array_map('strtolower', $this->allowedMimeTypes()[$extension] ?? []);

        if (! empty($expectedMimeTypes) && ! in_array($mimeType, $expectedMimeTypes, true)) {
            throw new \InvalidArgumentException('El MIME detectado no corresponde con la extension del archivo.');
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
            'safe_file_name' => $safeBaseName.'.'.$extension,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ];
    }

    private function allowedMimeTypes(): array
    {
        return config('evidence.upload.allowed_mime_types', self::DEFAULT_ALLOWED_MIME_TYPES);
    }

    private function maxFilenameChars(): int
    {
        return (int) config('evidence.upload.max_filename_chars', 120);
    }

    private function publicationDirectory(FormatPublication $publication): string
    {
        return 'format-publications/'.$publication->id;
    }

    private function normalizeRelativePath(string $path): string
    {
        $normalized = str_replace('\\\\', '/', trim($path));
        $normalized = preg_replace('#/+#', '/', $normalized);
        $normalized = ltrim((string) $normalized, '/');

        if ($normalized === '' || str_contains($normalized, "\0")) {
            throw new \InvalidArgumentException('Ruta relativa invalida.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Ruta relativa invalida.');
            }
        }

        return $normalized;
    }

    private function notifyTeachers(FormatPublication $publication, string $title, string $message): void
    {
        User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::DOCENTE))
            ->where('is_active', true)
            ->get()
            ->each(fn (User $teacher) => $this->notificationService->notifyImmediate(
                $teacher,
                NotificationType::GENERAL,
                $title,
                $message,
                $publication
            ));
    }
}
