<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SeguimientoSharedFileService
{
    private const SEGMENT_LABELS = [
        1 => 'SEG 01',
        2 => 'SEG 02',
        3 => 'SEG 03',
        4 => 'SEG 04 FINAL',
    ];

    public function itemStep(?string $name): ?int
    {
        $normalized = $this->normalizeName($name);

        return match (true) {
            str_contains($normalized, 'SEG 01') || str_contains($normalized, 'SEG01') => 1,
            str_contains($normalized, 'SEG 02') || str_contains($normalized, 'SEG02') => 2,
            str_contains($normalized, 'SEG 03') || str_contains($normalized, 'SEG03') => 3,
            str_contains($normalized, 'SEG 04') || str_contains($normalized, 'SEG04') => 4,
            default => null,
        };
    }

    public function folderStep(?string $name): ?int
    {
        return $this->itemStep($name);
    }

    public function isSharedItem(?string $name): bool
    {
        return $this->itemStep($name) !== null;
    }

    public function sharedFilesForCell(
        ?EvidenceSubmission $currentSubmission,
        Collection $loadSubmissions,
        ?string $currentItemName
    ): Collection {
        if (! $this->isSharedItem($currentItemName)) {
            return $currentSubmission?->files
                ? $currentSubmission->files->map(fn (EvidenceFile $file) => [$file, null])->values()
                : collect();
        }

        return $loadSubmissions
            ->filter(fn (EvidenceSubmission $submission) => $this->isSharedItem($submission->evidenceItem?->name))
            ->flatMap(function (EvidenceSubmission $submission) use ($currentSubmission) {
                return $submission->files->map(function (EvidenceFile $file) use ($submission, $currentSubmission) {
                    $linkedFrom = $currentSubmission && (int) $submission->id === (int) $currentSubmission->id
                        ? null
                        : $this->labelForItemName($submission->evidenceItem?->name);

                    return [$file, $linkedFrom];
                });
            })
            ->unique(fn (array $entry) => $entry[0]->id)
            ->values();
    }

    public function currentSharedFileForSubmission(EvidenceSubmission $submission): ?EvidenceFile
    {
        $submission->loadMissing('evidenceItem');

        if (! $this->isSharedItem($submission->evidenceItem?->name)) {
            return null;
        }

        $submissionIds = EvidenceSubmission::query()
            ->with('evidenceItem')
            ->where('teacher_user_id', $submission->teacher_user_id)
            ->where('semester_id', $submission->semester_id)
            ->where('teaching_load_id', $submission->teaching_load_id)
            ->get()
            ->filter(fn (EvidenceSubmission $candidate) => $this->isSharedItem($candidate->evidenceItem?->name))
            ->pluck('id');

        if ($submissionIds->isEmpty()) {
            return null;
        }

        return EvidenceFile::query()
            ->with(['submission.evidenceItem', 'folderNode', 'uploadedBy'])
            ->currentVersion()
            ->whereIn('submission_id', $submissionIds)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->first();
    }

    public function sharedFilesForFolder(FolderNode $folder, User $user): Collection
    {
        if (! $folder->parent_id || ! $this->folderStep($folder->name)) {
            return collect();
        }

        return FolderNode::query()
            ->where('storage_root_id', $folder->storage_root_id)
            ->where('parent_id', $folder->parent_id)
            ->get()
            ->filter(fn (FolderNode $sibling) => $sibling->id !== $folder->id && $this->folderStep($sibling->name))
            ->flatMap(function (FolderNode $sibling) use ($user) {
                if (! $user->can('view', $sibling)) {
                    return collect();
                }

                return $sibling
                    ->files()
                    ->with(['uploadedBy', 'submission', 'folderNode'])
                    ->currentVersion()
                    ->get()
                    ->filter(fn (EvidenceFile $file) => $user->can('view', $file))
                    ->map(fn (EvidenceFile $file) => [$file, $this->labelForFolderName($sibling->name)]);
            })
            ->unique(fn (array $entry) => $entry[0]->id)
            ->values();
    }

    public function labelForItemName(?string $name): ?string
    {
        $step = $this->itemStep($name);

        return $step ? self::SEGMENT_LABELS[$step] : null;
    }

    public function labelForFolderName(?string $name): ?string
    {
        $step = $this->folderStep($name);

        return $step ? self::SEGMENT_LABELS[$step] : $name;
    }

    private function normalizeName(?string $name): string
    {
        $normalized = Str::ascii(mb_strtoupper((string) $name));

        return trim(preg_replace('/\s+/', ' ', str_replace(['.', '_', '-'], ' ', $normalized)));
    }
}
