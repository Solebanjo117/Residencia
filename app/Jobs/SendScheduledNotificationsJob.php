<?php

namespace App\Jobs;

use App\Models\NotificationSchedule;
use App\Models\TeachingLoad;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        $startedAt = microtime(true);

        $due = NotificationSchedule::where('is_sent', false)
            ->where('notify_at', '<=', now())
            ->with(['semester', 'evidenceItem'])
            ->get();

        Log::channel('operations')->info('notifications.job.started', [
            'job' => self::class,
            'due_schedules' => $due->count(),
        ]);

        $processedSchedules = 0;
        $sentNotifications = 0;

        foreach ($due as $schedule) {
            $scheduleStartedAt = microtime(true);

            try {
                // Notify all teachers in the relevant semester
                $teachers = User::whereHas('teachingLoads', function ($query) use ($schedule) {
                    $query->where('semester_id', $schedule->semester_id);
                })->get();

                foreach ($teachers as $teacher) {
                    $notificationService->notifyImmediate(
                        $teacher,
                        $schedule->notification_type,
                        "Scheduled Notification: " . $schedule->evidenceItem->name,
                        "Reminder for " . $schedule->evidenceItem->name . " in semester " . $schedule->semester->name,
                        $schedule
                    );

                    $sentNotifications++;
                }

                $schedule->update(['is_sent' => true]);
                $processedSchedules++;

                Log::channel('operations')->info('notifications.job.schedule_processed', [
                    'job' => self::class,
                    'notification_schedule_id' => $schedule->id,
                    'semester_id' => $schedule->semester_id,
                    'evidence_item_id' => $schedule->evidence_item_id,
                    'notification_type' => $schedule->notification_type->value,
                    'target_teachers' => $teachers->count(),
                    'duration_ms' => (int) round((microtime(true) - $scheduleStartedAt) * 1000),
                ]);
            } catch (\Throwable $exception) {
                Log::channel('operations')->error('notifications.job.schedule_failed', [
                    'job' => self::class,
                    'notification_schedule_id' => $schedule->id,
                    'semester_id' => $schedule->semester_id,
                    'evidence_item_id' => $schedule->evidence_item_id,
                    'notification_type' => $schedule->notification_type->value,
                    'error' => $exception->getMessage(),
                    'duration_ms' => (int) round((microtime(true) - $scheduleStartedAt) * 1000),
                ]);

                throw $exception;
            }
        }

        Log::channel('operations')->info('notifications.job.completed', [
            'job' => self::class,
            'processed_schedules' => $processedSchedules,
            'sent_notifications' => $sentNotifications,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
