<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormatPublicationFile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'format_publication_id',
        'file_name',
        'stored_relative_path',
        'mime_type',
        'size_bytes',
        'file_hash',
        'is_current',
        'uploaded_by_user_id',
        'uploaded_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'uploaded_at' => 'datetime',
    ];

    public function publication()
    {
        return $this->belongsTo(FormatPublication::class, 'format_publication_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}

