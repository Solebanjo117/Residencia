<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\EvidenceItem;
use App\Models\Notification;
use App\Models\NotificationSchedule;
use App\Models\Semester;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notifyImmediate(User $to, NotificationType $type, string $title, string $message, $relatedEntity = null)
    {
        $notification = Notification::create([
            'user_id' => $to->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_entity_type' => $relatedEntity ? get_class($relatedEntity) : null,
            'related_entity_id' => $relatedEntity ? $relatedEntity->id : null,
            'created_at' => now(),
        ]);

        Log::channel('operations')->info('notifications.immediate_sent', [
            'notification_id' => $notification->id,
            'target_user_id' => $to->id,
            'type' => $type->value,
            'related_entity_type' => $relatedEntity ? get_class($relatedEntity) : null,
            'related_entity_id' => $relatedEntity?->id,
        ]);

        return $notification;
    }

    public function schedule(Semester $semester, EvidenceItem $item, Carbon $date, NotificationType $type)
    {
        $schedule = NotificationSchedule::create([
            'semester_id' => $semester->id,
            'evidence_item_id' => $item->id,
            'notify_at' => $date,
            'notification_type' => $type,
            'is_sent' => false,
        ]);

        Log::channel('operations')->info('notifications.scheduled', [
            'notification_schedule_id' => $schedule->id,
            'semester_id' => $semester->id,
            'evidence_item_id' => $item->id,
            'type' => $type->value,
            'notify_at' => $date->toIso8601String(),
        ]);

        return $schedule;
    }
}
