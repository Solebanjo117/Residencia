<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EvidenceFile;
use App\Models\FolderNode;
use App\Models\Semester;
use App\Models\User;
use App\Services\FolderStructureService;
use Illuminate\Support\Facades\DB;

class FolderStructureSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear existing evidence files and folder nodes
        DB::statement('PRAGMA foreign_keys = OFF');
        EvidenceFile::query()->forceDelete();
        FolderNode::query()->delete();
        DB::statement('PRAGMA foreign_keys = ON');
        $this->command->info('Folder nodes and evidence files cleared.');

        // 2. Get the active semester
        $semester = Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();

        if (!$semester) {
            $this->command->warn('No semester found.');
            return;
        }

        // 3. Get all docentes (role_id = 1)
        $docentes = User::where('role_id', 1)->get();

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
