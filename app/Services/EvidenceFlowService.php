<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EvidenceFlowService
{
    public function requirementsForDepartment(int $semesterId, ?int $departmentId): Collection
    {
        $requirements = EvidenceRequirement::with('evidenceItem')
            ->where('semester_id', $semesterId)
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id');

                if ($departmentId !== null) {
                    $query->orWhere('department_id', $departmentId);
                }
            })
            ->get();

        return $requirements
            ->sortBy([
                fn (EvidenceRequirement $requirement) => $requirement->department_id === $departmentId ? 0 : 1,
                fn (EvidenceRequirement $requirement) => $this->stageOrder($requirement->evidenceItem?->name),
                fn (EvidenceRequirement $requirement) => $requirement->evidenceItem?->name ?? '',
            ])
            ->unique('evidence_item_id')
            ->values();
    }

    public function stageOrder(?string $itemName): int
    {
        $name = $this->normalizeName($itemName);

        if ($name === '') {
            return 99;
        }

        if (str_contains($name, 'HORARIO') || str_contains($name, 'INSTRUM')) {
            return 0;
        }

        if (
            str_contains($name, 'ACTAS')
            || str_contains($name, 'REP FINAL')
            || str_contains($name, 'REPORTE FINAL')
            || str_contains($name, 'REPORTES EVIDENCIAS ASIGNATURAS')
            || (str_contains($name, 'EVIDENCIAS') && str_contains($name, 'ASIGNATUR'))
            || (str_contains($name, 'CALIF') && str_contains($name, 'FINAL'))
        ) {
            return 50;
        }

        if (str_contains($name, 'SEG 04') || str_contains($name, 'SD4')) {
            return 40;
        }

        if (str_contains($name, 'SEG 03') || str_contains($name, 'SD3') || str_contains($name, 'PARCIALES 3')) {
            return 30;
        }

        if (
            str_contains($name, 'SEG 02')
            || str_contains($name, 'SD2')
            || str_contains($name, 'PARCIALES 2')
            || str_contains($name, 'PROY IND')
            || str_contains($name, 'PROYECTOS INDIVIDUALES')
        ) {
            return 20;
        }

        if (
            str_contains($name, 'DIAGN')
            || str_contains($name, 'SEG 01')
            || str_contains($name, 'SD1')
            || str_contains($name, 'PARCIALES')
            || str_contains($name, 'ASESOR')
        ) {
            return 10;
        }

        return 10;
    }

    public function stageLabel(int $stage): string
    {
        return match ($stage) {
            0 => 'Etapa inicial',
            10 => 'SD1',
            20 => 'SD2',
            30 => 'SD3',
            40 => 'SD4',
            50 => 'Etapa final',
            default => $stage < 50 ? 'SD1' : 'Etapa final',
        };
    }

    public function isStageUnlocked(
        EvidenceRequirement $currentRequirement,
        Collection $requirements,
        Collection $submissionsByItem
    ): bool {
        $currentStage = $this->stageOrder($currentRequirement->evidenceItem?->name);

        if ($currentStage <= 0) {
            return true;
        }

        return ! $requirements
            ->filter(fn (EvidenceRequirement $requirement) => $this->stageOrder($requirement->evidenceItem?->name) < $currentStage)
            ->contains(function (EvidenceRequirement $requirement) use ($submissionsByItem) {
                $submission = $submissionsByItem->get($requirement->evidence_item_id);

                return ! $this->countsAsCompletedForProgress($submission);
            });
    }

    public function countsAsCompletedForProgress(?EvidenceSubmission $submission): bool
    {
        if (! $submission) {
            return false;
        }

        return in_array($submission->status, [
            SubmissionStatus::SUBMITTED,
            SubmissionStatus::APPROVED,
            SubmissionStatus::NA,
        ], true);
    }

    public function resolveWindowForLoad(Collection $windows, ?TeachingLoad $load): ?SubmissionWindow
    {
        if ($windows->isEmpty()) {
            return null;
        }

        return $windows->firstWhere('modality', $load?->modality)
            ?? $windows->firstWhere('modality', null)
            ?? $windows->first();
    }

    public function resolveAvailability(
        ?SubmissionWindow $window,
        bool $stageUnlocked,
        bool $hasUnlock = false,
        ?EvidenceSubmission $submission = null,
        bool $historical = false
    ): array {
        if ($submission?->status === SubmissionStatus::NA) {
            return [
                'code' => 'NA',
                'label' => 'No aplica',
                'is_available' => false,
                'is_late' => false,
                'is_future' => false,
                'tone' => 'slate',
            ];
        }

        if ($historical) {
            return [
                'code' => 'HISTORICAL',
                'label' => 'Bloqueado por semestre no activo',
                'is_available' => false,
                'is_late' => false,
                'is_future' => false,
                'tone' => 'blue',
            ];
        }

        if (! $stageUnlocked) {
            return [
                'code' => 'STAGE_LOCKED',
                'label' => 'Bloqueado por etapa previa',
                'is_available' => false,
                'is_late' => false,
                'is_future' => true,
                'tone' => 'slate',
            ];
        }

        $now = now();

        if ($hasUnlock) {
            $isLate = ! $window || $now->greaterThan($window->closes_at);

            return [
                'code' => 'UNLOCKED',
                'label' => $isLate ? 'Prorroga activa (extemporanea)' : 'Prorroga activa',
                'is_available' => true,
                'is_late' => $isLate,
                'is_future' => false,
                'tone' => 'amber',
            ];
        }

        if (! $window) {
            return [
                'code' => 'NOT_CONFIGURED',
                'label' => 'Sin ventana configurada',
                'is_available' => false,
                'is_late' => false,
                'is_future' => false,
                'tone' => 'red',
            ];
        }

        if ($now->lessThan($window->opens_at)) {
            return [
                'code' => 'UPCOMING',
                'label' => 'Bloqueado hasta apertura',
                'is_available' => false,
                'is_late' => false,
                'is_future' => true,
                'tone' => 'blue',
            ];
        }

        if ($now->lessThanOrEqualTo($window->closes_at)) {
            return [
                'code' => 'OPEN',
                'label' => 'Disponible en tiempo',
                'is_available' => true,
                'is_late' => false,
                'is_future' => false,
                'tone' => 'green',
            ];
        }

        return [
            'code' => 'LATE',
            'label' => 'Disponible extemporaneamente',
            'is_available' => true,
            'is_late' => true,
            'is_future' => false,
            'tone' => 'amber',
        ];
    }

    public function uiStatus(?EvidenceSubmission $submission, array $availability): string
    {
        if ($submission?->manual_ui_status) {
            return $submission->manual_ui_status;
        }

        if ($submission?->status === SubmissionStatus::NA) {
            return 'NA';
        }

        if ($submission?->status === SubmissionStatus::APPROVED) {
            return $submission->final_approved_at ? 'VF' : 'AO';
        }

        if ($submission?->status === SubmissionStatus::REJECTED) {
            return 'R';
        }

        if ($submission?->status === SubmissionStatus::SUBMITTED) {
            return 'PA';
        }

        if ($submission?->status === SubmissionStatus::DRAFT) {
            return $availability['is_available'] ? 'PA' : 'BL';
        }

        if ($submission?->status === SubmissionStatus::NE) {
            return 'NE';
        }

        if (! $submission && in_array($availability['code'], ['UPCOMING', 'STAGE_LOCKED'], true)) {
            return 'BL';
        }

        return 'NE';
    }

    private function normalizeName(?string $value): string
    {
        return Str::ascii(mb_strtoupper((string) $value));
    }
}
