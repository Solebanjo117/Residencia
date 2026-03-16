<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvisorySchedule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'teaching_load_id',
        'semester_id',
        'day_of_week',
        'start_time',
        'end_time',
        'location',
    ];

    public function teachingLoad()
    {
        return $this->belongsTo(TeachingLoad::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function getDayNameAttribute(): string
    {
        $days = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];
        return $days[$this->day_of_week] ?? '';
    }
}
