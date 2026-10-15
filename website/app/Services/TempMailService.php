<?php

namespace App\Services;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TempMailAddress;
use App\Models\TempMailDomain;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\ClientManager;

class TempMailService
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
    ) {}

    /**
     * Generate a new temporary email address.
     */
    public function generateAddress(
        Workspace $workspace,
        User $user,
        ?int $domainId = null,
        ?string $label = null
    ): TempMailAddress {
        // Check plan allows temp mail
        $this->planLimitService->assertFeatureEnabled($workspace, 'temp_mail');

        // Check address limit
        $currentCount = TempMailAddress::where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->count();

        $limit = $this->planLimitService->getLimit($workspace, 'temp_mail_addresses');
        if ($limit !== null && $currentCount >= $limit) {
            throw new PlanLimitReachedException(
                'temp_mail_addresses', $currentCount, $limit
            );
        }

        // Pick domain
        $domain = $domainId
            ? TempMailDomain::where('status', 'active')->findOrFail($domainId)
            : TempMailDomain::where('status', 'active')->inRandomOrder()->firstOrFail();

        // Generate unique local part
        $localPart = $this->generateLocalPart($domain);

        // Calculate lifetime from plan or domain default
        $lifetimeHours = $this->planLimitService->getLimit($workspace, 'temp_mail_lifetime_hours')
            ?? $domain->default_lifetime_hours
            ?? 24;

        // Create conversation for this address
        $conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'channel' => 'temp_mail',
            'subject' => "Temp Mail: {$localPart}@{$domain->domain}",
            'status' => 'open',
            'priority' => 'normal',
        ]);

        // Create address
        return TempMailAddress::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'temp_mail_domain_id' => $domain->id,
            'local_part' => $localPart,
            'full_address' => $domain->getFullAddress($localPart),
            'label' => $label,
            'conversation_id' => $conversation->id,
            'expires_at' => now()->addHours($lifetimeHours),
        ]);
    }

    /**
     * Sync incoming emails for a domain via IMAP.
     */
    public function syncDomain(TempMailDomain $domain): int
    {
        if (!$domain->isActive()) {
            Log::info('TempMail.service: domain inactive, skipping', [
                'domain' => $domain->domain,
                'status' => $domain->status,
            ]);
            return 0;
        }

        $newMessages = 0;

        try {
            Log::info('TempMail.service: connecting IMAP', [
                'domain' => $domain->domain,
                'host' => $domain->imap_host,
                'port' => $domain->imap_port,
                'encryption' => $domain->imap_encryption,
                'user' => $domain->imap_username,
            ]);

            $cm = new ClientManager();
            $client = $cm->make([
                'host' => $domain->imap_host,
                'port' => $domain->imap_port,
                'encryption' => $domain->imap_encryption === 'none' ? false : $domain->imap_encryption,
                'validate_cert' => false,
                'username' => $domain->imap_username,
                'password' => $domain->imap_password,
                'protocol' => 'imap',
            ]);

            $client->connect();
            Log::info('TempMail.service: IMAP connected', ['domain' => $domain->domain]);

            $folder = $client->getFolder('INBOX');

            // Get messages since last sync.
            // NOTE: setFetchAttachment() is NOT a method on WhereQuery in the
            // current webklex/php-imap release — it only exists as a static
            // toggle on the Config::set() level or via Message methods after
            // fetch. Calling it on the query throws "Method … not supported"
            // and aborts the whole sync. setFetchBody(true) is enough; bodies
            // and attachments come down together for the matched messages.
            $query = $folder->messages()->all();
            if ($domain->last_synced_uid) {
                $query = $folder->messages()->unseen();
            }

            $messages = $query->setFetchBody(true)->get();

            Log::info('TempMail.service: fetched messages from INBOX', [
                'domain' => $domain->domain,
                'fetched_count' => is_countable($messages) ? count($messages) : 0,
                'mode' => $domain->last_synced_uid ? 'unseen-only' : 'all',
            ]);

            // Snapshot the live temp addresses so the per-message log lines
            // can show the user exactly what's "expected" vs what arrived.
            $liveTargets = TempMailAddress::where('is_active', true)
                ->where('expires_at', '>', now())
                ->pluck('full_address')
                ->map(fn ($a) => strtolower($a))
                ->all();
            Log::info('TempMail.service: live temp addresses currently watching', [
                'count' => count($liveTargets),
                'addresses' => $liveTargets,
            ]);

            $msgIndex = 0;
            foreach ($messages as $imapMessage) {
                $msgIndex++;
                $msgSubject = $imapMessage->getSubject()?->toString();
                $msgFrom = $imapMessage->getFrom()[0]->mail ?? null;

                $toAddresses = $imapMessage->getTo() ?? [];
                $ccAddresses = method_exists($imapMessage, 'getCc') ? ($imapMessage->getCc() ?? []) : [];
                $bccAddresses = method_exists($imapMessage, 'getBcc') ? ($imapMessage->getBcc() ?? []) : [];
                // Headers Hostinger / cPanel / Postfix / etc. use to record the
                // ORIGINAL envelope recipient when a catch-all rewrites To: to
                // the catch-all mailbox. We check every common variant + parse
                // the `Received: ... for <xxx>` chain because that's where the
                // envelope recipient actually survives on most setups.
                $envelopeRecipients = [];
                $rawHeaders = '';
                try {
                    $hdrs = $imapMessage->getHeader();
                    if ($hdrs) {
                        foreach ([
                            'delivered-to', 'x-delivered-to',
                            'x-original-to', 'original-to',
                            'envelope-to', 'x-envelope-to',
                            'x-forwarded-to', 'x-rcpt-to',
                            'x-original-recipient',
                        ] as $h) {
                            $v = $hdrs->get($h);
                            if ($v) {
                                // Some headers can be multi-valued (e.g. multiple Delivered-To)
                                $vals = is_array($v) ? $v : [(string) $v];
                                foreach ($vals as $vv) {
                                    foreach (preg_split('/[\s,;]+/', (string) $vv) as $piece) {
                                        $piece = trim(strtolower(trim($piece)), "<>\"' \t");
                                        if (filter_var($piece, FILTER_VALIDATE_EMAIL)) {
                                            $envelopeRecipients[] = $piece;
                                        }
                                    }
                                }
                            }
                        }
                        // Also scan every Received: header for `for <…@…>` —
                        // this is where the true envelope recipient is recorded
                        // on the FIRST hop into the destination MX, before any
                        // catch-all rewriting changes the headers.
                        $rawHeaders = (string) $hdrs->raw;
                        if ($rawHeaders && preg_match_all('/\bfor\s*<([^>]+@[^>]+)>/i', $rawHeaders, $m)) {
                            foreach ($m[1] as $addr) {
                                $addr = strtolower(trim($addr));
                                if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                                    $envelopeRecipients[] = $addr;
                                }
                            }
                        }
                    }
                } catch (\Throwable $_) {}

                $envelopeRecipients = array_values(array_unique($envelopeRecipients));

                $toRaw = array_map(fn ($a) => strtolower($a->mail ?? (string) $a), is_array($toAddresses) ? $toAddresses : []);
                $ccRaw = array_map(fn ($a) => strtolower($a->mail ?? (string) $a), is_array($ccAddresses) ? $ccAddresses : []);
                $bccRaw = array_map(fn ($a) => strtolower($a->mail ?? (string) $a), is_array($bccAddresses) ? $bccAddresses : []);

                Log::info('TempMail.service: inspecting INBOX message', [
                    'idx' => $msgIndex,
                    'from' => $msgFrom,
                    'subject' => $msgSubject,
                    'to' => $toRaw,
                    'cc' => $ccRaw,
                    'bcc' => $bccRaw,
                    'envelope_recipients' => $envelopeRecipients,
                ]);

                // Final recipient set = To + Cc + Bcc + every envelope-recipient
                // header / Received-for parse. This is what we match against
                // live temp addresses.
                $allRecipients = array_values(array_unique(array_filter(array_merge(
                    $toRaw,
                    $ccRaw,
                    $bccRaw,
                    $envelopeRecipients
                ))));

                if (empty($allRecipients)) {
                    Log::warning('TempMail.service: message has zero recipient headers, skipping', [
                        'idx' => $msgIndex,
                        'subject' => $msgSubject,
                    ]);
                    continue;
                }

                $matched = false;
                foreach ($allRecipients as $toEmail) {
                    // Find matching active temp address
                    $tempAddress = TempMailAddress::where('full_address', $toEmail)
                        ->where('is_active', true)
                        ->where('expires_at', '>', now())
                        ->first();

                    if (!$tempAddress) {
                        Log::info('TempMail.service: recipient does not match any live temp address', [
                            'idx' => $msgIndex,
                            'recipient_tried' => $toEmail,
                            'live_addresses' => $liveTargets,
                            'subject' => $msgSubject,
                        ]);
                        continue;
                    }

                    Log::info('TempMail.service: matched temp address — will store', [
                        'idx' => $msgIndex,
                        'to' => $toEmail,
                        'temp_address_id' => $tempAddress->id,
                        'workspace_id' => $tempAddress->workspace_id,
                    ]);
                    $matched = true;

                    // Check if message already exists (dedup by message-id)
                    $messageIdHeader = $imapMessage->getMessageId()?->toString() ?? '';
                    if ($messageIdHeader && Message::where('message_id_header', $messageIdHeader)
                        ->where('workspace_id', $tempAddress->workspace_id)->exists()) {
                        continue;
                    }

                    // Extract message data
                    $fromArr = $imapMessage->getFrom();
                    $fromEmail = $fromArr[0]->mail ?? '';
                    $fromName = $fromArr[0]->personal ?? $fromEmail;
                    $subject = $imapMessage->getSubject()?->toString() ?? '(No Subject)';
                    $bodyHtml = $imapMessage->getHTMLBody() ?? '';
                    $bodyText = $imapMessage->getTextBody() ?? strip_tags($bodyHtml);
                    $date = $imapMessage->getDate()?->toDate() ?? now();

                    // Store as Message in the address's conversation.
                    // NOTE: `type` is the Message category enum
                    // (message|note|ai_draft|system_event) — inserting 'email'
                    // truncated silently and aborted the sync, which is why
                    // the domain was auto-flipping to "error" status. Use
                    // 'message'; the channel distinction lives on the
                    // conversation.channel column.
                    $msg = Message::create([
                        'conversation_id' => $tempAddress->conversation_id,
                        'workspace_id' => $tempAddress->workspace_id,
                        'type' => 'message',
                        'direction' => 'inbound',
                        'sender_type' => 'contact',
                        'from_email' => $fromEmail,
                        'from_name' => $fromName,
                        'to_emails' => [$toEmail],
                        'subject' => $subject,
                        'body_html' => $bodyHtml,
                        'body_text' => $bodyText,
                        'message_id_header' => $messageIdHeader,
                        'delivery_status' => 'delivered',
                        'created_at' => $date,
                    ]);

                    // Handle attachments
                    $attachments = $imapMessage->getAttachments();
                    foreach ($attachments as $attachment) {
                        $fileName = $attachment->getName() ?? 'attachment';
                        $path = "temp-mail-attachments/{$tempAddress->workspace_id}/" . Str::random(10) . '_' . $fileName;

                        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $attachment->getContent());

                        \App\Models\Attachment::create([
                            'message_id' => $msg->id,
                            'workspace_id' => $tempAddress->workspace_id,
                            'filename' => $fileName,
                            'original_filename' => $fileName,
                            'storage_path' => $path,
                            'size' => strlen($attachment->getContent()),
                            'mime_type' => $attachment->getMimeType() ?? 'application/octet-stream',
                        ]);
                    }

                    // Update address counters
                    $tempAddress->increment('messages_count');
                    $tempAddress->update(['last_received_at' => now()]);

                    // Mark conversation as having new messages.
                    // NOTE: don't pass DB::raw('messages_count + 1') through
                    // ->update() — Eloquent runs the model casts on every
                    // attribute and Conversation::$casts maps messages_count
                    // to 'integer'. Casting an Expression object to int
                    // throws "Object of class Illuminate\Database\Query\Expression
                    // could not be converted to int" and aborts the sync,
                    // which then auto-disconnects the domain. increment()
                    // emits the SQL directly without going through casts.
                    if ($tempAddress->conversation) {
                        $tempAddress->conversation->update(['last_message_at' => now()]);
                        $tempAddress->conversation->increment('messages_count');
                    }

                    $newMessages++;

                    // Mark as seen in IMAP
                    $imapMessage->setFlag('Seen');
                }

                if (! $matched) {
                    Log::info('TempMail.service: message had no recipient matching any live temp address — final verdict', [
                        'idx' => $msgIndex,
                        'subject' => $msgSubject,
                        'from' => $msgFrom,
                        'recipients_tried' => $allRecipients,
                        'live_addresses' => $liveTargets,
                    ]);
                }
            }

            // Update sync state
            $domain->update([
                'last_synced_at' => now(),
                'error_message' => null,
            ]);

            $client->disconnect();

        } catch (\Throwable $e) {
            Log::error("Temp mail sync failed for domain {$domain->domain}: " . $e->getMessage());
            $domain->update([
                'status' => 'error',
                'error_message' => Str::limit($e->getMessage(), 490),
            ]);
        }

        return $newMessages;
    }

    /**
     * Cleanup expired temp mail addresses.
     */
    public function cleanupExpired(): array
    {
        $expired = TempMailAddress::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->get();

        $addressesCleaned = 0;
        $messagesDeleted = 0;

        foreach ($expired as $address) {
            // Deactivate
            $address->update(['is_active' => false]);

            // Delete messages via conversation
            if ($address->conversation_id) {
                $msgCount = Message::where('conversation_id', $address->conversation_id)->count();
                Message::where('conversation_id', $address->conversation_id)->delete();
                Conversation::where('id', $address->conversation_id)->delete();
                $messagesDeleted += $msgCount;
            }

            $address->delete(); // soft delete
            $addressesCleaned++;
        }

        // Force-delete addresses soft-deleted more than 30 days ago
        $autoCleanupDays = (int) \App\Models\SystemSetting::get('temp_mail_auto_cleanup_days', 30);
        TempMailAddress::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays($autoCleanupDays))
            ->forceDelete();

        return ['addresses_cleaned' => $addressesCleaned, 'messages_deleted' => $messagesDeleted];
    }

    /**
     * Delete a specific address.
     */
    public function deleteAddress(TempMailAddress $address): void
    {
        $address->update(['is_active' => false]);

        if ($address->conversation_id) {
            Message::where('conversation_id', $address->conversation_id)->delete();
            Conversation::where('id', $address->conversation_id)->delete();
        }

        $address->delete();
    }

    /**
     * Get available domains for address generation.
     */
    public function getAvailableDomains(): \Illuminate\Support\Collection
    {
        return TempMailDomain::where('status', 'active')->orderBy('display_name')->get();
    }

    /**
     * Generate a unique random local part for a domain.
     */
    private function generateLocalPart(TempMailDomain $domain): string
    {
        $maxAttempts = 20;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $localPart = strtolower(Str::random(rand(8, 12)));

            // Ensure it's not blocked
            if ($domain->isLocalPartBlocked($localPart)) continue;

            // Ensure uniqueness
            $exists = TempMailAddress::where('temp_mail_domain_id', $domain->id)
                ->where('local_part', $localPart)
                ->exists();

            if (!$exists) return $localPart;
        }

        // Fallback: use UUID prefix
        return strtolower(substr(str_replace('-', '', Str::uuid()), 0, 12));
    }
}
