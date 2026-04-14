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

    public function scopeOpen($query)
    {
        return $query
            ->where('status', SemesterStatus::OPEN->value)
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    public static function active(): ?self
    {
        return static::query()->open()->first();
    }

    public static function activeOrLatest(): ?self
    {
        return static::active()
            ?? static::query()
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();
    }

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
