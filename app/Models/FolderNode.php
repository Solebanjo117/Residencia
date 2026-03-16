<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FolderNode extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // folder_nodes table has no updated_at column

    protected $fillable = [
        'parent_id',
        'storage_root_id',
        'name',
        'relative_path',
        'owner_user_id',
        'semester_id'
    ];

    public function parent()
    {
        return $this->belongsTo(FolderNode::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(FolderNode::class, 'parent_id');
    }

    public function root()
    {
        return $this->belongsTo(StorageRoot::class, 'storage_root_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
    
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function files()
    {
        return $this->hasMany(EvidenceFile::class, 'folder_node_id');
    }
}
