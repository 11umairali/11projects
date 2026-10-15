<?php
namespace App\Observers;

use App\Models\Message;
use App\Services\Email\MessageAlerts;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Covers SMTP, scheduled sends, provider sync and persisted delivery callbacks. */
class MessageNotificationObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Message $message): void { app(MessageAlerts::class)->changed($message, true); }
    public function updated(Message $message): void
    {
        if ($message->wasChanged(['delivery_status', 'body_text', 'body_html', 'deleted_at'])) {
            app(MessageAlerts::class)->changed($message, false);
        }
    }
    public function deleted(Message $message): void { app(MessageAlerts::class)->changed($message, false); }
}
