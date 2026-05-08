<?php

use App\Http\Controllers\Admin\AdvisoryController;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('uses SeguimientoDocente as the live asesorias view', function () {
    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $docente = User::factory()->create(['role_id' => $docenteRoleId]);

    $this
        ->actingAs($docente)
        ->get(route('asesorias'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SeguimientoDocente')
        );
});

it('keeps legacy asesorias2 route unavailable', function () {
    $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $docente = User::factory()->create(['role_id' => $docenteRoleId]);

    $this
        ->actingAs($docente)
        ->get('/asesorias2')
        ->assertNotFound();
});

it('keeps final row status as office approved when final evidence approval is pending', function () {
    $controller = app(AdvisoryController::class);
    $method = new ReflectionMethod($controller, 'resolveRowStatus');
    $method->setAccessible(true);

    $status = $method->invoke($controller, collect([
        ['status' => 'AO'],
        ['status' => 'VF'],
        ['status' => 'NA'],
    ]));

    expect($status)->toBe('AO');
});
