<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowFailed extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $workflowName,
        public readonly string $errorMessage,
        public readonly int $workflowId
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
            'type' => 'workflow_failed',
            'title' => "Workflow \"{$this->workflowName}\" failed",
            'body' => $this->errorMessage,
            'action_url' => "/workflows/{$this->workflowId}",
            'icon' => 'alert-triangle',
        ];
    }
}
