<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvidenceStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'evidence_status_history';
    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'old_status',
        'new_status',
        'changed_by_user_id',
        'change_reason',
        'changed_at'
    ];

    protected $casts = [
        'old_status' => SubmissionStatus::class,
        'new_status' => SubmissionStatus::class,
        'changed_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(EvidenceSubmission::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
