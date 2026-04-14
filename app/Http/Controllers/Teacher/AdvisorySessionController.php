<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Semester;
use App\Models\TeachingLoad;
use App\Models\AdvisorySession;
use App\Models\AdvisoryFile;
use App\Services\AdvisoryService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AdvisorySessionController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $currentSemester = Semester::activeOrLatest();

        // Fetch teaching loads to allow the user to select which group the session was for.
        $loads = TeachingLoad::with('subject')
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $currentSemester?->id)
            ->get();

        // Fetch the logged sessions for this semester using Eloquent relationships
        $sessions = AdvisorySession::with(['teachingLoad.subject', 'files'])
            ->where('created_by_user_id', $user->id)
            ->where('semester_id', $currentSemester?->id)
            ->orderBy('session_date', 'desc')
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'session_date' => $session->session_date,
                    'topic' => $session->topic,
                    'duration_minutes' => $session->duration_minutes,
                    'notes' => $session->notes,
                    'group_name' => $session->teachingLoad?->group_code,
                    'subject_name' => $session->teachingLoad?->subject?->name,
                    'files' => $session->files,
                ];
            });

        return Inertia::render('Docente/MyAdvisories', [
            'sessions' => $sessions,
            'teaching_loads' => $loads,
            'semester' => $currentSemester
        ]);
    }

    public function store(Request $request, AdvisoryService $advisoryService)
    {
        $request->validate([
            'teaching_load_id' => 'required|exists:teaching_loads,id',
            'session_date' => 'required|date',
            'topic' => 'required|string|max:255',
            'duration_minutes' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:500',
            'files.*' => 'nullable|file|mimes:pdf,jpg,png,docx|max:5120'
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $load = TeachingLoad::findOrFail($request->teaching_load_id);

        // Ensure the load belongs to the user
        if ($load->teacher_user_id !== $user->id) {
            abort(403);
        }

        DB::transaction(function () use ($request, $user, $load, $advisoryService) {
            // Use AdvisoryService to create the session (includes audit logging)
            $session = $advisoryService->recordSession(
                $load,
                $user,
                Carbon::parse($request->session_date),
                $request->topic,
                $request->duration_minutes,
                $request->notes
            );

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $fileName = $file->getClientOriginalName();
                    // Store the physical file
                    $path = $file->storeAs(
                        "advisories/sem_{$load->semester_id}/docente_{$user->id}",
                        Str::random(10) . '_' . $fileName,
                        'public'
                    );

                    AdvisoryFile::create([
                        'advisory_session_id' => $session->id,
                        'file_name' => $fileName,
                        'stored_relative_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'uploaded_by_user_id' => $user->id,
                        'uploaded_at' => now()
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Sesion de asesoria registrada.');
    }

    public function destroy($id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $session = AdvisorySession::with('files')->findOrFail($id);

        if ($session->created_by_user_id !== $user->id) {
            abort(403);
        }

        // Wrap file deletion + DB deletion in a transaction so both succeed or fail together
        DB::transaction(function () use ($session) {
            // Delete the physical files from storage
            foreach ($session->files as $f) {
                Storage::disk('public')->delete($f->stored_relative_path);
            }

            // Delete the session (cascade should handle advisory_files rows)
            $session->delete();
        });

        return redirect()->back()->with('success', 'Registro eliminado correctamente.');
    }
}
