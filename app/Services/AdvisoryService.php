<?php

namespace App\Services;

use App\Models\AdvisorySession;
use App\Models\TeachingLoad;
use App\Models\User;
use Carbon\Carbon;

class AdvisoryService
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function recordSession(TeachingLoad $load, User $creator, Carbon $date, string $topic, ?int $duration, ?string $notes)
    {
        $session = AdvisorySession::create([
            'teaching_load_id' => $load->id,
            'semester_id' => $load->semester_id,
            'session_date' => $date,
            'topic' => $topic,
            'duration_minutes' => $duration,
            'notes' => $notes,
            'created_by_user_id' => $creator->id,
            'created_at' => now(),
        ]);

        $this->auditService->log($creator, 'CREATE_ADVISORY', 'AdvisorySession', $session->id);

        return $session;
    }
}
