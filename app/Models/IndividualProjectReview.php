<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndividualProjectReview extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'individual_project_id',
        'reviewed_by_user_id',
        'decision',
        'comments',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(IndividualProject::class, 'individual_project_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}

