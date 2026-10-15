<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\ChannelIntegration;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Traits\AuthorizesApiActions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Real sending for the mobile app. The stock REST API (POST /conversations and
 * /conversations/{id}/reply) only stores rows and never delivers anything, so
 * these endpoints mirror what the website's Livewire ComposeEmail / ReplyComposer do:
 *
 *  - email: message is queued with scheduled_at = now()+10s (undo window) and the
 *    existing `SendScheduledEmails` scheduler command delivers it. Your cron
 *    (php artisan schedule:run every minute) must be running, exactly as for the website.
 *  - sms / whatsapp / telegram / slack / live_chat: delivered immediately through the
 *    same channel services the website uses.
 */
class SendController extends Controller
{
    use AuthorizesApiActions;

    private function wid(Request $r): int { return (int) $r->user()->active_workspace_id; }

    /** POST inbox/send  { channel, to, subject?, body, from_account_id?, cc?, bcc?, scheduled_at?, timezone? } */
    public function compose(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $data = $request->validate([
            'channel'         => 'required|string|in:email,sms,whatsapp,telegram,slack',
            'to'              => 'required|string|max:255',
            'subject'         => 'nullable|string|max:500',
            'body'            => 'required|string|min:1',
            'body_html'       => 'nullable|string',
            'attachments'     => 'nullable|array|max:10',
            'attachments.*'   => 'file|max:25600',
            'from_account_id' => 'nullable|integer',
            'cc'              => 'nullable|string|max:1000',
            'bcc'             => 'nullable|string|max:1000',
            'scheduled_at'    => 'nullable|string|max:40',
            'timezone'        => 'nullable|string|max:60',
        ]);

        $wid = $this->wid($request);
        $user = $request->user();

        try {
            // Plan limits (same service as the website).
            $limitKey = ['email' => 'emails_per_month', 'sms' => 'sms_per_month', 'whatsapp' => 'whatsapp_per_month'][$data['channel']] ?? null;
            if ($limitKey && class_exists(\App\Services\PlanLimitService::class)) {
                $ws = $user->activeWorkspace;
                if ($ws && !app(\App\Services\PlanLimitService::class)->canUse($ws, $limitKey)) {
                    return response()->json(['message' => 'You have reached your monthly limit for this channel. Please upgrade your plan.'], 422);
                }
            }

            return $data['channel'] === 'email'
                ? $this->composeEmail($request, $data, $wid)
                : $this->composeSocial($request, $data, $wid);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Mobile compose failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to send: ' . Str::limit($e->getMessage(), 200)], 500);
        }
    }

    private function composeEmail(Request $request, array $data, int $wid): JsonResponse
    {
        $to = trim($data['to']);
        if (preg_match('/<(.+?)>/', $to, $m)) $to = $m[1];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Enter a valid recipient email address.', 'errors' => ['to' => ['Invalid email address.']]], 422);
        }
        if (trim((string) ($data['subject'] ?? '')) === '') {
            return response()->json(['message' => 'Subject is required.', 'errors' => ['subject' => ['Subject is required.']]], 422);
        }

        $account = $data['from_account_id'] ?? null
            ? EmailAccount::where('workspace_id', $wid)->where('id', $data['from_account_id'])->first()
            : EmailAccount::where('workspace_id', $wid)->where('status', 'connected')->first();
        if (!$account) {
            return response()->json(['message' => 'No email account connected. Add one in Settings → Email accounts.'], 422);
        }

        $contact = Contact::firstOrCreate(
            ['workspace_id' => $wid, 'email' => $to],
            ['first_name' => explode('@', $to)[0], 'status' => 'active']
        );

        $bodyText = trim($data['body']);
        $bodyHtml = $this->html($data);

        $conversation = Conversation::create([
            'workspace_id'     => $wid,
            'contact_id'       => $contact->id,
            'email_account_id' => $account->id,
            'assigned_to'      => $request->user()->id,
            'channel'          => 'email',
            'status'           => 'open',
            'priority'         => 'normal',
            'subject'          => $data['subject'],
            'preview'          => Str::limit($bodyText, 200),
            'is_read'          => true,
            'messages_count'   => 1,
            'last_message_at'  => now(),
        ]);

