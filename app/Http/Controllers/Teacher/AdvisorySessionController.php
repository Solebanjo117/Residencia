<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AdvisorySchedule;
use App\Models\Semester;
use App\Models\TeachingLoad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdvisorySessionController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $currentSemester = $this->resolveCurrentSemester();

        $loads = TeachingLoad::with('subject')
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $currentSemester?->id)
            ->get();

        $schedules = AdvisorySchedule::with(['teachingLoad.subject'])
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $currentSemester?->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (AdvisorySchedule $schedule) => $this->serializeSchedule($schedule));

        return Inertia::render('Docente/MyAdvisories', [
            'sessions' => $schedules,
            'teaching_loads' => $loads,
            'semester' => $currentSemester,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateScheduleRequest($request);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $load = $this->resolveTeachingLoad($validated['teaching_load_id'] ?? null, $user);
        $semester = $this->resolveCurrentSemester();

        AdvisorySchedule::create([
            'teacher_user_id' => $user->id,
            'teaching_load_id' => $load?->id,
            'semester_id' => $load?->semester_id ?? $semester->id,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'location' => $validated['location'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Horario de asesoria registrado.');
    }

    public function update(Request $request, AdvisorySchedule $session)
    {
        $validated = $this->validateScheduleRequest($request);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($session->teacher_user_id !== $user->id) {
            abort(403);
        }

        $load = $this->resolveTeachingLoad($validated['teaching_load_id'] ?? null, $user);

        $session->update([
            'teaching_load_id' => $load?->id,
            'semester_id' => $load?->semester_id ?? $session->semester_id,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'location' => $validated['location'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Horario de asesoria actualizado.');
    }

    public function destroy(AdvisorySchedule $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($id->teacher_user_id !== $user->id) {
            abort(403);
        }

        $id->delete();

        return redirect()->back()->with('success', 'Horario eliminado correctamente.');
    }

    private function validateScheduleRequest(Request $request): array
    {
        return $request->validate([
            'teaching_load_id' => 'nullable|exists:teaching_loads,id',
            'day_of_week' => 'required|integer|min:1|max:5',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'location' => 'nullable|string|max:100',
        ]);
    }

    private function resolveTeachingLoad(mixed $teachingLoadId, $user): ?TeachingLoad
    {
        if (! $teachingLoadId) {
            return null;
        }

        $load = TeachingLoad::findOrFail($teachingLoadId);

        if ($load->teacher_user_id !== $user->id) {
            abort(403);
        }

        return $load;
    }

    private function resolveCurrentSemester(): Semester
    {
        return Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->firstOrFail();
    }

    private function serializeSchedule(AdvisorySchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'teacher_user_id' => $schedule->teacher_user_id,
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
