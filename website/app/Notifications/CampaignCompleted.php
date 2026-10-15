<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CampaignCompleted extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $campaignName,
        public readonly int $recipientCount,
        public readonly int $campaignId
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
            'type' => 'campaign_completed',
            'title' => "Campaign \"{$this->campaignName}\" sent",
            'body' => "Delivered to {$this->recipientCount} recipients",
            'action_url' => "/campaigns/{$this->campaignId}/report",
            'icon' => 'send',
        ];
    }
}
