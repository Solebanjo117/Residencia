<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\ReviewDecision;
use App\Enums\SubmissionStatus;
use App\Models\Semester;
use App\Models\TeachingLoad;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Services\EvidenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdvisoryController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $department = $user->departments()->first();

        $semesterQuery = $request->input('semester');

        $semester = $semesterQuery
            ? Semester::where('name', $semesterQuery)->first()
            : (Semester::where('status', 'OPEN')->first() ?? Semester::orderBy('start_date', 'desc')->first());

        if (!$semester || !$department) {
            return Inertia::render('SeguimientoDocente', [
                'rows' => [],
                'semesters' => Semester::pluck('name')->toArray(),
                'columns' => [],
                'currentSemester' => $semesterQuery ?? '',
                'userRole' => $user->role->name ?? '',
            ]);
        }

        $requirements = EvidenceRequirement::with('evidenceItem')
            ->where('semester_id', $semester->id)
            ->where('department_id', $department->id)
            ->get();

        $evidenceItems = $requirements->map(fn($req) => $req->evidenceItem);

        // Docente sees only their own loads; admins see all
        $loadsQuery = TeachingLoad::with(['teacher', 'subject'])
            ->where('semester_id', $semester->id);

        if ($user->isDocente()) {
            $loadsQuery->where('teacher_user_id', $user->id);
        }

        $teachingLoads = $loadsQuery->get();

        $submissions = EvidenceSubmission::with(['files', 'reviews' => fn($q) => $q->latest('reviewed_at')])
            ->where('semester_id', $semester->id)
            ->get()
            ->groupBy('teaching_load_id');

        $rows = [];
        foreach ($teachingLoads as $load) {
            $loadSubmissions = $submissions->get($load->id) ?? collect([]);

            $rowData = [
                'id' => $load->id,
                'maestro' => $load->teacher->name,
                'materia' => $load->subject->name,
                'carrera' => $load->group_name,
                'clave_tecnm' => $load->subject->code,
                'semestre' => $semester->name,
                'cells' => [],
            ];

            $allApproved = true;
            $anyNe = false;

            foreach ($evidenceItems as $item) {
                $sub = $loadSubmissions->firstWhere('evidence_item_id', $item->id);

                $uiStatus = $this->mapStatus($sub);

                if ($uiStatus !== 'A') $allApproved = false;
                if ($uiStatus === 'NE') $anyNe = true;

                $lastReview = $sub?->reviews?->first();

                $rowData['cells']['item_' . $item->id] = [
                    'status' => $uiStatus,
                    'db_status' => $sub?->status?->value,
                    'submission_id' => $sub?->id,
                    'files' => $sub ? $sub->files->map(fn($f) => [
                        'id' => $f->id,
                        'file_name' => $f->file_name,
                        'size' => $f->size_bytes,
                        'uploaded_at' => $f->uploaded_at?->toDateTimeString(),
                    ])->values()->toArray() : [],
                    'last_review' => $lastReview ? [
                        'decision' => $lastReview->decision->value,
                        'comments' => $lastReview->comments,
                        'reviewed_at' => $lastReview->reviewed_at?->toDateTimeString(),
                    ] : null,
                ];
            }

            $rowData['estado_final'] = $allApproved && $evidenceItems->count() > 0
                ? 'A'
                : ($anyNe ? 'NE' : 'PA');

            $rows[] = $rowData;
        }

        return Inertia::render('SeguimientoDocente', [
            'rows' => $rows,
            'semesters' => Semester::pluck('name')->toArray(),
            'columns' => $evidenceItems->map(fn($item) => [
                'key' => 'item_' . $item->id,
                'label' => $item->name,
                'item_id' => $item->id,
            ])->values()->toArray(),
            'currentSemester' => $semester->name,
            'userRole' => $user->role->name ?? '',
        ]);
    }

    public function reviewEvidence(Request $request, EvidenceSubmission $submission, EvidenceService $evidenceService)
    {
        $request->validate([
            'decision' => 'required|in:APPROVE,REJECT',
            'comments' => 'nullable|string|max:500',
        ]);

        $decision = ReviewDecision::from($request->decision);

        $evidenceService->review(
            $submission,
            $request->user(),
            $decision,
            $request->comments
        );

        return back()->with('success', $decision === ReviewDecision::APPROVE
            ? 'Evidencia aprobada.'
            : 'Evidencia rechazada. Se notificó al docente.'
        );
    }

    private function mapStatus(?EvidenceSubmission $sub): string
    {
        if (!$sub) return 'NE';

        return match ($sub->status) {
            SubmissionStatus::APPROVED => 'A',
            SubmissionStatus::NA => 'NA',
            SubmissionStatus::DRAFT, SubmissionStatus::SUBMITTED => 'PA',
            SubmissionStatus::REJECTED => 'R',
            SubmissionStatus::NE => 'NE',
            default => 'NE',
        };
    }
}
