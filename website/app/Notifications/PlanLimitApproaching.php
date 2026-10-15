<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlanLimitApproaching extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $limitType,
        public readonly int $currentUsage,
        public readonly int $maxLimit
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
        $percentage = $this->maxLimit > 0
            ? round(($this->currentUsage / $this->maxLimit) * 100)
            : 0;

        return [
            'type' => 'plan_limit_approaching',
            'title' => ucfirst($this->limitType) . " limit at {$percentage}%",
            'body' => "{$this->currentUsage} of {$this->maxLimit} used",
            'action_url' => '/settings/billing',
            'icon' => 'alert-circle',
        ];
    }
}
