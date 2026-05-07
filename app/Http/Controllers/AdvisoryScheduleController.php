<?php

namespace App\Http\Controllers;

use App\Models\AdvisorySchedule;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdvisoryScheduleController extends Controller
{
    public function index(Request $request)
    {
        $semesterQuery = $request->input('semester');

        $semester = $semesterQuery
            ? Semester::where('name', $semesterQuery)->first()
            : (Semester::where('status', 'OPEN')->first() ?? Semester::orderBy('start_date', 'desc')->first());

        if (! $semester) {
            return Inertia::render('Asesorias/Index', [
                'rows' => [],
                'schedules' => [],
                'teachers' => [],
                'teachingLoads' => [],
                'semesters' => Semester::pluck('name')->toArray(),
                'currentSemester' => '',
                'currentSemesterId' => null,
                'canManage' => false,
            ]);
        }

        $schedules = AdvisorySchedule::with(['teacher', 'teachingLoad.subject'])
            ->where('semester_id', $semester->id)
            ->orderBy('teacher_user_id')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

        return Inertia::render('Asesorias/Index', [
            'rows' => $this->buildRows($schedules),
            'schedules' => $schedules->map(fn (AdvisorySchedule $schedule) => $this->serializeSchedule($schedule))->values(),
            'teachers' => User::query()
                ->where('role_id', $teacherRoleId)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'teachingLoads' => TeachingLoad::with(['teacher', 'subject'])
                ->where('semester_id', $semester->id)
                ->orderBy('teacher_user_id')
                ->orderBy('group_code')
                ->get()
                ->map(fn (TeachingLoad $load) => [
                    'id' => $load->id,
                    'teacher_user_id' => $load->teacher_user_id,
                    'subject_name' => $load->subject?->name,
                    'group_name' => $load->group_code,
                ])
                ->values(),
            'semesters' => Semester::pluck('name')->toArray(),
            'currentSemester' => $semester->name,
            'currentSemesterId' => $semester->id,
            'canManage' => Auth::user()?->isAdministrativeAuthority() ?? false,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $validated = $this->validateScheduleRequest($request);
        $load = $this->resolveTeachingLoad($validated);

        AdvisorySchedule::create([
            'teacher_user_id' => $validated['teacher_user_id'],
            'teaching_load_id' => $load?->id,
            'semester_id' => $load?->semester_id ?? $validated['semester_id'],
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'location' => $validated['location'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Horario de asesoria registrado.');
    }

    public function update(Request $request, AdvisorySchedule $schedule)
    {
        $this->authorizeManage();

        $validated = $this->validateScheduleRequest($request);
        $load = $this->resolveTeachingLoad($validated);

        $schedule->update([
            'teacher_user_id' => $validated['teacher_user_id'],
            'teaching_load_id' => $load?->id,
            'semester_id' => $load?->semester_id ?? $validated['semester_id'],
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'location' => $validated['location'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Horario de asesoria actualizado.');
    }

    public function destroy(AdvisorySchedule $schedule)
    {
        $this->authorizeManage();

        $schedule->delete();

        return redirect()->back()->with('success', 'Horario de asesoria eliminado.');
    }

    private function validateScheduleRequest(Request $request): array
    {
        return $request->validate([
            'teacher_user_id' => 'required|exists:users,id',
            'teaching_load_id' => 'nullable|exists:teaching_loads,id',
            'semester_id' => 'required|exists:semesters,id',
            'day_of_week' => 'required|integer|min:1|max:5',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'location' => 'nullable|string|max:100',
        ]);
    }

    private function resolveTeachingLoad(array $validated): ?TeachingLoad
    {
        $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');
        $isTeacher = User::query()
            ->whereKey($validated['teacher_user_id'])
            ->where('role_id', $teacherRoleId)
            ->exists();

        if (! $isTeacher) {
            throw ValidationException::withMessages([
                'teacher_user_id' => 'Selecciona un docente valido.',
            ]);
        }

        if (empty($validated['teaching_load_id'])) {
            return null;
        }

        $load = TeachingLoad::findOrFail($validated['teaching_load_id']);

        if ($load->teacher_user_id !== (int) $validated['teacher_user_id']) {
            throw ValidationException::withMessages([
                'teaching_load_id' => 'La carga academica no pertenece al docente seleccionado.',
            ]);
        }

        return $load;
    }

    private function authorizeManage(): void
    {
        abort_unless(Auth::user()?->isAdministrativeAuthority(), 403);
    }

    private function buildRows($schedules)
    {
        return $schedules
            ->groupBy(fn (AdvisorySchedule $schedule) => implode('|', [
                $schedule->teacher_user_id,
                $schedule->teaching_load_id ?: 'general',
                $schedule->location ?: '',
            ]))
            ->map(function ($group) {
                /** @var AdvisorySchedule $first */
                $first = $group->first();
                $days = [];

                foreach ($group as $schedule) {
                    $time = substr((string) $schedule->start_time, 0, 5).'-'.substr((string) $schedule->end_time, 0, 5);
                    $days[$schedule->day_of_week][] = $time;
                }

                return [
                    'id' => $first->id,
                    'materia' => $first->teachingLoad?->subject?->name ?? 'Asesoria general',
                    'docente' => $first->teacher?->name ?? 'Sin docente',
                    'L' => implode(', ', $days[1] ?? []),
                    'M' => implode(', ', $days[2] ?? []),
                    'Mi' => implode(', ', $days[3] ?? []),
                    'J' => implode(', ', $days[4] ?? []),
                    'V' => implode(', ', $days[5] ?? []),
                    'carrera' => $first->teachingLoad?->group_code ?? 'General',
                    'aula' => $first->location,
                ];
            })
            ->values();
    }

    private function serializeSchedule(AdvisorySchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'teacher_user_id' => $schedule->teacher_user_id,
            'teacher_name' => $schedule->teacher?->name,
            'teaching_load_id' => $schedule->teaching_load_id,
            'semester_id' => $schedule->semester_id,
            'day_of_week' => $schedule->day_of_week,
            'day_name' => $schedule->day_name,
            'start_time' => substr((string) $schedule->start_time, 0, 5),
            'end_time' => substr((string) $schedule->end_time, 0, 5),
            'location' => $schedule->location,
            'subject_name' => $schedule->teachingLoad?->subject?->name,
            'group_name' => $schedule->teachingLoad?->group_code,
        ];
    }
}
