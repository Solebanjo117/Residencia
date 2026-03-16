<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeachingLoad extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'teacher_user_id',
        'semester_id',
        'subject_id',
        'group_code',
        'hours_per_week'
    ];

    // Expose 'group_name' as an alias for 'group_code' so all frontend/controllers
    // can use the friendlier name without a DB column rename.
    protected $appends = ['group_name'];

    public function getGroupNameAttribute(): ?string
    {
        return $this->group_code;
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function submissions()
    {
        return $this->hasMany(EvidenceSubmission::class);
    }

    public function advisorySessions()
    {
        return $this->hasMany(AdvisorySession::class);
    }

    public function advisorySchedules()
    {
        return $this->hasMany(AdvisorySchedule::class);
    }
}
