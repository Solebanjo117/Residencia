<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name'];
    public $timestamps = false; // Based on migration

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_department');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_department')
            ->whereHas('role', fn ($query) => $query->where('name', Role::DOCENTE));
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(EvidenceRequirement::class, 'department_id');
    }
}
