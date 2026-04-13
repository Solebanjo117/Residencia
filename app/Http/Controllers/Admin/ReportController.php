<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $semester = $this->resolveSemester($request);

        if (!$semester) {
            return Inertia::render('Oficina/Reports', [
                'rows' => [],
                'summary' => [
                    'teachers' => 0,
                    'submissions' => 0,
                    'submitted' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                ],
                'filters' => [
                    'semester_id' => null,
                    'search' => (string) $request->query('search', ''),
                    'status_focus' => (string) $request->query('status_focus', 'all'),
                ],
                'semesters' => Semester::orderBy('start_date', 'desc')->get(['id', 'name']),
            ]);
        }

        $search = trim((string) $request->query('search', ''));
        $statusFocus = (string) $request->query('status_focus', 'all');

        $rows = $this->buildRows($semester->id, $search, $statusFocus);
        $summary = $this->buildSummary($rows);

        if ((string) $request->query('export') === 'csv') {
            return $this->exportCsv($rows, $semester->name);
        }

        return Inertia::render('Oficina/Reports', [
            'rows' => $rows,
            'summary' => $summary,
            'filters' => [
                'semester_id' => $semester->id,
                'search' => $search,
                'status_focus' => $statusFocus,
            ],
            'semesters' => Semester::orderBy('start_date', 'desc')->get(['id', 'name']),
        ]);
    }

    private function resolveSemester(Request $request): ?Semester
    {
        $semesterId = $request->query('semester_id');

        if ($semesterId) {
            return Semester::find($semesterId);
        }

        return Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();
    }

    private function buildRows(int $semesterId, string $search, string $statusFocus)
    {
        $loads = DB::table('teaching_loads')
            ->where('semester_id', $semesterId)
            ->selectRaw('teacher_user_id, COUNT(*) as loads_count')
            ->groupBy('teacher_user_id');

        $submissions = DB::table('evidence_submissions')
            ->where('semester_id', $semesterId)
            ->selectRaw("teacher_user_id,
                COUNT(*) as total_submissions,
                SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END) as draft_count,
                SUM(CASE WHEN status = 'SUBMITTED' THEN 1 ELSE 0 END) as submitted_count,
                SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN status IN ('DRAFT','REJECTED') THEN 1 ELSE 0 END) as delayed_count")
            ->groupBy('teacher_user_id');

        $query = DB::table('users')
            ->joinSub($loads, 'loads', function ($join) {
                $join->on('loads.teacher_user_id', '=', 'users.id');
            })
            ->leftJoinSub($submissions, 'subs', function ($join) {
                $join->on('subs.teacher_user_id', '=', 'users.id');
            })
            ->selectRaw("users.id as teacher_id,
                users.name as teacher_name,
                users.email as teacher_email,
                loads.loads_count,
                COALESCE(subs.total_submissions, 0) as total_submissions,
                COALESCE(subs.draft_count, 0) as draft_count,
                COALESCE(subs.submitted_count, 0) as submitted_count,
                COALESCE(subs.approved_count, 0) as approved_count,
                COALESCE(subs.rejected_count, 0) as rejected_count,
                COALESCE(subs.delayed_count, 0) as delayed_count")
            ->orderBy('users.name');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $rows = collect($query->get())->map(function ($row) {
            $completed = ((int) $row->approved_count) + ((int) $row->submitted_count);
            $total = (int) $row->total_submissions;

            $compliance = $total > 0
                ? (int) round(($completed / $total) * 100)
                : 0;

            return [
                'teacher_id' => (int) $row->teacher_id,
                'teacher_name' => (string) $row->teacher_name,
                'teacher_email' => (string) $row->teacher_email,
                'loads_count' => (int) $row->loads_count,
                'total_submissions' => $total,
                'draft_count' => (int) $row->draft_count,
                'submitted_count' => (int) $row->submitted_count,
                'approved_count' => (int) $row->approved_count,
                'rejected_count' => (int) $row->rejected_count,
                'delayed_count' => (int) $row->delayed_count,
                'compliance' => $compliance,
            ];
        });

        return $this->applyStatusFocus($rows, $statusFocus)->values()->all();
    }

    private function applyStatusFocus($rows, string $statusFocus)
    {
        return match ($statusFocus) {
            'pending_review' => $rows->where('submitted_count', '>', 0),
            'approved' => $rows->where('approved_count', '>', 0),
            'delayed' => $rows->where('delayed_count', '>', 0),
            'no_submissions' => $rows->where('total_submissions', 0),
            default => $rows,
        };
    }

    private function buildSummary(array $rows): array
    {
        $collection = collect($rows);

        return [
            'teachers' => $collection->count(),
            'submissions' => $collection->sum('total_submissions'),
            'submitted' => $collection->sum('submitted_count'),
            'approved' => $collection->sum('approved_count'),
            'rejected' => $collection->sum('rejected_count'),
        ];
    }

    private function exportCsv(array $rows, string $semesterName)
    {
        $filename = 'reporte-docentes-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows, $semesterName) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Semestre', $semesterName]);
            fputcsv($handle, [
                'Docente',
                'Correo',
                'Cargas',
                'Entregas Totales',
                'Borrador',
                'En Revision',
                'Aprobadas',
                'Rechazadas',
                'Con Atraso',
                'Cumplimiento %',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['teacher_name'],
                    $row['teacher_email'],
                    $row['loads_count'],
                    $row['total_submissions'],
                    $row['draft_count'],
                    $row['submitted_count'],
                    $row['approved_count'],
                    $row['rejected_count'],
                    $row['delayed_count'],
                    $row['compliance'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
