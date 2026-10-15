<?php

namespace App\Livewire\Inbox;

use App\Jobs\SendScheduledEmailJob;
use App\Models\Attachment;
use App\Models\ChannelIntegration;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Services\AI\AIManager;
use App\Services\Channels\TwilioSMSService;
use App\Services\Channels\WhatsAppService;
use App\Services\Email\EmailSendService;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class ComposeEmail extends Component
{
    use WithFileUploads;
    use AuthorizesWorkspaceActions;

    // Channel
    public string $channel = 'email';
    /**
     * When true, the channel-tab strip in the compose UI hides every
     * channel except the currently-selected one. Flipped on by the
     * inbox when "Compose" is clicked while a specific channel filter
     * is active (e.g. user is on the SMS inbox view) — they probably
     * don't want to switch channels mid-compose, so we hide the noise.
     * On the "All channels" inbox we leave it false so all tabs show.
     */
    public bool $lockChannel = false;

    // SMS / WhatsApp / Telegram / Slack fields
    // toPhone reused as the Slack channel/user ID when channel='slack'
    public string $toPhone = '';
    public string $messageBody = '';

    // Slack channel picker source — populated on switchToSlack()
    public array $slackChannelsList = [];

    // Email fields
    #[Rule('required|integer|exists:email_accounts,id')]
    public ?int $fromAccountId = null;

    #[Rule('required|string|email')]
    public string $toEmail = '';

    public string $toSearch = '';

    #[Rule('nullable|string|max:500')]
    public string $ccEmails = '';

    #[Rule('nullable|string|max:500')]
    public string $bccEmails = '';

    public bool $showCcBcc = false;

    #[Rule('required|string|max:500')]
    public string $subject = '';

    #[Rule('required|string')]
    public string $body = '';

    // Attachments
    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    // AI Write
    public bool $showAiWrite = false;
    public string $aiPrompt = '';
    public string $aiTone = 'professional';
    public bool $aiLoading = false;
    public string $aiGeneratedContent = '';

    // Schedule
    public bool $showSchedule = false;
    public ?string $scheduledAt = null;

    // Contact autocomplete
    public array $contactSuggestions = [];
    public array $groupSuggestions = [];
    public bool $showContactSuggestions = false;

    // Group send
    public ?int $selectedGroupId = null;
    public string $selectedGroupName = '';
    public int $selectedGroupCount = 0;

    // Status
    public bool $sending = false;
    public bool $saving = false;

    // Undo send
    public bool $showUndoBar = false;
    public ?int $pendingSendMessageId = null;
    public int $undoCountdown = 10;

    // Canned-response picker state (triggered by typing "/" in the editor)
    public bool $showCannedResponses = false;
    public string $cannedSearch = '';

    public function mount(): void
    {
        $workspaceId = auth()->user()->active_workspace_id;

        // Set default "From" account
        $defaultAccount = EmailAccount::where('workspace_id', $workspaceId)
            ->where('status', 'connected')
            ->where('is_default', true)
            ->first();

        if (!$defaultAccount) {
            $defaultAccount = EmailAccount::where('workspace_id', $workspaceId)
                ->where('status', 'connected')
                ->first();
        }

        $this->fromAccountId = $defaultAccount?->id;

        // Honour ?channel=... query param so deep links land on the right tab.
        $qsChannel = request()->query('channel');
        if (is_string($qsChannel) && in_array($qsChannel, ['email','sms','whatsapp','telegram','slack'], true)) {
            $this->channel = $qsChannel;
        }
    }

    /**
     * Set the composer's channel tab from an outside event. The inbox page
     * dispatches this when the user clicks "Compose" while filtering by a
     * specific channel, so the composer lands on that channel by default
     * instead of always opening on Email.
     *
     * For channels that need prep work (e.g. Slack has to fetch the channel
     * picker list from its API), we route through the channel-specific
     * switcher so clicking the channel tab directly and arriving via this
     * event produce identical composer state.
     */
    #[On('compose-set-channel')]
    public function setComposeChannel($channel = null, $lock = null): void
    {
        // Livewire 3 hands payload as an associative array when the
        // dispatcher passes it as `{channel: 'sms', lock: true}`.
        if (is_array($channel)) {
            $lock    = $channel['lock'] ?? null;
            $channel = $channel['channel'] ?? null;
        }
        if (! is_string($channel) || ! in_array($channel, ['email','sms','whatsapp','telegram','slack'], true)) {
            $this->lockChannel = false;
            return;
        }

        // The inbox passes lock=true when there's a non-"all" channel
        // filter active, so the compose modal stays focused on that
        // single channel and doesn't show every other tab.
        $this->lockChannel = (bool) $lock;

        if ($channel === 'slack') {
            // Populates $slackChannelsList from the Slack API. Without this
            // the Slack channel dropdown renders empty when Compose is
            // opened from the Slack sidebar filter.
            $this->switchToSlack();
            return;
        }

        $this->channel = $channel;
    }

    /**
     * The composer uses a rich-text editor for email, so $this->body arrives
     * as HTML like "<p>/hii</p>". Strip tags + decode entities before looking
     * for the "/" shortcut so the quick-reply dropdown opens on typing /xxx,
     * matching the reply composer's behaviour.
     */
    public function updatedBody(): void
    {
        $plain = trim(html_entity_decode(
            strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $this->body ?? '')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        if (str_starts_with($plain, '/') && strlen($plain) > 1) {
            $firstLine = explode("\n", $plain, 2)[0];
            $this->cannedSearch = ltrim($firstLine, '/');
            $this->showCannedResponses = true;
        } else {
            $this->showCannedResponses = false;
            $this->cannedSearch = '';
        }
    }

    /**
     * Dropdown click handler — replaces the editor body with the canned
     * response's content and bumps its usage_count. Distinct signature
     * from the legacy insertCannedResponse(string) event listener which
     * APPENDS, so we keep that behaviour for other integrations.
     */
    public function insertCannedResponseById(int $cannedId): void
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $canned = \App\Models\CannedResponse::where('workspace_id', $workspaceId)->find($cannedId);
        if ($canned) {
            $this->body = $canned->content;
            $canned->increment('usage_count');
        }
        $this->showCannedResponses = false;
        $this->cannedSearch = '';
    }

    // ---- Contact Autocomplete ----

    public function updatedToSearch(): void
    {
        $term = trim($this->toSearch);
        if (strlen($term) < 2) {
            $this->contactSuggestions = [];
            $this->showContactSuggestions = false;
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;
        $like = '%' . $term . '%';

        $contacts = Contact::where('workspace_id', $workspaceId)
            ->where(function ($q) use ($like) {
                $q->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('company', 'like', $like);
            })
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->limit(8)
            ->get()
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->full_name,
                'email' => $c->email,
                'company' => $c->company,
                'initials' => $c->initials,
            ])
            ->toArray();

        // Also search groups by name
        $groups = \App\Models\ContactList::where('workspace_id', $workspaceId)
            ->where('name', 'like', $like)
            ->limit(5)
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'count' => $g->contacts_count ?? $g->contacts()->count(),
            ])
            ->toArray();

        $this->contactSuggestions = $contacts;
        $this->groupSuggestions = $groups;
        $this->showContactSuggestions = !empty($contacts) || !empty($groups);
    }

    public function selectContact(string $email, string $name): void
    {
        $this->toEmail = $email;
        $this->toSearch = "{$name} <{$email}>";
        $this->showContactSuggestions = false;
        $this->contactSuggestions = [];
        $this->groupSuggestions = [];
        $this->selectedGroupId = null;
    }

    public function selectGroup(int $groupId): void
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $group = \App\Models\ContactList::where('workspace_id', $workspaceId)->find($groupId);
        if (! $group) return;

        $this->selectedGroupId = $group->id;
        $this->selectedGroupName = $group->name;
        $this->selectedGroupCount = $group->contacts()->count();
        $this->toSearch = "Group: {$group->name} ({$this->selectedGroupCount} contacts)";
        $this->toEmail = ''; // Will be set per-contact during send
        $this->showContactSuggestions = false;
        $this->contactSuggestions = [];
        $this->groupSuggestions = [];
    }

    public function clearGroup(): void
    {
        $this->selectedGroupId = null;
        $this->selectedGroupName = '';
        $this->selectedGroupCount = 0;
        $this->toSearch = '';
        $this->toEmail = '';
    }

    public function dismissSuggestions(): void
    {
        $this->showContactSuggestions = false;
    }

    // ---- CC/BCC Validation ----

    protected function validateCcBccEmails(): bool
    {
        if ($this->ccEmails) {
            $ccList = array_filter(array_map('trim', explode(',', $this->ccEmails)));
            foreach ($ccList as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addError('ccEmails', "Invalid CC email: {$email}");
                    return false;
                }
            }
        }

        if ($this->bccEmails) {
            $bccList = array_filter(array_map('trim', explode(',', $this->bccEmails)));
            foreach ($bccList as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addError('bccEmails', "Invalid BCC email: {$email}");
                    return false;
                }
            }
        }

        return true;
    }

    // ---- Send ----

    public function send(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) {
            Log::warning('ComposeEmail: send blocked — workspace permission check failed', ['user_id' => auth()->id()]);
            return;
        }
        if ($this->sending) {
            Log::warning('ComposeEmail: send ignored — previous send still marked in progress', ['user_id' => auth()->id()]);
            return;
        }
        $this->sending = true;

        // Plan limit check — block if monthly email limit reached
        $planLimitService = app(\App\Services\PlanLimitService::class);
        $workspace = auth()->user()->activeWorkspace;
        if (!$planLimitService->canUse($workspace, 'emails_per_month')) {
            $this->sending = false;
            Log::warning('ComposeEmail: send blocked — monthly email plan limit reached', ['workspace_id' => $workspace?->id]);
            session()->flash('error', 'You have reached your monthly email limit. Please upgrade your plan.');
            return;
        }

        // Group send — send to all contacts in the selected group
        if ($this->selectedGroupId) {
            try {
                $this->validate([
                    'fromAccountId' => 'required|integer',
                    'subject' => 'required|string|min:1|max:500',
                    'body' => 'required|string|min:1',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->sending = false;
                Log::warning('ComposeEmail: group send validation failed', ['errors' => $e->errors()]);
                throw $e;
            }

            $this->sendToGroup();
            return;
        }

        // If user typed email directly in toSearch without selecting from autocomplete
        if (!$this->toEmail && $this->toSearch) {
            $email = trim($this->toSearch);
            if (preg_match('/<(.+?)>/', $email, $matches)) {
                $email = $matches[1];
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->toEmail = $email;
            }
        }

        try {
            $this->validate([
                'fromAccountId' => 'required|integer',
                'toEmail' => 'required|email',
                'subject' => 'required|string|min:1|max:500',
                'body' => 'required|string|min:1',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Without this reset, $sending stays true and every later click on
            // Send returns silently at the "if ($this->sending) return;" guard.
            $this->sending = false;
            Log::warning('ComposeEmail: validation failed', ['errors' => $e->errors(), 'from' => $this->fromAccountId, 'to' => $this->toEmail]);
            throw $e;
        }

        // Validate CC/BCC emails before proceeding
        if (!$this->validateCcBccEmails()) {
            $this->sending = false;
            return;
        }

        // Remove expired temp files — use realPath check (bypasses flysystem)
        if (!empty($this->attachments)) {
            $this->attachments = array_values(array_filter($this->attachments, function ($f) {
                return $f && $f->getRealPath() && file_exists($f->getRealPath());
            }));
        }

        $this->withOperationLock("compose-send-" . auth()->id(), function () {
            try {
                $workspaceId = auth()->user()->active_workspace_id;
                Log::info('ComposeEmail: sending', ['to' => $this->toEmail, 'subject' => $this->subject, 'from' => $this->fromAccountId]);

                $account = EmailAccount::where('workspace_id', $workspaceId)
                    ->where('id', $this->fromAccountId)
                    ->firstOrFail();

                // Find or create contact
                $contact = Contact::firstOrCreate(
                    ['workspace_id' => $workspaceId, 'email' => $this->toEmail],
                    [
                        'first_name' => explode('@', $this->toEmail)[0],
                        'status' => 'active',
                    ]
                );

                // Create conversation
                $conversation = Conversation::create([
                    'workspace_id' => $workspaceId,
                    'contact_id' => $contact->id,
                    'email_account_id' => $account->id,
                    'assigned_to' => auth()->id(),
                    'channel' => 'email',
                    'status' => 'open',
                    'priority' => 'normal',
                    'subject' => $this->subject,
                    'preview' => Str::limit(strip_tags($this->body), 200),
                    'is_read' => true,
                    'messages_count' => 1,
                    'last_message_at' => now(),
                ]);

                // Build message
                $messageData = [
                    'conversation_id' => $conversation->id,
                    'workspace_id' => $workspaceId,
                    'direction' => 'outbound',
                    'sender_type' => 'agent',
                    'sender_id' => auth()->id(),
                    'type' => 'message',
                    'subject' => $this->subject,
                    'body_text' => strip_tags($this->body),
                    'body_html' => $this->body,
                    'from_email' => $account->email,
                    'from_name' => $account->display_name ?? auth()->user()->name,
                    'to_emails' => [$this->toEmail],
                    'delivery_status' => 'queued',
                    'message_id_header' => '<' . Str::uuid() . '@' . parse_url(config('app.url'), PHP_URL_HOST) . '>',
                ];

                if ($this->ccEmails) {
                    $messageData['cc_emails'] = array_filter(array_map('trim', explode(',', $this->ccEmails)));
                }
                if ($this->bccEmails) {
                    $messageData['bcc_emails'] = array_filter(array_map('trim', explode(',', $this->bccEmails)));
                }

                if ($this->scheduledAt) {
                    // The datetime-local input submits a naive wall-clock string
                    // ("2026-04-18T10:51"). Parse it in the user's timezone and
                    // convert to UTC for storage so the readyToSend() scope
                    // fires at the right moment regardless of server timezone.
                    $tz = $this->userTimezone();
                    $utc = \Carbon\Carbon::parse($this->scheduledAt, $tz)->utc();
                    $messageData['scheduled_at'] = $utc;
                    $messageData['schedule_status'] = 'pending';
                    Log::info('ComposeEmail: scheduling in tz', [
                        'input' => $this->scheduledAt,
                        'tz_used' => $tz,
                        'stored_utc' => $utc->toDateTimeString(),
                    ]);
                } elseif ($this->channel === 'email') {
                    // Undo-send: 10-second grace period (email only)
                    $messageData['scheduled_at'] = now()->addSeconds(10);
                    $messageData['schedule_status'] = 'pending';
                }

                $message = Message::create($messageData);

                // Handle attachments
                $this->storeAttachments($message, $workspaceId);

                // Update contact last_contacted_at
                $contact->update(['last_contacted_at' => now()]);

                Log::info('ComposeEmail: message created', ['msg_id' => $message->id, 'scheduled_at' => $message->scheduled_at]);

                if ($this->scheduledAt) {
                    // Format the flash using the user's timezone so they see
                    // the same local time they picked, not the UTC conversion.
                    $scheduledTime = \Carbon\Carbon::parse($this->scheduledAt, $this->userTimezone());
                    session()->flash('compose-success', 'Email scheduled for ' . $scheduledTime->format('M j, Y \a\t g:i A') . '.');
                    $this->redirect(route('inbox'), navigate: true);
                } else {
                    $this->pendingSendMessageId = $message->id;
                    $this->showUndoBar = true;
                    $this->undoCountdown = 10;
                    Log::info('ComposeEmail: undo bar shown', ['msg_id' => $message->id]);
                }
            } catch (\Exception $e) {
                Log::error('ComposeEmail: SEND FAILED', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                session()->flash('compose-error', 'Failed to send email: ' . $e->getMessage());
            }
        });

        $this->sending = false;
    }

    // ---- Multi-channel Dispatcher ----

    public function sendMessage(): void
    {
        Log::info('ComposeEmail: sendMessage called', ['channel' => $this->channel]);
        match ($this->channel) {
            'email' => $this->send(),
            'sms' => $this->sendSms(),
            'whatsapp' => $this->sendWhatsApp(),
            'telegram' => $this->sendTelegram(),
            'slack' => $this->sendSlack(),
            'chat' => $this->addError('channel', 'Live Chat is visitor-initiated. Use the chat widget instead.'),
            default => $this->addError('channel', ucfirst($this->channel) . ' sending is not supported yet.'),
        };
    }

    // ---- Phone validation helper ----

    protected function validatePhoneNumber(string $phone): bool
    {
        // E.164 format or at least 10 digits
        if (!preg_match('/^\+?[1-9]\d{6,14}$/', preg_replace('/[\s\-\(\)]/', '', $phone))) {
            $this->addError('toPhone', 'Please enter a valid phone number (E.164 format recommended, e.g. +1234567890).');
            return false;
        }
        return true;
    }

    // ---- Send SMS ----

    public function sendSms(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if ($this->sending) return;
        $this->sending = true;

        // Plan limit check — block if monthly SMS limit reached
        $planLimitService = app(\App\Services\PlanLimitService::class);
        $workspace = auth()->user()->activeWorkspace;
        if (!$planLimitService->canUse($workspace, 'sms_per_month')) {
            $this->sending = false;
            session()->flash('error', 'You have reached your monthly SMS limit. Please upgrade your plan.');
            return;
        }

        $this->validate([
            'toPhone' => 'required|string|min:10',
            'messageBody' => 'required|string|min:1',
        ]);

        if (!$this->validatePhoneNumber($this->toPhone)) {
            $this->sending = false;
            return;
        }

        $this->withOperationLock("compose-sms-" . auth()->id(), function () {
            try {
                $workspaceId = auth()->user()->active_workspace_id;

                // Find or create contact by phone
                $contact = Contact::firstOrCreate(
                    ['workspace_id' => $workspaceId, 'phone' => $this->toPhone],
                    ['first_name' => 'SMS Contact', 'status' => 'active']
                );

                // Reuse existing open conversation for this contact+channel
                $conversation = Conversation::where('workspace_id', $workspaceId)
                    ->where('contact_id', $contact->id)
                    ->where('channel', 'sms')
                    ->where('status', '!=', 'closed')
                    ->orderBy('last_message_at', 'desc')
                    ->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'workspace_id' => $workspaceId,
                        'contact_id' => $contact->id,
                        'assigned_to' => auth()->id(),
                        'channel' => 'sms',
                        'status' => 'open',
                        'priority' => 'normal',
                        'subject' => 'SMS to ' . $this->toPhone,
                        'preview' => Str::limit($this->messageBody, 200),
                        'is_read' => true,
                        'messages_count' => 0,
                        'last_message_at' => now(),
                    ]);
                }

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'workspace_id' => $workspaceId,
                    'direction' => 'outbound',
                    'sender_type' => 'agent',
                    'sender_id' => auth()->id(),
                    'type' => 'message',
                    'body_text' => $this->messageBody,
                    'delivery_status' => 'queued',
                    'sent_at' => now(),
                ]);

                // Test mode: bypass Twilio API, mark as sent immediately
                if (config('services.channel_test_mode')) {
                    Log::info('ComposeEmail: TEST MODE — bypassing SMS API, marking as sent', ['message_id' => $message->id]);
                    $message->update(['delivery_status' => 'sent']);
                    $deliveryOk = true;
                } else {
                    $deliveryOk = false;
                    // Try to send via Twilio
                    try {
                        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
                            ->where('channel', 'sms')
                            ->where('status', 'active')
                            ->first();

                        if ($integration) {
                            $smsService = app(TwilioSMSService::class);
                            $creds = $integration->credentials ?? [];
                            $result = $smsService->sendSMS(
                                $this->toPhone,
                                $this->messageBody,
                                $creds['phone_number'] ?? null,
                                $creds['sid'] ?? null,
                                $creds['auth_token'] ?? null
                            );
                            $message->update([
                                'delivery_status' => 'sent',
                                'channel_message_id' => $result->sid ?? null,
                            ]);
                            \App\Models\UsageRecord::incrementUsage($workspaceId, 'sms_sent');
                            $deliveryOk = true;
                        } else {
                            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No SMS integration configured']);
                        }
                    } catch (\Throwable $e) {
                        $message->update(['delivery_status' => 'failed', 'delivery_error' => $e->getMessage()]);
                        Log::error('SMS send failed', ['error' => $e->getMessage()]);
                    }
                }

                // Update conversation
                $conversation->update([
                    'preview' => Str::limit($this->messageBody, 200),
                    'last_message_at' => now(),
                    'is_read' => true,
                ]);
                $conversation->increment('messages_count');

                $contact->update(['last_contacted_at' => now()]);

                if ($deliveryOk) {
                    $testLabel = config('services.channel_test_mode') ? ' (Test Mode — not actually delivered)' : '';
                    session()->flash('compose-success', 'SMS sent to ' . $this->toPhone . $testLabel);
                } else {
                    session()->flash('compose-error', 'SMS delivery failed. Check channel settings.');
                }
                $this->redirect(route('inbox'), navigate: true);
            } catch (\Exception $e) {
                session()->flash('compose-error', 'Failed: ' . $e->getMessage());
            }
        });

        $this->sending = false;
    }

    // ---- Send WhatsApp ----

    public function sendWhatsApp(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if ($this->sending) return;
        $this->sending = true;

        // Plan limit check — block if monthly WhatsApp limit reached
        $planLimitService = app(\App\Services\PlanLimitService::class);
        $workspace = auth()->user()->activeWorkspace;
        if (!$planLimitService->canUse($workspace, 'whatsapp_per_month')) {
            $this->sending = false;
            session()->flash('error', 'You have reached your monthly WhatsApp message limit. Please upgrade your plan.');
            return;
        }

        $this->validate([
            'toPhone' => 'required|string|min:10',
            'messageBody' => 'required|string|min:1',
        ]);

        if (!$this->validatePhoneNumber($this->toPhone)) {
            $this->sending = false;
            return;
        }

        $this->withOperationLock("compose-whatsapp-" . auth()->id(), function () {
            try {
                $workspaceId = auth()->user()->active_workspace_id;

                $contact = Contact::firstOrCreate(
                    ['workspace_id' => $workspaceId, 'phone' => $this->toPhone],
                    ['first_name' => 'WhatsApp Contact', 'status' => 'active']
                );

                // Reuse existing open conversation for this contact+channel
                $conversation = Conversation::where('workspace_id', $workspaceId)
                    ->where('contact_id', $contact->id)
                    ->where('channel', 'whatsapp')
                    ->where('status', '!=', 'closed')
                    ->orderBy('last_message_at', 'desc')
                    ->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'workspace_id' => $workspaceId,
                        'contact_id' => $contact->id,
                        'assigned_to' => auth()->id(),
                        'channel' => 'whatsapp',
                        'status' => 'open',
                        'priority' => 'normal',
                        'subject' => 'WhatsApp to ' . $this->toPhone,
                        'preview' => Str::limit($this->messageBody, 200),
                        'is_read' => true,
                        'messages_count' => 0,
                        'last_message_at' => now(),
                    ]);
                }

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'workspace_id' => $workspaceId,
                    'direction' => 'outbound',
                    'sender_type' => 'agent',
                    'sender_id' => auth()->id(),
                    'type' => 'message',
                    'body_text' => $this->messageBody,
                    'delivery_status' => 'queued',
                    'sent_at' => now(),
                ]);

                // Test mode: bypass WhatsApp API, mark as sent immediately
                if (config('services.channel_test_mode')) {
                    Log::info('ComposeEmail: TEST MODE — bypassing WhatsApp API, marking as sent', ['message_id' => $message->id]);
                    $message->update(['delivery_status' => 'sent']);
                    $deliveryOk = true;
                } else {
                    $deliveryOk = false;
                    try {
                        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
                            ->where('channel', 'whatsapp')
                            ->where('status', 'active')
                            ->first();

                        if ($integration) {
                            $creds = $integration->credentials ?? [];
                            $phoneNumberId = $creds['phone_number_id'] ?? '';
                            $accessToken = $creds['access_token'] ?? '';

                            if (!$phoneNumberId || !$accessToken) {
                                throw new \RuntimeException('WhatsApp credentials incomplete. Check phone_number_id and access_token.');
                            }

                            $waService = app(WhatsAppService::class);
                            $waResult = $waService->sendText($phoneNumberId, $accessToken, $this->toPhone, $this->messageBody);
                            $message->update([
                                'delivery_status' => 'sent',
                                'channel_message_id' => $waResult['messages'][0]['id'] ?? null,
                            ]);
                            $deliveryOk = true;
                        } else {
                            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No WhatsApp integration configured']);
                        }
                    } catch (\Throwable $e) {
                        $message->update(['delivery_status' => 'failed', 'delivery_error' => $e->getMessage()]);
                        Log::error('WhatsApp send failed', ['error' => $e->getMessage()]);
                    }
                }

                // Update conversation
                $conversation->update([
                    'preview' => Str::limit($this->messageBody, 200),
                    'last_message_at' => now(),
                    'is_read' => true,
                ]);
                $conversation->increment('messages_count');

                $contact->update(['last_contacted_at' => now()]);

                if ($deliveryOk) {
                    $testLabel = config('services.channel_test_mode') ? ' (Test Mode — not actually delivered)' : '';
                    session()->flash('compose-success', 'WhatsApp message sent to ' . $this->toPhone . $testLabel);
                } else {
                    session()->flash('compose-error', 'WhatsApp delivery failed. Check channel settings.');
                }
                $this->redirect(route('inbox'), navigate: true);
            } catch (\Exception $e) {
                session()->flash('compose-error', 'Failed: ' . $e->getMessage());
            }
        });

        $this->sending = false;
    }

    // ---- Send Telegram ----

    public function sendTelegram(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if ($this->sending) return;
        $this->sending = true;

        $this->validate([
            'toPhone' => 'required|string|min:1',
            'messageBody' => 'required|string|min:1',
        ]);

        $this->withOperationLock("compose-telegram-" . auth()->id(), function () {
            try {
                $workspaceId = auth()->user()->active_workspace_id;
                // Resolve @username to chat ID from DB
                $chatId = $this->toPhone;
                if (str_starts_with($chatId, '@') && !config('services.channel_test_mode')) {
                    $username = ltrim($chatId, '@');
                    $resolved = false;

                    // Look up chat ID from existing telegram conversations in DB
                    // (saved when user sent /start to the bot via webhook)
                    $existingConvo = Conversation::where('workspace_id', $workspaceId)
                        ->where('channel', 'telegram')
                        ->whereNotNull('channel_conversation_id')
                        ->whereHas('contact', function ($q) use ($username) {
                            $q->where('last_name', '@' . $username)
                              ->orWhere('first_name', $username)
                              ->orWhere('phone', $username);
                        })
                        ->first();

                    if ($existingConvo) {
                        $chatId = $existingConvo->channel_conversation_id;
                        $resolved = true;
                    }

                    if (!$resolved) {
                        throw new \RuntimeException("Username @{$username} not found. The user must send /start to your Telegram bot first.");
                    }
                }

                Log::info('ComposeEmail: sending Telegram', ['chat_id' => $chatId, 'body' => Str::limit($this->messageBody, 50)]);

                $contact = Contact::firstOrCreate(
                    ['workspace_id' => $workspaceId, 'phone' => $this->toPhone],
                    ['first_name' => 'Telegram User', 'status' => 'active']
                );

                // Reuse existing open conversation for this contact+channel
                $conversation = Conversation::where('workspace_id', $workspaceId)
                    ->where('contact_id', $contact->id)
                    ->where('channel', 'telegram')
                    ->where('status', '!=', 'closed')
                    ->orderBy('last_message_at', 'desc')
                    ->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'workspace_id' => $workspaceId,
                        'contact_id' => $contact->id,
                        'assigned_to' => auth()->id(),
                        'channel' => 'telegram',
                        'channel_conversation_id' => $chatId,
                        'status' => 'open',
                        'priority' => 'normal',
                        'subject' => 'Telegram to ' . $this->toPhone,
                        'preview' => Str::limit($this->messageBody, 200),
                        'is_read' => true,
                        'messages_count' => 0,
                        'last_message_at' => now(),
                    ]);
                }

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'workspace_id' => $workspaceId,
                    'direction' => 'outbound',
                    'sender_type' => 'agent',
                    'sender_id' => auth()->id(),
                    'type' => 'message',
                    'body_text' => $this->messageBody,
                    'delivery_status' => 'queued',
                    'sent_at' => now(),
                ]);

                // Test mode: bypass Telegram API, mark as sent immediately
                if (config('services.channel_test_mode')) {
                    Log::info('ComposeEmail: TEST MODE — bypassing Telegram API, marking as sent', ['message_id' => $message->id]);
                    $message->update(['delivery_status' => 'sent']);
                    $deliveryOk = true;
                } else {
                    $deliveryOk = false;
                    try {
                        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
                            ->where('channel', 'telegram')
                            ->where('status', 'active')
                            ->first();

                        if ($integration) {
                            $creds = $integration->credentials ?? [];
                            $botToken = $creds['bot_token'] ?? '';

                            if (!$botToken) {
                                throw new \RuntimeException('Telegram bot token not configured.');
                            }

                            $telegramService = new \App\Services\Channels\TelegramService($botToken);
                            $tgResult = $telegramService->sendMessage($chatId, $this->messageBody);
                            $message->update([
                                'delivery_status' => 'sent',
                                'channel_message_id' => isset($tgResult['message_id']) ? "tg_{$chatId}_{$tgResult['message_id']}" : null,
                            ]);
                            $deliveryOk = true;
                            Log::info('ComposeEmail: Telegram sent', ['chat_id' => $this->toPhone, 'msg_id' => $message->id]);
                        } else {
                            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No Telegram integration configured']);
                            Log::warning('ComposeEmail: No Telegram integration found');
                        }
                    } catch (\Throwable $e) {
                        $message->update(['delivery_status' => 'failed', 'delivery_error' => $e->getMessage()]);
                        Log::error('ComposeEmail: Telegram send failed', ['error' => $e->getMessage()]);
                    }
                }

                // Update conversation
                $conversation->update([
                    'preview' => Str::limit($this->messageBody, 200),
                    'last_message_at' => now(),
                    'is_read' => true,
                ]);
                $conversation->increment('messages_count');

                $contact->update(['last_contacted_at' => now()]);
                $this->dispatch('conversations-updated');

                if ($deliveryOk) {
                    $testLabel = config('services.channel_test_mode') ? ' (Test Mode — not actually delivered)' : '';
                    session()->flash('compose-success', 'Telegram message sent.' . $testLabel);
                } else {
                    session()->flash('compose-error', 'Telegram delivery failed. Check channel settings.');
                }
                $this->redirect(route('inbox'), navigate: true);
            } catch (\Exception $e) {
                Log::error('ComposeEmail: Telegram failed', ['error' => $e->getMessage()]);
                session()->flash('compose-error', 'Failed: ' . $e->getMessage());
            }
        });

        $this->sending = false;
    }

    // ---- Send Slack ----

    /**
     * Switch the composer to Slack mode and fetch available channels for the
     * picker dropdown. The bot can only post to channels it's a member of,
     * so we pull them fresh from Slack each time instead of caching — that way
     * channels the user just /invite'd show up without a page reload.
     */
    public function switchToSlack(): void
    {
        $this->channel = 'slack';
        $this->toPhone = '';

        try {
            $workspaceId = auth()->user()->active_workspace_id;
            $slack = app(\App\Services\Integrations\SlackIntegrationService::class);
            $this->slackChannelsList = $slack->syncChannels($workspaceId);
        } catch (\Throwable $e) {
            Log::warning('ComposeEmail: could not load Slack channels', ['error' => $e->getMessage()]);
            $this->slackChannelsList = [];
        }
    }

    public function sendSlack(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        if ($this->sending) return;
        $this->sending = true;

        $this->validate([
            'toPhone' => 'required|string|min:1',
            'messageBody' => 'required|string|min:1',
        ]);

        $this->withOperationLock("compose-slack-" . auth()->id(), function () {
            try {
                $workspaceId = auth()->user()->active_workspace_id;
                Log::info('ComposeEmail: sending Slack', ['channel' => $this->toPhone, 'body' => Str::limit($this->messageBody, 50)]);

                $contact = Contact::firstOrCreate(
                    ['workspace_id' => $workspaceId, 'email' => $this->toPhone . '@slack'],
                    ['first_name' => 'Slack: ' . $this->toPhone, 'status' => 'active']
                );

                // Reuse existing open conversation for this contact+channel
                $conversation = Conversation::where('workspace_id', $workspaceId)
                    ->where('contact_id', $contact->id)
                    ->where('channel', 'slack')
                    ->where('status', '!=', 'closed')
                    ->orderBy('last_message_at', 'desc')
                    ->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'workspace_id' => $workspaceId,
                        'contact_id' => $contact->id,
                        'assigned_to' => auth()->id(),
                        'channel' => 'slack',
                        'channel_conversation_id' => $this->toPhone,
                        'status' => 'open',
                        'priority' => 'normal',
                        'subject' => 'Slack #' . $this->toPhone,
                        'preview' => Str::limit($this->messageBody, 200),
                        'is_read' => true,
                        'messages_count' => 0,
                        'last_message_at' => now(),
                    ]);
                }

                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'workspace_id' => $workspaceId,
                    'direction' => 'outbound',
                    'sender_type' => 'agent',
                    'sender_id' => auth()->id(),
                    'type' => 'message',
                    'body_text' => $this->messageBody,
                    'delivery_status' => 'queued',
                    'sent_at' => now(),
                ]);

                // Test mode: bypass Slack API, mark as sent immediately
                if (config('services.channel_test_mode')) {
                    Log::info('ComposeEmail: TEST MODE — bypassing Slack API, marking as sent', ['message_id' => $message->id]);
                    $message->update(['delivery_status' => 'sent']);
                    $deliveryOk = true;
                } else {
                    $deliveryOk = false;
                    try {
                        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
                            ->where('channel', 'slack')
                            ->where('status', 'active')
                            ->first();

                        if ($integration) {
                            $creds = $integration->credentials ?? [];
                            $botToken = $creds['bot_token'] ?? '';

                            if (!$botToken) {
                                throw new \RuntimeException('Slack bot token not configured.');
                            }

                            $slackService = app(\App\Services\Channels\SlackService::class);
                            $slackService->postMessage($botToken, $this->toPhone, $this->messageBody);
                            $message->update(['delivery_status' => 'sent']);
                            $deliveryOk = true;
                            Log::info('ComposeEmail: Slack sent', ['channel' => $this->toPhone, 'msg_id' => $message->id]);
                        } else {
                            $message->update(['delivery_status' => 'failed', 'delivery_error' => 'No Slack integration configured']);
                            Log::warning('ComposeEmail: No Slack integration found');
                        }
                    } catch (\Throwable $e) {
                        $message->update(['delivery_status' => 'failed', 'delivery_error' => $e->getMessage()]);
                        Log::error('ComposeEmail: Slack send failed', ['error' => $e->getMessage()]);
                    }
                }

                // Update conversation
                $conversation->update([
                    'preview' => Str::limit($this->messageBody, 200),
                    'last_message_at' => now(),
                    'is_read' => true,
                ]);
                $conversation->increment('messages_count');

                $contact->update(['last_contacted_at' => now()]);
                $this->dispatch('conversations-updated');

                if ($deliveryOk) {
                    $testLabel = config('services.channel_test_mode') ? ' (Test Mode — not actually delivered)' : '';
                    session()->flash('compose-success', 'Slack message sent.' . $testLabel);
                } else {
                    session()->flash('compose-error', 'Slack delivery failed. Check channel settings.');
                }
                $this->redirect(route('inbox'), navigate: true);
            } catch (\Exception $e) {
                Log::error('ComposeEmail: Slack failed', ['error' => $e->getMessage()]);
                session()->flash('compose-error', 'Failed: ' . $e->getMessage());
            }
        });

        $this->sending = false;
    }

    // ---- Auto Save Draft ----

    public function autoSaveDraft(): void
    {
        if (!empty($this->subject) || !empty($this->body)) {
            $this->saveDraft();
        }
    }

    // ---- Group Send ----

    protected function sendToGroup(): void
    {
        $workspaceId = auth()->user()->active_workspace_id;

        // Plan limit check — block if monthly email limit reached
        $planLimitService = app(\App\Services\PlanLimitService::class);
        $workspace = auth()->user()->activeWorkspace;
        if (!$planLimitService->canUse($workspace, 'emails_per_month')) {
            $this->sending = false;
            session()->flash('error', 'You have reached your monthly email limit. Please upgrade your plan.');
            return;
        }

        $group = \App\Models\ContactList::where('workspace_id', $workspaceId)->find($this->selectedGroupId);

        if (! $group) {
            $this->addError('toSearch', 'Group not found.');
            $this->sending = false;
            return;
        }

        $contacts = $group->contacts()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('status', 'active')
            ->whereNull('unsubscribed_at')
            ->get();

        if ($contacts->isEmpty()) {
            $this->addError('toSearch', 'No active contacts with email in this group.');
            $this->sending = false;
            return;
        }

        $this->withOperationLock("compose-group-send-" . auth()->id(), function () use ($workspaceId, $contacts) {
            $account = EmailAccount::where('workspace_id', $workspaceId)
                ->where('id', $this->fromAccountId)
                ->firstOrFail();

            $sent = 0;
            foreach ($contacts as $contact) {
                try {
                    $conversation = Conversation::create([
                        'workspace_id' => $workspaceId,
                        'contact_id' => $contact->id,
                        'email_account_id' => $account->id,
                        'assigned_to' => auth()->id(),
                        'channel' => 'email',
                        'status' => 'closed',
                        'priority' => 'normal',
                        'subject' => $this->subject,
                        'preview' => Str::limit(strip_tags($this->body), 200),
                        'is_read' => true,
                        'messages_count' => 1,
                        'last_message_at' => now(),
                    ]);

                    $message = Message::create([
                        'conversation_id' => $conversation->id,
                        'workspace_id' => $workspaceId,
                        'direction' => 'outbound',
                        'sender_type' => 'agent',
                        'sender_id' => auth()->id(),
                        'type' => 'message',
                        'subject' => $this->subject,
                        'body_text' => strip_tags($this->body),
                        'body_html' => nl2br(e($this->body)),
                        'from_email' => $account->email,
                        'from_name' => $account->display_name ?? auth()->user()->name,
                        'to_emails' => [$contact->email],
                        'delivery_status' => 'queued',
                        'message_id_header' => '<' . Str::uuid() . '@' . parse_url(config('app.url'), PHP_URL_HOST) . '>',
                        'scheduled_at' => now()->addSeconds(5),
                        'schedule_status' => 'pending',
                    ]);

                    $sent++;
                } catch (\Throwable $e) {
                    \Log::warning("ComposeEmail: Failed to send to {$contact->email} in group send", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            session()->flash('success', "Email queued for {$sent} contacts in \"{$this->selectedGroupName}\".");
            $this->redirect(route('inbox'), navigate: true);
        });
    }

    // ---- Save as Draft ----

    public function saveDraft(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;
        $this->saving = true;

        try {
            $workspaceId = auth()->user()->active_workspace_id;

            $account = $this->fromAccountId
                ? EmailAccount::where('workspace_id', $workspaceId)->find($this->fromAccountId)
                : null;

            // Find or create contact if email provided
            $contact = null;
            if ($this->toEmail && filter_var($this->toEmail, FILTER_VALIDATE_EMAIL)) {
                $contact = Contact::firstOrCreate(
                    ['workspace_id' => $workspaceId, 'email' => $this->toEmail],
                    [
                        'first_name' => explode('@', $this->toEmail)[0],
                        'status' => 'active',
                    ]
                );
            }

            $conversation = Conversation::create([
                'workspace_id' => $workspaceId,
                'contact_id' => $contact?->id,
                'email_account_id' => $account?->id,
                'assigned_to' => auth()->id(),
                'channel' => 'email',
                'status' => 'open',
                'priority' => 'normal',
                'subject' => $this->subject ?: '(Draft)',
                'preview' => Str::limit(strip_tags($this->body), 200),
                'is_read' => true,
                'messages_count' => 1,
                'last_message_at' => now(),
            ]);

            $messageData = [
                'conversation_id' => $conversation->id,
                'workspace_id' => $workspaceId,
                'direction' => 'outbound',
                'sender_type' => 'agent',
                'sender_id' => auth()->id(),
                'type' => 'message',
                'subject' => $this->subject,
                'body_text' => strip_tags($this->body),
                'body_html' => nl2br(e($this->body)),
                'from_email' => $account?->email ?? auth()->user()->email,
                'from_name' => $account?->display_name ?? auth()->user()->name,
                'to_emails' => $this->toEmail ? [$this->toEmail] : [],
                'delivery_status' => 'queued',
            ];

            if ($this->ccEmails) {
                $messageData['cc_emails'] = array_filter(array_map('trim', explode(',', $this->ccEmails)));
            }
            if ($this->bccEmails) {
                $messageData['bcc_emails'] = array_filter(array_map('trim', explode(',', $this->bccEmails)));
            }

            $message = Message::create($messageData);

            $this->storeAttachments($message, $workspaceId);

            $this->dispatch('draft-saved');

            session()->flash('compose-success', 'Draft saved successfully.');
            $this->redirect(route('inbox'), navigate: true);
        } catch (\Exception $e) {
            Log::error('ComposeEmail: draft save failed', ['error' => $e->getMessage()]);
            session()->flash('compose-error', 'Failed to save draft: ' . $e->getMessage());
        }

        $this->saving = false;
    }

    // ---- Schedule Send ----

    public function scheduleSend(): void
    {
        if (!$this->authorizeWorkspaceAction('interact')) return;

        if (empty($this->scheduledAt)) {
            session()->flash('compose-error', 'Please select a date and time to schedule.');
            return;
        }

        // Parse the datetime-local input in the user's timezone so "10:51"
        // means 10:51 for them, not 10:51 on the server.
        $scheduledTime = \Carbon\Carbon::parse($this->scheduledAt, $this->userTimezone());

        if ($scheduledTime->isPast()) {
            session()->flash('compose-error', 'Scheduled time must be in the future.');
            return;
        }

        // Delegate to send() which checks $this->scheduledAt
        $this->send();
    }

    /**
     * Return the authenticated user's timezone. Simple priority:
     * profile → workspace → app default. The profile gets auto-populated
     * from the browser on inbox load via POST /inbox/api/set-timezone,
     * so by the time the user schedules anything, profile is correct.
     */
    protected function userTimezone(): string
    {
        $profile = auth()->user()->timezone;
        $workspace = auth()->user()->activeWorkspace?->timezone;
        $resolved = $profile ?: ($workspace ?: config('app.timezone', 'UTC'));

        Log::info('ComposeEmail: userTimezone resolved', [
            'profile_tz' => $profile,
            'workspace_tz' => $workspace,
            'resolved' => $resolved,
        ]);

        return $resolved;
    }

    /**
     * Set scheduledAt from a quick-pick preset. The preset is written in the
     * user's local time (wall-clock) so the datetime-local input shows what
     * they expect; we convert back to UTC on save.
     */
    public function setSchedulePreset(string $preset): void
    {
        $tz = $this->userTimezone();

        $this->scheduledAt = match ($preset) {
            'in_2_hours'    => now($tz)->addHours(2)->format('Y-m-d\TH:i'),
            'in_4_hours'    => now($tz)->addHours(4)->format('Y-m-d\TH:i'),
            'tomorrow_9am'  => now($tz)->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'),
            'monday_9am'    => now($tz)->next(\Carbon\Carbon::MONDAY)->setTime(9, 0)->format('Y-m-d\TH:i'),
            default         => $this->scheduledAt,
        };
    }

    // ---- Undo Send ----

    public function undoSend(): void
    {
        if ($this->pendingSendMessageId) {
            Message::where('id', $this->pendingSendMessageId)
                ->where('schedule_status', 'pending')
                ->update(['schedule_status' => 'cancelled']);

            $this->showUndoBar = false;
            $this->pendingSendMessageId = null;
            session()->flash('compose-success', 'Email send cancelled.');
        }
    }

    public function confirmSend(): void
    {
        if ($this->pendingSendMessageId) {
            $message = Message::find($this->pendingSendMessageId);
            if ($message && $message->schedule_status === 'pending') {
                // Send immediately — no queue, instant delivery
                SendScheduledEmailJob::dispatchSync($message);
            }
            $this->showUndoBar = false;
            $this->pendingSendMessageId = null;
            $this->dispatch('conversations-updated');
            $this->reset(['toEmail', 'toSearch', 'subject', 'body', 'ccEmails', 'bccEmails']);
        }
    }

    public function cancelScheduled(int $messageId): void
    {
        $message = Message::where('id', $messageId)
            ->where('schedule_status', 'pending')
            ->first();

        if ($message) {
            $message->update(['schedule_status' => 'cancelled']);
            session()->flash('compose-success', 'Scheduled email cancelled.');
        }
    }

    #[On('insert-canned-response')]
    public function insertCannedResponse(string $content): void
    {
        $this->body = ($this->body ? $this->body . "\n\n" : '') . $content;
    }

    // ---- AI Write ----

    public function toggleAiWrite(): void
    {
        $this->showAiWrite = !$this->showAiWrite;
        if (!$this->showAiWrite) {
            $this->aiPrompt = '';
            $this->aiGeneratedContent = '';
        }
    }

    public function aiWrite(): void
    {
        if (!trim($this->aiPrompt)) {
            return;
        }

        $this->aiLoading = true;
        $this->aiGeneratedContent = '';

        try {
            $workspace = auth()->user()->activeWorkspace;
            $aiManager = app(AIManager::class);

            $response = $aiManager->generateCompose($workspace, $this->aiPrompt, $this->aiTone, [
                'recipient_name' => $this->toEmail ? explode('@', $this->toEmail)[0] : null,
                'subject' => $this->subject ?: null,
                'sender_name' => auth()->user()->name,
            ]);

            $this->aiGeneratedContent = $response->content;
        } catch (\Exception $e) {
            session()->flash('compose-error', 'AI generation failed: ' . $e->getMessage());
        }

        $this->aiLoading = false;
    }

    public function acceptAiContent(): void
    {
        $this->body = $this->aiGeneratedContent;
        $this->aiGeneratedContent = '';
        $this->showAiWrite = false;
        $this->aiPrompt = '';
    }

    public function insertAiContent(): void
    {
        // Append to existing body instead of replacing
        $this->body = $this->body
            ? $this->body . "\n\n" . $this->aiGeneratedContent
            : $this->aiGeneratedContent;
        $this->aiGeneratedContent = '';
        $this->showAiWrite = false;
        $this->aiPrompt = '';
    }

    public function discardAiContent(): void
    {
        $this->aiGeneratedContent = '';
    }

    public function regenerateAi(): void
    {
        $this->aiGeneratedContent = '';
        $this->aiWrite();
    }

    // ---- Attachments ----

    public function addAttachment(): void
    {
        $this->validate([
            'attachments.*' => 'file|max:25600|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip,mp4,mp3',
        ]);
    }

    public function removeAttachment(int $index): void
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
        }
    }

    protected function storeAttachments(Message $message, int $workspaceId): void
    {
        if (empty($this->attachments)) {
            return;
        }

        foreach ($this->attachments as $index => $file) {
            try {
                // Get file info BEFORE any disk operations that might fail
                $originalName = $file->getClientOriginalName();
                $mimeType = $file->getMimeType() ?: 'application/octet-stream';
                $realPath = $file->getRealPath();

                if (!$realPath || !file_exists($realPath)) {
                    Log::warning("ComposeEmail: Attachment #{$index} temp file gone, skipping");
                    continue;
                }

                $fileSize = filesize($realPath);
                $filename = time() . '_' . Str::random(8) . '_' . $originalName;
                $storagePath = "attachments/{$workspaceId}/{$filename}";

                // Copy raw file content directly — bypasses Livewire's flysystem layer
                \Illuminate\Support\Facades\Storage::disk('local')->put($storagePath, file_get_contents($realPath));

                Attachment::create([
                    'message_id' => $message->id,
                    'filename' => $filename,
                    'original_filename' => $originalName,
                    'mime_type' => $mimeType,
                    'size' => $fileSize,
                    'storage_path' => $storagePath,
                    'is_inline' => false,
                ]);

                Log::info("ComposeEmail: Attachment stored", ['path' => $storagePath, 'name' => $originalName, 'size' => $fileSize]);
            } catch (\Throwable $e) {
                Log::error("ComposeEmail: Attachment FAILED: " . $e->getMessage());
            }
        }
    }

    // ---- Render ----

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $emailAccounts = EmailAccount::where('workspace_id', $workspaceId)
            ->where('status', 'connected')
            ->orderByDesc('is_default')
            ->get();

        $connectedChannels = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->pluck('channel')
            ->toArray();

        $cannedResponses = collect();
        if ($this->showCannedResponses && $this->cannedSearch) {
            $term = '%' . $this->cannedSearch . '%';
            $cannedResponses = \App\Models\CannedResponse::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                        ->orWhere('shortcut', 'like', $term)
                        ->orWhere('content', 'like', $term);
                })
                ->where(function ($q) {
                    $q->whereNull('user_id')
                        ->orWhere('user_id', auth()->id());
                })
                ->orderByDesc('usage_count')
                ->limit(8)
                ->get();
        }

        return view('livewire.inbox.compose-email', [
            'emailAccounts' => $emailAccounts,
            'connectedChannels' => $connectedChannels,
            'cannedResponses' => $cannedResponses,
        ]);
    }
}