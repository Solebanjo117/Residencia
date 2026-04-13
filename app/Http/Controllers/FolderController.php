<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\FolderNode;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FolderController extends Controller
{
    protected $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Fetch root folders accessible to the user
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

        $visibleChildren = $contents->children
            ->filter(fn (FolderNode $child) => $user->can('view', $child))
            ->values();

        $visibleFiles = $contents->files
            ->filter(fn (EvidenceFile $file) => $user->can('view', $file))
            ->values();

        // Re-fetch tree structure if needed, or pass it via props (already loaded in index if SPA navigation works well, 
        // but Inertia reloads props on visit unless partial reload).
        // For simplicity, let's just return the current folder's content. The tree can be fetched via API or passed again.
        // Or we can assume the frontend maintains the tree state if it's not a full page reload.
        // But for robust initial load, we might need the tree.
        
        // Let's return the tree structure as well, perhaps optimized.
        $roots = $this->storageService->getAccessibleRoots($user);

        $folder->load('semester');

        return Inertia::render('FileManager/Index', [
            'folderTree' => $roots,
            'currentFolder' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'can_upload' => $user->can('upload', $folder),
            ],
            'semesterName' => $folder->semester?->name,
            'allowedExtensions' => config('evidence.upload.allowed_extensions', ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp']),
            'contents' => [
                'folders' => $visibleChildren,
                'files' => $visibleFiles->map(function ($file) use ($user) {
                    $submission = $file->submission;
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
                        'can_preview' => $canPreview,
                        'preview_url' => $canPreview ? route('files.preview', $file->id) : null,
                        'can_replace' => $user->can('replace', $file),
                        'can_delete' => $user->can('delete', $file),
                        'download_url' => route('files.download', $file->id),
                    ];
                }),
            ],
        ]);
    }
}
