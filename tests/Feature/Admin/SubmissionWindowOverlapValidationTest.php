<?php

use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\Role;
use App\Models\Semester;
use App\Models\SubmissionWindow;
use App\Models\User;
use Illuminate\Support\Str;

function createSubmissionWindowContext(): array
{
    $jefeOficinaRoleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    $jefeOficina = User::factory()->create(['role_id' => $jefeOficinaRoleId]);

    $semester = Semester::create([
        'name' => 'SEM-WIN-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
        'status' => 'OPEN',
    ]);

    $category = EvidenceCategory::create([
        'name' => 'CAT-WIN-' . Str::upper(Str::random(6)),
        'description' => 'Categoria para pruebas de ventanas',
    ]);

    $item = EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'ITEM-WIN-' . Str::upper(Str::random(8)),
        'description' => 'Item para pruebas de solapamiento',
        'requires_subject' => true,
        'active' => true,
    ]);

    return compact('jefeOficina', 'semester', 'item');
}

it('blocks creating overlapping active submission windows for same semester and evidence', function () {
    $ctx = createSubmissionWindowContext();

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'opens_at' => now()->addDay(),
        'closes_at' => now()->addDays(5),
        'created_by_user_id' => $ctx['jefeOficina']->id,
        'status' => 'ACTIVE',
    ]);

    $response = $this
        ->from(route('admin.windows.index'))
        ->actingAs($ctx['jefeOficina'])
        ->post(route('admin.windows.store'), [
            'semester_id' => $ctx['semester']->id,
            'evidence_item_id' => $ctx['item']->id,
            'opens_at' => now()->addDays(3)->toDateTimeString(),
            'closes_at' => now()->addDays(7)->toDateTimeString(),
            'status' => 'ACTIVE',
        ]);

    $response->assertRedirect(route('admin.windows.index'));
    $response->assertSessionHasErrors('opens_at');
    $this->assertDatabaseCount('submission_windows', 1);
});

it('allows creating non-overlapping active submission windows', function () {
    $ctx = createSubmissionWindowContext();

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'opens_at' => now()->addDay(),
        'closes_at' => now()->addDays(5),
        'created_by_user_id' => $ctx['jefeOficina']->id,
        'status' => 'ACTIVE',
    ]);

    $response = $this
        ->from(route('admin.windows.index'))
        ->actingAs($ctx['jefeOficina'])
        ->post(route('admin.windows.store'), [
            'semester_id' => $ctx['semester']->id,
            'evidence_item_id' => $ctx['item']->id,
            'opens_at' => now()->addDays(6)->toDateTimeString(),
            'closes_at' => now()->addDays(9)->toDateTimeString(),
            'status' => 'ACTIVE',
        ]);

    $response->assertRedirect(route('admin.windows.index'));
    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseCount('submission_windows', 2);
});

it('blocks updating an active submission window into an overlapping range', function () {
    $ctx = createSubmissionWindowContext();

    $baseOpen = now()->addDay();
    $baseClose = now()->addDays(4);
    $secondOpen = now()->addDays(6);
    $secondClose = now()->addDays(9);

    $baseWindow = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'opens_at' => $baseOpen,
        'closes_at' => $baseClose,
        'created_by_user_id' => $ctx['jefeOficina']->id,
        'status' => 'ACTIVE',
    ]);

    $windowToUpdate = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'opens_at' => $secondOpen,
        'closes_at' => $secondClose,
        'created_by_user_id' => $ctx['jefeOficina']->id,
        'status' => 'ACTIVE',
    ]);

    $response = $this
        ->from(route('admin.windows.index'))
        ->actingAs($ctx['jefeOficina'])
        ->put(route('admin.windows.update', $windowToUpdate->id), [
            'semester_id' => $ctx['semester']->id,
            'evidence_item_id' => $ctx['item']->id,
            'opens_at' => now()->addDays(3)->toDateTimeString(),
            'closes_at' => now()->addDays(8)->toDateTimeString(),
            'status' => 'ACTIVE',
        ]);

    $response->assertRedirect(route('admin.windows.index'));
    $response->assertSessionHasErrors('opens_at');

    expect($baseWindow->fresh()->opens_at->toDateTimeString())->toBe($baseOpen->toDateTimeString());
    expect($windowToUpdate->fresh()->opens_at->toDateTimeString())->toBe($secondOpen->toDateTimeString());
});
