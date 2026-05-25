<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormatPublication extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $fillable = [
        'evidence_item_id',
        'title',
        'body',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
        'current_format_publication_file_id',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function evidenceItem()
    {
        return $this->belongsTo(EvidenceItem::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function files()
    {
        return $this->hasMany(FormatPublicationFile::class);
    }

    public function currentFile()
    {
        return $this->belongsTo(FormatPublicationFile::class, 'current_format_publication_file_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}

