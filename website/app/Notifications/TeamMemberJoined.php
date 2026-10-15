<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TeamMemberJoined extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $memberName,
        public readonly string $role
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
            'type' => 'team_member_joined',
            'title' => "{$this->memberName} joined the team",
            'body' => 'Role: ' . ucfirst($this->role),
            'action_url' => '/settings/team',
            'icon' => 'user-plus',
        ];
    }
}
