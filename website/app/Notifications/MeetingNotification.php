<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** In-app notification for meeting invitations (bell on the website, notifications in the app). */
class MeetingNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $title, public readonly string $body, public readonly string $url, public readonly string $senderName = '', public readonly string $senderAvatar = '', public readonly string $type = 'friend_meeting') {}

    public static function invited(string $host, string $title, string $when, string $code, string $avatar = ''): self
    {
        return new self("{$host} invited you to “{$title}”", $when !== '' ? $when : 'Meeting invitation · open to check availability.', '/meet/' . $code, $host, $avatar);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        // type contains "friend" so the app files it with the chat notifications (sound + system notification)
        return ['type' => $this->type, 'title' => $this->title, 'body' => $this->body, 'action_url' => $this->url, 'icon' => 'video', 'sender_name' => $this->senderName, 'sender_avatar' => $this->senderAvatar];
    }
}
