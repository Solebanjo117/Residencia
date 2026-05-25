<?php

use App\Models\AuditLog;
use App\Models\EvidenceCategory;
use App\Models\EvidenceFile;
use App\Models\EvidenceItem;
use App\Models\EvidenceSubmission;
use App\Models\FolderNode;
use App\Models\FormatPublication;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\Subject;
use App\Models\TeachingLoad;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function createAuditNavigationContext(): array
{
    $officeRole = Role::firstOrCreate(['name' => Role::JEFE_OFICINA]);
    $teacherRole = Role::firstOrCreate(['name' => Role::DOCENTE]);

    $office = User::factory()->create(['role_id' => $officeRole->id]);
    $teacher = User::factory()->create(['role_id' => $teacherRole->id]);

    $semester = Semester::create([
        'name' => 'ENE-JUN 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-06-30',
        'status' => 'OPEN',
    ]);

    $subject = Subject::create([
        'code' => 'AUD-101',
        'name' => 'Auditoria institucional',
    ]);

    $load = TeachingLoad::create([
        'teacher_user_id' => $teacher->id,
        'semester_id' => $semester->id,
        'subject_id' => $subject->id,
        'group_code' => 'A',
        'hours_per_week' => 4,
        'modality' => TeachingLoad::MODALITY_PRESENCIAL,
    ]);

    $category = EvidenceCategory::create([
        'name' => 'AUDITORIA',
        'description' => 'Categoria para auditorias',
    ]);

    $item = EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'Planeacion didactica',
        'description' => 'Rubro auditado',
        'requires_subject' => true,
        'active' => true,
    ]);

    $submission = EvidenceSubmission::create([
        'semester_id' => $semester->id,
        'teacher_user_id' => $teacher->id,
        'evidence_item_id' => $item->id,
        'teaching_load_id' => $load->id,
        'status' => 'SUBMITTED',
        'last_updated_at' => now(),
    ]);

    $root = StorageRoot::create([
        'name' => 'audit-root',
        'base_path' => 'evidencias',
        'is_active' => true,
    ]);

    $folder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Planeacion',
        'relative_path' => 'ENE-JUN 2026/Planeacion',
        'owner_user_id' => $teacher->id,
        'semester_id' => $semester->id,
    ]);

    $file = EvidenceFile::create([
        'submission_id' => $submission->id,
        'folder_node_id' => $folder->id,
        'file_name' => 'planeacion.pdf',
        'stored_relative_path' => 'ENE-JUN 2026/Planeacion/planeacion.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'uploaded_at' => now(),
        'uploaded_by_user_id' => $teacher->id,
    ]);

    $publication = FormatPublication::create([
        'evidence_item_id' => $item->id,
        'title' => 'Formato de auditoria',
        'body' => null,
        'status' => FormatPublication::STATUS_ACTIVE,
        'created_by_user_id' => $office->id,
        'updated_by_user_id' => $office->id,
        'published_at' => now(),
    ]);

    return compact('office', 'submission', 'file', 'folder', 'publication');
}

it('adds contextual target URLs to supported audit entities', function () {
    $ctx = createAuditNavigationContext();

    AuditLog::create([
        'user_id' => $ctx['office']->id,
        'action' => 'CHANGE_STATUS',
        'entity_type' => 'EvidenceSubmission',
        'entity_id' => $ctx['submission']->id,
        'at' => now()->subMinutes(3),
        'metadata' => ['from' => 'DRAFT', 'to' => 'SUBMITTED'],
    ]);

    AuditLog::create([
        'user_id' => $ctx['office']->id,
        'action' => 'REPLACE_FILE',
        'entity_type' => 'EvidenceFile',
        'entity_id' => $ctx['file']->id,
        'at' => now()->subMinutes(2),
        'metadata' => ['old_file_name' => 'anterior.pdf', 'new_file_name' => 'planeacion.pdf'],
    ]);

    AuditLog::create([
        'user_id' => $ctx['office']->id,
        'action' => 'PUBLISH_FORMAT',
        'entity_type' => 'FormatPublication',
        'entity_id' => $ctx['publication']->id,
        'at' => now()->subMinute(),
        'metadata' => ['title' => 'Formato de auditoria'],
    ]);

    $this
        ->actingAs($ctx['office'])
        ->get(route('admin.audits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AuditLogs')
            ->where('logs.0.entity_url', '/formatos?publication='.$ctx['publication']->id)
            ->where('logs.0.entity_label', 'Formato #'.$ctx['publication']->id)
            ->where('logs.1.entity_url', '/asesorias?semester=ENE-JUN+2026&submission_id='.$ctx['submission']->id.'&focus_file_id='.$ctx['file']->id)
            ->where('logs.1.change_summary', 'anterior.pdf -> planeacion.pdf')
            ->where('logs.2.entity_url', '/asesorias?semester=ENE-JUN+2026&submission_id='.$ctx['submission']->id)
            ->where('logs.2.change_summary', 'DRAFT -> SUBMITTED')
        );
});

it('keeps missing audit targets visible without a link', function () {
    $ctx = createAuditNavigationContext();

    AuditLog::create([
        'user_id' => $ctx['office']->id,
        'action' => 'CHANGE_STATUS',
        'entity_type' => 'EvidenceSubmission',
        'entity_id' => 999999,
        'at' => now(),
        'metadata' => ['from' => 'SUBMITTED', 'to' => 'APPROVED'],
    ]);

    $this
        ->actingAs($ctx['office'])
        ->get(route('admin.audits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AuditLogs')
            ->where('logs.0.entity_url', null)
            ->where('logs.0.entity_label', 'EvidenceSubmission #999999')
            ->where('logs.0.target_status', 'missing')
            ->where('logs.0.change_summary', 'SUBMITTED -> APPROVED')
        );
});

it('keeps audit logs restricted to administrative roles', function () {
    $ctx = createAuditNavigationContext();

    $this
        ->actingAs(User::factory()->create([
            'role_id' => Role::where('name', Role::DOCENTE)->value('id'),
        ]))
        ->get(route('admin.audits.index'))
        ->assertForbidden();

    $this
        ->actingAs($ctx['office'])
        ->get(route('admin.audits.index'))
        ->assertOk();
});
