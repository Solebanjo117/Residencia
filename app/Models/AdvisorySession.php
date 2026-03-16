<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvisorySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_load_id',
        'semester_id',
        'session_date',
        'topic',
        'duration_minutes',
        'notes',
        'created_by_user_id'
    ];

    protected $casts = [
        'session_date' => 'date',
    ];

    public function teachingLoad()
    {
        return $this->belongsTo(TeachingLoad::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function files()
    {
        return $this->hasMany(AdvisoryFile::class);
    }
}
