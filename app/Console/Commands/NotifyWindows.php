<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
        $this->info('Starting Notification Job...');

        $now = now();

        // 1. Find all unsent schedules that are due
        $schedules = DB::table('notification_schedules')
            ->where('is_sent', false)
            ->where('notify_at', '<=', $now)
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No pending notifications to resolve.');
            return;
        }

        $this->info("Found {$schedules->count()} schedules due for dispatch.");

        foreach ($schedules as $schedule) {
            // Find the active window this relates to
            $window = SubmissionWindow::with('evidenceItem')->where('semester_id', $schedule->semester_id)
                ->where('evidence_item_id', $schedule->evidence_item_id)
                ->first();

            if (!$window) {
                $this->markAsSent($schedule->id);
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
                ->pluck('user_id')
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
            $this->info("Dispatched {$type} for Item #{$schedule->evidence_item_id} to " . count($insertData) . " teachers.");
        }

        $this->info('Job Finished.');
    }

    private function markAsSent($id)
    {
        DB::table('notification_schedules')->where('id', $id)->update(['is_sent' => true]);
    }
}
