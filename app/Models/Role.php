<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name'];
    public $timestamps = false; // Based on migration

    // Constants for role names
    const DOCENTE = 'DOCENTE';
    const JEFE_OFICINA = 'JEFE_OFICINA';
    const JEFE_DEPTO = 'JEFE_DEPTO';

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
