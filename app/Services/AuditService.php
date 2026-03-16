<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    public function log(User $user, string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = [])
    {
        return AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'at' => now(),
            'metadata' => $metadata,
        ]);
    }
}
