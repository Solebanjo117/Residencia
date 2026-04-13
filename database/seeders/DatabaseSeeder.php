<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect([
            Role::DOCENTE,
            Role::JEFE_DEPTO,
            Role::JEFE_OFICINA,
        ])->mapWithKeys(function (string $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            return [$roleName => $role->id];
        });

        $department = Department::firstOrCreate(['name' => 'Sistemas y Computación']);

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
                    'password' => Hash::make('password'),
                    'role_id' => $roles[$seedUser['role']],
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            $user->departments()->syncWithoutDetaching([$department->id]);
        }

        $this->call([
            SeguimientoSeeder::class,
            FolderStructureSeeder::class,
            AdvisoryScheduleSeeder::class,
        ]);
    }
}
