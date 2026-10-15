<?php

namespace App\Livewire\Settings;

use App\Models\ChannelIntegration;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

class ChannelSettings extends Component
{
    use AuthorizesWorkspaceActions;

    public ?string $configuringChannel = null;

    // WhatsApp fields
    public string $whatsappPhoneNumberId = '';
    public string $whatsappAccessToken = '';
    public string $whatsappVerifyToken = '';
    public string $whatsappAppSecret = '';

    // Twilio SMS fields
    public string $twilioSid = '';
    public string $twilioAuthToken = '';
    public string $twilioPhoneNumber = '';

    // Telegram fields
    public string $telegramBotToken = '';

    // Slack fields
    public string $slackClientId = '';
    public string $slackClientSecret = '';
    public string $slackSigningSecret = '';

    // Live Chat fields
    public string $chatWidgetColor = '#4F46E5';
    public string $chatWelcomeMessage = 'Hi there! How can we help you today?';
    public string $chatCompanyName = '';
    public string $chatPosition = 'bottom-right';
    public string $chatOfflineMessage = 'We are currently offline. Leave a message and we will get back to you.';
    public bool $chatAiAutoReply = true;

    public string $saveStatus = '';

    public function configure(string $channel): void
    {
        $this->configuringChannel = $channel;
        $this->saveStatus = '';

        $workspaceId = auth()->user()->active_workspace_id;
        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', $channel)
            ->first();

        if ($integration) {
            // The model's getCredentialsAttribute() accessor already decrypts
            $creds = $integration->credentials ?? [];

            match ($channel) {
                'whatsapp' => $this->fillWhatsApp($creds),
                'sms' => $this->fillSms($creds),
                'telegram' => $this->fillTelegram($creds),
                'slack' => $this->fillSlack($creds),
                'chat' => $this->fillChat($creds, $integration->config ?? []),
                default => null,
            };
        }
    }

    public function closeConfig(): void
    {
        $this->configuringChannel = null;
        $this->saveStatus = '';
    }

    public function saveChannel(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;
        $channel = $this->configuringChannel;

        // Plan feature check — block if channel not available on current plan
        $featureMap = [
            'whatsapp' => 'whatsapp',
            'sms' => 'sms',
            'telegram' => 'telegram',
            'slack' => 'slack',
            'chat' => 'live_chat',
        ];
        $featureKey = $featureMap[$channel] ?? null;
        if ($featureKey) {
            $workspace = auth()->user()->activeWorkspace;
            if (!app(\App\Services\PlanLimitService::class)->hasFeature($workspace, $featureKey)) {
                session()->flash('error', ucfirst($channel) . ' is not available on your current plan. Please upgrade.');
                return;
            }
        }

        // Validate required credential fields per channel
        $this->validateChannelCredentials($channel);

        $credentials = match ($channel) {
            'whatsapp' => [
                'phone_number_id' => $this->whatsappPhoneNumberId,
                'access_token' => $this->whatsappAccessToken,
                'verify_token' => $this->whatsappVerifyToken,
                'app_secret' => $this->whatsappAppSecret,
            ],
            'sms' => [
                'sid' => $this->twilioSid,
                'auth_token' => $this->twilioAuthToken,
                'phone_number' => $this->twilioPhoneNumber,
            ],
            'telegram' => [
                'bot_token' => $this->telegramBotToken,
            ],
            'slack' => [
                'client_id' => $this->slackClientId,
                'client_secret' => $this->slackClientSecret,
                'signing_secret' => $this->slackSigningSecret,
            ],
            'chat' => [
                'widget_color' => $this->chatWidgetColor,
                'welcome_message' => $this->chatWelcomeMessage,
                'company_name' => $this->chatCompanyName,
                'position' => $this->chatPosition,
                'offline_message' => $this->chatOfflineMessage,
            ],
            default => [],
        };

        $config = $channel === 'chat' ? ['ai_auto_reply' => $this->chatAiAutoReply] : [];

        $hasCredentials = collect($credentials)->filter(fn ($v) => ! empty($v))->isNotEmpty();

        // Top-level columns that the rest of the app queries directly
        // (campaign editor's "From number" dropdown reads phone_number off
        // the channel_integrations row, not the encrypted credentials JSON).
        // Mirror SMS phone + WhatsApp phone-number-id into the dedicated columns.
        $topLevel = [
            'credentials' => $credentials,
            'config' => $config,
            'status' => $hasCredentials ? 'active' : 'inactive',
            'ai_auto_reply' => $channel === 'chat' ? $this->chatAiAutoReply : false,
        ];
        if ($channel === 'sms') {
            $topLevel['phone_number'] = $this->twilioPhoneNumber ?: null;
            $topLevel['account_name'] = $this->twilioSid ? 'Twilio' : null;
        } elseif ($channel === 'whatsapp') {
            $topLevel['phone_number'] = $this->whatsappPhoneNumberId ?: null;
        }

        // Pass plain credentials — the model's setCredentialsAttribute() mutator encrypts them
        ChannelIntegration::updateOrCreate(
            ['workspace_id' => $workspaceId, 'channel' => $channel],
            $topLevel
        );

        // Ensure a chat_widgets row exists so the embed script has a public_id token
        if ($channel === 'chat') {
            $widgetData = [
                'primary_color' => $this->chatWidgetColor,
                'welcome_message' => $this->chatWelcomeMessage,
                'company_name' => $this->chatCompanyName ?: null,
                'position' => $this->chatPosition,
                'offline_message' => $this->chatOfflineMessage,
                'ai_auto_reply' => $this->chatAiAutoReply,
                'updated_at' => now(),
            ];

            $exists = DB::table('chat_widgets')->where('workspace_id', $workspaceId)->exists();
            if (! $exists) {
                DB::table('chat_widgets')->insert(array_merge($widgetData, [
                    'workspace_id' => $workspaceId,
                    'public_id' => Str::random(32),
                    'created_at' => now(),
                ]));
            } else {
                DB::table('chat_widgets')->where('workspace_id', $workspaceId)->update($widgetData);
            }
        }

        // Auto-register webhooks if on HTTPS
        if ($hasCredentials && str_starts_with(config('app.url'), 'https')) {
            try {
                match ($channel) {
                    'telegram' => (function () use ($credentials) {
                        $service = new \App\Services\Channels\TelegramService($credentials['bot_token']);
                        $service->setWebhook(url('/api/webhooks/telegram'));
                        \Log::info("ChannelSettings: Telegram webhook auto-registered");
                    })(),
                    'whatsapp' => \Log::info("ChannelSettings: WhatsApp webhook must be set manually in Meta Dashboard: " . url('/api/webhooks/whatsapp')),
                    'sms' => \Log::info("ChannelSettings: Twilio webhook must be set manually in Twilio Console: " . url('/api/webhooks/twilio/incoming')),
                    'slack' => \Log::info("ChannelSettings: Slack webhook must be set manually in Slack App settings: " . url('/api/webhooks/slack/events')),
                    default => null,
                };
            } catch (\Throwable $e) {
                \Log::warning("ChannelSettings: Auto-webhook failed for {$channel}: {$e->getMessage()}");
            }
        }

        $this->saveStatus = 'saved';
        session()->flash('success', ucfirst($channel) . ' channel settings saved.');
    }

