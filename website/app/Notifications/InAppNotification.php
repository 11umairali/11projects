<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app notification used by the workflow engine's send_notification
 * action and any other code path that needs to drop a row into the bell-icon
 * dropdown without crafting a dedicated Notification class.
 *
 * Stored via Laravel's database channel — appears in user.unreadNotifications
 * and is rendered by the header bell component.
 */
class InAppNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body = '',
        public readonly ?string $actionUrl = null,
        public readonly string $icon = 'bell',
        public readonly ?string $type = 'workflow',
        public readonly string $senderName = '',
        public readonly string $senderAvatar = '',
        public readonly ?int $messageId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'icon' => $this->icon,
            'sender_name' => $this->senderName,
            'sender_avatar' => $this->senderAvatar,
            'message_id' => $this->messageId,
        ];
    }
}
