<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResubmissionUnlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'unlocked_by_user_id',
        'unlocked_at',
        'expires_at',
        'reason'
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    public $timestamps = false;

    public function submission()
    {
        return $this->belongsTo(EvidenceSubmission::class);
    }

    public function unlockedBy()
    {
        return $this->belongsTo(User::class, 'unlocked_by_user_id');
    }
}
