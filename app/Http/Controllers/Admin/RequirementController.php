<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\EvidenceCategory;
use App\Models\EvidenceRequirement;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RequirementController extends Controller
{
    public function index(Request $request)
    {
        $semesters = Semester::orderBy('start_date', 'desc')->get();
        $semesterId = $request->query('semester_id') ?? $semesters->first()?->id;
        $departments = Department::orderBy('name')->get();
        $teacherRoleId = Role::where('name', Role::DOCENTE)->value('id');

        // Group items by category for nicer UI
        $categories = EvidenceCategory::with(['items' => function ($q) {
            $q->where('active', true)->orderBy('name');
        }])->orderBy('name')->get();

        $requirements = $semesterId
            ? EvidenceRequirement::where('semester_id', $semesterId)->get()
            : collect();
        $teachers = $semesterId && $teacherRoleId
            ? User::query()
                ->where('role_id', $teacherRoleId)
                ->whereHas('teachingLoads', fn ($query) => $query->where('semester_id', $semesterId))
                ->with([
                    'departments:id,name',
                    'teachingLoads' => fn ($query) => $query
                        ->where('semester_id', $semesterId)
                        ->with('subject:id,name,code')
                        ->orderBy('subject_id')
                        ->orderBy('group_code'),
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (User $teacher) => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'departments' => $teacher->departments->map(fn (Department $department) => [
                        'id' => $department->id,
                        'name' => $department->name,
                    ])->values(),
                    'loads' => $teacher->teachingLoads->map(fn (TeachingLoad $load) => [
                        'id' => $load->id,
                        'department_ids' => $teacher->departments->pluck('id')->values(),
                        'subject_name' => $load->subject?->name,
                        'subject_code' => $load->subject?->code,
                        'group_code' => $load->group_code,
                    ])->values(),
                ])
            : collect();

        return Inertia::render('Admin/Requirements/Matrix', [
            'semesters' => $semesters,
            'departments' => $departments,
            'categories' => $categories,
            'requirements' => $requirements,
            'teachers' => $teachers,
            'selectedSemester' => $semesterId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'requirements' => 'present|array',
            'requirements.*.department_id' => 'nullable|exists:departments,id',
            'requirements.*.evidence_item_id' => 'required|exists:evidence_items,id',
            'requirements.*.is_mandatory' => 'required|boolean',
            'requirements.*.applies_condition' => 'nullable|array',
            'requirements.*.applies_condition.teacher_overrides' => 'nullable|array',
            'requirements.*.applies_condition.teacher_overrides.*' => 'boolean',
            'requirements.*.applies_condition.teaching_load_overrides' => 'nullable|array',
            'requirements.*.applies_condition.teaching_load_overrides.*' => 'boolean',
        ]);

        DB::transaction(function () use ($validated) {
            // Because it's a matrix toggle setup, we clear existing for this semester
            // and simply re-insert what was submitted.
            EvidenceRequirement::where('semester_id', $validated['semester_id'])->delete();

            foreach ($validated['requirements'] as $req) {
                EvidenceRequirement::create([
                    'semester_id' => $validated['semester_id'],
                    'department_id' => $req['department_id'],
                    'evidence_item_id' => $req['evidence_item_id'],
                    'is_mandatory' => $req['is_mandatory'] ?? true,
                    'applies_condition' => $this->normalizeAppliesCondition($req['applies_condition'] ?? null),
                ]);
            }
        });

        return redirect()->back()->with('success', 'Matriz de evidencias guardada correctamente.');
    }

    private function normalizeAppliesCondition(?array $condition): ?array
    {
        if (! $condition) {
            return null;
        }

        $normalized = [];

        foreach (['teacher_overrides', 'teaching_load_overrides'] as $key) {
            $values = collect($condition[$key] ?? [])
                ->filter(fn ($value, $id) => is_numeric($id) && is_bool($value))
                ->mapWithKeys(fn (bool $value, $id) => [(string) $id => $value])
                ->all();

            if ($values !== []) {
                $normalized[$key] = $values;
            }
        }

        return $normalized === [] ? null : $normalized;
    }
}
