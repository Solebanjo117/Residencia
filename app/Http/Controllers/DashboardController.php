<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceReview;
use App\Models\EvidenceSubmission;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Semester;
use App\Models\SubmissionWindow;
use App\Models\User;
use App\Services\EvidenceFlowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, EvidenceFlowService $flowService)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $semester = Semester::activeOrLatest();

        $overview = [];
        $quickActions = $this->quickActionsFor($user);
        $upcomingDeadlines = [];

        if ($semester) {
            $overview = $this->overviewFor($user, $semester, $flowService);
            $upcomingDeadlines = $this->upcomingDeadlinesFor($user, $semester, $flowService);
        }

        $overview[] = [
            'key' => 'unread_notifications',
            'label' => 'Notificaciones sin leer',
            'value' => Notification::where('user_id', $user->id)->where('is_read', false)->count(),
            'description' => 'Alertas pendientes para tu cuenta.',
            'tone' => 'slate',
        ];

        return Inertia::render('Dashboard', [
            'semester' => $semester,
            'overview' => $overview,
            'quickActions' => $quickActions,
            'upcomingDeadlines' => $upcomingDeadlines,
        ]);
    }

    private function overviewFor(User $user, Semester $semester, EvidenceFlowService $flowService): array
    {
        if ($user->isDocente()) {
            return $this->docenteOverview($user, $semester, $flowService);
        }

        if ($user->isJefeOficina()) {
            return $this->jefeOficinaOverview($user, $semester);
        }

        if ($user->isJefeDepto()) {
            return $this->jefeDeptoOverview($user, $semester, $flowService);
        }

        return [
            [
                'key' => 'active_windows',
                'label' => 'Ventanas activas',
                'value' => $this->activeWindowsCount($semester),
                'description' => 'Ventanas vigentes del semestre.',
                'tone' => 'blue',
            ],
        ];
    }

    private function docenteOverview(User $user, Semester $semester, EvidenceFlowService $flowService): array
    {
        $base = EvidenceSubmission::query()
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $semester->id);

        $mandatoryItems = collect();
        $department = $user->departments()->first();
        if ($department) {
            $mandatoryItems = $flowService->requirementsForDepartment($semester->id, $department->id)
                ->filter(fn (EvidenceRequirement $requirement) => $requirement->is_mandatory)
                ->pluck('evidence_item_id');
        }

        $mandatorySubmissions = collect();
        if ($mandatoryItems->isNotEmpty()) {
            $mandatorySubmissions = EvidenceSubmission::query()
                ->where('teacher_user_id', $user->id)
                ->where('semester_id', $semester->id)
                ->whereIn('evidence_item_id', $mandatoryItems)
                ->get()
                ->keyBy('evidence_item_id');
        }

        $applicableMandatory = $mandatoryItems->reject(function ($itemId) use ($mandatorySubmissions) {
            return $mandatorySubmissions->get($itemId)?->status === SubmissionStatus::NA;
        });

        $mandatorySubmitted = $applicableMandatory->filter(function ($itemId) use ($mandatorySubmissions) {
            return in_array($mandatorySubmissions->get($itemId)?->status, [SubmissionStatus::SUBMITTED, SubmissionStatus::APPROVED], true);
        })->count();

        return [
            [
                'key' => 'my_pending',
                'label' => 'Entregas por enviar',
                'value' => (clone $base)->whereIn('status', [SubmissionStatus::DRAFT, SubmissionStatus::REJECTED])->count(),
                'description' => 'Borradores o evidencias rechazadas pendientes.',
                'tone' => 'amber',
            ],
            [
                'key' => 'my_under_review',
                'label' => 'En revision',
                'value' => (clone $base)->where('status', SubmissionStatus::SUBMITTED)->count(),
                'description' => 'Evidencias enviadas en espera de dictamen institucional.',
                'tone' => 'blue',
            ],
            [
                'key' => 'my_office_approved',
                'label' => 'Aprobadas por oficina',
                'value' => (clone $base)->where('status', SubmissionStatus::APPROVED)->whereNull('final_approved_at')->count(),
                'description' => 'Evidencias pendientes de visto bueno final.',
                'tone' => 'green',
            ],
            [
                'key' => 'my_final_approved',
                'label' => 'Liberadas',
                'value' => (clone $base)->whereNotNull('final_approved_at')->count(),
                'description' => 'Evidencias con visto bueno final.',
                'tone' => 'slate',
            ],
            [
                'key' => 'mandatory_progress',
                'label' => 'Obligatorias cubiertas',
                'value' => $applicableMandatory->isNotEmpty()
                    ? $mandatorySubmitted . ' / ' . $applicableMandatory->count()
                    : '0 / 0',
                'description' => 'Avance sobre los requerimientos obligatorios aplicables.',
                'tone' => 'slate',
            ],
            [
                'key' => 'my_late_submissions',
                'label' => 'Extemporaneas',
                'value' => (clone $base)->where('submitted_late', true)->count(),
                'description' => 'Entregas realizadas despues del cierre regular.',
                'tone' => 'amber',
            ],
        ];
    }

    private function jefeOficinaOverview(User $user, Semester $semester): array
    {
        $base = EvidenceSubmission::query()->where('semester_id', $semester->id);

        return [
            [
                'key' => 'pending_review',
                'label' => 'Pendientes de revision',
                'value' => (clone $base)->where('status', SubmissionStatus::SUBMITTED)->count(),
                'description' => 'Entregas listas para dictamen institucional.',
                'tone' => 'amber',
            ],
            [
                'key' => 'office_approved',
                'label' => 'Aprobadas por oficina',
                'value' => (clone $base)->where('status', SubmissionStatus::APPROVED)->whereNull('final_approved_at')->count(),
                'description' => 'Entregas pendientes de visto bueno final.',
                'tone' => 'green',
            ],
            [
                'key' => 'final_approved',
                'label' => 'Liberadas',
                'value' => (clone $base)->whereNotNull('final_approved_at')->count(),
                'description' => 'Entregas con liberacion final.',
                'tone' => 'blue',
            ],
            [
                'key' => 'rejected',
                'label' => 'Rechazadas',
                'value' => (clone $base)->where('status', SubmissionStatus::REJECTED)->count(),
                'description' => 'Entregas devueltas para correccion.',
                'tone' => 'red',
            ],
            [
                'key' => 'late_submissions',
                'label' => 'Extemporaneas',
                'value' => (clone $base)->where('submitted_late', true)->count(),
                'description' => 'Entregas recibidas fuera del periodo regular.',
                'tone' => 'amber',
            ],
            [
                'key' => 'reviews_today',
                'label' => 'Dictamenes hoy',
                'value' => EvidenceReview::query()
                    ->where('reviewed_by_user_id', $user->id)
                    ->whereDate('reviewed_at', now()->toDateString())
                    ->count(),
                'description' => 'Actividad de revision del dia.',
                'tone' => 'blue',
            ],
        ];
    }

    private function jefeDeptoOverview(User $user, Semester $semester, EvidenceFlowService $flowService): array
    {
        $departmentIds = $user->departments()->pluck('departments.id');

        $teacherIds = collect();
        if ($departmentIds->isNotEmpty()) {
            $teacherIds = User::query()
                ->where('role_id', Role::where('name', Role::DOCENTE)->value('id'))
                ->whereHas('departments', function ($query) use ($departmentIds) {
                    $query->whereIn('departments.id', $departmentIds);
                })
                ->pluck('id');
        }

        $submissionBase = EvidenceSubmission::query()
            ->where('semester_id', $semester->id)
            ->when($teacherIds->isNotEmpty(), function ($query) use ($teacherIds) {
                $query->whereIn('teacher_user_id', $teacherIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            });

        $itemIds = collect();
        foreach ($departmentIds as $departmentId) {
            $itemIds = $itemIds->merge(
                $flowService->requirementsForDepartment($semester->id, (int) $departmentId)->pluck('evidence_item_id')
            );
        }
        $itemIds = $itemIds->unique()->values();

        $activeWindows = 0;
        if ($itemIds->isNotEmpty()) {
            $activeWindows = SubmissionWindow::query()
                ->where('semester_id', $semester->id)
                ->whereIn('evidence_item_id', $itemIds)
                ->where('status', 'ACTIVE')
                ->where('opens_at', '<=', now())
                ->count();
        }

        return [
            [
                'key' => 'teachers_scope',
                'label' => 'Docentes en alcance',
                'value' => $teacherIds->count(),
                'description' => 'Docentes adscritos a tus departamentos.',
                'tone' => 'blue',
            ],
            [
                'key' => 'final_pending',
                'label' => 'Pendientes de visto bueno',
                'value' => (clone $submissionBase)->where('status', SubmissionStatus::APPROVED)->whereNull('final_approved_at')->count(),
                'description' => 'Entregas aprobadas por oficina que esperan tu liberacion.',
                'tone' => 'amber',
            ],
            [
                'key' => 'final_approved',
                'label' => 'Liberadas',
                'value' => (clone $submissionBase)->whereNotNull('final_approved_at')->count(),
                'description' => 'Entregas con visto bueno final.',
                'tone' => 'green',
            ],
            [
                'key' => 'mandatory_requirements',
                'label' => 'Requerimientos activos',
                'value' => EvidenceRequirement::query()
                    ->where('semester_id', $semester->id)
                    ->where(function ($query) use ($departmentIds) {
                        $query->whereNull('department_id')
                            ->orWhereIn('department_id', $departmentIds);
                    })
                    ->where('is_mandatory', true)
                    ->count(),
                'description' => 'Matriz obligatoria vigente para tu alcance.',
                'tone' => 'slate',
            ],
            [
                'key' => 'active_windows_scope',
                'label' => 'Ventanas activas',
                'value' => $activeWindows,
                'description' => 'Ventanas vigentes para evidencias de tu alcance.',
                'tone' => 'green',
            ],
        ];
    }

    private function upcomingDeadlinesFor(User $user, Semester $semester, EvidenceFlowService $flowService): array
    {
        $query = SubmissionWindow::query()
            ->with('evidenceItem')
            ->where('semester_id', $semester->id)
            ->where('status', 'ACTIVE')
            ->where('closes_at', '>', now())
            ->orderBy('closes_at', 'asc');

        if ($user->isJefeDepto()) {
            $departmentIds = $user->departments()->pluck('departments.id');
            $itemIds = collect();

            foreach ($departmentIds as $departmentId) {
                $itemIds = $itemIds->merge(
                    $flowService->requirementsForDepartment($semester->id, (int) $departmentId)->pluck('evidence_item_id')
                );
            }

            $itemIds = $itemIds->unique()->values();

            if ($itemIds->isEmpty()) {
                return [];
            }

            $query->whereIn('evidence_item_id', $itemIds);
        }

        return $query
            ->take(5)
            ->get()
            ->map(function (SubmissionWindow $window) {
                $now = now();

                return [
                    'id' => $window->id,
                    'item_name' => $window->evidenceItem?->name ?? 'Evidencia',
                    'opens_at' => $window->opens_at?->toDateTimeString(),
                    'closes_at' => $window->closes_at?->toDateTimeString(),
                    'is_open' => $window->opens_at && $window->closes_at
                        ? $now->between($window->opens_at, $window->closes_at)
                        : false,
                ];
            })
            ->values()
            ->all();
    }

    private function quickActionsFor(User $user): array
    {
        if ($user->isDocente()) {
            return [
                [
                    'title' => 'Mis Evidencias',
                    'description' => 'Inicializa, carga y envia tus documentos.',
                    'href' => '/docente/evidencias',
                ],
                [
                    'title' => 'Mis Asesorias',
                    'description' => 'Registra sesiones y actividades de asesoria.',
                    'href' => '/docente/asesorias',
                ],
                [
                    'title' => 'File Manager',
                    'description' => 'Administra tu estructura documental.',
                    'href' => '/files/manager',
                ],
            ];
        }

        if ($user->isJefeOficina()) {
            return [
                [
                    'title' => 'Pendientes de Revision',
                    'description' => 'Atiende entregas en estado SUBMITTED.',
                    'href' => '/oficina/revisiones',
                ],
                [
                    'title' => 'Reportes Docentes',
                    'description' => 'Consulta indicadores consolidados.',
                    'href' => '/oficina/reportes',
                ],
                [
                    'title' => 'Auditoria',
                    'description' => 'Revisa bitacora institucional de acciones.',
                    'href' => '/admin/audits',
                ],
            ];
        }

        if ($user->isJefeDepto()) {
            return [
                [
                    'title' => 'Ventanas de Entrega',
                    'description' => 'Configura calendarios de recepcion.',
                    'href' => '/admin/windows',
                ],
                [
                    'title' => 'Matriz de Evidencias',
                    'description' => 'Manten la asignacion por semestre.',
                    'href' => '/admin/requirements',
                ],
                [
                    'title' => 'Directorio Docentes',
                    'description' => 'Gestiona docentes y estructuras asociadas.',
                    'href' => '/admin/teachers',
                ],
            ];
        }

        return [
            [
                'title' => 'Dashboard Docente',
                'description' => 'Accede al panel de entregas y progreso.',
                'href' => '/docente/dashboard',
            ],
            [
                'title' => 'Seguimiento Docente',
                'description' => 'Consulta el tablero de control academico.',
                'href' => '/asesorias',
            ],
        ];
    }

    private function activeWindowsCount(Semester $semester): int
    {
        return SubmissionWindow::query()
            ->where('semester_id', $semester->id)
            ->where('status', 'ACTIVE')
            ->where('opens_at', '<=', now())
            ->count();
    }
}
