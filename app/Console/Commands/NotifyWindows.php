<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SubmissionWindow;
use App\Models\TeachingLoad;
use App\Models\User;
use Carbon\Carbon;

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

        // 1. Find all unsent schedules that are due
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

            // Find the active window this relates to
            $window = SubmissionWindow::with('evidenceItem')->where('semester_id', $schedule->semester_id)
                ->where('evidence_item_id', $schedule->evidence_item_id)
                ->where('status', 'ACTIVE')
                ->first();

            if (!$window) {
                $this->markAsSent($schedule->id);

                Log::channel('operations')->warning('notify_windows.window_not_found', [
                    'command' => $this->getName(),
                    'notification_schedule_id' => $schedule->id,
                    'semester_id' => $schedule->semester_id,
                    'evidence_item_id' => $schedule->evidence_item_id,
                    'notification_type' => $schedule->notification_type,
                    'duration_ms' => (int) round((microtime(true) - $scheduleStartedAt) * 1000),
                ]);

                continue;
            }

            $title = '';
            $message = '';
            $type = $schedule->notification_type; // 'WINDOW_OPEN' or 'WINDOW_CLOSING'

            if ($type === 'WINDOW_OPEN') {
                $title = "Ventana de Recepción Abierta";
                $message = "El periodo para subir '{$window->evidenceItem->name}' ha comenzado y finalizará el " . Carbon::parse($window->closes_at)->format('d/m/Y h:i A') . ".";
            } else {
                $title = "Cierre de Ventana Próximo";
                $message = "Urgente: La recepción para '{$window->evidenceItem->name}' terminará el " . Carbon::parse($window->closes_at)->format('d/m/Y h:i A') . ". Por favor, asegúrate de enviar tu evidencia.";
            }

            // Find all teachers associated with this semester
            $teacherIds = DB::table('teaching_loads')
                ->where('semester_id', $schedule->semester_id)
                ->pluck('teacher_user_id')
                ->unique();

            // Bulk Insert
            $insertData = [];
            foreach ($teacherIds as $tId) {
                // To avoid spamming closing notifications if they already submitted, we could check submissions here:
                if ($type === 'WINDOW_CLOSING') {
                    $hasSubmitted = DB::table('evidence_submissions')
                        ->where('semester_id', $schedule->semester_id)
                        ->where('evidence_item_id', $schedule->evidence_item_id)
                        ->where('teacher_user_id', $tId)
                        ->whereIn('status', ['SUBMITTED', 'APPROVED'])
                        ->exists();

                    if ($hasSubmitted) {
                        continue; // Teacher already delivered, no need to alert
                    }
                }

                $insertData[] = [
                    'user_id' => $tId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'related_entity_type' => 'App\Models\SubmissionWindow',
                    'related_entity_id' => $window->id,
                    'is_read' => false,
                    'created_at' => now(),
                ];
            }

            if (!empty($insertData)) {
                DB::table('notifications')->insert($insertData);
            }

            $this->markAsSent($schedule->id);
            $processedSchedules++;
            $createdNotifications += count($insertData);

            $this->info("Dispatched {$type} for Item #{$schedule->evidence_item_id} to " . count($insertData) . " teachers.");

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

    private function markAsSent($id)
    {
        DB::table('notification_schedules')->where('id', $id)->update(['is_sent' => true]);
    }
}
