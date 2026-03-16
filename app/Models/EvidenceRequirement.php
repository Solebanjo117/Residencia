<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvidenceRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_id',
        'department_id',
        'evidence_item_id',
        'is_mandatory',
        'applies_condition'
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'applies_condition' => 'array',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function evidenceItem()
    {
        return $this->belongsTo(EvidenceItem::class);
    }
}
