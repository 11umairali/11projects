<?php

namespace App\Services\Email;

use App\Mail\DynamicEmail;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Models\UsageRecord;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailSendService
{
    /**
     * Send a reply message using the appropriate provider.
     */
    public function sendReply(Message $replyMessage, EmailAccount $account): bool
    {
        return \Illuminate\Support\Facades\Cache::lock('email-send:'.$replyMessage->id, 300)->block(10, function () use ($replyMessage, $account) {
            $replyMessage->refresh();
            if (in_array($replyMessage->delivery_status, ['sent', 'delivered', 'read', 'bounced'], true)) return true;
            if ($replyMessage->scheduled_at && $replyMessage->schedule_status !== 'pending') return false;
            // The same mailbox must not receive separate To/Cc/Bcc deliveries.
            $seen = [];
            foreach (['to_emails', 'cc_emails', 'bcc_emails'] as $field) {
                $recipients = [];
                foreach ($replyMessage->$field ?? [] as $email) {
                    $email = trim($email);
                    $key = strtolower($email);
                    if ($key !== '' && !isset($seen[$key])) { $seen[$key] = true; $recipients[] = $email; }
                }
                $replyMessage->$field = $recipients;
            }
            return $this->deliverReply($replyMessage, $account);
        });
    }

    private function deliverReply(Message $replyMessage, EmailAccount $account): bool
    {
        try {
            // Open/click tracking (hidden pixel + rewritten links) is a strong spam
            // signal on one-to-one mail from a young domain, so it is OFF unless an
            // admin enables it: SystemSetting 'track_one_to_one_emails' = '1'.
            // Campaign emails use their own tracking and are not affected.
            $bodyHtml = $this->shouldTrackOneToOne()
                ? $this->injectTracking($replyMessage)
                : ($replyMessage->body_html ?? '');

            $result = match ($account->provider) {
                'imap', 'custom' => $this->sendViaSMTP($replyMessage, $account, $bodyHtml),
                'gmail' => $this->sendViaGmail($replyMessage, $account, $bodyHtml),
                'outlook' => $this->sendViaOutlook($replyMessage, $account, $bodyHtml),
                default => throw new \InvalidArgumentException("Unsupported provider: {$account->provider}"),
            };

            if ($result) {
                $replyMessage->update([
                    'delivery_status' => 'sent',
                    'sent_at' => now(),
                ]);

                // Track email send usage for plan limit enforcement
                if ($replyMessage->workspace_id) {
                    try { UsageRecord::incrementUsage($replyMessage->workspace_id, 'emails_sent'); }
                    catch (\Throwable $e) { Log::warning('Email sent; usage accounting failed', ['message_id' => $replyMessage->id]); }
                }

                Log::info("EmailSend: Message {$replyMessage->id} sent via {$account->provider}");
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("EmailSend: Failed to send message {$replyMessage->id}: {$e->getMessage()}", [
                'message_id' => $replyMessage->id,
                'account_id' => $account->id,
                'provider' => $account->provider,
            ]);

            $replyMessage->update([
                'delivery_status' => 'failed',
                'delivery_error' => Str::limit($e->getMessage(), 500),
            ]);

            throw $e;
        }
    }

    /**
     * Send via SMTP using Laravel Mail with a dynamically configured mailer.
     */
    protected function sendViaSMTP(Message $replyMessage, EmailAccount $account, string $bodyHtml): bool
    {
        $mailerName = 'dynamic_smtp_' . $account->id;
        $mailerKey = "mail.mailers.{$mailerName}";
        $timeout = $account->smtp_timeout ?? config('mail.timeout', 30);

        // Save original config so we can restore it (not just null it out)
        $originalConfig = config($mailerKey);

        // Set up dynamic SMTP configuration at runtime
        Config::set($mailerKey, [
            'transport' => 'smtp',
            'host' => $account->smtp_host,
            'port' => $account->smtp_port,
            'encryption' => $account->smtp_encryption,
            ...$this->smtpSecurityOptions($account),
            'username' => $account->smtp_username,
            'password' => $account->smtp_password, // auto-decrypted via model cast
            'timeout' => $timeout,
            'local_domain' => parse_url(config('app.url'), PHP_URL_HOST),
        ]);

        try {
            $signature = $this->getSignature($account, $replyMessage);

            $mailable = new DynamicEmail(
                toAddresses: $replyMessage->to_emails ?? [],
                ccAddresses: $replyMessage->cc_emails ?? [],
                bccAddresses: $replyMessage->bcc_emails ?? [],
                emailSubject: $replyMessage->subject ?? $replyMessage->conversation?->subject ?? '(no subject)',
                bodyHtml: $bodyHtml,
                attachmentPaths: $this->getAttachmentPaths($replyMessage),
                signatureHtml: $signature,
                fromName: $replyMessage->from_name ?? $account->display_name ?? $account->email,
                fromEmail: $replyMessage->from_email ?? $account->email,
                inReplyTo: $replyMessage->in_reply_to,
                referencesHeader: $replyMessage->references_header ?? [],
            );

            $mailer = Mail::mailer($mailerName);
            $this->relaxTlsVerificationIfLoopback($mailer, $account->smtp_host);

            $mailer->send($mailable);

            return true;
        } finally {
            // Restore original config (or remove the key entirely if it didn't exist).
            // Config::set($key, null) leaves a null entry in the array which can leak
            // the mailer name to subsequent resolution attempts on long-lived processes.
            $this->resetMailerConfig($mailerKey, $mailerName, $originalConfig);
        }
    }

    /**
     * Send via Gmail API.
     */
    protected function sendViaGmail(Message $replyMessage, EmailAccount $account, string $bodyHtml): bool
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken([
            'access_token' => $account->oauth_token,
            'refresh_token' => $account->oauth_refresh_token,
            'expires_in' => 3600,
        ]);

        // Refresh token if expired
        if ($client->isAccessTokenExpired()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($account->oauth_refresh_token);
            if (isset($newToken['access_token'])) {
                $account->update([
                    'oauth_token' => $newToken['access_token'],
                    'oauth_token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
                ]);
            }
        }

        $gmail = new Gmail($client);

        // Build raw RFC 2822 message
        $rawMessage = $this->buildRawRfc2822($replyMessage, $account, $bodyHtml);

        $gmailMessage = new Gmail\Message();
        $gmailMessage->setRaw($this->base64UrlEncode($rawMessage));

        // If it's a reply, set threadId to keep conversation grouped
        $conversation = $replyMessage->conversation;
        if ($conversation) {
            $originalMessage = $conversation->messages()
                ->whereNotNull('channel_message_id')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($originalMessage?->channel_message_id) {
                try {
                    $origGmailMsg = $gmail->users_messages->get('me', $originalMessage->channel_message_id, ['format' => 'metadata']);
                    $gmailMessage->setThreadId($origGmailMsg->getThreadId());
                } catch (\Throwable $e) {
                    Log::warning("EmailSend: Could not get Gmail thread ID: {$e->getMessage()}");
                }
            }
        }

        $result = $gmail->users_messages->send('me', $gmailMessage);

        // Keep provider acceptance separate from local bookkeeping.
        $replyMessage->channel_message_id = $result->getId();

        return true;
    }

    /**
     * Send via Microsoft Graph API.
     */
    protected function sendViaOutlook(Message $replyMessage, EmailAccount $account, string $bodyHtml): bool
    {
        $httpClient = new \GuzzleHttp\Client();
        $accessToken = $account->oauth_token;

        $signature = $this->getSignature($account, $replyMessage);
        $fullHtml = $bodyHtml . ($signature ? "<br>{$signature}" : '');

        $payload = [
            'message' => [
                'subject' => $replyMessage->subject ?? $replyMessage->conversation?->subject ?? '(no subject)',
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $fullHtml,
                ],
                'toRecipients' => collect($replyMessage->to_emails ?? [])->map(fn ($email) => [
                    'emailAddress' => ['address' => $email],
                ])->toArray(),
                'ccRecipients' => collect($replyMessage->cc_emails ?? [])->map(fn ($email) => [
                    'emailAddress' => ['address' => $email],
                ])->toArray(),
                'bccRecipients' => collect($replyMessage->bcc_emails ?? [])->map(fn ($email) => [
                    'emailAddress' => ['address' => $email],
                ])->toArray(),
            ],
            'saveToSentItems' => true,
        ];

        // Add threading headers for reply threading
        if ($replyMessage->in_reply_to) {
            $payload['message']['internetMessageHeaders'] = [
                ['name' => 'In-Reply-To', 'value' => $replyMessage->in_reply_to],
            ];
            if (! empty($replyMessage->references_header)) {
                $payload['message']['internetMessageHeaders'][] = [
                    'name' => 'References',
                    'value' => implode(' ', $replyMessage->references_header),
                ];
            }
        }

        // Add attachments (with size guard to prevent OOM)
        $attachmentPaths = $this->getAttachmentPaths($replyMessage);
        $maxAttachmentSize = 25 * 1024 * 1024; // 25 MB
        if (! empty($attachmentPaths)) {
            $payload['message']['attachments'] = [];
            foreach ($attachmentPaths as $path) {
                if (file_exists($path['path']) && filesize($path['path']) <= $maxAttachmentSize) {
                    $payload['message']['attachments'][] = [
                        '@odata.type' => '#microsoft.graph.fileAttachment',
                        'name' => $path['name'],
                        'contentType' => $path['mime'],
                        'contentBytes' => base64_encode(file_get_contents($path['path'])),
                    ];
                }
            }
        }

        $response = $httpClient->post('https://graph.microsoft.com/v1.0/me/sendMail', [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        return $response->getStatusCode() === 202;
    }

    /**
     * Send a one-off email through a specific EmailAccount.
     *
     * Dispatches to the right transport based on provider — Gmail OAuth
     * accounts have no SMTP credentials so the old SMTP-only path crashed
     * with "Dsn host must be string, null given". Mirrors sendReply's
     * provider-aware routing but accepts primitive to/subject/body args.
     */
    public function send(
        EmailAccount $account,
        string $to,
        string $subject,
        string $htmlBody,
        array $options = []
    ): void {
        try {
            match ($account->provider) {
                'gmail' => $this->simpleSendGmail($account, $to, $subject, $htmlBody, $options),
                'outlook' => $this->simpleSendOutlook($account, $to, $subject, $htmlBody, $options),
                'imap', 'custom' => $this->simpleSendSmtp($account, $to, $subject, $htmlBody, $options),
                default => throw new \InvalidArgumentException("Unsupported provider: {$account->provider}"),
            };

            Log::info('Email sent successfully', [
                'account_id' => $account->id,
                'provider' => $account->provider,
                'to' => $to,
                'subject' => $subject,
            ]);
        } catch (\Throwable $e) {
            Log::error('Email send failed', [
                'account_id' => $account->id,
                'provider' => $account->provider,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "Failed to send email via account {$account->email}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Simple Gmail API send for one-off emails (campaign tests, notifications).
     */
    protected function simpleSendGmail(
        EmailAccount $account,
        string $to,
        string $subject,
        string $htmlBody,
        array $options
    ): void {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken([
            'access_token' => $account->oauth_token,
            'refresh_token' => $account->oauth_refresh_token,
            'expires_in' => 3600,
        ]);

        if ($client->isAccessTokenExpired()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($account->oauth_refresh_token);
            if (isset($newToken['access_token'])) {
                $account->update([
                    'oauth_token' => $newToken['access_token'],
                    'oauth_token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
                ]);
                $client->setAccessToken([
                    'access_token' => $newToken['access_token'],
                    'refresh_token' => $account->oauth_refresh_token,
                    'expires_in' => $newToken['expires_in'] ?? 3600,
                ]);
            }
        }

        $gmail = new Gmail($client);

        $raw = $this->buildSimpleRfc2822($account, $to, $subject, $htmlBody, $options);

        $gmailMessage = new Gmail\Message();
        $gmailMessage->setRaw($this->base64UrlEncode($raw));
        $gmail->users_messages->send('me', $gmailMessage);
    }

    /**
     * Simple Outlook/Graph send for one-off emails.
     */
    protected function simpleSendOutlook(
        EmailAccount $account,
        string $to,
        string $subject,
        string $htmlBody,
        array $options
    ): void {
        $httpClient = new \GuzzleHttp\Client();

        $payload = [
            'message' => [
                'subject' => $subject,
                'body' => ['contentType' => 'HTML', 'content' => $htmlBody],
                'toRecipients' => [['emailAddress' => ['address' => $to]]],
            ],
            'saveToSentItems' => true,
        ];

        if (! empty($options['cc'])) {
            $payload['message']['ccRecipients'] = collect((array) $options['cc'])
                ->map(fn ($e) => ['emailAddress' => ['address' => $e]])->all();
        }
        if (! empty($options['bcc'])) {
            $payload['message']['bccRecipients'] = collect((array) $options['bcc'])
                ->map(fn ($e) => ['emailAddress' => ['address' => $e]])->all();
        }
        if (! empty($options['headers'])) {
            $payload['message']['internetMessageHeaders'] = [];
            foreach ($options['headers'] as $name => $value) {
                $payload['message']['internetMessageHeaders'][] = ['name' => $name, 'value' => $value];
            }
        }

        $httpClient->post('https://graph.microsoft.com/v1.0/me/sendMail', [
            'headers' => [
                'Authorization' => "Bearer {$account->oauth_token}",
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);
    }

    /**
     * Simple SMTP send for IMAP/custom accounts. Preserves the original
     * dynamic-mailer config dance used by the older send() path.
     */
    protected function simpleSendSmtp(
        EmailAccount $account,
        string $to,
        string $subject,
        string $htmlBody,
        array $options
    ): void {
        $displayName = $account->display_name ?? config('app.name');
        $mailerName = "campaign_{$account->id}";
        $mailerKey = "mail.mailers.{$mailerName}";
        $timeout = $account->smtp_timeout ?? config('mail.timeout', 30);

        $originalConfig = config($mailerKey);

        Config::set($mailerKey, [
            'transport' => 'smtp',
            'host' => $account->smtp_host,
            'port' => $account->smtp_port,
            'encryption' => $account->smtp_encryption,
            ...$this->smtpSecurityOptions($account),
            'username' => $account->smtp_username,
            'password' => $account->smtp_password,
            'timeout' => $timeout,
        ]);

        try {
            $mailer = Mail::mailer($mailerName);
            $this->relaxTlsVerificationIfLoopback($mailer, $account->smtp_host);

            $mailer
                ->html($htmlBody, function (\Illuminate\Mail\Message $message) use ($account, $to, $subject, $displayName, $options) {
                    $message->from($account->email, $displayName)
                        ->to($to)
                        ->subject($subject);

                    if (! empty($options['cc'])) {
                        $message->cc($options['cc']);
                    }
                    if (! empty($options['bcc'])) {
                        $message->bcc($options['bcc']);
                    }
                    if (! empty($options['replyTo'])) {
                        $message->replyTo($options['replyTo']);
                    }
                    if (! empty($options['headers'])) {
                        $symfonyMessage = $message->getSymfonyMessage();
                        foreach ($options['headers'] as $name => $value) {
                            $symfonyMessage->getHeaders()->addTextHeader($name, $value);
                        }
                    }
                });
        } finally {
            $this->resetMailerConfig($mailerKey, $mailerName, $originalConfig);
        }
    }

    /**
     * Build a minimal RFC 2822 message for a one-off send (no reply threading
     * headers, no attachments). Used by the Gmail simple-send path.
     */
    protected function buildSimpleRfc2822(
        EmailAccount $account,
        string $to,
        string $subject,
        string $htmlBody,
        array $options
    ): string {
        $fromName = str_replace(['"', "\r", "\n"], '', $account->display_name ?? $account->email);
        $fromEmail = $account->email;

        $headers = [
            "From: \"{$fromName}\" <{$fromEmail}>",
            "To: {$to}",
        ];

        if (! empty($options['cc'])) {
            $cc = is_array($options['cc']) ? implode(', ', $options['cc']) : $options['cc'];
            $headers[] = "Cc: {$cc}";
        }
        if (! empty($options['bcc'])) {
            $bcc = is_array($options['bcc']) ? implode(', ', $options['bcc']) : $options['bcc'];
            $headers[] = "Bcc: {$bcc}";
        }
        if (! empty($options['replyTo'])) {
            $headers[] = "Reply-To: {$options['replyTo']}";
        }

        $headers[] = "Subject: {$subject}";
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset="UTF-8"';
        $headers[] = 'Content-Transfer-Encoding: base64';

        if (! empty($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $headers[] = "{$name}: {$value}";
            }
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($htmlBody));
    }

    /**
     * The mail server's TLS certificate is issued for the public hostname
     * (e.g. mail.dahify.com), not for 127.0.0.1 -- so a loopback SMTP
     * connection always fails certificate-name verification with
     * "Peer certificate CN=... did not match expected CN=...", even
     * though it's really talking to the right server.
     *
     * IMAP already tolerates this via MAIL_IMAP_VALIDATE_CERT=false. This
     * does the same for SMTP, controlled by MAIL_SUBMISSION_VERIFY_PEER
     * (config('dahify.smtp.verify_peer')) -- but only when the account's
     * configured smtp_host is actually a loopback address, so a real
     * external SMTP host (Gmail's, a client's own mail server, etc.) still
     * gets full certificate validation as normal.
     */
    protected function relaxTlsVerificationIfLoopback(\Illuminate\Mail\Mailer $mailer, ?string $smtpHost): void
    {
        if (! in_array($smtpHost, ['127.0.0.1', 'localhost', '::1'], true)) {
            return;
        }

        if ((bool) config('dahify.smtp.verify_peer', false)) {
            // Explicitly configured to require verification -- leave it alone.
            return;
        }

        $transport = $mailer->getSymfonyTransport();

        if (! $transport instanceof \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport) {
            return;
        }

        $stream = $transport->getStream();

        if ($stream instanceof \Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream) {
            $stream->setStreamOptions([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]);
        }
    }

    /**
     * Reset a dynamically created mailer config and purge its cached transport.
     *
     * Config::set($key, null) does NOT remove the key from the config repository --
     * it leaves a null entry that can confuse Laravel's MailManager on long-lived
     * processes (queue workers). This method properly restores the original value
     * and purges the cached Symfony transport so credentials are never retained.
     */
    protected function resetMailerConfig(string $configKey, string $mailerName, mixed $originalConfig): void
    {
        // Restore the original config value. If the key didn't exist before
        // (originalConfig is null), we remove the entire mailer entry from
        // the config array so it doesn't linger with null credentials.
        if ($originalConfig === null) {
            $allMailers = config('mail.mailers', []);
            unset($allMailers[$mailerName]);
            Config::set('mail.mailers', $allMailers);
        } else {
            Config::set($configKey, $originalConfig);
        }

        // Purge the cached mailer/transport instance from the MailManager so
        // subsequent calls don't reuse a transport with stale credentials.
        Mail::purge($mailerName);
    }

    /**
     * Inject open-tracking pixel and click-tracking links into the HTML body.
     */
    /**
     * Translate the account's Encryption dropdown into what this Laravel version reads.
     * It ignores an 'encryption' key: the connection type comes from `scheme`, and only
     * port 465 was treated as implicit TLS — so "SSL" on any other port silently used
     * plain SMTP and "None" could not switch STARTTLS off. Mirrors AdminMailConfig.
     */
    protected function smtpSecurityOptions(EmailAccount $account): array
    {
        $encryption = strtolower((string) $account->smtp_encryption);

        return [
            'scheme' => ($encryption === 'ssl' || (int) $account->smtp_port === 465) ? 'smtps' : 'smtp',
            'auto_tls' => $encryption !== 'none',
        ];
    }

    protected function shouldTrackOneToOne(): bool
    {
        return \App\Models\SystemSetting::get('track_one_to_one_emails', '0') === '1';
    }

    protected function injectTracking(Message $message): string
    {
        $bodyHtml = $message->body_html ?? '';
        $appUrl = config('app.url');
        $uuid = $message->uuid;

        // Inject open tracking pixel before closing </body> tag
        $trackingPixel = "<img src=\"{$appUrl}/api/track/open/{$uuid}\" width=\"1\" height=\"1\" style=\"display:none;\" alt=\"\" />";

        if (stripos($bodyHtml, '</body>') !== false) {
            $bodyHtml = str_ireplace('</body>', $trackingPixel . '</body>', $bodyHtml);
        } else {
            $bodyHtml .= $trackingPixel;
        }

        // Wrap all <a href="..."> links for click tracking
        $bodyHtml = preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+?)["\']([^>]*?)>/i',
            function ($matches) use ($appUrl, $uuid) {
                $originalUrl = $matches[2];

                // Skip tracking URLs, mailto, tel, and anchor links
                if (str_contains($originalUrl, '/api/track/') ||
                    str_starts_with($originalUrl, 'mailto:') ||
                    str_starts_with($originalUrl, 'tel:') ||
                    str_starts_with($originalUrl, '#')) {
                    return $matches[0];
                }

                // Only track valid HTTP(S) URLs -- skip malformed or protocol-relative URLs
                if (!filter_var($originalUrl, FILTER_VALIDATE_URL) || !str_starts_with($originalUrl, 'http')) {
                    return $matches[0];
                }

                $trackedUrl = "{$appUrl}/api/track/click/{$uuid}?url=" . urlencode($originalUrl);

                return "<a {$matches[1]}href=\"{$trackedUrl}\"{$matches[3]}>";
            },
            $bodyHtml
        );

        return $bodyHtml;
    }

    /**
     * Get the email signature for the account.
     */
    protected function getSignature(EmailAccount $account, Message $message): ?string
    {
        $isReply = ! empty($message->in_reply_to);

        $signature = $account->emailSignatures()
            ->where('is_default', true)
            ->where($isReply ? 'append_to_replies' : 'append_to_new', true)
            ->first();

        return $signature?->content_html;
    }

    /**
     * Get attachment file paths for a message.
     *
     * @return array<array{path: string, name: string, mime: string}>
     */
    protected function getAttachmentPaths(Message $message): array
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        return $message->attachments->map(function ($att) use ($disk) {
            // Resolve via the disk so we always match whatever root is configured
            // (Laravel 11's default local disk is rooted at storage/app/private,
            // not storage/app — a direct storage_path('app/...') lookup misses it).
            $path = $disk->path($att->storage_path);

            // Back-compat: fall back to legacy storage_path('app/...') if the
            // file isn't on the local disk (older rows written before the disk
            // root changed).
            if (! file_exists($path)) {
                $legacy = storage_path("app/{$att->storage_path}");
                if (file_exists($legacy)) {
                    $path = $legacy;
                }
            }

            return [
                'path' => $path,
                'name' => $att->original_filename ?? $att->filename,
                'mime' => $att->mime_type,
            ];
        })->toArray();
    }

    /**
     * Build an RFC 2822 raw email string for Gmail API.
     */
    protected function buildRawRfc2822(Message $message, EmailAccount $account, string $bodyHtml): string
    {
        $signature = $this->getSignature($account, $message);
        $mail = new DynamicEmail(
            $message->to_emails ?? [], $message->cc_emails ?? [], $message->bcc_emails ?? [],
            $message->subject ?? $message->conversation?->subject ?? '(no subject)', $bodyHtml,
            signatureHtml: $signature,
        );
        $html = $bodyHtml . ($signature ? "<br>{$signature}" : '');
        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($message->from_email ?? $account->email, $message->from_name ?? $account->display_name ?? ''))
            ->subject($mail->emailSubject)->text($mail->plainTextBody())->html($html);
        foreach ($message->to_emails ?? [] as $address) $email->addTo($address);
        foreach ($message->cc_emails ?? [] as $address) $email->addCc($address);
        foreach ($message->bcc_emails ?? [] as $address) $email->addBcc($address);
        if ($message->in_reply_to) $email->getHeaders()->addIdHeader('In-Reply-To', trim($message->in_reply_to, '<>'));
        if ($message->references_header) $email->getHeaders()->addIdHeader('References', array_map(fn ($id) => trim($id, '<>'), $message->references_header));
        foreach ($this->getAttachmentPaths($message) as $attachment) {
            if (is_file($attachment['path']) && filesize($attachment['path']) <= 25 * 1024 * 1024) {
                $email->attachFromPath($attachment['path'], $attachment['name'], $attachment['mime']);
            }
        }
        // Gmail derives its delivery envelope from the raw headers. Symfony strips
        // Bcc for SMTP, so preserve it in this API submission; Gmail removes it on delivery.
        $bcc = $email->getBcc();
        return ($bcc ? 'Bcc: '.implode(', ', array_map(fn ($address) => $address->toString(), $bcc))."\r\n" : '') . $email->toString();
    }

    /**
     * Base64url encode for Gmail API.
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}