<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\EvidenceItem;
use App\Models\NotificationSchedule;
use App\Models\Semester;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Services\EvidenceFlowService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SubmissionWindowController extends Controller
{
    public function __construct(private readonly EvidenceFlowService $flowService) {}

    public function index(Request $request)
    {
        $semesterId = $request->query('semester_id');
        $status = $request->query('status');

        $query = SubmissionWindow::with(['semester', 'evidenceItem', 'createdBy'])
            ->orderBy('opens_at', 'desc');

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        $this->applyOperationalStatusFilter($query, $status);

        $windows = $query->paginate(15)->withQueryString();

        $semesters = Semester::orderBy('start_date', 'desc')->get();
        // Solo items activos para poder asignarles una ventana
        $evidenceItems = EvidenceItem::where('active', true)
            ->get()
            ->sortBy([
                fn (EvidenceItem $item) => $this->flowService->stageOrder($item->name),
                fn (EvidenceItem $item) => $item->name,
            ])
            ->map(fn (EvidenceItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'stage_order' => $this->flowService->stageOrder($item->name),
                'stage_label' => $this->flowService->stageLabel($this->flowService->stageOrder($item->name)),
            ])
            ->values();

        return Inertia::render('Admin/Windows/Index', [
            'windows' => $windows,
            'semesters' => $semesters,
            'evidenceItems' => $evidenceItems,
            'modalities' => $this->modalityOptions(),
            'selectedSemester' => $semesterId,
            'selectedStatus' => $status,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'evidence_item_id' => 'required_without:evidence_item_ids|nullable|exists:evidence_items,id',
            'evidence_item_ids' => 'required_without:evidence_item_id|nullable|array|min:1',
            'evidence_item_ids.*' => 'integer|distinct|exists:evidence_items,id',
            'modality' => ['nullable', 'string', Rule::in([TeachingLoad::MODALITY_PRESENCIAL, TeachingLoad::MODALITY_EN_LINEA])],
            'opens_at' => 'required|date',
            'closes_at' => 'required|date|after_or_equal:opens_at',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $baseData = $this->normalizeWindowData([
            'semester_id' => $validated['semester_id'],
            'modality' => $validated['modality'] ?? null,
            'opens_at' => $validated['opens_at'],
            'closes_at' => $validated['closes_at'],
            'status' => $validated['status'],
        ]);

        $itemIds = collect($validated['evidence_item_ids'] ?? [$validated['evidence_item_id']])
            ->filter()
            ->unique()
            ->values();

        foreach ($itemIds as $itemId) {
            $this->ensureNoActiveWindowOverlap([
                ...$baseData,
                'evidence_item_id' => (int) $itemId,
            ]);
        }

        $windows = DB::transaction(function () use ($baseData, $itemIds) {
            return $itemIds->map(function ($itemId) use ($baseData) {
                $window = SubmissionWindow::create([
                    ...$baseData,
                    'evidence_item_id' => (int) $itemId,
                    'created_by_user_id' => Auth::id(),
                ]);

                $this->syncNotificationSchedules($window);

                return $window;
            });
        });

        $message = $windows->count() === 1
            ? 'Ventana de entrega creada correctamente.'
            : "Se crearon {$windows->count()} ventanas de entrega correctamente.";

        return redirect()->back()->with('success', $message);
    }

    public function update(Request $request, SubmissionWindow $window)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'evidence_item_id' => 'required|exists:evidence_items,id',
            'modality' => ['nullable', 'string', Rule::in([TeachingLoad::MODALITY_PRESENCIAL, TeachingLoad::MODALITY_EN_LINEA])],
            'opens_at' => 'required|date',
            'closes_at' => 'required|date|after_or_equal:opens_at',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);
        $validated = $this->normalizeWindowData($validated);

        $this->ensureNoActiveWindowOverlap($validated, $window->id);

        $window->update($validated);
        $this->syncNotificationSchedules($window->refresh());

        return redirect()->back()->with('success', 'Ventana de entrega actualizada correctamente.');
    }

    public function destroy(SubmissionWindow $window)
    {
        $window->delete();

        return redirect()->back()->with('success', 'Ventana de entrega eliminada correctamente.');
    }

    private function ensureNoActiveWindowOverlap(array $data, ?int $ignoreWindowId = null): void
    {
        if (($data['status'] ?? null) !== 'ACTIVE') {
            return;
        }

        $query = SubmissionWindow::query()
            ->where('semester_id', $data['semester_id'])
            ->where('evidence_item_id', $data['evidence_item_id'])
            ->where('modality', $data['modality'] ?? null)
            ->where('status', 'ACTIVE')
            ->where('opens_at', '<=', $data['closes_at'])
            ->where('closes_at', '>=', $data['opens_at']);

        if ($ignoreWindowId) {
            $query->where('id', '!=', $ignoreWindowId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'opens_at' => 'Ya existe una ventana activa que se solapa para el mismo semestre y evidencia.',
            ]);
        }
    }

    private function normalizeWindowData(array $data): array
    {
        $data['modality'] = ($data['modality'] ?? null) ?: null;
        $data['opens_at'] = CarbonImmutable::parse($data['opens_at'])->startOfDay();
        $data['closes_at'] = CarbonImmutable::parse($data['closes_at'])->endOfDay();

        return $data;
    }

    private function syncNotificationSchedules(SubmissionWindow $window): void
    {
        NotificationSchedule::query()
            ->where(function ($query) use ($window) {
                $query->where('submission_window_id', $window->id)
                    ->orWhere(function ($legacyQuery) use ($window) {
                        $legacyQuery
                            ->whereNull('submission_window_id')
                            ->where('semester_id', $window->semester_id)
                            ->where('evidence_item_id', $window->evidence_item_id);
                    });
            })
            ->where('is_sent', false)
            ->whereIn('notification_type', [
                NotificationType::WINDOW_OPEN->value,
                NotificationType::TASK_DUE_SOON->value,
                NotificationType::WINDOW_CLOSING->value,
            ])
            ->delete();

        $status = $window->status instanceof \BackedEnum
            ? $window->status->value
            : $window->status;

        if ($status !== 'ACTIVE') {
            return;
        }

        NotificationSchedule::create([
            'submission_window_id' => $window->id,
            'semester_id' => $window->semester_id,
            'evidence_item_id' => $window->evidence_item_id,
            'notify_at' => $window->opens_at,
            'notification_type' => NotificationType::WINDOW_OPEN,
            'is_sent' => false,
        ]);

        NotificationSchedule::create([
            'submission_window_id' => $window->id,
            'semester_id' => $window->semester_id,
            'evidence_item_id' => $window->evidence_item_id,
            'notify_at' => CarbonImmutable::parse($window->closes_at)->subDays(4),
            'notification_type' => NotificationType::TASK_DUE_SOON,
            'is_sent' => false,
        ]);

        NotificationSchedule::create([
            'submission_window_id' => $window->id,
            'semester_id' => $window->semester_id,
            'evidence_item_id' => $window->evidence_item_id,
            'notify_at' => CarbonImmutable::parse($window->closes_at)->subDays(3),
            'notification_type' => NotificationType::WINDOW_CLOSING,
            'is_sent' => false,
        ]);
    }

    private function applyOperationalStatusFilter($query, mixed $status): void
    {
        $now = now();

        match ($status) {
            'OPEN' => $query
                ->where('status', 'ACTIVE')
                ->where('opens_at', '<=', $now)
                ->where('closes_at', '>=', $now),
            'UPCOMING' => $query
                ->where('status', 'ACTIVE')
                ->where('opens_at', '>', $now),
            'EXPIRED' => $query
                ->where('status', 'ACTIVE')
                ->where('closes_at', '<', $now),
            'INACTIVE' => $query->where('status', 'INACTIVE'),
            default => null,
        };
    }

    private function modalityOptions(): array
    {
        return [
            ['value' => '', 'label' => 'General'],
            ['value' => TeachingLoad::MODALITY_PRESENCIAL, 'label' => 'Presencial'],
            ['value' => TeachingLoad::MODALITY_EN_LINEA, 'label' => 'Materia en linea'],
        ];
    }
}
