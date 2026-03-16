<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorageRoot extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'base_path', 'is_active'];
    public $timestamps = false;
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
