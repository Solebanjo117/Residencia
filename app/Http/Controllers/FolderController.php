<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\FolderNode;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FolderController extends Controller
{
    public function __construct(private StorageService $storageService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $roots = $this->storageService->getAccessibleRoots($user);

        return Inertia::render('FileManager/Index', [
            'folderTree' => $roots,
            'currentFolder' => null,
            'contents' => [],
            'allowedExtensions' => config('evidence.upload.allowed_extensions', ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp']),
        ]);
    }

    public function show(Request $request, FolderNode $folder)
    {
        $this->authorize('view', $folder);

        $contents = $folder->load(['children', 'files.uploadedBy', 'files.submission']);
        $user = $request->user();
        $folder->load(['semester', 'parent']);
        $ancestors = $this->buildFolderAncestors($folder, $user);

        $visibleChildren = $contents->children
            ->filter(fn (FolderNode $child) => $user->can('view', $child))
            ->values();

        $visibleFiles = $contents->files
            ->filter(fn (EvidenceFile $file) => $user->can('view', $file))
            ->values();

        $linkedAdvanceFiles = $this->linkedAdvanceFilesFor($folder, $user);
        $allVisibleFiles = collect($visibleFiles)
            ->map(fn (EvidenceFile $file) => [$file, null])
            ->merge($linkedAdvanceFiles)
            ->values();

        $roots = $this->storageService->getAccessibleRoots($user);

        return Inertia::render('FileManager/Index', [
            'folderTree' => $roots,
            'currentFolder' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'parent_id' => $folder->parent_id,
                'can_upload' => $user->can('upload', $folder),
                'ancestors' => $ancestors,
            ],
            'semesterName' => $folder->semester?->name,
            'allowedExtensions' => config('evidence.upload.allowed_extensions', ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp']),
            'contents' => [
                'folders' => $visibleChildren,
                'files' => $allVisibleFiles->map(function (array $entry) use ($user) {
                    /** @var EvidenceFile $file */
                    [$file, $linkedFrom] = $entry;
                    $submission = $file->submission;
                    $isDocx = $file->isDocx();
                    $canPreview = in_array($file->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);

                    return [
                        'id' => $file->id,
                        'name' => $file->file_name,
                        'size' => $file->size_bytes,
                        'uploaded_at' => $file->uploaded_at->format('Y-m-d H:i'),
                        'uploaded_by' => $file->uploadedBy->name,
                        'mime_type' => $file->mime_type,
                        'status' => $submission
                            ? ($submission->final_approved_at
                                ? 'FINAL_APPROVED'
                                : ($submission->status->value === 'APPROVED' ? 'OFFICE_APPROVED' : $submission->status->value))
                            : null,
                        'is_late' => (bool) $submission?->submitted_late,
                        'linked_from' => $linkedFrom,
                        'is_docx' => $isDocx,
                        'can_preview' => $canPreview,
                        'preview_url' => $canPreview ? route('files.preview', $file->id) : null,
                        'docx_editor_url' => $isDocx ? route('files.docx.show', $file->id) : null,
                        'can_edit_docx' => $isDocx && $user->can('replace', $file),
                        'can_replace' => $user->can('replace', $file),
                        'can_delete' => $user->can('delete', $file),
                        'download_url' => route('files.download', $file->id),
                    ];
                }),
            ],
        ]);
    }

    private function buildFolderAncestors(FolderNode $folder, User $user): array
    {
        $ancestors = [];
        $current = $folder->parent;

        while ($current) {
            $ancestors[] = [
                'id' => $current->id,
                'name' => $current->name,
                'can_view' => $user->can('view', $current),
            ];

            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return array_reverse($ancestors);
    }

    private function linkedAdvanceFilesFor(FolderNode $folder, User $user)
    {
        $sourceFolder = $this->sourceAdvanceFolderFor($folder);

        if (!$sourceFolder || !$user->can('view', $sourceFolder)) {
            return collect();
        }

        return $sourceFolder
            ->files()
            ->with(['uploadedBy', 'submission'])
            ->get()
            ->filter(fn (EvidenceFile $file) => $user->can('view', $file))
            ->map(fn (EvidenceFile $file) => [$file, 'SD2-AVANCE-50%'])
            ->values();
    }

    private function sourceAdvanceFolderFor(FolderNode $folder): ?FolderNode
    {
        $currentName = mb_strtoupper($folder->name);

        if (!str_contains($currentName, 'SD4') || !str_contains($currentName, '100')) {
            return null;
        }

        $sourceNames = [
            str_replace('SD4', 'SD2', $folder->name),
            str_replace('SD4-AVANCE-100%', 'SD2-AVANCE-50%', $folder->name),
            'SD2-AVANCE-50%',
        ];

        return FolderNode::query()
            ->where('storage_root_id', $folder->storage_root_id)
            ->where('parent_id', $folder->parent_id)
            ->whereIn('name', array_values(array_unique($sourceNames)))
            ->first();
    }
}
