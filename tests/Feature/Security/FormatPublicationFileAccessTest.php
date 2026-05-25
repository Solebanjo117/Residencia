<?php

use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\FormatPublication;
use App\Models\FormatPublicationFile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
});

function createFormatSecurityContext(): array
{
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $officeRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $departmentRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');

    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $office = User::factory()->create(['role_id' => $officeRoleId]);
    $departmentHead = User::factory()->create(['role_id' => $departmentRoleId]);

    $category = EvidenceCategory::create([
        'name' => 'FORMATOS-SEC-'.strtoupper(fake()->bothify('????##')),
        'description' => 'Categoria de seguridad de formatos',
    ]);

    $item = EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'Rubro seguro '.fake()->unique()->bothify('??##'),
        'description' => 'Rubro de seguridad para formatos',
        'requires_subject' => true,
        'active' => true,
    ]);

    return compact('teacher', 'office', 'departmentHead', 'item');
}

function publishTestFormat(User $publisher, EvidenceItem $item): FormatPublication
{
    test()->actingAs($publisher)->post(route('formatos.store'), [
        'title' => 'Formato protegido',
        'body' => 'Documento institucional.',
        'evidence_item_id' => $item->id,
        'file' => UploadedFile::fake()->create('protegido.pdf', 128, 'application/pdf'),
    ]);

    return FormatPublication::firstOrFail();
}

it('prevents teachers from managing format publications', function () {
    Storage::fake('local');
    $ctx = createFormatSecurityContext();
    $publication = publishTestFormat($ctx['office'], $ctx['item']);

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('formatos.store'), [
            'title' => 'Intento docente',
            'body' => 'No permitido.',
            'evidence_item_id' => $ctx['item']->id,
            'file' => UploadedFile::fake()->create('docente.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    $this
        ->actingAs($ctx['teacher'])
        ->patch(route('formatos.update', $publication), ['title' => 'Cambio no permitido'])
        ->assertForbidden();

    $this
        ->actingAs($ctx['teacher'])
        ->post(route('formatos.replace-file', $publication), [
            'file' => UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    $this
        ->actingAs($ctx['teacher'])
        ->patch(route('formatos.archive', $publication))
        ->assertForbidden();
});

it('hides archived publications from teachers and blocks their downloads', function () {
    Storage::fake('local');
    $ctx = createFormatSecurityContext();
    $publication = publishTestFormat($ctx['departmentHead'], $ctx['item']);

    $this
        ->actingAs($ctx['office'])
        ->patch(route('formatos.archive', $publication))
        ->assertRedirect(route('formatos.index'));

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('formatos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('publications', 0));

    $this
        ->actingAs($ctx['teacher'])
        ->get(route('formatos.download', $publication))
        ->assertForbidden();

    $this
        ->actingAs($ctx['office'])
        ->get(route('formatos.download', $publication))
        ->assertOk();
});

it('rejects invalid upload formats', function () {
    Storage::fake('local');
    $ctx = createFormatSecurityContext();

    $this
        ->from(route('formatos.index'))
        ->actingAs($ctx['office'])
        ->post(route('formatos.store'), [
            'title' => 'Formato invalido',
            'body' => 'No debe guardar ejecutables.',
            'evidence_item_id' => $ctx['item']->id,
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])
        ->assertRedirect(route('formatos.index'))
        ->assertSessionHasErrors('file');

    expect(FormatPublication::count())->toBe(0);
});

it('rejects downloads whose stored path is outside the publication directory', function () {
    Storage::fake('local');
    $ctx = createFormatSecurityContext();
    $publication = publishTestFormat($ctx['office'], $ctx['item']);

    FormatPublicationFile::query()
        ->where('format_publication_id', $publication->id)
        ->where('is_current', true)
        ->update(['stored_relative_path' => '../outside.pdf']);

    $this
        ->actingAs($ctx['office'])
        ->get(route('formatos.download', $publication))
        ->assertNotFound();
});
