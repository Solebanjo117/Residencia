<?php

use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;

function adminForFilters(): User
{
    $role = Role::firstOrCreate(['name' => Role::JEFE_DEPTO]);

    return User::factory()->create(['role_id' => $role->id]);
}

it('filters evidence items by search term', function () {
    $admin = adminForFilters();
    $category = EvidenceCategory::firstOrCreate(['name' => 'I_CARGA_ACADEMICA']);

    EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'PLAN_TRABAJO',
        'description' => 'Plan de trabajo del docente',
        'requires_subject' => false,
        'active' => true,
    ]);

    EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'SEGUIMIENTO',
        'description' => 'Seguimiento degrupo',
        'requires_subject' => true,
        'active' => true,
    ]);

    $response = $this->actingAs($admin)->get('/admin/evidence-items?search=PLAN');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('items.data', 1)
        ->where('items.data.0.name', 'PLAN_TRABAJO')
    );
});

it('filters evidence items by category', function () {
    $admin = adminForFilters();
    $catA = EvidenceCategory::firstOrCreate(['name' => 'I_CARGA_ACADEMICA']);
    $catB = EvidenceCategory::create(['name' => 'II_TUTORIAS']);

    EvidenceItem::create([
        'category_id' => $catA->id,
        'name' => 'ITEM_CAT_A',
        'description' => null,
        'requires_subject' => true,
        'active' => true,
    ]);

    EvidenceItem::create([
        'category_id' => $catB->id,
        'name' => 'ITEM_CAT_B',
        'description' => null,
        'requires_subject' => false,
        'active' => true,
    ]);

    $response = $this->actingAs($admin)->get("/admin/evidence-items?category_id={$catA->id}");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('items.data', 1)
        ->where('items.data.0.name', 'ITEM_CAT_A')
    );
});

it('filters evidence items by active status', function () {
    $admin = adminForFilters();
    $category = EvidenceCategory::firstOrCreate(['name' => 'I_CARGA_ACADEMICA']);

    EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'ACTIVE_ITEM',
        'description' => null,
        'requires_subject' => true,
        'active' => true,
    ]);

    EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'INACTIVE_ITEM',
        'description' => null,
        'requires_subject' => true,
        'active' => false,
    ]);

    $response = $this->actingAs($admin)->get('/admin/evidence-items?status=active');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('items.data', 1)
        ->where('items.data.0.name', 'ACTIVE_ITEM')
    );

    $response = $this->actingAs($admin)->get('/admin/evidence-items?status=inactive');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('items.data', 1)
        ->where('items.data.0.name', 'INACTIVE_ITEM')
    );
});

it('filters evidence items by usage', function () {
    $admin = adminForFilters();
    $category = EvidenceCategory::firstOrCreate(['name' => 'I_CARGA_ACADEMICA']);

    $usedItem = EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'USED_ITEM',
        'description' => null,
        'requires_subject' => true,
        'active' => true,
    ]);

    EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'UNUSED_ITEM',
        'description' => null,
        'requires_subject' => true,
        'active' => true,
    ]);

    $semester = Semester::create([
        'name' => 'SEM-FILTER-TEST',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    EvidenceRequirement::create([
        'semester_id' => $semester->id,
        'department_id' => null,
        'evidence_item_id' => $usedItem->id,
        'is_mandatory' => true,
    ]);

    $response = $this->actingAs($admin)->get('/admin/evidence-items?usage=used');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('items.data', 1)
        ->where('items.data.0.name', 'USED_ITEM')
    );

    $response = $this->actingAs($admin)->get('/admin/evidence-items?usage=unused');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('items.data', 1)
        ->where('items.data.0.name', 'UNUSED_ITEM')
    );
});

it('preserves filters via query string in pagination', function () {
    $admin = adminForFilters();
    $category = EvidenceCategory::firstOrCreate(['name' => 'I_CARGA_ACADEMICA']);

    EvidenceItem::create([
        'category_id' => $category->id,
        'name' => 'PAGINATE_SEARCH',
        'description' => null,
        'requires_subject' => true,
        'active' => true,
    ]);

    $response = $this->actingAs($admin)->get('/admin/evidence-items?search=PAGINATE&status=active');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('filters.search', 'PAGINATE')
        ->where('filters.status', 'active')
    );
});
