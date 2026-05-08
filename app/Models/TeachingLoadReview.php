<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeachingLoadReview extends Model
{
    protected $fillable = [
        'teaching_load_id',
        'reviewed_by_user_id',
        'decision',
        'comments',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function teachingLoad()
    {
        return $this->belongsTo(TeachingLoad::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
