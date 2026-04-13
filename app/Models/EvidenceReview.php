<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvidenceReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'reviewed_by_user_id',
        'decision',
        'stage',
        'comments',
        'reviewed_at'
    ];

    protected $casts = [
        'decision' => ReviewDecision::class,
        'reviewed_at' => 'datetime',
    ];

    public $timestamps = false;

    public const STAGE_OFFICE = 'OFFICE';
    public const STAGE_FINAL = 'FINAL';

    public function submission()
    {
        return $this->belongsTo(EvidenceSubmission::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
