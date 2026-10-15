<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DealStageChanged extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $dealName,
        public readonly string $oldStage,
        public readonly string $newStage,
        public readonly int $dealId
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
            'type' => 'deal_stage_changed',
            'title' => "Deal \"{$this->dealName}\" moved",
            'body' => "{$this->oldStage} -> {$this->newStage}",
            'action_url' => "/deals/{$this->dealId}",
            'icon' => 'trending-up',
        ];
    }
}
