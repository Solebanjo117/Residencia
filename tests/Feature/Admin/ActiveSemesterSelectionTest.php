<?php

use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('closes any previously open semester when a new open semester is created', function () {
    $adminRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $admin = User::factory()->create(['role_id' => $adminRoleId]);

    $existingOpen = Semester::create([
        'name' => 'SEM-ACTIVO-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonths(4)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.semesters.store'), [
            'name' => 'SEM-NUEVO-' . Str::upper(Str::random(6)),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'status' => 'OPEN',
            'academic_period_id' => null,
        ])
        ->assertRedirect(route('admin.semesters.index'));

    $newSemester = Semester::query()->latest('id')->firstOrFail();

    expect($newSemester->status->value)->toBe('OPEN');
    expect($existingOpen->fresh()->status->value)->toBe('CLOSED');
    expect(Semester::active()?->id)->toBe($newSemester->id);
});

it('uses the newly opened semester as the default selection across pages', function () {
    $adminRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $admin = User::factory()->create(['role_id' => $adminRoleId]);

    $firstSemester = Semester::create([
        'name' => 'SEM-DEFAULT-A-' . Str::upper(Str::random(5)),
        'start_date' => now()->subMonths(6)->toDateString(),
        'end_date' => now()->subMonths(2)->toDateString(),
        'status' => 'OPEN',
    ]);

    $secondSemester = Semester::create([
        'name' => 'SEM-DEFAULT-B-' . Str::upper(Str::random(5)),
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
        'status' => 'CLOSED',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('asesorias.horarios'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asesorias/Index')
            ->where('currentSemester', $firstSemester->name)
        );

    $this
        ->actingAs($admin)
        ->put(route('admin.semesters.update', $secondSemester), [
            'name' => $secondSemester->name,
            'start_date' => $secondSemester->start_date->toDateString(),
            'end_date' => $secondSemester->end_date->toDateString(),
            'status' => 'OPEN',
            'academic_period_id' => null,
        ])
        ->assertRedirect(route('admin.semesters.index'));

    expect($firstSemester->fresh()->status->value)->toBe('CLOSED');
    expect($secondSemester->fresh()->status->value)->toBe('OPEN');

    $this
        ->actingAs($admin)
        ->get(route('asesorias.horarios'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asesorias/Index')
            ->where('currentSemester', $secondSemester->name)
        );
});
