<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvidenceFile extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'previous_version_file_id',
        'root_file_id',
        'folder_node_id',
        'file_name',
        'stored_relative_path',
        'mime_type',
        'size_bytes',
        'file_hash',
        'uploaded_at',
        'last_edited_at',
        'last_edited_by_user_id',
        'editor_source',
        'editor_meta',
        'is_current_version',
        'uploaded_by_user_id',
        'deleted_by_user_id',
        'deleted_at'
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'editor_meta' => 'array',
        'is_current_version' => 'boolean',
    ];

    public function submission()
    {
        return $this->belongsTo(EvidenceSubmission::class, 'submission_id');
    }

    public function folderNode()
    {
        return $this->belongsTo(FolderNode::class);
    }

    public function previousVersion()
    {
        return $this->belongsTo(self::class, 'previous_version_file_id')->withTrashed();
    }

    public function nextVersions()
    {
        return $this->hasMany(self::class, 'previous_version_file_id')->withTrashed();
    }

    public function rootFile()
    {
        return $this->belongsTo(self::class, 'root_file_id')->withTrashed();
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'last_edited_by_user_id');
    }
    
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function scopeCurrentVersion($query)
    {
        return $query->where('is_current_version', true);
    }

    public function isDocx(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        $extension = strtolower((string) pathinfo($this->file_name, PATHINFO_EXTENSION));

        return $extension === 'docx'
            || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            || $mime === 'application/zip';
    }

    public function versionRootId(): int
    {
        return (int) ($this->root_file_id ?: $this->id);
    }
}
