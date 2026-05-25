<?php

use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\FormatPublication;
use App\Models\FormatPublicationFile;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function createFormatPublicationContext(): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $departmentRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');

    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $secondTeacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $office = User::factory()->create(['role_id' => $officeRoleId]);
    $departmentHead = User::factory()->create(['role_id' => $departmentRoleId]);

    $category = EvidenceCategory::create([
        'name' => 'FORMATOS-'.strtoupper(fake()->bothify('????##')),
        'description' => 'Categoria de formatos',
    ]);

    $item = EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'Rubro formatos '.fake()->unique()->bothify('??##'),
        'description' => 'Rubro para publicaciones de formatos',
        'requires_subject' => true,
        'active' => true,
    ]);

    return compact('teacher', 'secondTeacher', 'office', 'departmentHead', 'item');
}

it('lets administrative users publish a format and teachers list and download it', function () {
    Storage::fake('local');
    $ctx = createFormatPublicationContext();

    $this
        ->actingAs($ctx['office'])
        ->post(route('formatos.store'), [
            'title' => 'Formato de planeacion semanal',
            'body' => 'Usar este documento para reportar la planeacion vigente.',
            'evidence_item_id' => $ctx['item']->id,
            'file' => UploadedFile::fake()->create('planeacion.pdf', 128, 'application/pdf'),
        ])
        ->assertRedirect(route('formatos.index'));

    $publication = FormatPublication::with('currentFile')->firstOrFail();

    expect($publication->title)->toBe('Formato de planeacion semanal')
        ->and($publication->status)->toBe('ACTIVE')
        ->and($publication->currentFile)->not->toBeNull();

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('formatos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Formatos/Index')
            ->where('canManageFormats', false)
            ->has('publications', 1)
            ->where('publications.0.title', 'Formato de planeacion semanal')
            ->where('publications.0.evidence_item.id', $ctx['item']->id)
            ->where('publications.0.file.file_name', 'planeacion.pdf')
        );

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('formatos.download', $publication))
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('keeps a single current file while preserving replacement history', function () {
    Storage::fake('local');
    $ctx = createFormatPublicationContext();

    $this->actingAs($ctx['departmentHead'])->post(route('formatos.store'), [
        'title' => 'Formato de reporte final',
        'body' => 'Version inicial del formato.',
        'evidence_item_id' => $ctx['item']->id,
        'file' => UploadedFile::fake()->create('reporte-v1.docx', 64, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ]);

    $publication = FormatPublication::firstOrFail();
    $firstFile = $publication->currentFile()->firstOrFail();

    $this
        ->actingAs($ctx['office'])
        ->post(route('formatos.replace-file', $publication), [
            'file' => UploadedFile::fake()->create('reporte-v2.docx', 70, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertRedirect(route('formatos.index', ['publication' => $publication->id]));

    $publication->refresh()->load('currentFile');

    expect($publication->currentFile->file_name)->toBe('reporte-v2.docx')
        ->and($publication->current_format_publication_file_id)->not->toBe($firstFile->id);

    expect(FormatPublicationFile::where('format_publication_id', $publication->id)->count())->toBe(2);
    expect(FormatPublicationFile::where('format_publication_id', $publication->id)->where('is_current', true)->count())->toBe(1);
});

it('notifies teachers when a format is published or replaced', function () {
    Storage::fake('local');
    $ctx = createFormatPublicationContext();

    $this->actingAs($ctx['office'])->post(route('formatos.store'), [
        'title' => 'Formato de seguimiento',
        'body' => 'Disponible para todas las cargas.',
        'evidence_item_id' => $ctx['item']->id,
        'file' => UploadedFile::fake()->create('seguimiento.pdf', 90, 'application/pdf'),
    ]);

    $publication = FormatPublication::firstOrFail();

    foreach ([$ctx['teacher'], $ctx['secondTeacher']] as $teacher) {
        $notification = Notification::query()
            ->where('user_id', $teacher->id)
            ->where('related_entity_type', FormatPublication::class)
            ->where('related_entity_id', $publication->id)
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->title)->toContain('Nuevo formato');
    }

    $this->actingAs($ctx['teacher'])
        ->get('/api/notifications')
        ->assertOk()
        ->assertJsonPath('notifications.0.action_url', route('formatos.index', ['publication' => $publication->id], false))
        ->assertJsonPath('notifications.0.action_label', 'Ver formato');

    $this->actingAs($ctx['departmentHead'])->post(route('formatos.replace-file', $publication), [
        'file' => UploadedFile::fake()->create('seguimiento-v2.pdf', 96, 'application/pdf'),
    ]);

    expect(Notification::query()
        ->where('user_id', $ctx['teacher']->id)
        ->where('related_entity_type', FormatPublication::class)
        ->where('related_entity_id', $publication->id)
        ->count())->toBe(2);
});
