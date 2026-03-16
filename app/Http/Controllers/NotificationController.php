<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * Get unread notifications for the banner/bell.
     */
    public function getUnread(Request $request)
    {
        $user = Auth::user();

        $notifications = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'count' => $unreadCount,
            'notifications' => $notifications
        ]);
    }

    /**
     * Mark a single notification or all as read.
     */
    public function markAsRead(Request $request, $id = null)
    {
        $user = Auth::user();

        if ($id) {
            Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
        } else {
            // Mark all
            Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);
        }

        return response()->json(['success' => true]);
    }
}
