<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewEmailReceived extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $senderName,
        public readonly string $subject,
        public readonly int $conversationId
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_email',
            'sender_name' => $this->senderName,
            'title' => "New email from {$this->senderName}",
            'body' => $this->subject,
            'action_url' => "/inbox?conversation={$this->conversationId}",
            'icon' => 'mail',
        ];
    }
}