        $msg = [
            'conversation_id'   => $conversation->id,
            'workspace_id'      => $wid,
            'direction'         => 'outbound',
            'sender_type'       => 'agent',
            'sender_id'         => $request->user()->id,
            'type'              => 'message',
            'subject'           => $data['subject'],
            'body_text'         => $bodyText,
            'body_html'         => $bodyHtml,
            'from_email'        => $account->email,
            'from_name'         => $account->display_name ?? $request->user()->name,
            'to_emails'         => [$to],
            'delivery_status'   => 'queued',
            'message_id_header' => '<' . Str::uuid() . '@' . parse_url(config('app.url'), PHP_URL_HOST) . '>',
        ];
        if (!empty($data['cc']))  $msg['cc_emails']  = array_values(array_filter(array_map('trim', explode(',', $data['cc']))));
        if (!empty($data['bcc'])) $msg['bcc_emails'] = array_values(array_filter(array_map('trim', explode(',', $data['bcc']))));

        $scheduled = !empty($data['scheduled_at']);
        if ($scheduled) {
            $msg['scheduled_at'] = Carbon::parse($data['scheduled_at'], $data['timezone'] ?? config('app.timezone', 'UTC'))->utc();
            $msg['schedule_status'] = 'pending';   // the scheduler delivers it at that time
        }

        $message = Message::create($msg);
        $this->storeAttachments($request, $message, $wid);
        $contact->update(['last_contacted_at' => now()]);

        if ($scheduled) {
            return response()->json(['message' => 'Email scheduled.', 'conversation_id' => $conversation->id, 'message_id' => $message->id, 'delivery_status' => 'queued'], 201);
        }

