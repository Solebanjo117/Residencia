<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\FolderStructureService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = env('SEED_DEFAULT_PASSWORD', 'password');
        $folderStructureService = app(FolderStructureService::class);
        $allFolderPermissionKeys = $folderStructureService->allFolderPermissionKeys();

        $roles = collect([
            Role::DOCENTE,
            Role::JEFE_DEPTO,
            Role::JEFE_OFICINA,
        ])->mapWithKeys(function (string $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            return [$roleName => $role->id];
        });

        $department = Department::firstOrCreate(['name' => 'Sistemas y Computacion']);

        $seedUsers = [
            [
                'name' => 'Jefe de Oficina',
                'email' => 'oficina@example.com',
                'role' => Role::JEFE_OFICINA,
            ],
            [
                'name' => 'Jefe de Departamento',
                'email' => 'depto@example.com',
                'role' => Role::JEFE_DEPTO,
            ],
            [
                'name' => 'Docente Uno',
                'email' => 'docente1@example.com',
                'role' => Role::DOCENTE,
            ],
            [
                'name' => 'Docente Dos',
                'email' => 'docente2@example.com',
                'role' => Role::DOCENTE,
            ],
        ];

        foreach ($seedUsers as $seedUser) {
            $user = User::updateOrCreate(
                ['email' => $seedUser['email']],
                [
                    'name' => $seedUser['name'],
                    'password' => Hash::make($defaultPassword),
                    'role_id' => $roles[$seedUser['role']],
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'folder_permission_keys' => $seedUser['role'] === Role::DOCENTE
                        ? $allFolderPermissionKeys
                        : null,
                ]
            );

            $user->departments()->syncWithoutDetaching([$department->id]);
        }

        $this->command?->info('Usuarios base creados/actualizados. Password por defecto: '.$defaultPassword);

        $this->call([
            SeguimientoSeeder::class,
            FolderStructureSeeder::class,
            AdvisoryScheduleSeeder::class,
        ]);
    }
}
