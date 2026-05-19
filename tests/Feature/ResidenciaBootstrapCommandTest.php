<?php

use App\Models\EvidenceItem;
use App\Models\Role;
use App\Models\StorageRoot;
use App\Models\User;

it('creates institutional base data without demo teaching records', function () {
    $this
        ->artisan('residencia:bootstrap', [
            '--admin-name' => 'Jefe Inicial',
            '--admin-email' => 'admin@residencia.test',
            '--admin-password' => 'Secret123!',
            '--department' => 'Sistemas',
        ])
        ->assertSuccessful();

    $admin = User::where('email', 'admin@residencia.test')->firstOrFail();

    expect(Role::where('name', Role::DOCENTE)->exists())->toBeTrue()
        ->and(Role::where('name', Role::JEFE_OFICINA)->exists())->toBeTrue()
        ->and($admin->role->name)->toBe(Role::JEFE_DEPTO)
        ->and($admin->departments()->where('name', 'Sistemas')->exists())->toBeTrue()
        ->and(StorageRoot::where('name', 'local_evidence')->exists())->toBeTrue()
        ->and(EvidenceItem::where('name', 'INSTRUM')->exists())->toBeTrue()
        ->and(EvidenceItem::where('name', 'SEG 02')->exists())->toBeTrue()
        ->and(EvidenceItem::where('name', 'SEG 04 FINAL')->exists())->toBeTrue()
        ->and(EvidenceItem::where('name', 'PROY IND SD2')->exists())->toBeFalse()
        ->and(EvidenceItem::where('name', 'PROY IND SD4')->exists())->toBeFalse()
        ->and(EvidenceItem::where('name', 'PROY IND')->exists())->toBeFalse();

    $this->assertDatabaseCount('subjects', 0);
    $this->assertDatabaseCount('teaching_loads', 0);
    $this->assertDatabaseCount('semesters', 0);
});

it('requires explicit admin credentials for institutional bootstrap', function () {
    $this
        ->artisan('residencia:bootstrap')
        ->assertFailed();
});
