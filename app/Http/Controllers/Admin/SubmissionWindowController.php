<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvidenceItem;
use App\Models\Semester;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SubmissionWindowController extends Controller
{
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
        $evidenceItems = EvidenceItem::where('active', true)->orderBy('name')->get();

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
            'evidence_item_id' => 'required|exists:evidence_items,id',
            'modality' => ['nullable', 'string', Rule::in([TeachingLoad::MODALITY_PRESENCIAL, TeachingLoad::MODALITY_EN_LINEA])],
            'opens_at' => 'required|date',
            'closes_at' => 'required|date|after_or_equal:opens_at',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);
        $validated = $this->normalizeWindowData($validated);

        $this->ensureNoActiveWindowOverlap($validated);

        $validated['created_by_user_id'] = Auth::id();

        SubmissionWindow::create($validated);

        return redirect()->back()->with('success', 'Ventana de entrega creada correctamente.');
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
