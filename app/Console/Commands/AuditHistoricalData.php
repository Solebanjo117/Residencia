<?php

namespace App\Console\Commands;

use App\Enums\ReviewDecision;
use App\Enums\SubmissionStatus;
use App\Enums\WindowStatus;
use App\Models\EvidenceFile;
use App\Models\EvidenceReview;
use App\Models\EvidenceStatusHistory;
use App\Models\EvidenceSubmission;
use App\Models\NotificationSchedule;
use App\Models\Role;
use App\Models\SubmissionWindow;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AuditHistoricalData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asad:audit-historical-data
        {--fix : Aplica correcciones seguras en inconsistencias reparables}
        {--strict-filesystem : Valida existencia fisica de archivos en disco local}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audita y sanea inconsistencias historicas en entregas, ventanas, schedules y archivos';

    /**
     * Valid state transitions map: from => [allowed destinations]
     */
    private const ALLOWED_TRANSITIONS = [
        'DRAFT' => ['SUBMITTED', 'NA', 'NE'],
        'SUBMITTED' => ['APPROVED', 'REJECTED', 'NA', 'NE'],
        'APPROVED' => [],
        'REJECTED' => ['SUBMITTED', 'NA', 'NE'],
        'NA' => ['DRAFT'],
        'NE' => ['DRAFT'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $applyFixes = (bool) $this->option('fix');
        $strictFilesystem = (bool) $this->option('strict-filesystem');

        $this->info('Iniciando auditoria de datos historicos (P4-01)...');

        $initialAudit = $this->runAudit($strictFilesystem);
        $this->renderAuditSummary($initialAudit, 'Hallazgos iniciales');

        if (!$applyFixes) {
            $totalIssues = $this->countIssues($initialAudit);
            if ($totalIssues > 0) {
                $this->warn("Se detectaron {$totalIssues} inconsistencias. Ejecuta con --fix para saneamiento automatico de casos reparables.");
                return self::FAILURE;
            }

            $this->info('No se detectaron inconsistencias.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->info('Aplicando correcciones seguras...');
        $fixes = $this->applyFixes($initialAudit);
        $this->renderFixSummary($fixes);

        $postFixAudit = $this->runAudit($strictFilesystem);
        $this->renderAuditSummary($postFixAudit, 'Hallazgos posteriores al saneamiento');

        $remainingIssues = $this->countIssues($postFixAudit);
        if ($remainingIssues > 0) {
            $this->warn("Persisten {$remainingIssues} inconsistencias no corregibles automaticamente. Requieren revision manual.");
            return self::FAILURE;
        }

        $this->info('Saneamiento completado sin inconsistencias remanentes.');
        return self::SUCCESS;
    }

    private function runAudit(bool $strictFilesystem): array
    {
        $issues = [
            'invalid_status_transitions' => [],
            'status_history_chain_breaks' => [],
            'history_status_mismatch' => [],
            'submitted_without_timestamp' => [],
            'terminal_status_without_review' => [],
            'overlapping_active_windows' => [],
            'orphan_due_schedules' => [],
            'duplicate_pending_schedules' => [],
            'file_path_outside_folder' => [],
            'missing_physical_files' => [],
        ];

        $this->detectSubmissionIssues($issues);
        $this->detectWindowOverlapIssues($issues);
        $this->detectNotificationScheduleIssues($issues);
        $this->detectFileIssues($issues, $strictFilesystem);

        return $issues;
    }

    private function detectSubmissionIssues(array &$issues): void
    {
        $submissions = EvidenceSubmission::query()
            ->with([
                'statusHistory' => fn ($query) => $query->orderBy('changed_at')->orderBy('id'),
                'reviews' => fn ($query) => $query->orderBy('reviewed_at')->orderBy('id'),
            ])
            ->get();

        foreach ($submissions as $submission) {
            $history = $submission->statusHistory;
            $previousNewStatus = null;

            foreach ($history as $entry) {
                $oldStatus = $entry->old_status->value;
                $newStatus = $entry->new_status->value;

                $allowedTargets = self::ALLOWED_TRANSITIONS[$oldStatus] ?? [];
                if (!in_array($newStatus, $allowedTargets, true)) {
                    $issues['invalid_status_transitions'][] = [
                        'submission_id' => $submission->id,
                        'history_id' => $entry->id,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ];
                }

                if ($previousNewStatus !== null && $oldStatus !== $previousNewStatus) {
                    $issues['status_history_chain_breaks'][] = [
                        'submission_id' => $submission->id,
                        'history_id' => $entry->id,
                        'expected_old_status' => $previousNewStatus,
                        'actual_old_status' => $oldStatus,
                    ];
                }

                $previousNewStatus = $newStatus;
            }

            $lastHistory = $history->last();
            if ($lastHistory && $lastHistory->new_status !== $submission->status) {
                $issues['history_status_mismatch'][] = [
                    'submission_id' => $submission->id,
                    'history_id' => $lastHistory->id,
                    'last_history_status' => $lastHistory->new_status->value,
                    'current_status' => $submission->status->value,
                    'changed_by_user_id' => $lastHistory->changed_by_user_id,
                ];
            }

            if ($submission->status === SubmissionStatus::SUBMITTED && $submission->submitted_at === null) {
                $issues['submitted_without_timestamp'][] = [
                    'submission_id' => $submission->id,
                ];
            }

            if (
                in_array($submission->status, [SubmissionStatus::APPROVED, SubmissionStatus::REJECTED], true)
                && $submission->reviews->isEmpty()
            ) {
                $issues['terminal_status_without_review'][] = [
                    'submission_id' => $submission->id,
                    'status' => $submission->status->value,
                ];
            }
        }
    }

    private function detectWindowOverlapIssues(array &$issues): void
    {
        $activeWindows = SubmissionWindow::query()
            ->where('status', WindowStatus::ACTIVE)
            ->orderBy('semester_id')
            ->orderBy('evidence_item_id')
            ->orderBy('opens_at')
            ->orderBy('id')
            ->get();

        $grouped = $activeWindows->groupBy(fn ($window) => $window->semester_id . '|' . $window->evidence_item_id);

        foreach ($grouped as $group) {
            $lastKeptWindow = null;

            foreach ($group as $window) {
                if ($lastKeptWindow && $window->opens_at->lte($lastKeptWindow->closes_at)) {
                    $issues['overlapping_active_windows'][] = [
                        'window_id' => $window->id,
                        'overlaps_with_window_id' => $lastKeptWindow->id,
                        'semester_id' => $window->semester_id,
                        'evidence_item_id' => $window->evidence_item_id,
                    ];
                    continue;
                }

                $lastKeptWindow = $window;
            }
        }
    }

    private function detectNotificationScheduleIssues(array &$issues): void
    {
        $pendingSchedules = NotificationSchedule::query()
            ->where('is_sent', false)
            ->orderBy('id')
            ->get();

        $activeWindowKeys = SubmissionWindow::query()
            ->where('status', WindowStatus::ACTIVE)
            ->select('semester_id', 'evidence_item_id')
            ->distinct()
            ->get()
            ->mapWithKeys(fn ($window) => [$window->semester_id . '|' . $window->evidence_item_id => true]);

        $now = now();
        foreach ($pendingSchedules as $schedule) {
            if ($schedule->notify_at->lte($now)) {
                $windowKey = $schedule->semester_id . '|' . $schedule->evidence_item_id;
                if (!$activeWindowKeys->has($windowKey)) {
                    $issues['orphan_due_schedules'][] = [
                        'schedule_id' => $schedule->id,
                    ];
                }
            }
        }

        $grouped = $pendingSchedules->groupBy(function ($schedule) {
            $notificationType = $schedule->notification_type instanceof \BackedEnum
                ? $schedule->notification_type->value
                : (string) $schedule->notification_type;

            return implode('|', [
                $schedule->semester_id,
                $schedule->evidence_item_id,
                $notificationType,
                $schedule->notify_at->format('Y-m-d H:i:s'),
            ]);
        });

        foreach ($grouped as $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $keepId = $group->first()->id;
            foreach ($group->skip(1) as $duplicate) {
                $issues['duplicate_pending_schedules'][] = [
                    'schedule_id' => $duplicate->id,
                    'keep_schedule_id' => $keepId,
                ];
            }
        }
    }

    private function detectFileIssues(array &$issues, bool $strictFilesystem): void
    {
        $files = EvidenceFile::query()
            ->with('folderNode:id,relative_path')
            ->get();

        $disk = Storage::disk('local');

        foreach ($files as $file) {
            if (!$file->folderNode) {
                continue;
            }

            $normalizedCurrentPath = $this->normalizePath($file->stored_relative_path);
            $normalizedFolderPath = trim($this->normalizePath($file->folderNode->relative_path), '/');

            if (
                $normalizedFolderPath !== ''
                && !str_starts_with($normalizedCurrentPath, $normalizedFolderPath . '/')
            ) {
                $issues['file_path_outside_folder'][] = [
                    'file_id' => $file->id,
                    'current_path' => $normalizedCurrentPath,
                    'target_path' => $this->targetPathForFolder($normalizedFolderPath, $normalizedCurrentPath),
                ];
            }

            if ($strictFilesystem && !$disk->exists($normalizedCurrentPath)) {
                $issues['missing_physical_files'][] = [
                    'file_id' => $file->id,
                    'path' => $normalizedCurrentPath,
                ];
            }
        }
    }

    private function applyFixes(array $audit): array
    {
        $fixes = [
            'submitted_timestamp_fixed' => 0,
            'missing_reviews_created' => 0,
            'history_status_synchronized' => 0,
            'overlapping_windows_deactivated' => 0,
            'orphan_due_schedules_marked_sent' => 0,
            'duplicate_schedules_marked_sent' => 0,
            'file_paths_corrected' => 0,
        ];

        $fixes['submitted_timestamp_fixed'] = $this->fixMissingSubmittedAt($audit['submitted_without_timestamp']);
        $fixes['missing_reviews_created'] = $this->fixMissingReviews($audit['terminal_status_without_review']);
        $fixes['history_status_synchronized'] = $this->fixHistoryStatusMismatch($audit['history_status_mismatch']);
        $fixes['overlapping_windows_deactivated'] = $this->fixOverlappingWindows($audit['overlapping_active_windows']);

        [$orphanSchedules, $duplicateSchedules] = $this->fixPendingSchedules(
            $audit['orphan_due_schedules'],
            $audit['duplicate_pending_schedules']
        );
        $fixes['orphan_due_schedules_marked_sent'] = $orphanSchedules;
        $fixes['duplicate_schedules_marked_sent'] = $duplicateSchedules;

        $fixes['file_paths_corrected'] = $this->fixFilePaths($audit['file_path_outside_folder']);

        return $fixes;
    }

    private function fixMissingSubmittedAt(array $issues): int
    {
        $submissionIds = collect($issues)->pluck('submission_id')->unique()->values();
        if ($submissionIds->isEmpty()) {
            return 0;
        }

        $submissions = EvidenceSubmission::query()
            ->whereIn('id', $submissionIds)
            ->with(['statusHistory' => fn ($query) => $query->orderBy('changed_at')->orderBy('id')])
            ->get();

        $fixed = 0;
        foreach ($submissions as $submission) {
            if ($submission->status !== SubmissionStatus::SUBMITTED || $submission->submitted_at !== null) {
                continue;
            }

            $submittedStatusEntry = $submission->statusHistory
                ->first(fn ($entry) => $entry->new_status === SubmissionStatus::SUBMITTED);

            $submittedAt = $submittedStatusEntry?->changed_at ?? $submission->last_updated_at ?? now();

            $submission->update([
                'submitted_at' => $submittedAt,
                'last_updated_at' => $submission->last_updated_at ?? now(),
            ]);

            $fixed++;
        }

        return $fixed;
    }

    private function fixMissingReviews(array $issues): int
    {
        $submissionIds = collect($issues)->pluck('submission_id')->unique()->values();
        if ($submissionIds->isEmpty()) {
            return 0;
        }

        $submissions = EvidenceSubmission::query()
            ->whereIn('id', $submissionIds)
            ->with([
                'reviews',
                'statusHistory' => fn ($query) => $query->orderBy('changed_at')->orderBy('id'),
            ])
            ->get();

        $fallbackReviewerId = $this->resolveFallbackReviewerId();
        $fixed = 0;

        foreach ($submissions as $submission) {
            if ($submission->reviews->isNotEmpty()) {
                continue;
            }

            if (!in_array($submission->status, [SubmissionStatus::APPROVED, SubmissionStatus::REJECTED], true)) {
                continue;
            }

            $decision = $submission->status === SubmissionStatus::APPROVED
                ? ReviewDecision::APPROVE
                : ReviewDecision::REJECT;

            $statusEntry = $submission->statusHistory
                ->filter(fn ($entry) => $entry->new_status === $submission->status)
                ->sortByDesc('changed_at')
                ->first();

            $reviewerId = $statusEntry?->changed_by_user_id
                ?? $fallbackReviewerId
                ?? $submission->teacher_user_id;

            EvidenceReview::create([
                'submission_id' => $submission->id,
                'reviewed_by_user_id' => $reviewerId,
                'decision' => $decision,
                'comments' => 'Registro generado por saneamiento historico (P4-01).',
                'reviewed_at' => $statusEntry?->changed_at ?? $submission->last_updated_at ?? now(),
            ]);

            $fixed++;
        }

        return $fixed;
    }

    private function fixHistoryStatusMismatch(array $issues): int
    {
        $submissionIds = collect($issues)->pluck('submission_id')->unique()->values();
        if ($submissionIds->isEmpty()) {
            return 0;
        }

        $submissions = EvidenceSubmission::query()
            ->whereIn('id', $submissionIds)
            ->with(['statusHistory' => fn ($query) => $query->orderBy('changed_at')->orderBy('id')])
            ->get()
            ->keyBy('id');

        $fallbackReviewerId = $this->resolveFallbackReviewerId();
        $fixed = 0;

        foreach ($issues as $issue) {
            $submission = $submissions->get($issue['submission_id'] ?? null);
            if (!$submission) {
                continue;
            }

            $lastEntry = $submission->statusHistory->last();
            if (!$lastEntry || $lastEntry->new_status === $submission->status) {
                continue;
            }

            $from = $lastEntry->new_status->value;
            $to = $submission->status->value;

            if (!$this->isAllowedTransition($from, $to)) {
                continue;
            }

            $changedByUserId = $lastEntry->changed_by_user_id
                ?? $fallbackReviewerId
                ?? $submission->teacher_user_id;

            EvidenceStatusHistory::create([
                'submission_id' => $submission->id,
                'old_status' => SubmissionStatus::from($from),
                'new_status' => SubmissionStatus::from($to),
                'changed_by_user_id' => $changedByUserId,
                'change_reason' => 'Saneamiento historico: sincronizacion con estado actual.',
                'changed_at' => $submission->last_updated_at ?? now(),
            ]);

            $fixed++;
        }

        return $fixed;
    }

    private function fixOverlappingWindows(array $issues): int
    {
        $windowIds = collect($issues)->pluck('window_id')->unique()->values();
        if ($windowIds->isEmpty()) {
            return 0;
        }

        return SubmissionWindow::query()
            ->whereIn('id', $windowIds)
            ->update(['status' => WindowStatus::INACTIVE]);
    }

    private function fixPendingSchedules(array $orphanIssues, array $duplicateIssues): array
    {
        $orphanIds = collect($orphanIssues)->pluck('schedule_id')->unique()->values();
        $duplicateIds = collect($duplicateIssues)
            ->pluck('schedule_id')
            ->unique()
            ->diff($orphanIds)
            ->values();

        $orphanFixed = 0;
        if ($orphanIds->isNotEmpty()) {
            $orphanFixed = NotificationSchedule::query()
                ->whereIn('id', $orphanIds)
                ->update(['is_sent' => true]);
        }

        $duplicateFixed = 0;
        if ($duplicateIds->isNotEmpty()) {
            $duplicateFixed = NotificationSchedule::query()
                ->whereIn('id', $duplicateIds)
                ->update(['is_sent' => true]);
        }

        return [$orphanFixed, $duplicateFixed];
    }

    private function fixFilePaths(array $issues): int
    {
        $filesById = EvidenceFile::query()
            ->whereIn('id', collect($issues)->pluck('file_id')->unique()->values())
            ->get()
            ->keyBy('id');

        if ($filesById->isEmpty()) {
            return 0;
        }

        $disk = Storage::disk('local');
        $fixed = 0;

        foreach ($issues as $issue) {
            $file = $filesById->get($issue['file_id'] ?? null);
            if (!$file) {
                continue;
            }

            $currentPath = $this->normalizePath($file->stored_relative_path);
            $targetPath = $this->normalizePath($issue['target_path'] ?? '');

            if ($targetPath === '' || $targetPath === $currentPath) {
                continue;
            }

            $moved = false;
            if ($disk->exists($currentPath)) {
                if (!$disk->exists($targetPath)) {
                    $disk->copy($currentPath, $targetPath);
                }
                $disk->delete($currentPath);
                $moved = true;
            } elseif ($disk->exists($targetPath)) {
                $moved = true;
            }

            if (!$moved) {
                continue;
            }

            $file->update(['stored_relative_path' => $targetPath]);
            $fixed++;
        }

        return $fixed;
    }

    private function resolveFallbackReviewerId(): ?int
    {
        $officeRoleId = Role::query()->where('name', Role::JEFE_OFICINA)->value('id');
        if (!$officeRoleId) {
            return null;
        }

        return User::query()->where('role_id', $officeRoleId)->value('id');
    }

    private function isAllowedTransition(string $from, string $to): bool
    {
        $allowedTargets = self::ALLOWED_TRANSITIONS[$from] ?? [];
        return in_array($to, $allowedTargets, true);
    }

    private function normalizePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }

    private function targetPathForFolder(string $normalizedFolderPath, string $normalizedCurrentPath): string
    {
        $fileName = basename($normalizedCurrentPath);
        return trim($normalizedFolderPath . '/' . $fileName, '/');
    }

    private function renderAuditSummary(array $audit, string $title): void
    {
        $rows = [
            ['Transiciones invalidas de historial', count($audit['invalid_status_transitions']), 'No'],
            ['Rupturas en cadena de historial', count($audit['status_history_chain_breaks']), 'No'],
            ['Estado actual desalineado con historial', count($audit['history_status_mismatch']), 'Parcial'],
            ['SUBMITTED sin submitted_at', count($audit['submitted_without_timestamp']), 'Si'],
            ['APPROVED/REJECTED sin revision', count($audit['terminal_status_without_review']), 'Si'],
            ['Ventanas activas solapadas', count($audit['overlapping_active_windows']), 'Si'],
            ['Schedules vencidos sin ventana activa', count($audit['orphan_due_schedules']), 'Si'],
            ['Schedules pendientes duplicados', count($audit['duplicate_pending_schedules']), 'Si'],
            ['Rutas de archivo fuera de carpeta', count($audit['file_path_outside_folder']), 'Si'],
            ['Archivos fisicos no encontrados', count($audit['missing_physical_files']), 'No'],
        ];

        $this->line('');
        $this->info($title);
        $this->table(['Tipo', 'Cantidad', 'Corregible'], $rows);
        $this->line('Total inconsistencias: ' . $this->countIssues($audit));
    }

    private function renderFixSummary(array $fixes): void
    {
        $rows = [
            ['submitted_at completados', $fixes['submitted_timestamp_fixed']],
            ['Revisiones sinteticas creadas', $fixes['missing_reviews_created']],
            ['Historial sincronizado', $fixes['history_status_synchronized']],
            ['Ventanas desactivadas', $fixes['overlapping_windows_deactivated']],
            ['Schedules huerfanos marcados enviados', $fixes['orphan_due_schedules_marked_sent']],
            ['Schedules duplicados marcados enviados', $fixes['duplicate_schedules_marked_sent']],
            ['Rutas de archivo corregidas', $fixes['file_paths_corrected']],
        ];

        $this->table(['Correccion aplicada', 'Registros'], $rows);
    }

    private function countIssues(array $audit): int
    {
        return collect($audit)
            ->map(fn ($items) => is_countable($items) ? count($items) : 0)
            ->sum();
    }
}
