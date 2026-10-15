<?php

namespace App\Observers;

use App\Helpers\HtmlSanitizer;
use App\Models\Message;

/**
 * Sanitizes HTML content on every write to the messages table.
 *
 * The Message model stores two body columns:
 *
 *  - body_html  — rich HTML from email clients, AI drafts, or user composition.
 *  - body_text  — plain-text fallback.
 *
 * body_html is the primary XSS vector because it is rendered with {!! !!} in
 * Blade views (via the safe_body_html accessor).  Sanitizing at the model
 * level ensures that no code path — controller, Livewire action, queue job,
 * artisan command, or seeder — can persist unsanitized HTML.
 *
 * body_text is also cleaned of null bytes and control characters to prevent
 * log injection and terminal escape-sequence attacks when displayed in
 * plain-text contexts.
 *
 * Registered in AppServiceProvider::boot().
 */
class MessageObserver
{
    /**
     * Handle the Message "creating" event.
     */
    public function creating(Message $message): void
    {
        $this->sanitizeBody($message);
    }

    /**
     * Handle the Message "updating" event.
     */
    public function updating(Message $message): void
    {
        // Only re-sanitize if body columns actually changed
        if ($message->isDirty('body_html') || $message->isDirty('body_text')) {
            $this->sanitizeBody($message);
        }
    }

    /**
     * Run the sanitizer on both body columns.
     */
    protected function sanitizeBody(Message $message): void
    {
        // Sanitize HTML body through the whitelist-based DOMDocument sanitizer
        if ($message->body_html !== null && $message->body_html !== '') {
            $message->body_html = HtmlSanitizer::sanitize($message->body_html);
        }

        // Strip null bytes and non-printable control characters from plain text.
        // Preserve \n, \r, \t which are valid in text content.
        if ($message->body_text !== null && $message->body_text !== '') {
            $message->body_text = preg_replace(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                '',
                $message->body_text
            );
        }
    }
}
