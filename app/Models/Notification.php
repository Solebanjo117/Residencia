<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'related_entity_type',
        'related_entity_id',
        'is_read',
        'read_at',
        'created_at' // explicitly allowing filling
    ];

    protected $casts = [
        'type' => NotificationType::class,
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];
    public $timestamps = false; // Using custom created_at

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Helper for polymorphic relation if needed
    public function relatedEntity()
    {
        // Assuming related_entity_type is the full class name or we use a morph map
        // For simplicity if it's just a string descriptor, we might not use morphTo directly
        // but let's assume standard Laravel polymorphic structure if applicable
        // However migration says varchar(60) not full class path usually. 
        // We might need a custom accessor or just leave it as string.
        // Let's leave it as property for now.
    }
}
