<?php

namespace App\Services\AI;

use App\Models\Message;

/**
 * Centralized "should AI reply to this message?" decision logic.
 *
 * Email is by far the noisiest channel — it gets bombarded with bounces,
 * auto-replies, vacation responders, marketing newsletters, system
 * notifications, and "do-not-reply" emails. Replying to ANY of those is
 * a bad UX (and at worst a feedback loop). This service is the single
 * place that decides "real human → REPLY" vs "noise → SKIP".
 *
 * Channel-aware: SMS / WhatsApp / Live Chat conversations don't have
 * mailing-list patterns the same way, so we apply lighter filters there.
 *
 * Returns a SkipDecision DTO with `reason` so the caller can log
 * exactly WHY a message was filtered out — invaluable when an admin
 * asks "why didn't the AI reply to this email?"
 */
class AIReplyFilters
{
    /**
     * From-address patterns that almost always mean "automated sender —
     * do not reply." Matched against the lowercased local part AND the
     * full address using str_contains.
     */
    private const NOREPLY_FROM_PATTERNS = [
        'noreply', 'no-reply', 'no_reply',
        'donotreply', 'do-not-reply', 'do_not_reply',
        'mailer-daemon', 'mailerdaemon', 'postmaster@',
        'bounce', 'bounces@',
        'nobody@', 'daemon@',
        'notification@', 'notifications@',
        'auto-confirm@', 'autoresponder@', 'auto-reply@',
        'system@', 'sysadmin@',
        'support-noreply', 'no-reply-',
        'mail-noreply',
    ];

    /**
     * Subject-line phrases that mean "this is an auto-generated message,
     * not a personal reply." Case-insensitive substring match.
     */
    private const AUTO_REPLY_SUBJECT_PATTERNS = [
        'out of office', 'out-of-office', 'ooo:',
        'auto-reply', 'auto reply', 'autoreply',
        'automatic reply', 'automatic response',
        'vacation response', 'on vacation', 'on holiday',
        'i am away', 'currently away', 'away from office',
    ];

    /**
     * Subject-line phrases for delivery failures and system bounces.
     */
    private const BOUNCE_SUBJECT_PATTERNS = [
        'delivery status notification', 'mail delivery failure',
        'undeliverable', 'undelivered', 'delivery failed',
        'returned mail', 'mail returned', 'failure notice',
        'delivery has failed', 'could not be delivered',
        'delayed delivery', 'mail system error',
        'message rejected', 'permanent error',
    ];

    /**
     * Subject-line markers for promotional / marketing emails.
     * NOTE: applied only when the channel-specific config asks for it,
     * since some businesses DO want AI to reply to promo questions.
     */
    private const PROMOTIONAL_SUBJECT_PATTERNS = [
        '[newsletter]', '[promotional]', '[ad]',
        'unsubscribe', 'sale ends', 'limited time',
        '% off', 'save now', 'special offer',
        'flash sale', 'last chance',
    ];

    /**
     * Decide whether the AI should reply to this message.
     *
     * @param Message     $message       The incoming message.
     * @param string      $channel       'email'|'whatsapp'|'sms'|'live_chat'|'telegram'
     * @param array<string,bool> $filters Per-channel filter flags. Defaults are
     *                                    permissive on non-email channels.
     *
     * @return array{skip: bool, reason: ?string} `skip=true` halts the reply.
     */
    public static function decide(Message $message, string $channel = 'email', array $filters = []): array
    {
        // Default filter set per channel. The settings UI overrides these.
        $defaults = match ($channel) {
            'email' => [
                'skip_noreply' => true,
                'skip_autoreply' => true,
                'skip_bounces' => true,
                'skip_promotional' => false,
                'skip_outbound' => true,
                'require_valid_from' => true,
            ],
            'whatsapp', 'sms' => [
                'skip_noreply' => false,
                'skip_autoreply' => false,
                'skip_bounces' => false,
                'skip_promotional' => false,
                'skip_outbound' => true,
                'require_valid_from' => false,
            ],
            'live_chat', 'telegram' => [
                'skip_noreply' => false,
                'skip_autoreply' => false,
                'skip_bounces' => false,
                'skip_promotional' => false,
                'skip_outbound' => true,
                'require_valid_from' => false,
            ],
            default => ['skip_outbound' => true],
        };

        $f = array_merge($defaults, $filters);

        // 1. Never reply to outbound messages — that would be replying to
        //    ourselves and would loop forever.
        if (($f['skip_outbound'] ?? true) && $message->direction === 'outbound') {
            return ['skip' => true, 'reason' => 'outbound_message'];
        }

        $fromEmail = strtolower(trim($message->from_email ?? ''));
        $fromName = strtolower(trim($message->from_name ?? ''));
        $subject = strtolower(trim($message->subject ?? ''));

        // 2. Email-only validation: from_email must look real.
        if ($f['require_valid_from'] ?? false) {
            if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                return ['skip' => true, 'reason' => 'invalid_from_address'];
            }
        }

        // 3. No-reply / system-sender pattern match.
        if ($f['skip_noreply'] ?? false) {
            foreach (self::NOREPLY_FROM_PATTERNS as $pattern) {
                if ($fromEmail !== '' && str_contains($fromEmail, $pattern)) {
                    return ['skip' => true, 'reason' => "noreply_pattern:{$pattern}"];
                }
            }
        }

        // 4. Auto-reply / out-of-office / vacation response detection.
        if ($f['skip_autoreply'] ?? false) {
            foreach (self::AUTO_REPLY_SUBJECT_PATTERNS as $pattern) {
                if ($subject !== '' && str_contains($subject, $pattern)) {
                    return ['skip' => true, 'reason' => "autoreply_subject:{$pattern}"];
                }
            }
            // "Auto-Submitted" header is the canonical signal but we don't
            // store raw headers; the from_name often contains "Auto-Reply"
            // in vacation responders, so check there too.
            if (str_contains($fromName, 'auto-reply') || str_contains($fromName, 'auto reply')) {
                return ['skip' => true, 'reason' => 'autoreply_from_name'];
            }
        }

        // 5. Delivery failure / bounce detection.
        if ($f['skip_bounces'] ?? false) {
            foreach (self::BOUNCE_SUBJECT_PATTERNS as $pattern) {
                if ($subject !== '' && str_contains($subject, $pattern)) {
                    return ['skip' => true, 'reason' => "bounce_subject:{$pattern}"];
                }
            }
        }

        // 6. Promotional / marketing email detection (opt-in).
        if ($f['skip_promotional'] ?? false) {
            foreach (self::PROMOTIONAL_SUBJECT_PATTERNS as $pattern) {
                if ($subject !== '' && str_contains($subject, $pattern)) {
                    return ['skip' => true, 'reason' => "promotional_subject:{$pattern}"];
                }
            }
        }

        // 7. Custom user-supplied blocklist patterns. Each entry is a plain
        //    string; matched against from_email + subject (lowercased).
        $customBlocklist = $f['custom_blocklist'] ?? [];
        if (is_array($customBlocklist) && !empty($customBlocklist)) {
            foreach ($customBlocklist as $needle) {
                $needle = strtolower(trim((string) $needle));
                if ($needle === '') continue;
                if ($fromEmail !== '' && str_contains($fromEmail, $needle)) {
                    return ['skip' => true, 'reason' => "custom_blocklist:{$needle}"];
                }
                if ($subject !== '' && str_contains($subject, $needle)) {
                    return ['skip' => true, 'reason' => "custom_blocklist_subject:{$needle}"];
                }
            }
        }

        return ['skip' => false, 'reason' => null];
    }
}
