<?php

namespace App\Services;

use App\Models\EvidenceFile;
use App\Models\FolderNode;
use App\Models\IndividualProject;
use App\Models\IndividualProjectReview;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class IndividualProjectService
{
    public function __construct(
        private FolderStructureService $folderStructureService,
        private StorageService $storageService,
    ) {}

    public function createProject(User $teacher, array $data): IndividualProject
    {
        return DB::transaction(function () use ($teacher, $data) {
            $semester = Semester::findOrFail($data['semester_id']);
            $folder = isset($data['folder_node_id'])
                ? $this->selectFolder($teacher, $semester, (int) $data['folder_node_id'])
                : $this->compatibleOrCreatedFolder($teacher, $semester, $data['type']);

            return IndividualProject::create([
                'semester_id' => $semester->id,
                'teacher_user_id' => $teacher->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'folder_node_id' => $folder->id,
                'status' => IndividualProject::STATUS_DRAFT,
            ]);
        });
    }

    public function changeFolder(IndividualProject $project, User $teacher, int $folderId): IndividualProject
    {
        $folder = $this->selectFolder($teacher, $project->semester, $folderId);
        $project->forceFill(['folder_node_id' => $folder->id])->save();

        return $project->fresh(['folderNode']);
    }

    public function uploadDocx(IndividualProject $project, UploadedFile $upload, User $user): EvidenceFile
    {
        $folder = $project->folderNode;
        if (! $folder) {
            $folder = $this->compatibleOrCreatedFolder($project->teacher, $project->semester, $project->type);
            $project->forceFill(['folder_node_id' => $folder->id])->save();
        }

        $existing = $project->docxFile;
        $file = $existing
            ? $this->storageService->overwriteEvidence($existing, $upload, $user)
            : $this->storageService->storeIndividualProjectFile($upload, $folder, $user, $project);

        $project->forceFill(['docx_file_id' => $file->id])->save();

        return $file->fresh();
    }

    public function applyTemplate(IndividualProject $project, User $teacher, EvidenceFile $templateFile): IndividualProject
    {
        $folder = $templateFile->folderNode;
        if (! $folder) {
            throw new InvalidArgumentException('La plantilla seleccionada no tiene carpeta asociada.');
        }

        $isOwnedTemplate = (int) $folder->owner_user_id === (int) $teacher->id;
        $isCommonTemplate = $folder->owner_user_id === null && $teacher->can('view', $folder);

        if ((int) $folder->semester_id !== (int) $project->semester_id || (! $isOwnedTemplate && ! $isCommonTemplate)) {
            throw new InvalidArgumentException('La plantilla seleccionada no esta dentro del alcance permitido.');
        }

        if (! $templateFile->isDocx()) {
            throw new InvalidArgumentException('La carpeta seleccionada debe contener un DOCX plantilla.');
        }

        $projectFolder = $project->folderNode;
        if (! $projectFolder) {
            $projectFolder = $this->compatibleOrCreatedFolder($project->teacher, $project->semester, $project->type);
            $project->forceFill(['folder_node_id' => $projectFolder->id])->save();
        }

        $currentDocx = $project->docxFile;
        $copiedFile = $this->storageService->copyTemplateEvidenceToProject(
            $templateFile,
            $projectFolder,
            $teacher,
            $currentDocx,
            $project->id
        );

        $project->forceFill([
            'docx_file_id' => $copiedFile->id,
        ])->save();

        return $project->fresh(['semester', 'folderNode', 'docxFile', 'reviews.reviewedBy']);
    }

    public function submit(IndividualProject $project): IndividualProject
    {
        if (! $project->folder_node_id || ! $project->docx_file_id) {
            throw new InvalidArgumentException('El proyecto debe tener carpeta y DOCX principal antes de enviarse.');
        }

        $project->forceFill([
            'status' => IndividualProject::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'review_comment' => null,
        ])->save();

        return $project->fresh();
    }

    public function approve(IndividualProject $project, User $reviewer, ?string $comment): IndividualProject
    {
        return $this->review($project, $reviewer, IndividualProject::STATUS_APPROVED, $comment);
    }

    public function reject(IndividualProject $project, User $reviewer, string $comment): IndividualProject
    {
        return $this->review($project, $reviewer, IndividualProject::STATUS_REJECTED, $comment);
    }

    public function folderOptionsFor(User $teacher, Semester $semester): array
    {
        return FolderNode::query()
            ->where('owner_user_id', $teacher->id)
            ->where('semester_id', $semester->id)
            ->orderBy('relative_path')
            ->get()
            ->map(fn (FolderNode $folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'display_path' => $this->displayPath($folder),
            ])
            ->all();
    }

    public function types(): array
    {
        return collect(IndividualProject::types())
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function review(IndividualProject $project, User $reviewer, string $status, ?string $comment): IndividualProject
    {
        DB::transaction(function () use ($project, $reviewer, $status, $comment) {
            $reviewedAt = now();

            $project->forceFill([
                'status' => $status,
                'reviewed_at' => $reviewedAt,
                'reviewed_by_user_id' => $reviewer->id,
                'review_comment' => $comment,
            ])->save();

            IndividualProjectReview::create([
                'individual_project_id' => $project->id,
                'reviewed_by_user_id' => $reviewer->id,
                'decision' => $status === IndividualProject::STATUS_APPROVED ? 'APPROVE' : 'REJECT',
                'comments' => $comment,
                'reviewed_at' => $reviewedAt,
            ]);
        });

        return $project->fresh();
    }

    private function compatibleOrCreatedFolder(User $teacher, Semester $semester, string $type): FolderNode
    {
        $existing = $this->findCompatibleFolder($teacher, $semester, $type);
        if ($existing) {
            return $existing;
        }

        $teacherFolder = $this->folderStructureService->ensureTeacherFolder($semester, $teacher);
        $root = StorageRoot::findOrFail($teacherFolder->storage_root_id);

        $projectsRoot = FolderNode::firstOrCreate(
            [
                'storage_root_id' => $root->id,
                'parent_id' => $teacherFolder->id,
                'name' => '4.PROYECTOS INDIVIDUALES',
            ],
            [
                'relative_path' => $teacherFolder->relative_path.'/4.PROYECTOS INDIVIDUALES',
                'owner_user_id' => $teacher->id,
                'semester_id' => $semester->id,
            ]
        );

        return FolderNode::firstOrCreate(
            [
                'storage_root_id' => $root->id,
                'parent_id' => $projectsRoot->id,
                'name' => $this->folderNameForType($type),
            ],
            [
                'relative_path' => $projectsRoot->relative_path.'/'.$this->folderNameForType($type),
                'owner_user_id' => $teacher->id,
                'semester_id' => $semester->id,
            ]
        );
    }

    private function findCompatibleFolder(User $teacher, Semester $semester, string $type): ?FolderNode
    {
        $projectsRoot = FolderNode::query()
            ->where('owner_user_id', $teacher->id)
            ->where('semester_id', $semester->id)
            ->where('name', '4.PROYECTOS INDIVIDUALES')
            ->first();

        if ($projectsRoot) {
            $folder = FolderNode::query()
                ->where('owner_user_id', $teacher->id)
                ->where('semester_id', $semester->id)
                ->where('parent_id', $projectsRoot->id)
                ->where('name', $this->folderNameForType($type))
                ->first();

            if ($folder) {
                return $folder;
            }
        }

        return FolderNode::query()
            ->where('owner_user_id', $teacher->id)
            ->where('semester_id', $semester->id)
            ->where('name', $this->folderNameForType($type))
            ->orderBy('id')
            ->first();
    }

    private function selectFolder(User $teacher, Semester $semester, int $folderId): FolderNode
    {
        return FolderNode::query()
            ->whereKey($folderId)
            ->where('owner_user_id', $teacher->id)
            ->where('semester_id', $semester->id)
            ->firstOrFail();
    }

    private function folderNameForType(string $type): string
    {
        return match ($type) {
            IndividualProject::TYPE_CAPACITACION => '4.1-CAPACITACION',
            IndividualProject::TYPE_ASESORIAS_DOCENTES => '4.3-PROYECTOS DOCENTES',
            IndividualProject::TYPE_MATERIAL_DIDACTICO => '4.4-MATERIAL DIDACTICO',
            default => throw new InvalidArgumentException('Tipo de proyecto individual no soportado.'),
        };
    }

    private function displayPath(FolderNode $folder): string
    {
        return collect(explode('/', $folder->relative_path))
            ->map(fn (string $segment) => (string) Str::of($segment)->replace('-', ' '))
            ->implode(' / ');
    }
}
