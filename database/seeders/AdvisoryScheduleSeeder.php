<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TeachingLoad;
use App\Models\Semester;
use App\Models\AdvisorySchedule;

class AdvisoryScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $semester = Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();

        if (!$semester) {
            $this->command->warn('No se encontró un semestre para generar horarios de asesoría.');
            return;
        }

        $loads = TeachingLoad::with(['teacher', 'subject'])
            ->where('semester_id', $semester->id)
            ->get();

        if ($loads->isEmpty()) {
            $this->command->warn('No hay cargas docentes para el semestre');
            return;
        }

        // Each load gets 1-2 days of advisory, with varied times and locations
        $patterns = [
            // pattern: [[day, start, end, location], ...]
            [[1, '10:00', '12:00', 'Aula 101'], [3, '10:00', '12:00', 'Aula 101']],
            [[2, '08:00', '10:00', 'Aula 201'], [4, '08:00', '10:00', 'Aula 201']],
            [[1, '14:00', '16:00', 'Lab. Computo 1']],
            [[3, '14:00', '16:00', 'Aula 102'], [5, '14:00', '16:00', 'Aula 102']],
            [[2, '12:00', '14:00', 'Aula 202']],
            [[4, '10:00', '12:00', 'Lab. Computo 2'], [5, '10:00', '12:00', 'Lab. Computo 2']],
            [[1, '16:00', '18:00', 'Aula 301']],
            [[2, '14:00', '16:00', 'Aula 103'], [4, '14:00', '16:00', 'Aula 103']],
            [[3, '08:00', '10:00', 'Aula 302']],
            [[1, '08:00', '10:00', 'Aula 104'], [3, '16:00', '18:00', 'Aula 104']],
            [[5, '09:00', '11:00', 'Lab. Computo 1']],
            [[2, '16:00', '18:00', 'Aula 203'], [4, '16:00', '18:00', 'Aula 203']],
        ];

        $count = 0;
        foreach ($loads as $index => $load) {
            $pattern = $patterns[$index % count($patterns)];

            foreach ($pattern as $slot) {
                AdvisorySchedule::firstOrCreate(
                    [
                        'teaching_load_id' => $load->id,
                        'semester_id' => $semester->id,
                        'day_of_week' => $slot[0],
                    ],
                    [
                        'start_time' => $slot[1],
                        'end_time' => $slot[2],
                        'location' => $slot[3],
                    ]
                );
                $count++;
            }
        }

        $this->command->info("Horarios de asesorías creados: {$count}");
    }
}
