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

class SendScheduledNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        $due = NotificationSchedule::where('is_sent', false)
            ->where('notify_at', '<=', now())
            ->with(['semester', 'evidenceItem'])
            ->get();

        foreach ($due as $schedule) {
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
            }

            $schedule->update(['is_sent' => true]);
        }
    }
}