        // Send right now (no cron / queue worker needed).
        if ($err = $this->sendEmailNow($message, $account)) {
            return response()->json(['message' => 'Email could not be sent: ' . $err, 'conversation_id' => $conversation->id, 'message_id' => $message->id, 'delivery_status' => 'failed'], 422);
        }
        return response()->json(['message' => 'Email sent.', 'conversation_id' => $conversation->id, 'message_id' => $message->id, 'delivery_status' => 'sent'], 201);
    }

    private function composeSocial(Request $request, array $data, int $wid): JsonResponse
    {
        $channel = $data['channel'];
        $to = trim($data['to']);
        $body = trim($data['body']);

        if (in_array($channel, ['sms', 'whatsapp'], true)) {
            if (!preg_match('/^\+?[1-9]\d{6,14}$/', preg_replace('/[\s\-\(\)]/', '', $to))) {
                return response()->json(['message' => 'Enter a valid phone number (e.g. +1234567890).', 'errors' => ['to' => ['Invalid phone number.']]], 422);
            }
        }

        // Telegram @username → chat id, same lookup as the website.
        $channelConvId = null;
        if ($channel === 'telegram') {
            $channelConvId = $to;
            if (str_starts_with($to, '@') && !config('services.channel_test_mode')) {
                $u = ltrim($to, '@');
                $existing = Conversation::where('workspace_id', $wid)->where('channel', 'telegram')
                    ->whereNotNull('channel_conversation_id')
                    ->whereHas('contact', fn ($q) => $q->where('last_name', '@' . $u)->orWhere('first_name', $u)->orWhere('phone', $u))
                    ->first();
                if (!$existing) {
                    return response()->json(['message' => "Username @{$u} not found. The user must send /start to your Telegram bot first."], 422);
                }
                $channelConvId = $existing->channel_conversation_id;
            }
        } elseif ($channel === 'slack') {
            $channelConvId = $to;
        }

        $label = ['sms' => 'SMS Contact', 'whatsapp' => 'WhatsApp Contact', 'telegram' => 'Telegram User', 'slack' => 'Slack Channel'][$channel];
        $contact = Contact::firstOrCreate(['workspace_id' => $wid, 'phone' => $to], ['first_name' => $label, 'status' => 'active']);

        $conversation = Conversation::where('workspace_id', $wid)->where('contact_id', $contact->id)
            ->where('channel', $channel)->where('status', '!=', 'closed')->orderBy('last_message_at', 'desc')->first();

        if (!$conversation) {
            $conversation = Conversation::create(array_filter([
                'workspace_id'            => $wid,
                'contact_id'              => $contact->id,
                'assigned_to'             => $request->user()->id,
                'channel'                 => $channel,
                'channel_conversation_id' => $channelConvId,
                'status'                  => 'open',
                'priority'                => 'normal',
                'subject'                 => ucfirst($channel) . ' to ' . $to,
                'preview'                 => Str::limit($body, 200),
                'is_read'                 => true,
                'messages_count'          => 0,
                'last_message_at'         => now(),
            ], fn ($v) => $v !== null));
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'workspace_id'    => $wid,
            'direction'       => 'outbound',
            'sender_type'     => 'agent',
            'sender_id'       => $request->user()->id,
            'type'            => 'message',
            'body_text'       => $body,
            'delivery_status' => 'queued',
            'sent_at'         => now(),
        ]);

        $this->deliver($message, $conversation, $wid, $request->user()->name, $to);

        $conversation->update(['preview' => Str::limit($body, 200), 'last_message_at' => now(), 'is_read' => true]);
        $conversation->increment('messages_count');
        $contact->update(['last_contacted_at' => now()]);

        $message->refresh();
        if ($message->delivery_status === 'failed') {
            return response()->json(['message' => ucfirst($channel) . ' delivery failed: ' . ($message->delivery_error ?: 'check channel settings.'),
                'conversation_id' => $conversation->id], 422);
        }
        return response()->json([
            'message'         => ucfirst($channel) . ' message sent.' . (config('services.channel_test_mode') ? ' (test mode – not delivered)' : ''),
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
            'delivery_status' => $message->delivery_status,
        ], 201);
    }

    /** POST inbox/conversations/{id}/send  { body, body_html?, mode: reply|reply_all|forward|note, to?, cc?, bcc?, attachments[], scheduled_at?, timezone? } */
    public function reply(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $data = $request->validate([
            'body'          => 'required|string|min:1',
            'body_html'     => 'nullable|string',
            'mode'          => 'nullable|string|in:reply,reply_all,forward,note',
            'to'            => 'nullable|string|max:500',
            'cc'            => 'nullable|string|max:1000',
            'bcc'           => 'nullable|string|max:1000',
            'attachments'   => 'nullable|array|max:10',
            'attachments.*' => 'file|max:25600',
            'scheduled_at'  => 'nullable|string|max:40',
            'timezone'      => 'nullable|string|max:60',
        ]);

        $wid = $this->wid($request);
        $conversation = Conversation::where('workspace_id', $wid)->with(['contact', 'emailAccount'])->find($id);
        if (!$conversation) return response()->json(['message' => 'Conversation not found.'], 404);

        try {
            $mode = $data['mode'] ?? 'reply';
            $channel = $conversation->channel ?? 'email';
            $bodyText = trim($data['body']);
            $user = $request->user();

            if ($mode !== 'note' && $conversation->status === 'closed') $conversation->update(['status' => 'open']);

            $account = null;
            if ($channel === 'email' && $mode !== 'note') {
                $account = $conversation->emailAccount ?? EmailAccount::where('workspace_id', $wid)->where('status', 'connected')->first();
                if (!$account) return response()->json(['message' => 'No email account connected. Add one in Settings → Email accounts.'], 422);
            }

            $bodyHtml = $channel === 'email' ? $this->html($data) : '';
            $msg = [
                'conversation_id' => $conversation->id, 'workspace_id' => $wid,
                'direction' => 'outbound', 'sender_type' => 'agent', 'sender_id' => $user->id,
                'from_email' => $account->email ?? $user->email, 'from_name' => $account->display_name ?? $user->name,
                'body_text' => $bodyText, 'body_html' => $bodyHtml,
            ];

            if ($mode === 'note') {
                $msg['type'] = 'note';
            } else {
                $msg['type'] = 'message';
                $subject = $conversation->subject ?? '(no subject)';
                $last = $conversation->messages()->where('direction', 'inbound')->latest()->first();

                if ($mode === 'forward') {
                    $to = array_values(array_filter(array_map('trim', explode(',', (string) ($data['to'] ?? '')))));
                    if (!$to) return response()->json(['message' => 'Enter who to forward to.', 'errors' => ['to' => ['Required.']]], 422);
                    $msg['subject'] = preg_match('/^fwd:/i', $subject) ? $subject : 'Fwd: ' . $subject;
                    $msg['to_emails'] = $to;
                    $orig = $conversation->messages()->latest()->first();
                    if ($orig) {
                        $msg['body_html'] = $bodyHtml . '<br><br><div style="border-left:3px solid #ccc;padding-left:10px;color:#555">---------- Forwarded message ----------<br>'
                            . 'From: ' . e(($orig->from_name ?? '') . ' <' . ($orig->from_email ?? '') . '>') . '<br>Subject: ' . e($orig->subject ?? $subject) . '<br><br>'
                            . ($orig->body_html ? \App\Helpers\HtmlSanitizer::sanitize($orig->body_html) : nl2br(e((string) $orig->body_text))) . '</div>';
                    }
                } else {
                    $msg['subject'] = preg_match('/^re:/i', $subject) ? $subject : 'Re: ' . $subject;
                    $msg['to_emails'] = $conversation->contact?->email ? [$conversation->contact->email] : [];
                    if ($mode === 'reply_all' && $last) {
                        $self = strtolower((string) ($account->email ?? ''));
                        $primary = strtolower((string) ($conversation->contact?->email ?? ''));
                        $others = collect(array_merge((array) ($last->to_emails ?? []), (array) ($last->cc_emails ?? [])))
                            ->map(fn ($e) => strtolower(trim((string) $e)))->filter()->reject(fn ($e) => $e === $self || $e === $primary)->unique()->values()->all();
                        if ($others) $data['cc'] = trim(($data['cc'] ?? '') . ',' . implode(',', $others), ',');
                    }
                    if ($last && $last->message_id_header) {
                        $msg['in_reply_to'] = $last->message_id_header;
                        $refs = $last->references_header ?? [];
                        $refs[] = $last->message_id_header;
                        $msg['references_header'] = array_values(array_unique($refs));
                    }
                }
                if (!empty($data['cc']))  $msg['cc_emails']  = array_values(array_filter(array_map('trim', explode(',', $data['cc']))));
                if (!empty($data['bcc'])) $msg['bcc_emails'] = array_values(array_filter(array_map('trim', explode(',', $data['bcc']))));

                $msg['delivery_status'] = 'queued';
                if (!empty($data['scheduled_at'])) {
                    $msg['scheduled_at'] = Carbon::parse($data['scheduled_at'], $data['timezone'] ?? config('app.timezone', 'UTC'))->utc();
                    $msg['schedule_status'] = 'pending';
                } elseif ($channel !== 'email') {
                    $msg['sent_at'] = now();
                }
            }

            $message = Message::create($msg);
            $this->storeAttachments($request, $message, $wid);
            $conversation->update(['preview' => Str::limit($bodyText, 200), 'last_message_at' => now(), 'messages_count' => ($conversation->messages_count ?? 0) + 1]);

            if ($mode === 'note') return response()->json(['message' => 'Note added.', 'message_id' => $message->id, 'delivery_status' => null], 201);
            if (!empty($data['scheduled_at'])) return response()->json(['message' => 'Scheduled.', 'message_id' => $message->id, 'delivery_status' => 'queued'], 201);

            if ($channel === 'email') {
                if ($err = $this->sendEmailNow($message, $account)) {
                    return response()->json(['message' => 'Email could not be sent: ' . $err, 'message_id' => $message->id, 'delivery_status' => 'failed'], 422);
                }
                return response()->json(['message' => 'Email sent.', 'message_id' => $message->id, 'delivery_status' => 'sent'], 201);
            }

            $this->deliver($message, $conversation, $wid, $user->name, null);
            $message->refresh();
            if ($message->delivery_status === 'failed') {
                return response()->json(['message' => 'Delivery failed: ' . ($message->delivery_error ?: 'check channel settings.'), 'message_id' => $message->id], 422);
            }
            return response()->json(['message' => 'Reply sent.', 'message_id' => $message->id, 'delivery_status' => $message->delivery_status], 201);
        } catch (\Throwable $e) {
            Log::error('Mobile reply failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to send: ' . Str::limit($e->getMessage(), 200)], 500);
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────

    /** HTML body: sanitised rich text from the app, or plain text converted to HTML. */
    private function html(array $data): string
    {
        $h = trim((string) ($data['body_html'] ?? ''));
        if ($h !== '') return \App\Helpers\HtmlSanitizer::sanitize($h);
        return nl2br(e(trim($data['body'])));
    }

    /** Sends immediately through the website's own EmailSendService. Returns null on success or an error string. */
    private function sendEmailNow(Message $m, EmailAccount $account): ?string
    {
        try {
            app(\App\Services\Email\EmailSendService::class)->sendReply($m->fresh(['attachments', 'conversation']), $account);
            $m->update(['schedule_status' => null, 'delivery_status' => 'sent', 'delivery_error' => null, 'sent_at' => now()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Mobile email send failed', ['message_id' => $m->id, 'error' => $e->getMessage()]);
            $m->update(['schedule_status' => null, 'delivery_status' => 'failed', 'delivery_error' => Str::limit($e->getMessage(), 240)]);
            return Str::limit($e->getMessage(), 200);
        }
    }

    private function storeAttachments(Request $request, Message $m, int $wid): void
    {
        $files = $request->file('attachments', []);
        if (!is_array($files)) $files = [$files];
        foreach ($files as $file) {
            if (!$file || !$file->isValid()) continue;
            $name = $file->getClientOriginalName();
            $path = "attachments/{$wid}/" . time() . '_' . Str::random(8) . '_' . preg_replace('/[^\w.\- ]+/u', '_', $name);
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
            Attachment::create([
                'message_id' => $m->id, 'workspace_id' => $wid, 'filename' => basename($path), 'original_filename' => $name,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(),
                'storage_path' => $path, 'is_inline' => false,
            ]);
        }
    }

    /** GET inbox/attachments/{id}  – authenticated download of any attachment in the workspace (sent or received). */
    public function attachment(Request $request, int $id)
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $a = Attachment::where('id', $id)->whereHas('message', fn ($q) => $q->where('workspace_id', $this->wid($request)))->first();
        if (!$a || !$a->storage_path) return response()->json(['message' => 'Attachment not found.'], 404);

        foreach ([Storage::disk('local')->path($a->storage_path), Storage::disk('public')->path($a->storage_path), storage_path('app/' . $a->storage_path)] as $p) {
            if (is_file($p)) {
                return response()->download($p, $a->original_filename ?: $a->filename, ['Content-Type' => $a->mime_type ?: 'application/octet-stream']);
            }
        }
        return response()->json(['message' => 'The file is no longer on the server.'], 404);
    }

    /** Channel delivery – same services and credential keys as the website. Never throws. */
    private function deliver(Message $m, Conversation $c, int $wid, string $agentName, ?string $toOverride)
    {
        $channel = $c->channel;
        try {
            if (config('services.channel_test_mode')) {
                $m->update(['delivery_status' => 'sent', 'sent_at' => now()]);
                return;
            }
            $int = $channel === 'live_chat' ? null
                : ChannelIntegration::where('workspace_id', $wid)->where('channel', $channel)->where('status', 'active')->first();
            if ($channel !== 'live_chat' && !$int) {
                $m->update(['delivery_status' => 'failed', 'delivery_error' => ucfirst($channel) . ' not connected']);
                return;
            }
            $creds = $int?->credentials ?? [];
            $phone = $toOverride ?? $c->contact?->phone;
            $convId = $c->channel_conversation_id;

            switch ($channel) {
                case 'sms':
                    if (!$phone) return $this->fail($m, 'No phone number for contact');
                    $r = app(\App\Services\Channels\TwilioSMSService::class)->sendSMS($phone, $m->body_text, $creds['phone_number'] ?? null, $creds['sid'] ?? null, $creds['auth_token'] ?? null);
                    $m->update(['delivery_status' => 'sent', 'sent_at' => now(), 'channel_message_id' => $r->sid ?? null]);
                    break;
                case 'whatsapp':
                    if (!$phone) return $this->fail($m, 'No phone number for contact');
                    $r = app(\App\Services\Channels\WhatsAppService::class)->sendText($creds['phone_number_id'] ?? '', $creds['access_token'] ?? '', $phone, $m->body_text);
                    $m->update(['delivery_status' => 'sent', 'sent_at' => now(), 'channel_message_id' => $r['messages'][0]['id'] ?? null]);
                    break;
                case 'telegram':
                    if (!$convId) return $this->fail($m, 'No Telegram chat ID for this conversation');
                    $r = (new \App\Services\Channels\TelegramService($creds['bot_token'] ?? ''))->sendMessage($convId, $m->body_text);
                    $m->update(['delivery_status' => 'sent', 'sent_at' => now(), 'channel_message_id' => isset($r['message_id']) ? "tg_{$convId}_{$r['message_id']}" : null]);
                    break;
                case 'slack':
                    if (!$convId) return $this->fail($m, 'No Slack channel ID for this conversation');
                    app(\App\Services\Channels\SlackService::class)->postMessage($creds['access_token'] ?? $creds['bot_token'] ?? '', $convId, $m->body_text);
                    $m->update(['delivery_status' => 'sent', 'sent_at' => now()]);
                    break;
                case 'live_chat':
                    if (!$convId) return $this->fail($m, 'No live chat session ID for this conversation');
                    app(\App\Services\Channels\LiveChatService::class)->sendToVisitor($convId, $m->body_text, $agentName, 'agent');
                    $m->update(['delivery_status' => 'sent', 'sent_at' => now()]);
                    break;
                default:
                    $m->update(['delivery_status' => 'sent']);
            }
        } catch (\Throwable $e) {
            Log::error("Mobile {$channel} delivery failed", ['error' => $e->getMessage(), 'message_id' => $m->id]);
            $this->fail($m, $e->getMessage());
        }
    }

    private function fail(Message $m, string $why)
    {
        $m->update(['delivery_status' => 'failed', 'delivery_error' => Str::limit($why, 240)]);
    }
}
