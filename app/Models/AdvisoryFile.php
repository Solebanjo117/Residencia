<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvisoryFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'advisory_session_id',
        'file_name',
        'stored_relative_path',
        'mime_type',
        'size_bytes',
        'uploaded_at',
        'uploaded_by_user_id'
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];
    public $timestamps = false;

    public function session()
    {
        return $this->belongsTo(AdvisorySession::class, 'advisory_session_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
