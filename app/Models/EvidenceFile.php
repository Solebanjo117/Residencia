<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvidenceFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'submission_id',
        'folder_node_id',
        'file_name',
        'stored_relative_path',
        'mime_type',
        'size_bytes',
        'file_hash',
        'uploaded_at',
        'uploaded_by_user_id',
        'deleted_by_user_id',
        'deleted_at'
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(EvidenceSubmission::class, 'submission_id');
    }

    public function folderNode()
    {
        return $this->belongsTo(FolderNode::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
    
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}
