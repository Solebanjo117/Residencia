<?php

use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;

function adminForEvidenceItemManagement(): User
{
    $role = Role::firstOrCreate(['name' => Role::JEFE_DEPTO]);

    return User::factory()->create(['role_id' => $role->id]);
}

it('allows an administrative user to create and update evidence items manually', function () {
    $admin = adminForEvidenceItemManagement();
    $category = EvidenceCategory::firstOrCreate(['name' => 'I_CARGA_ACADEMICA']);

    $this
        ->actingAs($admin)
        ->post(route('admin.evidence-items.store'), [
            'category_id' => $category->id,
            'name' => 'INSTRUM',
            'description' => 'Instrumentacion didactica',
            'requires_subject' => true,
            'active' => true,
        ])
        ->assertRedirect(route('admin.evidence-items.index'));

    $item = EvidenceItem::where('name', 'INSTRUM')->firstOrFail();

    $this
        ->actingAs($admin)
        ->put(route('admin.evidence-items.update', $item), [
            'category_id' => $category->id,
            'name' => 'INSTRUM',
            'description' => 'Instrumentacion base del curso',
            'requires_subject' => false,
            'active' => false,
        ])
        ->assertRedirect(route('admin.evidence-items.index'));

    expect($item->fresh())
        ->description->toBe('Instrumentacion base del curso')
        ->requires_subject->toBeFalse()
        ->active->toBeFalse();
});

it('blocks deleting evidence items that are already used in a requirements matrix', function () {
    $admin = adminForEvidenceItemManagement();
    $category = EvidenceCategory::firstOrCreate(['name' => 'I_CARGA_ACADEMICA']);
    $item = EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'SEG 01',
        'description' => 'Primer seguimiento',
        'requires_subject' => true,
        'active' => true,
    ]);
    $semester = Semester::create([
        'name' => 'SEM-EVIDENCE-LOCK',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    EvidenceRequirement::create([
        'semester_id' => $semester->id,
        'department_id' => null,
        'evidence_item_id' => $item->id,
        'is_mandatory' => true,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.evidence-items.destroy', $item))
        ->assertSessionHasErrors('error');

    $this->assertDatabaseHas('evidence_items', [
        'id' => $item->id,
    ]);
});
