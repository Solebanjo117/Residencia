<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_id',
        'evidence_item_id',
        'notify_at',
        'notification_type',
        'is_sent'
    ];

    protected $casts = [
        'notify_at' => 'datetime',
        'notification_type' => NotificationType::class,
        'is_sent' => 'boolean',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function evidenceItem()
    {
        return $this->belongsTo(EvidenceItem::class);
    }
}
