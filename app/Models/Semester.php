<?php

namespace App\Models;

use App\Enums\SemesterStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['name', 'start_date', 'end_date', 'status', 'academic_period_id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => SemesterStatus::class,
    ];

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function teachingLoads()
    {
        return $this->hasMany(TeachingLoad::class);
    }

    public function requirements()
    {
        return $this->hasMany(EvidenceRequirement::class);
    }

    public function submissionWindows()
    {
        return $this->hasMany(SubmissionWindow::class);
    }
}
