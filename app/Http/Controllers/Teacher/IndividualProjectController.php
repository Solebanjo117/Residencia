<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\EvidenceFile;
use App\Models\IndividualProject;
use App\Models\Semester;
use App\Services\DocxEditorService;
use App\Services\IndividualProjectService;
use App\Services\OnlyOfficeService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use RuntimeException;

class IndividualProjectController extends Controller
{
    public function __construct(private IndividualProjectService $projects) {}

    public function index(Request $request, StorageService $storageService)
    {
        $semester = Semester::activeOrLatest();
        $projects = IndividualProject::query()
            ->with(['semester', 'folderNode', 'docxFile', 'reviews.reviewedBy'])
            ->where('teacher_user_id', $request->user()->id)
            ->when($semester, fn ($query) => $query->where('semester_id', $semester->id))
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Teacher/IndividualProjects/Index', [
            'semester' => $semester ? [
                'id' => $semester->id,
                'name' => $semester->name,
            ] : null,
            'types' => $this->projects->types(),
            'folderTree' => $storageService->getAccessibleRoots($request->user()),
            'projects' => $projects->map(fn (IndividualProject $project) => $this->projectPayload($project)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => ['required', 'exists:semesters,id'],
            'type' => ['required', Rule::in(array_keys(IndividualProject::types()))],
            'title' => ['required', 'string', 'max:255'],
            'folder_node_id' => ['nullable', 'integer', 'exists:folder_nodes,id'],
        ]);

        $project = $this->projects->createProject($request->user(), $validated);

