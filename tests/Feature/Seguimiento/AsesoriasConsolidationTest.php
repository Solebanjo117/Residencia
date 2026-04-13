<?php

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
