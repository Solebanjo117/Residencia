<?php

use App\Enums\SubmissionStatus;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createCollapseFileVersionsContext(): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-COLLAPSE-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $root = StorageRoot::create([
        'name' => 'root-collapse-'.Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $folder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Expediente',
        'relative_path' => 'collapse/'.$semester->id,
        'owner_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'parent_id' => null,
    ]);

    $subject = Subject::create([
        'code' => 'COLL-'.Str::upper(Str::random(6)),
        'name' => 'Materia Collapse',
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
    ]);

    $category = EvidenceCategory::create([
        'name' => 'CAT-COLL-'.Str::upper(Str::random(6)),
        'description' => 'Categoria collapse',
    ]);

    $item = EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'ITEM-COLL-'.Str::upper(Str::random(6)),
        'description' => 'Item collapse',
        'requires_subject' => true,
        'active' => true,
    ]);

    $submission = EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $item->id,
        'teaching_load_id' => $load->id,
        'status' => SubmissionStatus::DRAFT,
        'last_updated_at' => now(),
    ]);

    return compact('teacher', 'semester', 'folder', 'submission');
}

function createVersionedEvidencePair(array $ctx): array
{
    $oldPath = $ctx['folder']->relative_path.'/old.pdf';
    $currentPath = $ctx['folder']->relative_path.'/current.pdf';
    Storage::disk('local')->put($oldPath, 'old-content');
    Storage::disk('local')->put($currentPath, 'current-content');

    $old = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'evidencia.pdf',
        'stored_relative_path' => $oldPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen('old-content'),
        'file_hash' => hash('sha256', 'old-content'),
        'uploaded_at' => now()->subDay(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => false,
    ]);

    $current = EvidenceFile::create([
        'submission_id' => $ctx['submission']->id,
        'previous_version_file_id' => $old->id,
        'root_file_id' => $old->id,
        'folder_node_id' => $ctx['folder']->id,
        'file_name' => 'evidencia.pdf',
        'stored_relative_path' => $currentPath,
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen('current-content'),
        'file_hash' => hash('sha256', 'current-content'),
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $ctx['teacher']->id,
        'is_current_version' => true,
    ]);

    return compact('old', 'current', 'oldPath', 'currentPath');
}

it('reports duplicate file versions without deleting them in dry run', function () {
    Storage::fake('local');
    $ctx = createCollapseFileVersionsContext();
    $pair = createVersionedEvidencePair($ctx);

    $this->artisan('files:collapse-versions --dry-run')
        ->expectsOutputToContain('Duplicados detectados: 1')
        ->assertExitCode(0);

    expect(EvidenceFile::query()->count())->toBe(2);
    Storage::disk('local')->assertExists($pair['oldPath']);
    Storage::disk('local')->assertExists($pair['currentPath']);
});

it('removes old file versions when forced', function () {
    Storage::fake('local');
    $ctx = createCollapseFileVersionsContext();
    $pair = createVersionedEvidencePair($ctx);

    $this->artisan('files:collapse-versions --force')
        ->expectsOutputToContain('Versiones eliminadas: 1')
        ->assertExitCode(0);

    expect(EvidenceFile::query()->count())->toBe(1);
    expect(EvidenceFile::query()->whereKey($pair['old']->id)->exists())->toBeFalse();

    $pair['current']->refresh();
    expect($pair['current']->previous_version_file_id)->toBeNull();
    expect($pair['current']->root_file_id)->toBeNull();
    expect($pair['current']->is_current_version)->toBeTrue();

    Storage::disk('local')->assertMissing($pair['oldPath']);
    Storage::disk('local')->assertExists($pair['currentPath']);
});