        return redirect()
            ->route('docente.proyectos-individuales.show', $project)
            ->with('success', 'Proyecto individual creado correctamente.');
    }

    public function show(
        Request $request,
        IndividualProject $project,
        StorageService $storageService,
        DocxEditorService $docxEditorService,
        OnlyOfficeService $onlyOfficeService
    ) {
        $this->authorize('view', $project);
        $project->load(['semester', 'folderNode', 'docxFile', 'reviewedBy', 'teacher', 'reviews.reviewedBy']);

        return Inertia::render('Teacher/IndividualProjects/Show', [
            'project' => $this->projectPayload($project, $request, $docxEditorService, $onlyOfficeService),
            'types' => $this->projects->types(),
            'folderTree' => $storageService->getAccessibleRoots($request->user()),
        ]);
    }

    public function updateFolder(Request $request, IndividualProject $project)
    {
        $this->authorize('update', $project);
        $validated = $request->validate([
            'folder_node_id' => ['required', 'integer', 'exists:folder_nodes,id'],
        ]);

        $this->projects->changeFolder($project, $request->user(), (int) $validated['folder_node_id']);

        return back()->with('success', 'Carpeta del proyecto actualizada.');
    }

    public function uploadDocx(Request $request, IndividualProject $project)
    {
        $this->authorize('update', $project);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:docx'],
        ]);

        $this->projects->uploadDocx($project, $validated['file'], $request->user());

        return back()->with('success', 'Documento DOCX asociado al proyecto.');
    }

    public function applyTemplate(Request $request, IndividualProject $project)
    {
        $this->authorize('update', $project);
        $validated = $request->validate([
            'template_file_id' => ['required', 'integer', 'exists:evidence_files,id'],
        ]);

        $templateFile = EvidenceFile::query()
            ->with(['folderNode'])
            ->findOrFail((int) $validated['template_file_id']);

        $this->authorize('view', $templateFile);

        $this->projects->applyTemplate($project, $request->user(), $templateFile);

        return back()->with('success', 'Formato copiado al proyecto y listo para editar.');
    }

    public function storeDocxEditor(Request $request, IndividualProject $project, DocxEditorService $docxEditorService)
    {
        $this->authorize('update', $project);
        $project->load(['docxFile']);

        $file = $project->docxFile;
        abort_unless(
            $file
                && (int) $file->individual_project_id === (int) $project->id
                && $file->isDocx(),
            404
        );
        $this->authorize('replace', $file);

        $validated = $request->validate([
            'html' => ['required', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'save_mode' => ['required', Rule::in(['replace_current'])],
        ]);

        try {
            $docxEditorService->saveProjectCopyAllowingUnsafeRewrite(
                $file,
                $validated['html'],
                $request->user(),
                $validated['header_html'] ?? null,
                $validated['footer_html'] ?? null
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'docx' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('docente.proyectos-individuales.show', $project)
            ->with('success', 'Documento DOCX guardado correctamente.');
    }

    public function submit(IndividualProject $project)
    {
        $this->authorize('submit', $project);
        $this->projects->submit($project);

        return back()->with('success', 'Proyecto enviado a revision.');
    }

    private function projectPayload(
        IndividualProject $project,
        ?Request $request = null,
        ?DocxEditorService $docxEditorService = null,
        ?OnlyOfficeService $onlyOfficeService = null
    ): array {
        $docxFile = $project->docxFile;
        $projectDocxFile = $this->projectOwnedDocxFile($project);
        $canEdit = in_array($project->status, [IndividualProject::STATUS_DRAFT, IndividualProject::STATUS_REJECTED], true);

        return [
            'id' => $project->id,
            'title' => $project->title,
            'type' => $project->type,
            'type_label' => $project->typeLabel(),
            'status' => $project->status,
            'semester' => $project->semester ? [
                'id' => $project->semester->id,
                'name' => $project->semester->name,
            ] : null,
            'folder' => $project->folderNode ? [
                'id' => $project->folderNode->id,
                'name' => $project->folderNode->name,
                'url' => route('folders.show', $project->folderNode->id, false),
            ] : null,
            'docx_file' => $docxFile ? [
                'id' => $docxFile->id,
                'name' => $docxFile->file_name,
            ] : null,
            'docx_editor_url' => $projectDocxFile ? route('files.docx.show', $projectDocxFile->id, false) : null,
            'docx_editor' => $this->docxEditorPayload($project, $request, $docxEditorService, $onlyOfficeService),
            'submitted_at' => $project->submitted_at?->toDateTimeString(),
            'reviewed_at' => $project->reviewed_at?->toDateTimeString(),
            'review_comment' => $project->review_comment,
            'review_history' => $project->reviews
                ->sortByDesc('reviewed_at')
                ->values()
                ->map(fn ($review) => [
                    'id' => $review->id,
                    'decision' => $review->decision,
                    'comments' => $review->comments,
                    'reviewed_at' => $review->reviewed_at?->toDateTimeString(),
                    'reviewed_by' => $review->reviewedBy?->name,
                ]),
            'can_submit' => $project->folder_node_id !== null
                && $project->docx_file_id !== null
                && in_array($project->status, [IndividualProject::STATUS_DRAFT, IndividualProject::STATUS_REJECTED], true),
            'can_edit' => $canEdit,
            'show_url' => route('docente.proyectos-individuales.show', $project, false),
        ];
    }

    private function docxEditorPayload(
        IndividualProject $project,
        ?Request $request,
        ?DocxEditorService $docxEditorService,
        ?OnlyOfficeService $onlyOfficeService
    ): ?array {
        $file = $this->projectOwnedDocxFile($project);
        if (! $file || ! $request || ! $docxEditorService || ! $onlyOfficeService) {
            return null;
        }

        $canEdit = $request->user()->can('replace', $file);
        $payload = null;
        $loadError = null;

        try {
            $payload = $docxEditorService->loadDocument($file);
        } catch (RuntimeException $exception) {
            $loadError = $exception->getMessage();
            $canEdit = false;
        }

        return [
            'store_url' => route('docente.proyectos-individuales.docx-editor', $project, false),
            'file' => [
                'id' => $file->id,
                'name' => $file->file_name,
                'mime_type' => $file->mime_type,
                'uploaded_at' => $file->uploaded_at?->toDateTimeString(),
                'uploaded_by' => $file->uploadedBy?->name,
                'last_edited_at' => $file->last_edited_at?->toDateTimeString(),
                'last_edited_by' => $file->editedBy?->name,
                'download_url' => route('files.download', $file->id),
                'onlyoffice_url' => $onlyOfficeService->isEnabled() ? route('files.onlyoffice.show', $file->id) : null,
                'folder_url' => $project->folderNode
                    ? route('folders.show', $project->folderNode->id, false)
                    : route('folders.show', $file->folder_node_id, false),
                'is_current_version' => (bool) $file->is_current_version,
                'can_edit' => $canEdit,
            ],
            'document' => [
                'html' => $payload['html'] ?? '',
                'header_html' => $payload['header_html'] ?? '',
                'footer_html' => $payload['footer_html'] ?? '',
                'warnings' => $payload['warnings'] ?? [],
                'safe_to_save' => $payload['safe_to_save'] ?? false,
                'blocking_features' => $payload['blocking_features'] ?? [],
                'stats' => $payload['stats'] ?? null,
                'load_error' => $loadError,
                'sections' => $payload['sections'] ?? [
                    'has_header' => false,
                    'has_footer' => false,
                ],
            ],
            'capabilities' => [
                'can_edit' => $canEdit,
            ],
        ];
    }

    private function projectOwnedDocxFile(IndividualProject $project): ?EvidenceFile
    {
        $file = $project->docxFile;

        if (! $file instanceof EvidenceFile) {
            return null;
        }

        if ((int) $file->individual_project_id !== (int) $project->id || ! $file->isDocx()) {
            return null;
        }

        return $file;
    }
}
