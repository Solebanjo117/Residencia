<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EvidenceFile;
use App\Models\FolderNode;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Services\FolderStructureService;
use Illuminate\Support\Facades\Schema;

class FolderStructureSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear existing evidence files and folder nodes
        Schema::disableForeignKeyConstraints();
        EvidenceFile::query()->forceDelete();
        FolderNode::query()->delete();
        Schema::enableForeignKeyConstraints();
        $this->command->info('Folder nodes and evidence files cleared.');

        // 2. Get the active semester
        $semester = Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();

        if (!$semester) {
            $this->command->warn('No semester found.');
            return;
        }

        // 3. Get all docentes by role name
        $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
        if (!$docenteRoleId) {
            $this->command->warn('DOCENTE role was not found.');
            return;
        }

        $docentes = User::where('role_id', $docenteRoleId)->get();

        if ($docentes->isEmpty()) {
            $this->command->warn('No docentes found.');
            return;
        }

        $service = app(FolderStructureService::class);

        foreach ($docentes as $docente) {
            $service->generateFullStructure($semester, $docente);
            $this->command->info("Structure generated for: {$docente->name}");
        }

        $this->command->info('Folder structure rebuilt: ' . FolderNode::count() . ' nodes created.');
    }
}
