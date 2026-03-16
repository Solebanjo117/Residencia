<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvidenceFormat extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'template_url', 'active'];
    public $timestamps = false;

    public function items()
    {
        return $this->belongsToMany(EvidenceItem::class, 'evidence_item_formats');
    }
}
