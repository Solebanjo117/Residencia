<?php

use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\EvidenceRequirement;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Str;

function createAdminForDepartmentTests(): User
{
    $roleId = Role::where('name', Role::JEFE_OFICINA)->value('id');
    return User::factory()->create(['role_id' => $roleId]);
}

it('blocks department deletion when requirements exist', function () {
    $admin = createAdminForDepartmentTests();

    $department = Department::create(['name' => 'Dept Req ' . Str::upper(Str::random(6))]);
    $semester = Semester::create([
        'name' => 'SEM-DEP-' . Str::upper(Str::random(6)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $categoryId = EvidenceCategory::where('name', 'I_CARGA_ACADEMICA')->value('id');
    $item = EvidenceItem::create([
        'category_id' => $categoryId,
        'name' => 'ITEM-DEP-' . Str::upper(Str::random(8)),
        'description' => 'Item department guard',
        'requires_subject' => true,
        'active' => true,
    ]);

    EvidenceRequirement::create([
        'semester_id' => $semester->id,
        'department_id' => $department->id,
        'evidence_item_id' => $item->id,
        'is_mandatory' => true,
    ]);

    $response = $this
        ->from('/admin/departments')
        ->actingAs($admin)
        ->delete(route('admin.departments.destroy', $department->id));

    $response->assertRedirect('/admin/departments');
    $response->assertSessionHasErrors('error');
    $this->assertDatabaseHas('departments', ['id' => $department->id]);
});

it('blocks department deletion when teachers are assigned', function () {
    $admin = createAdminForDepartmentTests();

    $department = Department::create(['name' => 'Dept Teach ' . Str::upper(Str::random(6))]);

    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
    $teacher = User::factory()->create(['role_id' => $teacherRoleId]);
    $teacher->departments()->attach($department->id);

    $response = $this
        ->from('/admin/departments')
        ->actingAs($admin)
        ->delete(route('admin.departments.destroy', $department->id));

    $response->assertRedirect('/admin/departments');
    $response->assertSessionHasErrors('error');
    $this->assertDatabaseHas('departments', ['id' => $department->id]);
});

it('deletes department when no requirements and no teachers exist', function () {
    $admin = createAdminForDepartmentTests();

    $department = Department::create(['name' => 'Dept Free ' . Str::upper(Str::random(6))]);

    $response = $this
        ->from('/admin/departments')
        ->actingAs($admin)
        ->delete(route('admin.departments.destroy', $department->id));

    $response->assertRedirect('/admin/departments');
    $this->assertDatabaseMissing('departments', ['id' => $department->id]);
});
