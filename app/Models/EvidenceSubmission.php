<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvidenceSubmission extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'semester_id',
        'teacher_user_id',
        'evidence_item_id',
        'teaching_load_id',
        'status',
        'submitted_at',
        'submitted_late',
        'office_reviewed_at',
        'office_reviewed_by_user_id',
        'final_approved_at',
        'final_approved_by_user_id',
        'last_updated_at'
    ];

    protected $casts = [
        'status' => SubmissionStatus::class,
        'submitted_at' => 'datetime',
        'submitted_late' => 'boolean',
        'office_reviewed_at' => 'datetime',
        'final_approved_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function evidenceItem()
    {
        return $this->belongsTo(EvidenceItem::class);
    }

    public function teachingLoad()
    {
        return $this->belongsTo(TeachingLoad::class);
    }

    public function files()
    {
        return $this->hasMany(EvidenceFile::class, 'submission_id')->currentVersion();
    }

    public function allFiles()
    {
        return $this->hasMany(EvidenceFile::class, 'submission_id');
    }

    public function reviews()
    {
        return $this->hasMany(EvidenceReview::class, 'submission_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(EvidenceStatusHistory::class, 'submission_id');
    }

    public function officeReviewer()
    {
        return $this->belongsTo(User::class, 'office_reviewed_by_user_id');
    }

    public function finalApprover()
    {
        return $this->belongsTo(User::class, 'final_approved_by_user_id');
    }

    public function activeResubmissionUnlock()
    {
        return $this->hasOne(ResubmissionUnlock::class, 'submission_id')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->latest('unlocked_at');
    }

    public function getHistory()
    {
        // Combine status history and file uploads/deletions
        $statusChanges = $this->statusHistory()->with('changedBy')->get()->map(function ($item) {
            return [
                'type' => 'status_change',
                'date' => $item->changed_at,
                'user' => $item->changedBy->name,
                'details' => "Estado cambiado de {$item->old_status->value} a {$item->new_status->value}",
                'reason' => $item->change_reason,
            ];
        });

        $files = $this->allFiles()->withTrashed()->with(['uploadedBy', 'editedBy', 'deletedBy'])->get()->map(function ($file) {
            $events = [];
            
            // Upload event
            $events[] = [
                'type' => 'file_upload',
                'date' => $file->uploaded_at,
                'user' => $file->uploadedBy->name,
                'details' => "Archivo subido: {$file->file_name} (" . $this->formatBytes($file->size_bytes) . ")",
                'file_id' => $file->id,
            ];

            if ($file->last_edited_at) {
                $events[] = [
                    'type' => 'file_edit',
                    'date' => $file->last_edited_at,
                    'user' => $file->editedBy?->name ?? $file->uploadedBy->name,
                    'details' => "Documento editado: {$file->file_name}",
                    'file_id' => $file->id,
                ];
            }

            // Delete event (if applicable)
            if ($file->deleted_at) {
                $events[] = [
                    'type' => 'file_delete',
                    'date' => $file->deleted_at,
                    'user' => $file->deletedBy?->name ?? 'Sistema',
                    'details' => "Archivo eliminado/reemplazado: {$file->file_name}",
                ];
            }

            return $events;
        })->flatten(1);

        return $statusChanges->merge($files)->sortByDesc('date')->values();
    }

    public function isOfficeApproved(): bool
    {
        return $this->status === SubmissionStatus::APPROVED && $this->office_reviewed_at !== null;
    }

    public function isFinalApproved(): bool
    {
        return $this->isOfficeApproved() && $this->final_approved_at !== null;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes === 0) return '0 B';
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), $precision) . ' ' . $sizes[$i];
    }
}
