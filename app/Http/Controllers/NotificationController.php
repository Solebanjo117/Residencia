<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\EvidenceFile;
use App\Models\EvidenceSubmission;
use App\Models\Notification;
use App\Models\NotificationSchedule;
use App\Models\SubmissionWindow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            ->get()
            ->map(fn (Notification $notification) => $this->notificationPayload($notification, $user));

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'count' => $unreadCount,
            'notifications' => $notifications,
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
                    'read_at' => now(),
                ]);
        } else {
            // Mark all
            Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
        }

        return response()->json(['success' => true]);
    }

    private function notificationPayload(Notification $notification, User $user): array
    {
        $actionUrl = $this->resolveActionUrl($notification, $user);

        return [
            'id' => $notification->id,
            'type' => $notification->type instanceof \BackedEnum
                ? $notification->type->value
                : $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'is_read' => $notification->is_read,
            'created_at' => $notification->created_at,
            'action_url' => $actionUrl,
            'action_label' => $this->resolveActionLabel($notification, $user, $actionUrl),
        ];
    }

    private function resolveActionUrl(Notification $notification, User $user): ?string
    {
        if (! $notification->related_entity_type || ! $notification->related_entity_id) {
            return null;
        }

        if ($notification->related_entity_type === EvidenceSubmission::class) {
            $submission = EvidenceSubmission::with(['semester', 'teacher'])
                ->find($notification->related_entity_id);

            if (! $submission) {
                return null;
            }

            if ($user->isDocente()) {
                if ($this->isRejectedSubmissionNotification($notification)) {
                    return route('asesorias', [
                        'semester' => $submission->semester?->name,
                        'submission_id' => $submission->id,
                        'teaching_load_id' => $submission->teaching_load_id,
                        'evidence_item_id' => $submission->evidence_item_id,
                    ], false);
                }

                return route('docente.evidencias', [
                    'semester_id' => $submission->semester_id,
                    'teaching_load_id' => $submission->teaching_load_id,
                ], false);
            }

            if ($user->isJefeOficina() || $user->isJefeDepto()) {
                return $this->reviewDetailUrl($submission);
            }
        }

        if ($notification->related_entity_type === EvidenceFile::class) {
            $file = EvidenceFile::with('submission.semester')
                ->find($notification->related_entity_id);

            if (! $file) {
                return null;
            }

            if (($user->isJefeOficina() || $user->isJefeDepto()) && $file->submission) {
                return $this->reviewDetailUrl($file->submission, [
                    'focus_file_id' => $file->id,
                ]);
            }

            return route('files.download', $file->id, false);
        }

        if ($notification->related_entity_type === SubmissionWindow::class) {
            $window = SubmissionWindow::find($notification->related_entity_id);

            if (! $window) {
                return null;
            }

            return $user->isDocente()
                ? route('docente.evidencias', ['semester_id' => $window->semester_id], false)
                : route('admin.windows.index', ['semester_id' => $window->semester_id], false);
        }

        if ($notification->related_entity_type === NotificationSchedule::class) {
            $schedule = NotificationSchedule::find($notification->related_entity_id);

            if (! $schedule) {
                return null;
            }

            return $user->isDocente()
                ? route('docente.evidencias', ['semester_id' => $schedule->semester_id], false)
                : route('admin.windows.index', ['semester_id' => $schedule->semester_id], false);
        }

        return null;
    }

    private function resolveActionLabel(Notification $notification, User $user, ?string $actionUrl): ?string
    {
        if (! $actionUrl) {
            return null;
        }

        if ($notification->related_entity_type === EvidenceSubmission::class) {
            if ($user->isDocente() && $this->isRejectedSubmissionNotification($notification)) {
                return 'Corregir evidencia';
            }

            return $user->isDocente() ? 'Ver evidencia' : 'Revisar entrega';
        }

        if ($notification->related_entity_type === EvidenceFile::class) {
            return $user->isJefeOficina() || $user->isJefeDepto()
                ? 'Revisar entrega'
                : 'Abrir archivo';
        }

        return $user->isDocente() ? 'Ver mis evidencias' : 'Abrir ventana';
    }

    private function isRejectedSubmissionNotification(Notification $notification): bool
    {
        $type = $notification->type instanceof \BackedEnum
            ? $notification->type->value
            : $notification->type;

        return $type === NotificationType::SUBMISSION_REJECTED->value;
    }

    private function reviewDetailUrl(EvidenceSubmission $submission, array $extraQuery = []): string
    {
        return route('oficina.revisiones.show', [
            'submission' => $submission->teacher_user_id,
            'focus_submission_id' => $submission->id,
            'teaching_load_id' => $submission->teaching_load_id,
            'evidence_item_id' => $submission->evidence_item_id,
            ...$extraQuery,
        ], false);
    }
}
