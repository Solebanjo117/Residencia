<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubmissionWindow extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_id',
        'evidence_item_id',
        'opens_at',
        'closes_at',
        'created_by_user_id',
        'status',
    ];

    protected $casts = [
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'status' => \App\Enums\WindowStatus::class,
    ];

    public $timestamps = false; // By default defined in migration as created_at useCurrent()

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function evidenceItem()
    {
        return $this->belongsTo(EvidenceItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
