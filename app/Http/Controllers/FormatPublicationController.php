<?php

namespace App\Http\Controllers;

use App\Models\EvidenceItem;
use App\Models\FormatPublication;
use App\Services\AuditService;
use App\Services\FormatPublicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FormatPublicationController extends Controller
{
    public function __construct(
        private readonly FormatPublicationService $formatPublicationService,
        private readonly AuditService $auditService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', FormatPublication::class);

        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $evidenceItemId = $request->query('evidence_item_id');

        $query = FormatPublication::query()
            ->with(['evidenceItem:id,name', 'currentFile', 'author:id,name', 'updatedBy:id,name'])
            ->when(! $user->isAdministrativeAuthority(), fn ($q) => $q->active())
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('body', 'like', '%'.$search.'%');
                });
            })
            ->when(is_numeric($evidenceItemId), fn ($q) => $q->where('evidence_item_id', (int) $evidenceItemId))
            ->orderByRaw('COALESCE(format_publications.updated_at, format_publications.published_at) desc')
            ->orderByDesc('format_publications.id');

        return Inertia::render('Formatos/Index', [
            'publications' => $query->get()->map(fn (FormatPublication $publication) => $this->publicationPayload($publication, $user))->values(),
            'evidenceItems' => EvidenceItem::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'canManageFormats' => $user->can('create', FormatPublication::class),
            'allowedExtensions' => $this->formatPublicationService->allowedExtensions(),
            'maxUploadKb' => $this->formatPublicationService->maxUploadKb(),
            'focusedPublicationId' => is_numeric($request->query('publication'))
                ? (int) $request->query('publication')
                : null,
            'filters' => [
                'search' => $search,
                'evidence_item_id' => is_numeric($evidenceItemId) ? (int) $evidenceItemId : null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', FormatPublication::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
            'evidence_item_id' => ['required', 'integer', 'exists:evidence_items,id'],
            'file' => ['required', 'file'],
        ]);

        try {
            $this->formatPublicationService->publish($validated, $request->file('file'), $request->user());
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('formatos.index')->with('success', 'Formato publicado correctamente.');
    }

    public function update(Request $request, FormatPublication $publication)
    {
        $this->authorize('update', $publication);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
            'evidence_item_id' => ['required', 'integer', 'exists:evidence_items,id'],
        ]);

        $this->formatPublicationService->update($publication, $validated, $request->user());

        return redirect()
            ->route('formatos.index', ['publication' => $publication->id])
            ->with('success', 'Formato actualizado correctamente.');
    }

    public function replaceFile(Request $request, FormatPublication $publication)
    {
        $this->authorize('replaceFile', $publication);

        $request->validate([
            'file' => ['required', 'file'],
        ]);

        try {
            $this->formatPublicationService->replaceFile($publication, $request->file('file'), $request->user());
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()
            ->route('formatos.index', ['publication' => $publication->id])
            ->with('success', 'Archivo del formato reemplazado correctamente.');
    }

    public function archive(Request $request, FormatPublication $publication)
    {
        $this->authorize('archive', $publication);

        $this->formatPublicationService->archive($publication, $request->user());

        return redirect()->route('formatos.index')->with('success', 'Formato archivado correctamente.');
    }

    public function restore(Request $request, FormatPublication $publication)
    {
        $this->authorize('restore', $publication);

        $this->formatPublicationService->restore($publication, $request->user());

        return redirect()
            ->route('formatos.index', ['publication' => $publication->id])
            ->with('success', 'Formato restaurado correctamente.');
    }

    public function download(Request $request, FormatPublication $publication)
    {
        $this->authorize('download', $publication);

        $file = $publication->currentFile;
        if (! $file) {
            abort(404);
        }

        $this->formatPublicationService->assertDownloadablePath($publication, $file);

        $this->auditService->log($request->user(), 'DOWNLOAD_FORMAT', 'FormatPublication', $publication->id, [
            'file_id' => $file->id,
            'file_name' => $file->file_name,
            'stored_relative_path' => $file->stored_relative_path,
        ]);

        return Storage::disk('local')->download($file->stored_relative_path, $file->file_name);
    }

    private function publicationPayload(FormatPublication $publication, $user): array
    {
        $file = $publication->currentFile;

        return [
            'id' => $publication->id,
            'title' => $publication->title,
            'body' => $publication->body,
            'status' => $publication->status,
            'published_at' => $publication->published_at?->toDateTimeString(),
            'updated_at' => $publication->updated_at?->toDateTimeString(),
            'evidence_item' => [
                'id' => $publication->evidenceItem?->id,
                'name' => $publication->evidenceItem?->name,
            ],
            'file' => $file ? [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'size_bytes' => $file->size_bytes,
                'mime_type' => $file->mime_type,
                'uploaded_at' => $file->uploaded_at?->toDateTimeString(),
                'download_url' => route('formatos.download', $publication, false),
            ] : null,
            'author_name' => $publication->author?->name,
            'updated_by_name' => $publication->updatedBy?->name,
            'can_update' => $user->can('update', $publication),
            'can_archive' => $user->can('archive', $publication),
            'can_restore' => $user->can('restore', $publication),
        ];
    }
}

