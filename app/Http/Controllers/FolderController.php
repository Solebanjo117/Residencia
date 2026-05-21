<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\FolderNode;
use App\Models\Semester;
use App\Models\User;
use App\Services\FolderManagerService;
use App\Services\OnlyOfficeService;
use App\Services\SeguimientoSharedFileService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FolderController extends Controller
{
    public function __construct(
        private StorageService $storageService,
        private FolderManagerService $folderManagerService,
        private SeguimientoSharedFileService $seguimientoSharedFiles,
        private OnlyOfficeService $onlyOfficeService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $defaultFolder = $this->defaultFolderFor($user);

        if ($defaultFolder) {
            return redirect()->route('folders.show', $defaultFolder->id);
        }

        $roots = $this->decorateFolderTree(
            $this->storageService->getAccessibleRoots($user)
        );

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

        $contents = $folder->load(['children']);
        $user = $request->user();
        $folder->load(['semester', 'parent']);
        $ancestors = $this->buildFolderAncestors($folder, $user);

        $visibleChildren = $contents->children
            ->filter(fn (FolderNode $child) => $user->can('view', $child))
            ->values();

        $visibleFolderIds = $this->visibleFolderIdsForContents($folder, $user);
        $visibleFiles = EvidenceFile::query()
            ->with(['uploadedBy', 'submission', 'folderNode'])
            ->currentVersion()
            ->whereIn('folder_node_id', $visibleFolderIds)
            ->orderByDesc('uploaded_at')
            ->get()
            ->filter(fn (EvidenceFile $file) => $user->can('view', $file))
            ->values();

        $linkedAdvanceFiles = $this->linkedAdvanceFilesFor($folder, $user);
        $allVisibleFiles = collect($visibleFiles)
            ->map(fn (EvidenceFile $file) => [$file, null])
            ->merge($linkedAdvanceFiles)
            ->unique(fn (array $entry) => $entry[0]->id)
            ->values();

        $roots = $this->decorateFolderTree(
            $this->storageService->getAccessibleRoots($user)
        );
        $displayPath = $this->readableFolderPath($folder);
        $readableUrl = $this->readableFolderUrl($folder);

        return Inertia::render('FileManager/Index', [
            'folderTree' => $roots,
            'currentFolder' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'parent_id' => $folder->parent_id,
                'display_path' => $displayPath,
                'readable_url' => $readableUrl,
                'icon_key' => $folder->icon_key,
                'color_key' => $folder->color_key,
                'can_upload' => $user->can('upload', $folder),
                'can_create_folder' => $user->can('create', $folder),
                'can_rename' => $user->can('update', $folder),
                'can_move' => $user->can('move', $folder),
                'can_delete' => $user->can('delete', $folder),
                'ancestors' => $ancestors,
            ],
            'semesterName' => $folder->semester?->name,
            'allowedExtensions' => config('evidence.upload.allowed_extensions', ['docx', 'pdf', 'jpg', 'jpeg', 'png', 'webp']),
            'contents' => [
                'folders' => $visibleChildren->map(fn (FolderNode $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'parent_id' => $child->parent_id,
                    'can_rename' => $user->can('update', $child),
                    'can_move' => $user->can('move', $child),
                    'can_delete' => $user->can('delete', $child),
                    'display_path' => $this->readableFolderPath($child),
                    'readable_url' => $this->readableFolderUrl($child),
                    'icon_key' => $child->icon_key,
                    'color_key' => $child->color_key,
                    'move_url' => route('folders.move', $child->id),
                    'update_url' => route('folders.update', $child->id),
                    'delete_url' => route('folders.destroy', $child->id),
                ]),
                'files' => $allVisibleFiles->map(function (array $entry) use ($user, $folder) {
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
                        'folder_name' => $file->folderNode?->name,
                        'folder_path' => $this->relativeFolderPath($folder, $file->folderNode),
                        'is_docx' => $isDocx,
                        'can_preview' => $canPreview,
                        'preview_url' => $canPreview ? route('files.preview', $file->id) : null,
                        'docx_editor_url' => $isDocx ? route('files.docx.show', $file->id) : null,
                        'onlyoffice_editor_url' => $isDocx && $this->onlyOfficeService->isEnabled() ? route('files.onlyoffice.show', $file->id) : null,
                        'file_url' => route('files.download', $file->id),
                        'folder_url' => $file->folderNode ? $this->readableFolderUrl($file->folderNode) : null,
                        'can_edit_docx' => $isDocx && $user->can('replace', $file),
                        'can_replace' => $user->can('replace', $file),
                        'can_delete' => $user->can('delete', $file),
                        'can_move' => $user->can('move', $file),
                        'move_url' => $user->can('move', $file) ? route('files.move', $file->id) : null,
                        'download_url' => route('files.download', $file->id),
                    ];
                }),
            ],
        ]);
    }

    public function showByPath(Request $request, string $folderPath)
    {
        $folder = $this->resolveFolderByReadablePath($folderPath);

        abort_unless($folder, 404);

        return $this->show($request, $folder);
    }

    private function defaultFolderFor(User $user): ?FolderNode
    {
        if (! $user->isDocente()) {
            return null;
        }

        $activeSemesterId = Semester::active()?->id;

        return FolderNode::query()
            ->where('owner_user_id', $user->id)
            ->when($activeSemesterId, fn ($query) => $query->where('semester_id', $activeSemesterId))
            ->orderBy('parent_id')
            ->orderBy('id')
            ->first();
    }

    private function visibleFolderIdsForContents(FolderNode $folder, User $user): array
    {
        $ids = [];
        $pending = [$folder];

        while ($pending !== []) {
            /** @var FolderNode $current */
            $current = array_shift($pending);

            if (! $user->can('view', $current)) {
                continue;
            }

            $ids[] = $current->id;

            $children = FolderNode::query()
                ->where('parent_id', $current->id)
                ->get();

            foreach ($children as $child) {
                $pending[] = $child;
            }
        }

        return array_values(array_unique($ids));
    }

    private function relativeFolderPath(FolderNode $currentFolder, ?FolderNode $fileFolder): ?string
    {
        if (! $fileFolder || $fileFolder->id === $currentFolder->id) {
            return null;
        }

        $segments = [];
        $cursor = $fileFolder;

        while ($cursor && $cursor->id !== $currentFolder->id) {
            array_unshift($segments, $cursor->name);
            $cursor->loadMissing('parent');
            $cursor = $cursor->parent;
        }

        return $cursor ? implode(' / ', $segments) : $fileFolder->name;
    }

    public function storeSubfolder(Request $request, FolderNode $folder)
    {
        $this->authorize('create', $folder);

        $request->validate([
            'name' => 'required|string|max:160',
        ]);

        try {
            $this->folderManagerService->createSubfolder($request->user(), $folder, $request->input('name'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return back()->with('success', 'Carpeta creada correctamente.');
    }

    public function update(Request $request, FolderNode $folder)
    {
        $this->authorize('update', $folder);

        $request->validate([
            'name' => 'required|string|max:160',
            'icon_key' => ['nullable', 'string', Rule::in(['folder', 'book', 'file', 'calendar', 'users', 'checklist'])],
            'color_key' => ['nullable', 'string', Rule::in(['yellow', 'blue', 'green', 'purple', 'red', 'gray'])],
        ]);

        try {
            $this->folderManagerService->updateFolder($request->user(), $folder, [
                'name' => $request->input('name'),
                'icon_key' => $request->exists('icon_key') ? $request->input('icon_key') : $folder->icon_key,
                'color_key' => $request->exists('color_key') ? $request->input('color_key') : $folder->color_key,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return back()->with('success', 'Carpeta renombrada correctamente.');
    }

    public function move(Request $request, FolderNode $folder)
    {
        $this->authorize('move', $folder);

        $request->validate([
            'target_folder_id' => 'required|exists:folder_nodes,id',
        ]);

        $target = FolderNode::findOrFail($request->input('target_folder_id'));

        try {
            $this->folderManagerService->moveFolder($request->user(), $folder, $target);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['target_folder_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Carpeta movida correctamente.');
    }

    public function destroy(Request $request, FolderNode $folder)
    {
        $this->authorize('delete', $folder);

        try {
            $this->folderManagerService->deleteFolder($request->user(), $folder);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['folder' => $e->getMessage()]);
        }

        return redirect()->route('folders.index')->with('success', 'Carpeta eliminada correctamente.');
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
                'display_path' => $this->readableFolderPath($current),
                'readable_url' => $this->readableFolderUrl($current),
                'icon_key' => $current->icon_key,
                'color_key' => $current->color_key,
            ];

            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return array_reverse($ancestors);
    }

    private function resolveFolderByReadablePath(string $folderPath): ?FolderNode
    {
        $segments = collect(explode('/', trim($folderPath, '/')))
            ->map(fn (string $segment) => rawurldecode($segment))
            ->filter(fn (string $segment) => $segment !== '')
            ->values();

        if ($segments->isEmpty()) {
            return null;
        }

        $folder = FolderNode::query()
            ->whereNull('parent_id')
            ->where('name', $segments->first())
            ->orderBy('id')
            ->first();

        foreach ($segments->skip(1) as $segment) {
            if (! $folder) {
                return null;
            }

            $folder = FolderNode::query()
                ->where('parent_id', $folder->id)
                ->where('name', $segment)
                ->orderBy('id')
                ->first();
        }

        return $folder;
    }

    private function readableFolderPath(FolderNode $folder): string
    {
        return implode(' / ', $this->folderPathSegments($folder));
    }

    private function readableFolderUrl(FolderNode $folder): string
    {
        $encodedSegments = array_map(
            fn (string $segment) => rawurlencode($segment),
            $this->folderPathSegments($folder)
        );

        return '/files/folders/'.implode('/', $encodedSegments);
    }

    private function folderPathSegments(FolderNode $folder): array
    {
        $segments = [];
        $current = $folder;

        while ($current) {
            array_unshift($segments, $current->name);
            $current->loadMissing('parent');
            $current = $current->parent;
        }

        return $segments;
    }

    private function decorateFolderTree(array $nodes): array
    {
        return array_map(function ($node) {
            if (is_array($node)) {
                $node['children'] = $this->decorateFolderTree($node['children'] ?? []);

                return $node;
            }

            if ($node instanceof FolderNode) {
                $node->setAttribute('display_path', $this->readableFolderPath($node));
                $node->setAttribute('readable_url', $this->readableFolderUrl($node));
                $node->setAttribute('icon_key', $node->icon_key);
                $node->setAttribute('color_key', $node->color_key);

                if ($node->relationLoaded('children')) {
                    $children = $node->children->map(function (FolderNode $child) {
                        $child->setAttribute('display_path', $this->readableFolderPath($child));
                        $child->setAttribute('readable_url', $this->readableFolderUrl($child));
                        $child->setAttribute('icon_key', $child->icon_key);
                        $child->setAttribute('color_key', $child->color_key);

                        if ($child->relationLoaded('children')) {
                            $child->setRelation('children', collect($this->decorateFolderTree($child->children->all())));
                        }

                        return $child;
                    });

                    $node->setRelation('children', $children);
                }
            }

            return $node;
        }, $nodes);
    }

    private function linkedAdvanceFilesFor(FolderNode $folder, User $user)
    {
        $seguimientoFiles = $this->seguimientoSharedFiles->sharedFilesForFolder($folder, $user);
        $sourceFolder = $this->sourceAdvanceFolderFor($folder);

        if (! $sourceFolder || ! $user->can('view', $sourceFolder)) {
            return $seguimientoFiles;
        }

        $projectFiles = $sourceFolder
            ->files()
            ->with(['uploadedBy', 'submission', 'folderNode'])
            ->currentVersion()
            ->get()
            ->filter(fn (EvidenceFile $file) => $user->can('view', $file))
            ->map(fn (EvidenceFile $file) => [$file, 'SD2-AVANCE-50%'])
            ->values();

        return $seguimientoFiles
            ->merge($projectFiles)
            ->unique(fn (array $entry) => $entry[0]->id)
            ->values();
    }

    private function sourceAdvanceFolderFor(FolderNode $folder): ?FolderNode
    {
        $currentName = mb_strtoupper($folder->name);

        if (! str_contains($currentName, 'SD4') || ! str_contains($currentName, '100')) {
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
