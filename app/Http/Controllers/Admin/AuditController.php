<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\AuditLog;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')
            ->select(
                'id',
                'action',
                'entity_type',
                'entity_id',
                'at',
                'user_id'
            );

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                })
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('entity_type', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('at', 'desc')->limit(200)->get();

        // Transform to include user_name and user_email for frontend compatibility
        $logs->transform(function ($log) {
            $log->user_name = $log->user?->name;
            $log->user_email = $log->user?->email;
            return $log;
        });

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs
        ]);
    }
}
