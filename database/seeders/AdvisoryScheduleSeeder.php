<?php

namespace Database\Seeders;

use App\Models\AdvisorySchedule;
use App\Models\Semester;
use App\Models\TeachingLoad;
use Illuminate\Database\Seeder;

class AdvisoryScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $semester = Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();

        if (! $semester) {
            $this->command->warn('No se encontro un semestre para generar horarios de asesoria.');

            return;
        }

        $loads = TeachingLoad::with(['teacher', 'subject'])
            ->where('semester_id', $semester->id)
            ->get();

        if ($loads->isEmpty()) {
            $this->command->warn('No hay cargas docentes para el semestre.');

            return;
        }

        $patterns = [
            [[1, '10:00', '12:00', 'Aula 101'], [3, '10:00', '12:00', 'Aula 101']],
            [[2, '08:00', '10:00', 'Aula 201'], [4, '08:00', '10:00', 'Aula 201']],
            [[1, '14:00', '16:00', 'Lab. Computo 1']],
            [[3, '14:00', '16:00', 'Aula 102'], [5, '14:00', '16:00', 'Aula 102']],
            [[2, '12:00', '14:00', 'Aula 202']],
            [[4, '10:00', '12:00', 'Lab. Computo 2'], [5, '10:00', '12:00', 'Lab. Computo 2']],
        ];

        $count = 0;

        foreach ($loads as $index => $load) {
            $pattern = $patterns[$index % count($patterns)];

            foreach ($pattern as $slot) {
                AdvisorySchedule::firstOrCreate(
                    [
                        'teacher_user_id' => $load->teacher_user_id,
                        'teaching_load_id' => $load->id,
                        'semester_id' => $semester->id,
                        'day_of_week' => $slot[0],
                        'start_time' => $slot[1],
                    ],
                    [
                        'end_time' => $slot[2],
                        'location' => $slot[3],
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Horarios de asesorias asegurados: {$count}");
    }
}
