<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\FcmPush;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Every in-app (database) notification that Laravel stores for a user is also pushed to that user's phones.
 * Respects the per-event preferences already saved on the account because those decide whether the notification is
 * created at all.
 */
class SendPushForNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || !($event->notifiable instanceof User)) return;

        $data = [];
        if (is_object($event->response) && isset($event->response->data) && is_array($event->response->data)) {
            $data = $event->response->data;
        } elseif (method_exists($event->notification, 'toArray')) {
            $data = (array) $event->notification->toArray($event->notifiable);
        }

        $title = (string) ($data['title'] ?? $data['subject'] ?? config('app.name'));
        $body  = (string) ($data['body'] ?? $data['message'] ?? '');

        $id = (string) ($event->response->id ?? $event->notification->id ?? '');
        \App\Services\RealtimeUpdates::users([$event->notifiable->id], ['type' => $data['type'] ?? 'notification', 'notification_id' => $id,
            'message_id' => $data['message_id'] ?? '', 'title' => $title]);

        // Keep long broadcast email content in the notification center; mobile pushes have a 4 KB envelope.
        if (str_starts_with((string) ($data['type'] ?? ''), 'announcement_')) {
            $title = \Illuminate\Support\Str::limit($title, 80);
            $body = \Illuminate\Support\Str::limit($body, 240);
        }

        // Email sends directly after persistence, like chat. Do not send twice.
        if ($event->notification instanceof \App\Notifications\InAppNotification && !$event->notification->pushViaDatabaseEvent) return;

        app(FcmPush::class)->sendToUser($event->notifiable, $title, $body, [
            'notification_id' => $id,
            'created_at_iso' => ($event->response->created_at ?? null)?->toIso8601String() ?? now()->toIso8601String(),
            'message_id' => $data['message_id'] ?? '',
            'type'       => $data['type'] ?? 'notification',
            'action_url' => $data['action_url'] ?? '',
            'sender_name' => $data['sender_name'] ?? '',
            'sender_avatar' => $data['sender_avatar'] ?? '',
        ]);
    }
}
