<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\EvidenceSubmission;
use App\Models\Semester;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Services\EvidenceFlowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, EvidenceFlowService $flowService)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Get current active semester (or latest)
        $currentSemester = Semester::activeOrLatest();

        $teachingLoads = [];
        $upcomingWindows = [];
        $requirements = [];
        $progress = [
            'total' => 0,
            'submitted' => 0,
            'percentage' => 0,
        ];

        if ($currentSemester) {
            // Get Teacher's load for this semester
            $teachingLoads = TeachingLoad::with('subject')
                ->where('teacher_user_id', $user->id)
                ->where('semester_id', $currentSemester->id)
                ->get();

            // Find current/upcoming submission windows
            $now = now();
            $upcomingWindows = SubmissionWindow::with('evidenceItem')
                ->where('semester_id', $currentSemester->id)
                ->where('status', 'ACTIVE')
                ->where('closes_at', '>', $now)
                ->orderBy('opens_at', 'asc')
                ->take(5)
                ->get();

            // Calculate progress: We need the user's primary department
            $department = $user->departments()->first();

            if ($department) {
                $requirements = $teachingLoads
                    ->flatMap(fn (TeachingLoad $load) => $flowService
                        ->requirementsForDepartment($currentSemester->id, $department->id, $load)
                        ->where('is_mandatory', true)
                        ->map(fn ($requirement) => [
                            'teaching_load_id' => $load->id,
                            'evidence_item_id' => $requirement->evidence_item_id,
                        ]))
                    ->values();

                $mandatoryIds = $requirements->pluck('evidence_item_id')->unique();
                $mandatorySubmissions = EvidenceSubmission::where('teacher_user_id', $user->id)
                    ->where('semester_id', $currentSemester->id)
                    ->whereIn('evidence_item_id', $mandatoryIds)
                    ->get()
                    ->keyBy(fn (EvidenceSubmission $submission) => $submission->teaching_load_id.':'.$submission->evidence_item_id);

                $applicableMandatory = $requirements->reject(function (array $requirement) use ($mandatorySubmissions) {
                    $key = $requirement['teaching_load_id'].':'.$requirement['evidence_item_id'];

                    return $mandatorySubmissions->get($key)?->status === \App\Enums\SubmissionStatus::NA;
                });

                $progress['total'] = $applicableMandatory->count();

                $progress['submitted'] = $applicableMandatory->filter(function (array $requirement) use ($mandatorySubmissions) {
                    $key = $requirement['teaching_load_id'].':'.$requirement['evidence_item_id'];

                    return in_array($mandatorySubmissions->get($key)?->status?->value, ['SUBMITTED', 'APPROVED'], true);
                })->count();

                $progress['percentage'] = $progress['total'] > 0
                   ? round(($progress['submitted'] / $progress['total']) * 100)
                   : 0;
            }
        }

        return Inertia::render('Teacher/Dashboard', [
            'semester' => $currentSemester,
            'teachingLoads' => $teachingLoads,
            'upcomingWindows' => $upcomingWindows,
            'progress' => $progress,
        ]);
    }
}
