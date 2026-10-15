<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Powers the bell-icon dropdown:
 *   GET  /notifications        → JSON list of recent notifications + unread count
 *   POST /notifications/read   → mark all unread as read
 *   POST /notifications/{id}/read → mark a single notification as read
 *   GET  /notifications/{id}/open → mark read + redirect to the notification's action_url
 */
class NotificationController extends Controller
{
    /**
     * Return the latest 20 notifications for the current user as JSON.
     * Used by the bell dropdown's wire:poll / fetch.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($n) use ($user) {
                $data = $n->data ?? [];
                $entry = null;
                $path = parse_url($data['action_url'] ?? '', PHP_URL_PATH) ?: '';
                if (preg_match('~^/meet/([a-zA-Z0-9-]+)$~', $path, $match)) {
                    $entry = app(\App\Services\Meetings\MeetingService::class)->entryState($user, $match[1]);
                    $data['body'] = $entry['label'];
                }
                return [
                    'id' => $n->id,
                    'message_id' => $data['message_id'] ?? null,
                    'recipient_user_id' => $user->id,
                    'session_entry' => $entry,
                    'title' => $data['title'] ?? 'Notification',
                    'body' => $data['body'] ?? '',
                    'action_url' => $data['action_url'] ?? null,
                    'icon' => $data['icon'] ?? 'bell',
                    'type' => $data['type'] ?? 'general',
                    'sender_name' => $data['sender_name'] ?? '',
                    'sender_avatar' => $data['sender_avatar'] ?? '',
                    'read' => $n->read_at !== null,
                    'created_at' => $n->created_at?->diffForHumans(),
                    'created_at_iso' => $n->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark notification as read and redirect to its action_url.
     * Used when a user clicks a notification item in the bell dropdown.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->first();

        if (!$notification) {
            return redirect('/dashboard');
        }

        $notification->markAsRead();
        $url = $notification->data['action_url'] ?? '/dashboard';

        return redirect($url);
    }
}
