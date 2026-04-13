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
use App\Models\TeachingLoad;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $semester = Semester::where('status', 'OPEN')->first()
            ?? Semester::orderBy('start_date', 'desc')->first();

        $overview = [];
        $quickActions = $this->quickActionsFor($user);
        $upcomingDeadlines = [];

        if ($semester) {
            $overview = $this->overviewFor($user, $semester);
            $upcomingDeadlines = $this->upcomingDeadlinesFor($user, $semester);
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

    private function overviewFor(User $user, Semester $semester): array
    {
        if ($user->isDocente()) {
            return $this->docenteOverview($user, $semester);
        }

        if ($user->isJefeOficina()) {
            return $this->jefeOficinaOverview($user, $semester);
        }

        if ($user->isJefeDepto()) {
            return $this->jefeDeptoOverview($user, $semester);
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

    private function docenteOverview(User $user, Semester $semester): array
    {
        $base = EvidenceSubmission::query()
            ->where('teacher_user_id', $user->id)
            ->where('semester_id', $semester->id);

        $mandatoryItems = collect();
        $department = $user->departments()->first();
        if ($department) {
            $mandatoryItems = EvidenceRequirement::query()
                ->where('semester_id', $semester->id)
                ->where('department_id', $department->id)
                ->where('is_mandatory', true)
                ->pluck('evidence_item_id');
        }

        $mandatorySubmitted = 0;
        if ($mandatoryItems->isNotEmpty()) {
            $mandatorySubmitted = EvidenceSubmission::query()
                ->where('teacher_user_id', $user->id)
                ->where('semester_id', $semester->id)
                ->whereIn('evidence_item_id', $mandatoryItems)
                ->whereIn('status', [SubmissionStatus::SUBMITTED, SubmissionStatus::APPROVED])
                ->distinct('evidence_item_id')
                ->count('evidence_item_id');
        }

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
                'label' => 'En revisión',
                'value' => (clone $base)->where('status', SubmissionStatus::SUBMITTED)->count(),
                'description' => 'Evidencias enviadas en espera de dictamen.',
                'tone' => 'blue',
            ],
            [
                'key' => 'my_approved',
                'label' => 'Aprobadas',
                'value' => (clone $base)->where('status', SubmissionStatus::APPROVED)->count(),
                'description' => 'Evidencias ya aprobadas para el semestre.',
                'tone' => 'green',
            ],
            [
                'key' => 'mandatory_progress',
                'label' => 'Obligatorias cubiertas',
                'value' => $mandatoryItems->isNotEmpty()
                    ? $mandatorySubmitted . ' / ' . $mandatoryItems->count()
                    : '0 / 0',
                'description' => 'Avance sobre los requerimientos obligatorios.',
                'tone' => 'slate',
            ],
        ];
    }

    private function jefeOficinaOverview(User $user, Semester $semester): array
    {
        $base = EvidenceSubmission::query()->where('semester_id', $semester->id);

        return [
            [
                'key' => 'pending_review',
                'label' => 'Pendientes de revisión',
                'value' => (clone $base)->where('status', SubmissionStatus::SUBMITTED)->count(),
                'description' => 'Entregas listas para dictamen institucional.',
                'tone' => 'amber',
            ],
            [
                'key' => 'approved',
                'label' => 'Aprobadas',
                'value' => (clone $base)->where('status', SubmissionStatus::APPROVED)->count(),
                'description' => 'Entregas aprobadas en el semestre actual.',
                'tone' => 'green',
            ],
            [
                'key' => 'rejected',
                'label' => 'Rechazadas',
                'value' => (clone $base)->where('status', SubmissionStatus::REJECTED)->count(),
                'description' => 'Entregas devueltas para corrección.',
                'tone' => 'red',
            ],
            [
                'key' => 'reviews_today',
                'label' => 'Dictámenes hoy',
                'value' => EvidenceReview::query()
                    ->where('reviewed_by_user_id', $user->id)
                    ->whereDate('reviewed_at', now()->toDateString())
                    ->count(),
                'description' => 'Actividad de revisión del día.',
                'tone' => 'blue',
            ],
        ];
    }

    private function jefeDeptoOverview(User $user, Semester $semester): array
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

        $itemIds = EvidenceRequirement::query()
            ->where('semester_id', $semester->id)
            ->whereIn('department_id', $departmentIds)
            ->pluck('evidence_item_id')
            ->unique();

        $activeWindows = 0;
        if ($itemIds->isNotEmpty()) {
            $activeWindows = SubmissionWindow::query()
                ->where('semester_id', $semester->id)
                ->whereIn('evidence_item_id', $itemIds)
                ->where('status', 'ACTIVE')
                ->where('opens_at', '<=', now())
                ->where('closes_at', '>=', now())
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
                'key' => 'mandatory_requirements',
                'label' => 'Requerimientos obligatorios',
                'value' => EvidenceRequirement::query()
                    ->where('semester_id', $semester->id)
                    ->whereIn('department_id', $departmentIds)
                    ->where('is_mandatory', true)
                    ->count(),
                'description' => 'Matriz activa de evidencias por departamento.',
                'tone' => 'slate',
            ],
            [
                'key' => 'pending_review_scope',
                'label' => 'Pendientes por revisar',
                'value' => (clone $submissionBase)->where('status', SubmissionStatus::SUBMITTED)->count(),
                'description' => 'Entregas de tu alcance en estado SUBMITTED.',
                'tone' => 'amber',
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

    private function upcomingDeadlinesFor(User $user, Semester $semester): array
    {
        $query = SubmissionWindow::query()
            ->with('evidenceItem')
            ->where('semester_id', $semester->id)
            ->where('status', 'ACTIVE')
            ->where('closes_at', '>', now())
            ->orderBy('closes_at', 'asc');

        if ($user->isJefeDepto()) {
            $departmentIds = $user->departments()->pluck('departments.id');
            $itemIds = EvidenceRequirement::query()
                ->where('semester_id', $semester->id)
                ->whereIn('department_id', $departmentIds)
                ->pluck('evidence_item_id')
                ->unique();

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
                    'description' => 'Inicializa, carga y envía tus documentos.',
                    'href' => '/docente/evidencias',
                ],
                [
                    'title' => 'Mis Asesorías',
                    'description' => 'Registra sesiones y actividades de asesoría.',
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
                    'title' => 'Pendientes de Revisión',
                    'description' => 'Atiende entregas en estado SUBMITTED.',
                    'href' => '/oficina/revisiones',
                ],
                [
                    'title' => 'Reportes Docentes',
                    'description' => 'Consulta indicadores consolidados.',
                    'href' => '/oficina/reportes',
                ],
                [
                    'title' => 'Auditoría',
                    'description' => 'Revisa bitácora institucional de acciones.',
                    'href' => '/admin/audits',
                ],
            ];
        }

        if ($user->isJefeDepto()) {
            return [
                [
                    'title' => 'Ventanas de Entrega',
                    'description' => 'Configura calendarios de recepción.',
                    'href' => '/admin/windows',
                ],
                [
                    'title' => 'Matriz de Evidencias',
                    'description' => 'Mantén la asignación por semestre.',
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
                'description' => 'Consulta el tablero de control académico.',
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
            ->where('closes_at', '>=', now())
            ->count();
    }
}
