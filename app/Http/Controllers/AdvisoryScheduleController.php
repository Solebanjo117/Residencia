<?php

namespace App\Http\Controllers;

use App\Models\AdvisorySchedule;
use App\Models\Semester;
use App\Models\TeachingLoad;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdvisoryScheduleController extends Controller
{
    public function index(Request $request)
    {
        $semesterQuery = $request->input('semester');

        $semester = $semesterQuery
            ? Semester::where('name', $semesterQuery)->first()
            : (Semester::where('status', 'OPEN')->first() ?? Semester::orderBy('start_date', 'desc')->first());

        if (!$semester) {
            return Inertia::render('Asesorias/Index', [
                'rows' => [],
                'semesters' => Semester::pluck('name')->toArray(),
                'currentSemester' => '',
            ]);
        }

        // Get all teaching loads with their schedules
        $loads = TeachingLoad::with(['teacher', 'subject', 'advisorySchedules' => function ($q) use ($semester) {
            $q->where('semester_id', $semester->id);
        }])
            ->where('semester_id', $semester->id)
            ->get();

        $rows = $loads->map(function ($load) {
            // Build day columns: 1=L, 2=M, 3=Mi, 4=J, 5=V
            $days = [];
            foreach ($load->advisorySchedules as $schedule) {
                $time = substr($schedule->start_time, 0, 5) . '-' . substr($schedule->end_time, 0, 5);
                $days[$schedule->day_of_week] = $time;
            }

            // Get location from first schedule
            $location = $load->advisorySchedules->first()?->location ?? '';

            return [
                'id' => $load->id,
                'materia' => $load->subject->name,
                'docente' => $load->teacher->name,
                'L' => $days[1] ?? '',
                'M' => $days[2] ?? '',
                'Mi' => $days[3] ?? '',
                'J' => $days[4] ?? '',
                'V' => $days[5] ?? '',
                'carrera' => $load->group_name,
                'aula' => $location,
            ];
        })->values();

        return Inertia::render('Asesorias/Index', [
            'rows' => $rows,
            'semesters' => Semester::pluck('name')->toArray(),
            'currentSemester' => $semester->name,
        ]);
    }
}
