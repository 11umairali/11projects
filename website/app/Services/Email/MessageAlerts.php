<?php
namespace App\Services\Email;

use App\Models\Message;
use App\Models\Conversation;
use App\Models\EmailAccount;
use App\Models\User;
use App\Notifications\InAppNotification;
use App\Services\RealtimeUpdates;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessageAlerts
{
    public function changed(Message $message, bool $created): void
    {
        try {
            RealtimeUpdates::workspace((int) $message->workspace_id, ['type' => 'email', 'conversation_id' => (int) $message->conversation_id]);
            $conversation = Conversation::withoutGlobalScopes()->where('workspace_id', $message->workspace_id)->find($message->conversation_id);
            if (!$conversation || $conversation->channel !== 'email' || $message->type !== 'message') return;
            $account = EmailAccount::withoutGlobalScopes()->where('workspace_id', $message->workspace_id)->find($conversation->email_account_id);
            if (!$account) return;
            RealtimeUpdates::users([(int) $account->user_id], ['type' => 'email', 'conversation_id' => (int) $message->conversation_id]);
            $type = null;
            if ($created && $message->direction === 'inbound') {
                // Initial sync/backfill must not notify for years of historical email.
                if (!$account->last_synced_at || !$message->sent_at || $message->sent_at->lt($account->last_synced_at)) return;
                $bounce = preg_match('/^(mailer-daemon|postmaster)@/i', (string) $message->from_email)
                    && preg_match('/undeliver|delivery.*fail|delivery status notification.*failure|returned mail/i', (string) $message->subject);
                $type = $bounce ? 'email_bounced' : 'email_received';
            } elseif ($message->direction === 'outbound' && (!$created && $message->wasChanged('delivery_status'))) {
                $type = match ($message->delivery_status) {
                    'sent' => 'email_sent', 'delivered' => 'email_delivered', 'failed' => 'email_failed', 'bounced' => 'email_bounced', default => null,
                };
            }
            if (!$type) return;
            $recipient = User::find($message->direction === 'outbound' ? ($message->sender_id ?: $account->user_id) : $account->user_id);
            if (!$recipient) return;
            if (\Illuminate\Support\Facades\Schema::hasTable('email_notification_mutes') && \Illuminate\Support\Facades\DB::table('email_notification_mutes')->where('user_id', $recipient->id)->where('conversation_id', $conversation->id)->exists()) return;
            $prefs = $recipient->notification_preferences ?? [];
            if (($prefs['inAppNotifs'] ?? true) === false || ($prefs['events']['contactReply']['inApp'] ?? true) === false) return;
            $title = match ($type) {
                'email_sent' => 'Email accepted for sending', 'email_delivered' => 'Email delivered',
                'email_failed' => 'Email could not be sent', 'email_bounced' => 'Email delivery failed', default => 'New email',
            };
            $sender = $message->direction === 'inbound'
                ? User::where('email', $message->from_email)->first()
                : $recipient;
            $contact = $conversation->contact_id ? $conversation->contact : null;
            $senderName = $message->direction === 'inbound'
                ? (string) ($sender?->name ?: $contact?->full_name ?: $message->from_name ?: $message->from_email)
                : (string) $recipient->name;
            $avatar = $sender?->avatar_path ?: $contact?->avatar_path;
            $notification = new InAppNotification($title, Str::limit(implode(' · ', array_filter([trim((string) $message->subject), trim(strip_tags((string) ($message->body_text ?: $message->body_html)))])), 240), '/inbox?cid='.$conversation->id, 'mail', $type, $senderName, $avatar ? asset('storage/'.$avatar) : '', messageId: (int) $message->id, pushViaDatabaseEvent: false);
            // A stored bell entry is not proof that Firebase was called. Email uses
            // the same direct sender as chat, independent of event discovery/cache.
            $notification->id = (string) Str::uuid();
            $recipient->notify($notification);
            $accepted = app(\App\Services\FcmPush::class)->sendToUser($recipient, $title, $notification->body, [
                'notification_id' => $notification->id, 'message_id' => (string) $message->id,
                'type' => $type, 'action_url' => $notification->actionUrl,
                'sender_name' => $senderName, 'sender_avatar' => $notification->senderAvatar,
                'created_at_iso' => now()->toIso8601String(),
            ]);
            if (!$accepted) Log::warning('Email push was not accepted', ['account_id' => $account->id, 'message_id' => $message->id]);
        } catch (\Throwable $e) {
            // Notification/provider failures cannot turn a successful SMTP send into a retry.
            Log::warning('Message alert unavailable', ['message_id' => $message->id, 'exception' => get_class($e)]);
        }
    }
}
