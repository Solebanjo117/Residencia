<?php

use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\NotificationSchedule;
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
        'name' => 'SEM-WIN-'.Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
        'status' => 'OPEN',
    ]);

    $category = EvidenceCategory::create([
        'name' => 'CAT-WIN-'.Str::upper(Str::random(6)),
        'description' => 'Categoria para pruebas de ventanas',
    ]);

    $item = EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'ITEM-WIN-'.Str::upper(Str::random(8)),
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
            'opens_at' => now()->addDays(3)->toDateString(),
            'closes_at' => now()->addDays(7)->toDateString(),
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
            'opens_at' => now()->addDays(6)->toDateString(),
            'closes_at' => now()->addDays(9)->toDateString(),
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
            'opens_at' => now()->addDays(3)->toDateString(),
            'closes_at' => now()->addDays(8)->toDateString(),
            'status' => 'ACTIVE',
        ]);

    $response->assertRedirect(route('admin.windows.index'));
    $response->assertSessionHasErrors('opens_at');

    expect($baseWindow->fresh()->opens_at->toDateTimeString())->toBe($baseOpen->toDateTimeString());
    expect($windowToUpdate->fresh()->opens_at->toDateTimeString())->toBe($secondOpen->toDateTimeString());
});

it('stores date windows as full-day ranges and allows a same-day window', function () {
    $ctx = createSubmissionWindowContext();
    $deliveryDate = now()->addDays(2)->toDateString();

    $response = $this
        ->from(route('admin.windows.index'))
        ->actingAs($ctx['jefeOficina'])
        ->post(route('admin.windows.store'), [
            'semester_id' => $ctx['semester']->id,
            'evidence_item_id' => $ctx['item']->id,
            'opens_at' => $deliveryDate,
            'closes_at' => $deliveryDate,
            'status' => 'ACTIVE',
        ]);

    $response->assertRedirect(route('admin.windows.index'));
    $response->assertSessionDoesntHaveErrors();

    $window = SubmissionWindow::first();

    expect($window->opens_at->toDateString())->toBe($deliveryDate);
    expect($window->opens_at->format('H:i:s'))->toBe('00:00:00');
    expect($window->closes_at->toDateString())->toBe($deliveryDate);
    expect($window->closes_at->format('H:i:s'))->toBe('23:59:59');

    $this->assertDatabaseHas('notification_schedules', [
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'notification_type' => 'WINDOW_OPEN',
        'is_sent' => false,
    ]);
    $this->assertDatabaseHas('notification_schedules', [
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'notification_type' => 'WINDOW_CLOSING',
        'is_sent' => false,
    ]);
});

it('creates submission windows in batch for multiple evidence items', function () {
    $ctx = createSubmissionWindowContext();
    $secondItem = EvidenceItem::create([
        'category_id' => $ctx['item']->category_id,
        'name' => 'ITEM-WIN-BATCH-'.Str::upper(Str::random(8)),
        'description' => 'Segundo item para lote',
        'requires_subject' => true,
        'active' => true,
    ]);
    $deliveryDate = now()->addDays(4)->toDateString();

    $response = $this
        ->from(route('admin.windows.index'))
        ->actingAs($ctx['jefeOficina'])
        ->post(route('admin.windows.store'), [
            'semester_id' => $ctx['semester']->id,
            'evidence_item_ids' => [$ctx['item']->id, $secondItem->id],
            'opens_at' => $deliveryDate,
            'closes_at' => $deliveryDate,
            'status' => 'ACTIVE',
        ]);

    $response->assertRedirect(route('admin.windows.index'));
    $response->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('submission_windows', [
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'status' => 'ACTIVE',
    ]);
    $this->assertDatabaseHas('submission_windows', [
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $secondItem->id,
        'status' => 'ACTIVE',
    ]);

    expect(SubmissionWindow::count())->toBe(2)
        ->and(NotificationSchedule::query()->where('notification_type', 'WINDOW_OPEN')->count())->toBe(2)
        ->and(NotificationSchedule::query()->where('notification_type', 'WINDOW_CLOSING')->count())->toBe(2);
});

it('blocks a batch submission window when one selected evidence overlaps', function () {
    $ctx = createSubmissionWindowContext();
    $secondItem = EvidenceItem::create([
        'category_id' => $ctx['item']->category_id,
        'name' => 'ITEM-WIN-BATCH-BLOCKED-'.Str::upper(Str::random(8)),
        'description' => 'Segundo item para lote bloqueado',
        'requires_subject' => true,
        'active' => true,
    ]);

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
            'evidence_item_ids' => [$ctx['item']->id, $secondItem->id],
            'opens_at' => now()->addDays(3)->toDateString(),
            'closes_at' => now()->addDays(7)->toDateString(),
            'status' => 'ACTIVE',
        ]);

    $response->assertRedirect(route('admin.windows.index'));
    $response->assertSessionHasErrors('opens_at');
    $this->assertDatabaseCount('submission_windows', 1);
    $this->assertDatabaseMissing('submission_windows', [
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $secondItem->id,
    ]);
});

it('refreshes pending notification schedules when updating a window', function () {
    $ctx = createSubmissionWindowContext();

    $window = SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'opens_at' => now()->addDays(5),
        'closes_at' => now()->addDays(8),
        'created_by_user_id' => $ctx['jefeOficina']->id,
        'status' => 'ACTIVE',
    ]);

    NotificationSchedule::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'notify_at' => now()->addDays(5),
        'notification_type' => 'WINDOW_OPEN',
        'is_sent' => false,
    ]);

    $newOpen = now()->addDays(12)->toDateString();
    $newClose = now()->addDays(16)->toDateString();

    $this
        ->from(route('admin.windows.index'))
        ->actingAs($ctx['jefeOficina'])
        ->put(route('admin.windows.update', $window->id), [
            'semester_id' => $ctx['semester']->id,
            'evidence_item_id' => $ctx['item']->id,
            'opens_at' => $newOpen,
            'closes_at' => $newClose,
            'status' => 'ACTIVE',
        ])
        ->assertRedirect(route('admin.windows.index'));

    expect(NotificationSchedule::query()
        ->where('semester_id', $ctx['semester']->id)
        ->where('evidence_item_id', $ctx['item']->id)
        ->where('notification_type', 'WINDOW_OPEN')
        ->where('is_sent', false)
        ->count())->toBe(1);

    $this->assertDatabaseHas('notification_schedules', [
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'notification_type' => 'WINDOW_OPEN',
        'notify_at' => Carbon\CarbonImmutable::parse($newOpen)->startOfDay()->toDateTimeString(),
        'is_sent' => false,
    ]);
});

it('filters submission windows by operational status', function () {
    $ctx = createSubmissionWindowContext();

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addDay(),
        'created_by_user_id' => $ctx['jefeOficina']->id,
        'status' => 'ACTIVE',
    ]);

    SubmissionWindow::create([
        'semester_id' => $ctx['semester']->id,
        'evidence_item_id' => $ctx['item']->id,
        'opens_at' => now()->addDays(10),
        'closes_at' => now()->addDays(12),
        'created_by_user_id' => $ctx['jefeOficina']->id,
        'status' => 'INACTIVE',
    ]);

    $this
        ->actingAs($ctx['jefeOficina'])
        ->get(route('admin.windows.index', [
            'semester_id' => $ctx['semester']->id,
            'status' => 'OPEN',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedStatus', 'OPEN')
            ->has('windows.data', 1)
            ->where('windows.data.0.status', 'ACTIVE')
        );
});
