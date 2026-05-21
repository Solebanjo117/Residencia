<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class OnlyOfficeService
{
    private const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function __construct(
        private StorageService $storageService,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('onlyoffice.enabled')
            && $this->documentServerUrl() !== '';
    }

    public function documentServerUrl(): string
    {
        return rtrim((string) config('onlyoffice.document_server_url'), '/');
    }

    public function apiUrl(EvidenceFile $file): string
    {
        $key = $this->documentKey($file);

        return $this->documentServerUrl().'/web-apps/apps/api/documents/api.js?shardkey='.$key;
    }

    public function editorConfig(EvidenceFile $file, User $user, bool $canEdit): array
    {
        $this->ensureReady($file);
        $mode = $canEdit ? 'edit' : 'view';

        return [
            'documentType' => 'word',
            'type' => 'desktop',
            'width' => '100%',
            'height' => '100%',
            'document' => [
                'fileType' => 'docx',
                'key' => $this->documentKey($file),
                'title' => $file->file_name,
                'url' => $this->signedDownloadUrl($file),
                'permissions' => [
                    'comment' => $canEdit,
                    'download' => true,
                    'edit' => $canEdit,
                    'fillForms' => $canEdit,
                    'print' => true,
                    'review' => $canEdit,
                ],
            ],
            'editorConfig' => [
                'callbackUrl' => $this->signedCallbackUrl($file, $user),
                'lang' => 'es-MX',
                'mode' => $mode,
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
                'customization' => [
                    'autosave' => true,
                    'forcesave' => true,
                ],
            ],
        ];
    }

    public function signedDownloadUrl(EvidenceFile $file): string
    {
        return URL::temporarySignedRoute(
            'onlyoffice.files.download',
            now()->addMinutes($this->signedUrlTtlMinutes()),
            ['file' => $file->id]
        );
    }

    public function signedCallbackUrl(EvidenceFile $file, User $user): string
    {
        return URL::temporarySignedRoute(
            'onlyoffice.files.callback',
            now()->addMinutes($this->signedUrlTtlMinutes()),
            [
                'file' => $file->id,
                'user' => $user->id,
            ]
        );
    }

    public function downloadEditedDocument(string $url): string
    {
        $response = Http::timeout((int) config('onlyoffice.request_timeout_seconds', 30))->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('OnlyOffice no devolvio el DOCX editado correctamente.');
        }

        $body = $response->body();
        if ($body === '') {
            throw new RuntimeException('OnlyOffice devolvio un documento vacio.');
        }

        return $body;
    }

    public function saveEditedDocument(EvidenceFile $file, User $user, string $binary, array $callbackPayload): EvidenceFile
    {
        $this->ensureReady($file);

        return $this->storageService->overwriteGeneratedEvidence(
            $file,
            $binary,
            $file->file_name,
            self::DOCX_MIME,
            $user,
            'ONLYOFFICE',
            [
                'callback_status' => $callbackPayload['status'] ?? null,
                'callback_key' => $callbackPayload['key'] ?? null,
                'callback_users' => $callbackPayload['users'] ?? [],
            ],
        );
    }

    public function ensureReady(EvidenceFile $file): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('OnlyOffice no esta configurado.');
        }

        if (! $file->isDocx()) {
            throw new RuntimeException('OnlyOffice solo esta habilitado para archivos DOCX.');
        }

        $this->storageService->assertEvidenceFilePath($file);

        if (! Storage::disk('local')->exists($file->stored_relative_path)) {
            throw new RuntimeException('No se encontro el archivo DOCX en almacenamiento.');
        }
    }

    public function documentKey(EvidenceFile $file): string
    {
        $hash = $file->file_hash ?: sha1((string) $file->stored_relative_path);

        return substr('residencia-'.$file->id.'-'.sha1($hash.'|'.$file->last_edited_at?->timestamp.'|'.$file->uploaded_at?->timestamp), 0, 120);
    }

    private function signedUrlTtlMinutes(): int
    {
        return max(5, (int) config('onlyoffice.signed_url_ttl_minutes', 120));
    }
}
