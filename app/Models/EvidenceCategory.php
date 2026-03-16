<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvidenceCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];
    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(EvidenceItem::class, 'category_id');
    }
}
