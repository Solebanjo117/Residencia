<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IndividualProject;
use App\Services\IndividualProjectService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IndividualProjectController extends Controller
{
    public function __construct(private IndividualProjectService $projects) {}

    public function index()
    {
        abort_unless(request()->user()?->isJefeOficina(), 403);

        $projects = IndividualProject::query()
            ->with(['semester', 'teacher', 'folderNode', 'docxFile', 'reviews.reviewedBy'])
            ->orderByRaw("CASE status WHEN 'SUBMITTED' THEN 0 WHEN 'REJECTED' THEN 1 WHEN 'DRAFT' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Oficina/IndividualProjects/Index', [
            'projects' => $projects->map(fn (IndividualProject $project) => $this->projectPayload($project)),
        ]);
    }

    public function show(IndividualProject $project)
    {
        $this->authorize('view', $project);
        $project->load(['semester', 'teacher', 'folderNode', 'docxFile', 'reviewedBy', 'reviews.reviewedBy']);

        return Inertia::render('Oficina/IndividualProjects/Show', [
            'project' => $this->projectPayload($project),
        ]);
    }

    public function approve(Request $request, IndividualProject $project)
    {
        $this->authorize('review', $project);
        $validated = $request->validate([
            'review_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->projects->approve($project, $request->user(), $validated['review_comment'] ?? null);

        return back()->with('success', 'Proyecto individual aprobado.');
    }

    public function reject(Request $request, IndividualProject $project)
    {
        $this->authorize('review', $project);
        $validated = $request->validate([
            'review_comment' => ['required', 'string', 'max:1000'],
        ]);

        $this->projects->reject($project, $request->user(), $validated['review_comment']);

        return back()->with('success', 'Proyecto individual devuelto a correccion.');
    }

    private function projectPayload(IndividualProject $project): array
    {
        $docxFile = $project->docxFile;

        return [
            'id' => $project->id,
            'title' => $project->title,
            'type' => $project->type,
            'type_label' => $project->typeLabel(),
            'status' => $project->status,
            'teacher' => $project->teacher ? [
                'id' => $project->teacher->id,
                'name' => $project->teacher->name,
                'email' => $project->teacher->email,
            ] : null,
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
            'docx_editor_url' => $docxFile ? route('files.docx.show', $docxFile->id, false) : null,
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
            'can_review' => $project->status === IndividualProject::STATUS_SUBMITTED,
            'show_url' => route('oficina.proyectos-individuales.show', $project, false),
        ];
    }
}
