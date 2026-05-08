<?php

use App\Models\Department;
use App\Models\FolderNode;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StorageRoot;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function createJefeDeptoScopeContext(): array
{
    $jefeDeptoRoleId = Role::where('name', Role::JEFE_DEPTO)->value('id');
    $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

    $deptoA = Department::create(['name' => 'DEP-A-' . Str::upper(Str::random(6))]);
    $deptoB = Department::create(['name' => 'DEP-B-' . Str::upper(Str::random(6))]);

    $jefeDepto = User::factory()->create(['role_id' => $jefeDeptoRoleId]);
    $jefeDepto->departments()->attach($deptoA->id);

    $teacherA = User::factory()->create(['role_id' => $teacherRoleId]);
    $teacherA->departments()->attach($deptoA->id);

    $teacherB = User::factory()->create(['role_id' => $teacherRoleId]);
    $teacherB->departments()->attach($deptoB->id);

    $root = StorageRoot::create([
        'name' => 'root-scope-' . Str::lower(Str::random(8)),
        'base_path' => 'storage_root',
        'is_active' => true,
    ]);

    $semester = Semester::create([
        'name' => 'SEMESTRE SCOPE ' . Str::upper(Str::random(4)),
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => 'OPEN',
    ]);

    $semesterFolder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'SEMESTRE SCOPE',
        'relative_path' => 'sem_scope_' . Str::lower(Str::random(6)),
        'owner_user_id' => null,
        'semester_id' => $semester->id,
        'parent_id' => null,
    ]);

    $allowedFolder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Docente Depto A',
        'relative_path' => $semesterFolder->relative_path . '/docente_' . $teacherA->id,
        'owner_user_id' => $teacherA->id,
        'semester_id' => null,
        'parent_id' => $semesterFolder->id,
    ]);

    $forbiddenFolder = FolderNode::create([
        'storage_root_id' => $root->id,
        'name' => 'Docente Depto B',
        'relative_path' => $semesterFolder->relative_path . '/docente_' . $teacherB->id,
        'owner_user_id' => $teacherB->id,
        'semester_id' => null,
        'parent_id' => $semesterFolder->id,
    ]);

    return compact('jefeDepto', 'semesterFolder', 'allowedFolder', 'forbiddenFolder');
}

it('shows full file manager tree for jefe de departamento', function () {
    $ctx = createJefeDeptoScopeContext();

    $this
        ->actingAs($ctx['jefeDepto'])
        ->get(route('folders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FileManager/Index')
            ->has('folderTree', 2)
            ->where('folderTree.0.id', 'active-semesters')
            ->where('folderTree.0.name', 'Semestres activos')
            ->has('folderTree.0.children', 1)
            ->where('folderTree.0.children.0.id', $ctx['semesterFolder']->id)
            ->has('folderTree.0.children.0.children', 2)
            ->where('folderTree.0.children.0.children.0.id', $ctx['allowedFolder']->id)
            ->where('folderTree.0.children.0.children.1.id', $ctx['forbiddenFolder']->id)
            ->where('folderTree.1.id', 'inactive-semesters')
        );
});

it('shows all folder contents for jefe de departamento in file manager view', function () {
    $ctx = createJefeDeptoScopeContext();

    $this
        ->actingAs($ctx['jefeDepto'])
        ->get(route('folders.show', $ctx['semesterFolder']->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FileManager/Index')
            ->has('contents.folders', 2)
            ->where('contents.folders.0.id', $ctx['allowedFolder']->id)
            ->where('contents.folders.1.id', $ctx['forbiddenFolder']->id)
        );
});

it('allows jefe de departamento to open any teacher folder in file manager', function () {
    $ctx = createJefeDeptoScopeContext();

    $this
        ->actingAs($ctx['jefeDepto'])
        ->get(route('folders.show', $ctx['forbiddenFolder']->id))
        ->assertOk();
});

it('moves semester folders between active and inactive groups when the active semester changes', function () {
    $ctx = createJefeDeptoScopeContext();

    $newSemester = Semester::create([
        'name' => 'SEMESTRE NUEVO ' . Str::upper(Str::random(4)),
        'start_date' => now()->addMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
        'status' => 'CLOSED',
    ]);

    FolderNode::query()->whereKey($ctx['semesterFolder']->id)->update(['semester_id' => $newSemester->id]);
    $ctx['semesterFolder']->refresh();

    $this
        ->actingAs($ctx['jefeDepto'])
        ->get(route('folders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('folderTree.0.children', 0)
            ->has('folderTree.1.children', 1)
            ->where('folderTree.1.children.0.id', $ctx['semesterFolder']->id)
        );

    $newSemester->update(['status' => 'OPEN']);
    Semester::query()
        ->where('id', '!=', $newSemester->id)
        ->update(['status' => 'CLOSED']);

    $this
        ->actingAs($ctx['jefeDepto'])
        ->get(route('folders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('folderTree.0.children', 1)
            ->where('folderTree.0.children.0.id', $ctx['semesterFolder']->id)
        );
});
