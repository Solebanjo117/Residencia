<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EvidenceFile;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\FormatPublication;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')
            ->select(
                'id',
                'action',
                'entity_type',
                'entity_id',
                'at',
                'user_id',
                'metadata'
            );

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                })
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('entity_type', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('at', 'desc')->limit(200)->get();

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs->map(fn (AuditLog $log) => $this->auditPayload($log)),
        ]);
    }

    private function auditPayload(AuditLog $log): array
    {
        $target = $this->resolveTarget($log);

        return [
            'id' => $log->id,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'at' => $log->at?->toDateTimeString(),
            'metadata' => $log->metadata,
            'user_name' => $log->user?->name,
            'user_email' => $log->user?->email,
            'entity_label' => $target['label'],
            'entity_url' => $target['url'],
            'target_status' => $target['status'],
            'change_summary' => $this->changeSummary($log),
        ];
    }

    private function resolveTarget(AuditLog $log): array
    {
        if (! $log->entity_type || ! $log->entity_id) {
            return [
                'label' => '-',
                'url' => null,
                'status' => 'none',
            ];
        }

        return match ($log->entity_type) {
            'EvidenceSubmission' => $this->submissionTarget($log->entity_id),
            'EvidenceFile' => $this->fileTarget($log->entity_id),
            'FolderNode' => $this->folderTarget($log->entity_id),
            'FormatPublication' => $this->formatPublicationTarget($log->entity_id),
            default => [
                'label' => "{$log->entity_type} #{$log->entity_id}",
                'url' => null,
                'status' => 'unsupported',
            ],
        };
    }

    private function submissionTarget(int $submissionId): array
    {
        $submission = EvidenceSubmission::query()
            ->with('semester:id,name')
            ->find($submissionId);

        if (! $submission) {
            return $this->missingTarget('EvidenceSubmission', $submissionId);
        }

        return [
            'label' => 'Entrega #'.$submission->id,
            'url' => $this->asesoriasUrl([
                'semester' => $submission->semester?->name,
                'submission_id' => $submission->id,
            ]),
            'status' => 'linked',
        ];
    }

    private function fileTarget(int $fileId): array
    {
        $file = EvidenceFile::withTrashed()
            ->with(['submission.semester:id,name', 'folderNode:id'])
            ->find($fileId);

        if (! $file) {
            return $this->missingTarget('EvidenceFile', $fileId);
        }

        if ($file->submission) {
            return [
                'label' => 'Archivo #'.$file->id,
                'url' => $this->asesoriasUrl([
                    'semester' => $file->submission->semester?->name,
                    'submission_id' => $file->submission->id,
                    'focus_file_id' => $file->id,
                ]),
                'status' => 'linked',
            ];
        }

        if ($file->folderNode) {
            return [
                'label' => 'Archivo #'.$file->id,
                'url' => route('folders.show', $file->folderNode->id, false),
                'status' => 'linked',
            ];
        }

        return [
            'label' => 'Archivo #'.$file->id,
            'url' => null,
            'status' => 'missing',
        ];
    }

    private function folderTarget(int $folderId): array
    {
        $folder = FolderNode::find($folderId);

        if (! $folder) {
            return $this->missingTarget('FolderNode', $folderId);
        }

        return [
            'label' => 'Carpeta #'.$folder->id,
            'url' => route('folders.show', $folder->id, false),
            'status' => 'linked',
        ];
    }

    private function formatPublicationTarget(int $publicationId): array
    {
        $publication = FormatPublication::find($publicationId);

        if (! $publication) {
            return $this->missingTarget('FormatPublication', $publicationId);
        }

        return [
            'label' => 'Formato #'.$publication->id,
            'url' => route('formatos.index', ['publication' => $publication->id], false),
            'status' => 'linked',
        ];
    }

    private function missingTarget(string $entityType, int $entityId): array
    {
        return [
            'label' => "{$entityType} #{$entityId}",
            'url' => null,
            'status' => 'missing',
        ];
    }

    private function asesoriasUrl(array $query): string
    {
        $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

        return route('asesorias', [], false).'?'.http_build_query($query);
    }

    private function changeSummary(AuditLog $log): ?string
    {
        $metadata = $log->metadata ?? [];

        return match ($log->action) {
            'CHANGE_STATUS' => $this->fromToSummary($metadata, 'from', 'to'),
            'REPLACE_FILE' => $this->fromToSummary($metadata, 'old_file_name', 'new_file_name'),
            'RENAME_FOLDER' => $this->fromToSummary($metadata, 'old_name', 'new_name'),
            'MOVE_FOLDER' => $this->fromToSummary($metadata, 'old_relative_path', 'new_relative_path'),
            'MOVE_FILE' => $this->fromToSummary($metadata, 'old_stored_relative_path', 'new_stored_relative_path'),
            'UPDATE_ADVISORY' => $this->changedFieldsSummary($metadata),
            default => $metadata['title'] ?? $metadata['file_name'] ?? $metadata['name'] ?? null,
        };
    }

    private function fromToSummary(array $metadata, string $fromKey, string $toKey): ?string
    {
        $from = $metadata[$fromKey] ?? null;
        $to = $metadata[$toKey] ?? null;

        if ($from === null && $to === null) {
            return null;
        }

        return trim(($from ?? '-').' -> '.($to ?? '-'));
    }

    private function changedFieldsSummary(array $metadata): ?string
    {
        $before = is_array($metadata['before'] ?? null) ? $metadata['before'] : [];
        $after = is_array($metadata['after'] ?? null) ? $metadata['after'] : [];

        $fields = collect(array_keys($after))
            ->filter(fn (string $key) => ($before[$key] ?? null) !== ($after[$key] ?? null))
            ->values();

        return $fields->isNotEmpty()
            ? 'Campos actualizados: '.$fields->implode(', ')
            : null;
    }
}
