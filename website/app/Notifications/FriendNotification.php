<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** In-app notification for the Friends feature (shown in the bell on the website and in the app). */
class FriendNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url = '/friends',
        public readonly string $senderName = '',
        public readonly string $senderAvatar = ''
    ) {}

    public static function suggestion(string $name, string $avatar = ''): self
    {
        return new self('friend_suggestion', "{$name} is on " . config('app.name'), 'You have their number saved. Open Friends to send a request.', '/friends', $name, $avatar);
    }

    public static function request(string $name, string $avatar = ''): self
    {
        return new self('friend_request', "{$name} sent you a friend request", 'Open Friends to accept or decline.', '/friends', $name, $avatar);
    }

    public static function accepted(string $name, string $avatar = ''): self
    {
        return new self('friend_accepted', "{$name} accepted your friend request", 'You can now message each other.', '/friends', $name, $avatar);
    }

    public static function declined(string $name, string $avatar = ''): self
    {
        return new self('friend_declined', "{$name} rejected your friend request", 'You can send another friend request.', '/friends', $name, $avatar);
    }

    public static function verifyPhone(): self
    {
        return new self('friend_verify', 'Verify your phone number', 'Verify it so friends who saved your number can find you.', '/settings/phone');
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'action_url' => $this->url, 'icon' => 'users', 'sender_name' => $this->senderName, 'sender_avatar' => $this->senderAvatar];
    }
}
