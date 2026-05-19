<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SendScheduledNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startedAt = microtime(true);

        Log::channel('operations')->info('notifications.job.started', [
            'job' => self::class,
            'delegates_to' => 'notify:windows',
        ]);

        $exitCode = Artisan::call('notify:windows');

        Log::channel('operations')->info('notifications.job.completed', [
            'job' => self::class,
            'exit_code' => $exitCode,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
