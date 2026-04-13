<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Semester;
use App\Models\TeachingLoad;
use App\Models\SubmissionWindow;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Services\EvidenceFlowService;

class DashboardController extends Controller
{
    public function index(Request $request, EvidenceFlowService $flowService)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Get current active semester (or latest)
        $currentSemester = Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();

        $teachingLoads = [];
        $upcomingWindows = [];
        $requirements = [];
        $progress = [
            'total' => 0,
            'submitted' => 0,
            'percentage' => 0
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
                 // Get Requirements for this department and semester
                 $requirements = $flowService->requirementsForDepartment($currentSemester->id, $department->id);

                 $mandatoryIds = $requirements->where('is_mandatory', true)->pluck('evidence_item_id');
                 $mandatorySubmissions = EvidenceSubmission::where('teacher_user_id', $user->id)
                    ->where('semester_id', $currentSemester->id)
                    ->whereIn('evidence_item_id', $mandatoryIds)
                    ->get()
                    ->keyBy('evidence_item_id');

                 $applicableMandatory = $mandatoryIds->reject(function ($itemId) use ($mandatorySubmissions) {
                    return $mandatorySubmissions->get($itemId)?->status === \App\Enums\SubmissionStatus::NA;
                 });

                 $progress['total'] = $applicableMandatory->count();

                 $progress['submitted'] = $applicableMandatory->filter(function ($itemId) use ($mandatorySubmissions) {
                    return in_array($mandatorySubmissions->get($itemId)?->status?->value, ['SUBMITTED', 'APPROVED'], true);
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