    public function disconnect(string $channel): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;
        ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', $channel)
            ->update(['status' => 'inactive']);

        session()->flash('success', ucfirst($channel) . ' channel disconnected.');
    }

    /**
     * Validate that required credentials are present for the given channel.
     */
    protected function validateChannelCredentials(string $channel): void
    {
        $rules = match ($channel) {
            'whatsapp' => [
                'whatsappPhoneNumberId' => 'required|string|max:255',
                'whatsappAccessToken' => 'required|string|max:1000',
                'whatsappVerifyToken' => 'required|string|max:255',
                'whatsappAppSecret' => 'required|string|max:255',
            ],
            'sms' => [
                'twilioSid' => 'required|string|max:255',
                'twilioAuthToken' => 'required|string|max:255',
                'twilioPhoneNumber' => 'required|string|max:30',
            ],
            'telegram' => [
                'telegramBotToken' => 'required|string|max:255',
            ],
            'slack' => [
                'slackClientId' => 'required|string|max:255',
                'slackClientSecret' => 'required|string|max:255',
                'slackSigningSecret' => 'required|string|max:255',
            ],
            'chat' => [
                'chatWidgetColor' => 'required|string|max:20',
                'chatWelcomeMessage' => 'required|string|max:500',
                'chatCompanyName' => 'nullable|string|max:100',
                'chatPosition' => 'required|string|in:bottom-right,bottom-left',
                'chatOfflineMessage' => 'nullable|string|max:500',
            ],
            default => [],
        };

        if ($rules) {
            $this->validate($rules);
        }
    }

    protected function fillWhatsApp(array $creds): void
    {
        $this->whatsappPhoneNumberId = $creds['phone_number_id'] ?? '';
        $this->whatsappAccessToken = $creds['access_token'] ?? '';
        $this->whatsappVerifyToken = $creds['verify_token'] ?? '';
        $this->whatsappAppSecret = $creds['app_secret'] ?? '';
    }

    protected function fillSms(array $creds): void
    {
        $this->twilioSid = $creds['sid'] ?? '';
        $this->twilioAuthToken = $creds['auth_token'] ?? '';
        $this->twilioPhoneNumber = $creds['phone_number'] ?? '';
    }

    protected function fillTelegram(array $creds): void
    {
        $this->telegramBotToken = $creds['bot_token'] ?? '';
    }

    protected function fillSlack(array $creds): void
    {
        $this->slackClientId = $creds['client_id'] ?? '';
        $this->slackClientSecret = $creds['client_secret'] ?? '';
        $this->slackSigningSecret = $creds['signing_secret'] ?? '';
    }

    protected function fillChat(array $creds, array $config): void
    {
        $this->chatWidgetColor = $creds['widget_color'] ?? '#4F46E5';
        $this->chatWelcomeMessage = $creds['welcome_message'] ?? 'Hi there! How can we help you today?';
        $this->chatCompanyName = $creds['company_name'] ?? '';
        $this->chatPosition = $creds['position'] ?? 'bottom-right';
        $this->chatOfflineMessage = $creds['offline_message'] ?? 'We are currently offline. Leave a message and we will get back to you.';
        $this->chatAiAutoReply = $config['ai_auto_reply'] ?? true;
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $integrations = ChannelIntegration::where('workspace_id', $workspaceId)
            ->get()
            ->keyBy('channel');

        // Get the chat widget public_id for the embed code
        $chatWidgetPublicId = DB::table('chat_widgets')
            ->where('workspace_id', $workspaceId)
            ->value('public_id');

        return view('livewire.settings.channel-settings', [
            'integrations' => $integrations,
            'chatWidgetPublicId' => $chatWidgetPublicId,
        ]);
    }
}
