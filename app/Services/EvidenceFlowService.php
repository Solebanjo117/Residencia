<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceSubmission;
use App\Models\SubmissionWindow;
use Illuminate\Support\Collection;

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

        if (str_contains($name, 'HORARIO')) {
            return 0;
        }

        if (str_contains($name, 'INSTRUM')) {
            return 0;
        }

        if (str_contains($name, 'DIAGN')) {
            return 1;
        }

        if (str_contains($name, 'SEG 01')) {
            return 2;
        }

        if (str_contains($name, 'SEG 02') || str_contains($name, 'SD2')) {
            return 3;
        }

        if (str_contains($name, 'SEG 03')) {
            return 4;
        }

        if (str_contains($name, 'SEG 04') || str_contains($name, 'SD4') || str_contains($name, 'FINAL')) {
            return 5;
        }

        return 2;
    }

    public function stageLabel(int $stage): string
    {
        return match (true) {
            $stage <= 0 => 'Etapa 0',
            $stage === 1 => 'Etapa 1',
            default => 'Etapa ' . $stage,
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

        return !$requirements
            ->filter(fn (EvidenceRequirement $requirement) => $this->stageOrder($requirement->evidenceItem?->name) < $currentStage)
            ->contains(function (EvidenceRequirement $requirement) use ($submissionsByItem) {
                $submission = $submissionsByItem->get($requirement->evidence_item_id);

                return !$this->countsAsCompletedForProgress($submission);
            });
    }

    public function countsAsCompletedForProgress(?EvidenceSubmission $submission): bool
    {
        if (!$submission) {
            return false;
        }

        return in_array($submission->status, [
            SubmissionStatus::SUBMITTED,
            SubmissionStatus::APPROVED,
            SubmissionStatus::NA,
        ], true);
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

        if (!$stageUnlocked) {
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
            $isLate = !$window || $now->greaterThan($window->closes_at);

            return [
                'code' => 'UNLOCKED',
                'label' => $isLate ? 'Prorroga activa (extemporanea)' : 'Prorroga activa',
                'is_available' => true,
                'is_late' => $isLate,
                'is_future' => false,
                'tone' => 'amber',
            ];
        }

        if (!$window) {
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

        if (!$submission && in_array($availability['code'], ['UPCOMING', 'STAGE_LOCKED'], true)) {
            return 'BL';
        }

        return 'NE';
    }

    private function normalizeName(?string $value): string
    {
        $normalized = mb_strtoupper((string) $value);
        $normalized = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $normalized);

        return $normalized;
    }
}
