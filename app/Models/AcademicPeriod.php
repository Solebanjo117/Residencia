<?php

namespace App\Models;

use App\Enums\AcademicPeriodStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['name', 'code', 'start_date', 'end_date', 'status'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => AcademicPeriodStatus::class,
    ];

    public function semesters()
    {
        return $this->hasMany(Semester::class);
    }
}
