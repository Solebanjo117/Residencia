<?php

namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Services\DocxEditorService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class DocxEditorController extends Controller
{
    public function show(Request $request, EvidenceFile $file, DocxEditorService $docxEditorService)
    {
        $this->authorize('view', $file);
        abort_unless($file->isDocx(), 404);

        $canEdit = $request->user()->can('replace', $file);
        $payload = null;
        $loadError = null;

        try {
            $payload = $docxEditorService->loadDocument($file);
        } catch (RuntimeException $exception) {
            $loadError = $exception->getMessage();
        }

        return Inertia::render('FileManager/DocxEditor', [
            'file' => [
                'id' => $file->id,
                'name' => $file->file_name,
                'mime_type' => $file->mime_type,
                'uploaded_at' => $file->uploaded_at?->toDateTimeString(),
                'uploaded_by' => $file->uploadedBy?->name,
                'last_edited_at' => $file->last_edited_at?->toDateTimeString(),
                'last_edited_by' => $file->editedBy?->name,
                'download_url' => route('files.download', $file->id),
                'folder_url' => route('folders.show', $file->folder_node_id),
                'is_current_version' => (bool) $file->is_current_version,
                'can_edit' => $canEdit,
            ],
            'document' => [
                'html' => $payload['html'] ?? '',
                'header_html' => $payload['header_html'] ?? '',
                'footer_html' => $payload['footer_html'] ?? '',
                'warnings' => $payload['warnings'] ?? [],
                'stats' => $payload['stats'] ?? null,
                'version_history' => $payload['version_history'] ?? [],
                'load_error' => $loadError,
                'sections' => $payload['sections'] ?? [
                    'has_header' => false,
                    'has_footer' => false,
                ],
            ],
            'capabilities' => [
                'can_edit' => $canEdit,
            ],
        ]);
    }

    public function store(Request $request, EvidenceFile $file, DocxEditorService $docxEditorService)
    {
        $this->authorize('replace', $file);
        abort_unless($file->isDocx(), 404);

        $validated = $request->validate([
            'html' => 'required|string',
            'header_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'save_mode' => 'required|in:replace_current,new_version',
        ]);

        try {
            $savedFile = $docxEditorService->saveDocument(
                $file,
                $validated['html'],
                $request->user(),
                $validated['save_mode'] === 'new_version',
                $validated['header_html'] ?? null,
                $validated['footer_html'] ?? null
            );

            $savedFile->submission?->update([
                'last_updated_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'docx' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('files.docx.show', $savedFile->id)
            ->with('success', $validated['save_mode'] === 'new_version'
                ? 'Nueva version DOCX guardada correctamente.'
                : 'Documento DOCX guardado correctamente.'
            );
    }
}
