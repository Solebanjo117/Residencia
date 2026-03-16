<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\EvidenceItem;
use App\Models\Notification;
use App\Models\NotificationSchedule;
use App\Models\Semester;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    public function notifyImmediate(User $to, NotificationType $type, string $title, string $message, $relatedEntity = null)
    {
        return Notification::create([
            'user_id' => $to->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_entity_type' => $relatedEntity ? get_class($relatedEntity) : null,
            'related_entity_id' => $relatedEntity ? $relatedEntity->id : null,
            'created_at' => now(),
        ]);
    }

    public function schedule(Semester $semester, EvidenceItem $item, Carbon $date, NotificationType $type)
    {
        return NotificationSchedule::create([
            'semester_id' => $semester->id,
            'evidence_item_id' => $item->id,
            'notify_at' => $date,
            'notification_type' => $type,
            'is_sent' => false,
        ]);
    }
}
