<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndividualProject extends Model
{
    use HasFactory;

    public const TYPE_CAPACITACION = 'CAPACITACION';
    public const TYPE_ASESORIAS_DOCENTES = 'ASESORIAS_DOCENTES';
    public const TYPE_MATERIAL_DIDACTICO = 'MATERIAL_DIDACTICO';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'semester_id',
        'teacher_user_id',
        'type',
        'title',
        'folder_node_id',
        'docx_file_id',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'review_comment',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function folderNode()
    {
        return $this->belongsTo(FolderNode::class);
    }

    public function docxFile()
    {
        return $this->belongsTo(EvidenceFile::class, 'docx_file_id');
    }

    public function files()
    {
        return $this->hasMany(EvidenceFile::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function reviews()
    {
        return $this->hasMany(IndividualProjectReview::class);
    }

    public static function types(): array
    {
        return [
            self::TYPE_CAPACITACION => 'Capacitacion',
            self::TYPE_ASESORIAS_DOCENTES => 'Asesorias docentes',
            self::TYPE_MATERIAL_DIDACTICO => 'Material didactico',
        ];
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? $this->type;
    }
}
