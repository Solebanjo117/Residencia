<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvidenceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'requires_subject',
        'active'
    ];
    public $timestamps = false;

    public function category()
    {
        return $this->belongsTo(EvidenceCategory::class, 'category_id');
    }

    public function formats()
    {
        return $this->belongsToMany(EvidenceFormat::class, 'evidence_item_formats');
    }
}
