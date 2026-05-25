<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\SubmissionWindow;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyWindows extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:windows';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks notification schedules and dispatches alerts to teachers regarding submission windows';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startedAt = microtime(true);
        $this->info('Starting Notification Job...');

        $now = now();

        Log::channel('operations')->info('notify_windows.started', [
            'command' => $this->getName(),
            'executed_at' => $now->toIso8601String(),
        ]);

        $schedules = DB::table('notification_schedules')
            ->where('is_sent', false)
            ->where('notify_at', '<=', $now)
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No pending notifications to resolve.');

            Log::channel('operations')->info('notify_windows.completed', [
                'command' => $this->getName(),
                'due_schedules' => 0,
                'processed_schedules' => 0,
                'notifications_created' => 0,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return;
        }

        $this->info("Found {$schedules->count()} schedules due for dispatch.");

        $processedSchedules = 0;
        $createdNotifications = 0;

        foreach ($schedules as $schedule) {
            $scheduleStartedAt = microtime(true);

            try {
                $window = $this->resolveWindow($schedule);

                if (! $window) {
                    $this->markAsSent($schedule->id);

                    Log::channel('operations')->warning('notify_windows.window_not_found', [
                        'command' => $this->getName(),
                        'notification_schedule_id' => $schedule->id,
                        'submission_window_id' => $schedule->submission_window_id ?? null,
                        'semester_id' => $schedule->semester_id,
                        'evidence_item_id' => $schedule->evidence_item_id,
                        'notification_type' => $schedule->notification_type,
                        'duration_ms' => (int) round((microtime(true) - $scheduleStartedAt) * 1000),
                    ]);

                    continue;
                }

                $type = $schedule->notification_type;
                [$title, $message] = $this->notificationCopy($type, $window);
                $insertData = $this->notificationsForSchedule($schedule, $window, $type, $title, $message);

                if (! empty($insertData)) {
                    DB::table('notifications')->insert($insertData);
                }

                $this->markAsSent($schedule->id);
                $processedSchedules++;
                $createdNotifications += count($insertData);

                $this->info("Dispatched {$type} for Item #{$schedule->evidence_item_id} to ".count($insertData).' teachers.');

                Log::channel('operations')->info('notify_windows.schedule_dispatched', [
                    'command' => $this->getName(),
                    'notification_schedule_id' => $schedule->id,
                    'window_id' => $window->id,
                    'semester_id' => $schedule->semester_id,
                    'evidence_item_id' => $schedule->evidence_item_id,
                    'notification_type' => $type,
                    'teachers_notified' => count($insertData),
                    'duration_ms' => (int) round((microtime(true) - $scheduleStartedAt) * 1000),
                ]);
            } catch (Throwable $exception) {
                Log::channel('operations')->error('notify_windows.schedule_failed', [
                    'command' => $this->getName(),
                    'notification_schedule_id' => $schedule->id,
                    'submission_window_id' => $schedule->submission_window_id ?? null,
                    'semester_id' => $schedule->semester_id,
                    'evidence_item_id' => $schedule->evidence_item_id,
                    'notification_type' => $schedule->notification_type,
                    'error' => $exception->getMessage(),
                    'duration_ms' => (int) round((microtime(true) - $scheduleStartedAt) * 1000),
                ]);
            }
        }

        $this->info('Job Finished.');

        Log::channel('operations')->info('notify_windows.completed', [
            'command' => $this->getName(),
            'due_schedules' => $schedules->count(),
            'processed_schedules' => $processedSchedules,
            'notifications_created' => $createdNotifications,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function resolveWindow(object $schedule): ?SubmissionWindow
    {
        if (! empty($schedule->submission_window_id)) {
            return SubmissionWindow::with('evidenceItem')
                ->whereKey($schedule->submission_window_id)
                ->where('status', 'ACTIVE')
                ->first();
        }

        return SubmissionWindow::with('evidenceItem')
            ->where('semester_id', $schedule->semester_id)
            ->where('evidence_item_id', $schedule->evidence_item_id)
            ->where('status', 'ACTIVE')
            ->first();
    }

    private function notificationCopy(string $type, SubmissionWindow $window): array
    {
        if ($type === NotificationType::WINDOW_OPEN->value) {
            return [
                'Ventana de Recepcion Abierta',
                "El periodo para subir '{$window->evidenceItem->name}' ha comenzado y finalizara el ".Carbon::parse($window->closes_at)->format('d/m/Y h:i A').'.',
            ];
        }

        if ($type === NotificationType::TASK_DUE_SOON->value) {
            return [
                'Tarea por vencer',
                "Recordatorio: '{$window->evidenceItem->name}' vence el ".Carbon::parse($window->closes_at)->format('d/m/Y h:i A').'. Faltan 4 dias para entregar esta evidencia.',
            ];
        }

        return [
            'Cierre de Ventana Proximo',
            "Urgente: La recepcion para '{$window->evidenceItem->name}' terminara el ".Carbon::parse($window->closes_at)->format('d/m/Y h:i A').'. Por favor, asegurate de enviar tu evidencia.',
        ];
    }

    private function notificationsForSchedule(object $schedule, SubmissionWindow $window, string $type, string $title, string $message): array
    {
        $teachingLoads = DB::table('teaching_loads')
            ->where('semester_id', $schedule->semester_id)
            ->when($window->modality !== null, fn ($query) => $query->where('modality', $window->modality))
            ->select(['id', 'teacher_user_id'])
            ->get();

        $insertData = [];
        foreach ($teachingLoads as $load) {
            if ($this->loadAlreadySubmitted($schedule, $load->id, $type)) {
                continue;
            }

            $insertData[] = [
                'user_id' => $load->teacher_user_id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'related_entity_type' => SubmissionWindow::class,
                'related_entity_id' => $window->id,
                'action_context' => json_encode([
                    'semester_id' => (int) $schedule->semester_id,
                    'teaching_load_id' => (int) $load->id,
                    'evidence_item_id' => (int) $schedule->evidence_item_id,
                    'submission_window_id' => (int) $window->id,
                ]),
                'is_read' => false,
                'created_at' => now(),
            ];
        }

        return $insertData;
    }

    private function loadAlreadySubmitted(object $schedule, int $teachingLoadId, string $type): bool
    {
        if (! in_array($type, [NotificationType::TASK_DUE_SOON->value, NotificationType::WINDOW_CLOSING->value], true)) {
            return false;
        }

        return DB::table('evidence_submissions')
            ->where('semester_id', $schedule->semester_id)
            ->where('evidence_item_id', $schedule->evidence_item_id)
            ->where('teaching_load_id', $teachingLoadId)
            ->whereIn('status', ['SUBMITTED', 'APPROVED'])
            ->exists();
    }

    private function markAsSent($id)
    {
        DB::table('notification_schedules')->where('id', $id)->update(['is_sent' => true]);
    }
}
