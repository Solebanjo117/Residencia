<?php

use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceItem;
use App\Models\Role;
use App\Models\StorageRoot;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notify:windows')->everyFiveMinutes();
Schedule::command('ops:backup --name=auto')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('residencia:bootstrap {--admin-name=Jefe de Departamento : Nombre del primer usuario con acceso administrativo} {--admin-email= : Email del primer usuario con acceso administrativo} {--admin-password= : Password inicial del primer usuario} {--department=Sistemas y Computacion : Departamento inicial} {--skip-evidence-catalog : No crear el catalogo base de rubros de evidencia}',
    function () {
        $adminEmail = trim((string) $this->option('admin-email'));
        $adminPassword = (string) $this->option('admin-password');

        if ($adminEmail === '' || $adminPassword === '') {
            $this->error('Debes indicar --admin-email y --admin-password para crear el primer acceso administrativo.');

            return 1;
        }

        $roles = collect([
            Role::DOCENTE,
            Role::JEFE_OFICINA,
            Role::JEFE_DEPTO,
        ])->mapWithKeys(fn (string $roleName) => [
            $roleName => Role::firstOrCreate(['name' => $roleName]),
        ]);

        $departmentName = trim((string) $this->option('department')) ?: 'Sistemas y Computacion';
        $department = Department::firstOrCreate(['name' => $departmentName]);

        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => trim((string) $this->option('admin-name')),
                'password' => Hash::make($adminPassword),
                'role_id' => $roles[Role::JEFE_DEPTO]->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $admin->departments()->syncWithoutDetaching([$department->id]);

        StorageRoot::firstOrCreate(
            ['name' => 'local_evidence'],
            [
                'base_path' => 'evidence',
                'is_active' => true,
            ],
        );

        if (! $this->option('skip-evidence-catalog')) {
            $category = EvidenceCategory::firstOrCreate(
                ['name' => 'I_CARGA_ACADEMICA'],
                ['description' => 'Evidencias relacionadas a la carga academica'],
            );

            foreach ([
                ['INSTRUM', 'Instrumentacion didactica base del curso.', true],
                ['HORARIO', 'Horario oficial del docente.', false],
                ['EV.DIAGN', 'Evaluacion diagnostica.', true],
                ['SEG 01', 'Primer seguimiento de evidencias.', true],
                ['CALIF. PARCIALES', 'Calificaciones parciales del primer seguimiento.', true],
                ['SEG 02', 'Segundo seguimiento de evidencias.', true],
                ['CALIF. PARCIALES 2', 'Calificaciones parciales del segundo seguimiento.', true],
                ['SEG 03', 'Tercer seguimiento de evidencias.', true],
                ['CALIF. PARCIALES 3', 'Calificaciones parciales del tercer seguimiento.', true],
                ['SEG 04 FINAL', 'Seguimiento final de evidencias.', true],
                ['CALIF. PARCIALES FINAL', 'Calificaciones finales.', true],
                ['REPORTES EVIDENCIAS ASIGNATURAS', 'Reporte de evidencias por asignatura.', true],
                ['REP FINAL', 'Reporte final.', true],
                ['ASESORIAS', 'Evidencia de asesorias academicas.', true],
                ['ACTAS FINALES', 'Actas finales.', true],
            ] as [$name, $description, $requiresSubject]) {
                EvidenceItem::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                    ],
                    [
                        'description' => $description,
                        'requires_subject' => $requiresSubject,
                        'active' => true,
                    ],
                );
            }
        }

        $this->info('Bootstrap institucional completado.');
        $this->line("Usuario administrativo: {$admin->email}");
        $this->line("Departamento inicial: {$department->name}");

        return 0;
    }
)->purpose('Crea el acceso administrativo y catalogos base sin datos demo.');
