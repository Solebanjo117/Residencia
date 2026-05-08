<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Services\FolderStructureService;
use Illuminate\Database\Seeder;

class FolderStructureSeeder extends Seeder
{
    public function run(): void
    {
        $semester = Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();

        if (! $semester) {
            $this->command->warn('No se encontro un semestre para generar carpetas.');

            return;
        }

        $docenteRoleId = Role::where('name', Role::DOCENTE)->value('id');
        if (! $docenteRoleId) {
            $this->command->warn('No se encontro el rol DOCENTE.');

            return;
        }

        $docentes = User::where('role_id', $docenteRoleId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($docentes->isEmpty()) {
            $this->command->warn('No hay docentes activos para generar carpetas.');

            return;
        }

        $service = app(FolderStructureService::class);

        foreach ($docentes as $docente) {
            $service->generateFullStructure($semester, $docente);
            $this->command->info("Estructura asegurada para: {$docente->name}");
        }

        $this->command->info('Estructura de carpetas asegurada sin borrar archivos existentes.');
    }
}
