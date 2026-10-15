<div class="space-y-6" x-data="{ dirty: false }" @change="dirty = true" @saved.window="dirty = false"
     x-on:livewire:navigating.window="if(dirty && !confirm('You have unsaved changes. Leave anyway?')) $event.preventDefault()"
     x-init="window.addEventListener('beforeunload', (e) => { if(dirty) { e.preventDefault(); e.returnValue = '' } })">
    <div>
        <h1 class="text-2xl font-bold text-ink">{{ __('Channel Integrations') }}</h1>
        <p class="text-sm text-muted mt-1">{{ __('Connect messaging channels to manage all conversations in one place.') }}</p>
    </div>

    @php
    $channels = [
        ['key' => 'whatsapp', 'name' => __('WhatsApp'), 'desc' => __('Connect WhatsApp Business API for messaging.'), 'icon' => 'message-circle', 'bg' => 'bg-success/10', 'text' => 'text-success'],
        ['key' => 'sms', 'name' => __('SMS (Twilio)'), 'desc' => __('Send and receive SMS via Twilio.'), 'icon' => 'phone', 'bg' => 'bg-danger/10', 'text' => 'text-danger'],
        ['key' => 'telegram', 'name' => __('Telegram'), 'desc' => __('Connect your Telegram bot.'), 'icon' => 'send', 'bg' => 'bg-info/10', 'text' => 'text-blue-600'],
        ['key' => 'slack', 'name' => __('Slack'), 'desc' => __('Message Slack channels two-way — bot posts replies, channel messages land in inbox.'), 'icon' => 'hash', 'bg' => 'bg-brand/10', 'text' => 'text-purple-600'],
        ['key' => 'chat', 'name' => __('Live Chat'), 'desc' => __('Embed a chat widget on your website.'), 'icon' => 'message-square', 'bg' => 'bg-cyan-50', 'text' => 'text-cyan-600'],
    ];
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($channels as $ch)
        @php $integration = $integrations[$ch['key']] ?? null; $connected = $integration && $integration->status === 'active'; @endphp
        <div class="bg-surface-2 rounded-2xl border border-border p-6 hover:shadow-md transition-all duration-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 {{ $ch['bg'] }} rounded-xl flex items-center justify-center">
                    <x-icon :name="$ch['icon']" class="w-6 h-6 {{ $ch['text'] }}" />
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-ink">{{ $ch['name'] }}</h3>
                    <p class="text-xs text-muted mt-0.5">{{ $ch['desc'] }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between mt-5 pt-4 border-t border-border">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5 text-xs font-medium {{ $connected ? 'text-success' : 'text-muted' }}">
                        <span class="w-2 h-2 rounded-full {{ $connected ? 'bg-success/100' : 'bg-gray-300' }}"></span>
                        {{ $connected ? 'Connected' : 'Not connected' }}
                    </span>
                    @if($connected)
                    <a href="{{ url('/inbox/compose?channel=' . $ch['key']) }}" class="text-xs text-brand hover:text-brand font-medium">
                        Send test message &rarr;
                    </a>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if($connected)
                    <button wire:click="disconnect('{{ $ch['key'] }}')" wire:confirm="Disconnect {{ $ch['name'] }}? You will need to reconfigure this channel."
                            class="px-3 py-1.5 text-xs font-medium text-danger border border-danger/20 rounded-lg hover:bg-danger/10 transition-colors">
                        Disconnect
                    </button>
                    @endif
                    <button wire:click="configure('{{ $ch['key'] }}')"
                            class="px-4 py-2 text-sm font-medium text-white bg-brand rounded-xl hover:bg-brand-strong transition-colors">
                        {{ $connected ? 'Edit' : 'Configure' }}
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Configure Modal --}}
    @if($configuringChannel)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="channel-config-modal-title">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeConfig"></div>
            <div class="relative bg-surface-2 rounded-2xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto" x-trap="$wire.configuringChannel">
                <div class="flex items-center justify-between mb-5">
                    <h2 id="channel-config-modal-title" class="text-lg font-semibold text-ink">
                        Configure
                        @switch($configuringChannel)
                            @case('whatsapp') WhatsApp @break
                            @case('sms') SMS (Twilio) @break
                            @case('telegram') Telegram @break
                            @case('slack') Slack @break
                            @case('chat') Live Chat @break
                        @endswitch
                    </h2>
                    <button wire:click="closeConfig" class="p-2 text-muted/60 hover:text-ink rounded-lg hover:bg-surface">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                @if($saveStatus === 'saved')
                <div class="mb-4 p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Settings saved successfully!
                </div>
                @endif

                <div class="space-y-4">
                    {{-- WhatsApp --}}
                    @if($configuringChannel === 'whatsapp')
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Phone Number ID') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="whatsappPhoneNumberId" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="{{ __('e.g. 1234567890') }}">
                        <p class="text-xs text-muted mt-1">{{ __('From Meta Developer Dashboard') }} → WhatsApp → API Setup</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Access Token') }} <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="whatsappAccessToken" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="{{ __('Permanent system user token') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Verify Token') }}</label>
                        <input type="text" wire:model="whatsappVerifyToken" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="{{ __('Custom verify token for webhook') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('App Secret') }} <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="whatsappAppSecret" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="{{ __('Meta App Secret — signs webhook deliveries') }}">
                        <p class="text-xs text-muted mt-1">{{ __('Meta Developer Dashboard → App Settings → Basic → App secret (click Show). Required for webhook signature verification.') }}</p>
                    </div>
                    <div class="bg-surface rounded-xl p-4">
                        <p class="text-xs font-medium text-ink/80 mb-1">{{ __('Notification URL') }}</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 text-xs bg-surface-2 border border-border rounded-lg px-3 py-2 text-muted  truncate">{{ url('/api/webhooks/whatsapp') }}</code>
                            <button x-data="{ copied: false }"
                                    @click="navigator.clipboard.writeText('{{ url('/api/webhooks/whatsapp') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="px-3 py-2 text-xs font-medium text-brand bg-brand/10 rounded-lg hover:bg-brand/15">
                                <span x-show="!copied">{{ __('Copy') }}</span>
                                <span x-show="copied" x-cloak class="text-success">{{ __('Copied!') }}</span>
                            </button>
                        </div>
                        <p class="text-xs text-muted mt-2">{{ __('Paste this URL in Meta Developer Dashboard') }} → WhatsApp → Configuration → Webhook URL</p>
                    </div>
                    <div class="bg-blue-500/5 border border-blue-500/10 rounded-xl p-4 space-y-2">
                        <p class="text-xs font-semibold text-blue-400">{{ __('Setup Guide') }}</p>
                        <ol class="text-xs text-muted space-y-1.5 list-decimal list-inside">
                            <li>{{ __('Go to') }} <a href="https://developers.facebook.com" target="_blank" class="text-blue-400 underline">developers.facebook.com</a> → {{ __('Create an App (Business type)') }}</li>
                            <li>{{ __('Add WhatsApp product to your app') }}</li>
                            <li>{{ __('Go to WhatsApp → API Setup → copy Phone Number ID') }}</li>
                            <li>{{ __('Create a System User in Business Settings → generate a Permanent Token with whatsapp_business_messaging permission') }}</li>
                            <li>{{ __('Paste Phone Number ID and Access Token above') }}</li>
                            <li>{{ __('Go to WhatsApp → Configuration → Webhook → paste the Notification URL above') }}</li>
                            <li>{{ __('Subscribe to "messages" webhook field') }}</li>
                            <li>{{ __('Set the Verify Token (same value as above)') }}</li>
                        </ol>
                    </div>
                    @endif

                    {{-- SMS (Twilio) --}}
                    @if($configuringChannel === 'sms')
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Account SID') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="twilioSid" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="ACxxxxxxxxxxxxxxxx">
                        <p class="text-xs text-muted mt-1">{{ __('From Twilio Console') }} → Account Info</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Auth Token') }} <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="twilioAuthToken" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="{{ __('Your auth token') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Phone Number') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="twilioPhoneNumber" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="+1234567890">
                    </div>
                    <div class="bg-surface rounded-xl p-4">
                        <p class="text-xs font-medium text-ink/80 mb-1">{{ __('Webhook URLs') }}</p>
                        <div class="space-y-2">
                            <div>
                                <span class="text-xs text-muted">{{ __('Incoming:') }}</span>
                                <div class="flex items-center gap-2 mt-1">
                                    <code class="flex-1 text-xs bg-surface-2 border border-border rounded-lg px-3 py-2 text-muted  truncate">{{ url('/api/webhooks/twilio/incoming') }}</code>
                                    <button x-data="{ copied: false }"
                                            @click="navigator.clipboard.writeText('{{ url('/api/webhooks/twilio/incoming') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="px-3 py-2 text-xs font-medium text-brand bg-brand/10 rounded-lg hover:bg-brand/15">
                                        <span x-show="!copied">{{ __('Copy') }}</span>
                                        <span x-show="copied" x-cloak class="text-success">{{ __('Copied!') }}</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <span class="text-xs text-muted">{{ __('Status:') }}</span>
                                <div class="flex items-center gap-2 mt-1">
                                    <code class="flex-1 text-xs bg-surface-2 border border-border rounded-lg px-3 py-2 text-muted  truncate">{{ url('/api/webhooks/twilio/status') }}</code>
                                    <button x-data="{ copied: false }"
                                            @click="navigator.clipboard.writeText('{{ url('/api/webhooks/twilio/status') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="px-3 py-2 text-xs font-medium text-brand bg-brand/10 rounded-lg hover:bg-brand/15">
                                        <span x-show="!copied">{{ __('Copy') }}</span>
                                        <span x-show="copied" x-cloak class="text-success">{{ __('Copied!') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-blue-500/5 border border-blue-500/10 rounded-xl p-4 space-y-2">
                        <p class="text-xs font-semibold text-blue-400">{{ __('Setup Guide') }}</p>
                        <ol class="text-xs text-muted space-y-1.5 list-decimal list-inside">
                            <li>{{ __('Go to') }} <a href="https://console.twilio.com" target="_blank" class="text-blue-400 underline">console.twilio.com</a> → {{ __('sign up or log in') }}</li>
                            <li>{{ __('Copy Account SID and Auth Token from the dashboard') }}</li>
                            <li>{{ __('Buy a phone number (Phone Numbers → Buy a Number)') }}</li>
                            <li>{{ __('Paste SID, Auth Token, and Phone Number above') }}</li>
                            <li>{{ __('Go to Phone Numbers → Active Numbers → click your number') }}</li>
                            <li>{{ __('Under Messaging → "A message comes in" → paste the Incoming webhook URL above') }}</li>
                            <li>{{ __('Under "Status callback URL" → paste the Status webhook URL above') }}</li>
                            <li>{{ __('Save and test by sending an SMS to your Twilio number') }}</li>
                        </ol>
                    </div>
                    @endif

                    {{-- Telegram --}}
                    @if($configuringChannel === 'telegram')
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Bot Token') }} <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="telegramBotToken" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
                        <p class="text-xs text-muted mt-1">{{ __('Message @BotFather on Telegram') }} → /newbot → copy the token</p>
                    </div>
                    <div class="bg-surface rounded-xl p-4">
                        <p class="text-xs font-medium text-ink/80 mb-1">{{ __('Notification URL') }}</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 text-xs bg-surface-2 border border-border rounded-lg px-3 py-2 text-muted  truncate">{{ url('/api/webhooks/telegram') }}</code>
                            <button x-data="{ copied: false }"
                                    @click="navigator.clipboard.writeText('{{ url('/api/webhooks/telegram') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="px-3 py-2 text-xs font-medium text-brand bg-brand/10 rounded-lg hover:bg-brand/15">
                                <span x-show="!copied">{{ __('Copy') }}</span>
                                <span x-show="copied" x-cloak class="text-success">{{ __('Copied!') }}</span>
                            </button>
                        </div>
                        <p class="text-xs text-muted mt-2">{{ __('The webhook is set automatically when you save (HTTPS required).') }}</p>
                    </div>
                    <div class="bg-blue-500/5 border border-blue-500/10 rounded-xl p-4 space-y-2">
                        <p class="text-xs font-semibold text-blue-400">{{ __('Setup Guide') }}</p>
                        <ol class="text-xs text-muted space-y-1.5 list-decimal list-inside">
                            <li>{{ __('Open Telegram → search for') }} <strong class="text-ink">@BotFather</strong></li>
                            <li>{{ __('Send') }} <code class="bg-white/5 px-1 rounded">/newbot</code> → {{ __('follow the prompts to name your bot') }}</li>
                            <li>{{ __('BotFather gives you a token like') }} <code class="bg-white/5 px-1 rounded">123456:ABC-DEF...</code> → {{ __('copy it') }}</li>
                            <li>{{ __('Paste the token above and click Save') }}</li>
                            <li>{{ __('The webhook URL is registered automatically (requires HTTPS)') }}</li>
                            <li>{{ __('Users must send') }} <code class="bg-white/5 px-1 rounded">/start</code> {{ __('to your bot before you can message them') }}</li>
                            <li>{{ __('Use the chat ID or @username to send messages from the inbox') }}</li>
                        </ol>
                    </div>
                    @endif

                    {{-- Slack --}}
                    @if($configuringChannel === 'slack')
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Client ID') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="slackClientId" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="{{ __('Your Slack app Client ID') }}">
                        <p class="text-xs text-muted mt-1">{{ __('From api.slack.com/apps') }} → Your App → Basic Information</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Client Secret') }} <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="slackClientSecret" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Signing Secret') }} <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="slackSigningSecret" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface">
                    </div>
                    <div class="bg-surface rounded-xl p-4 space-y-2">
                        <p class="text-xs font-medium text-ink/80">{{ __('Slack App URLs') }}</p>
                        <div><span class="text-xs text-muted">{{ __('Events:') }}</span> <code class="text-xs">{{ url('/api/webhooks/slack/events') }}</code></div>
                        <div><span class="text-xs text-muted">{{ __('Commands:') }}</span> <code class="text-xs">{{ url('/api/webhooks/slack/commands') }}</code></div>
                        <div><span class="text-xs text-muted">{{ __('Interactions:') }}</span> <code class="text-xs">{{ url('/api/webhooks/slack/interactions') }}</code></div>
                    </div>
                    <div class="bg-blue-500/5 border border-blue-500/10 rounded-xl p-4 space-y-2">
                        <p class="text-xs font-semibold text-blue-400">{{ __('Setup Guide') }}</p>
                        <ol class="text-xs text-muted space-y-1.5 list-decimal list-inside">
                            <li>{{ __('Go to') }} <a href="https://api.slack.com/apps" target="_blank" class="text-blue-400 underline">api.slack.com/apps</a> → {{ __('Create New App → From Scratch') }}</li>
                            <li>{{ __('Copy Client ID, Client Secret, Signing Secret from Basic Information') }}</li>
                            <li>{{ __('Paste them above') }}</li>
                            <li>{{ __('Go to Event Subscriptions → Enable → paste the Events URL above') }}</li>
                            <li>{{ __('Subscribe to bot events:') }} <code class="bg-white/5 px-1 rounded">message.channels</code>, <code class="bg-white/5 px-1 rounded">message.im</code></li>
                            <li>{{ __('Go to Interactivity → Enable → paste the Interactions URL above') }}</li>
                            <li>{{ __('Go to Slash Commands → create commands → paste the Commands URL above') }}</li>
                            <li>{{ __('Go to OAuth & Permissions → add scopes:') }} <code class="bg-white/5 px-1 rounded">chat:write</code>, <code class="bg-white/5 px-1 rounded">channels:history</code>, <code class="bg-white/5 px-1 rounded">im:history</code></li>
                            <li>{{ __('Install app to workspace → authorize') }}</li>
                            <li>{{ __('Use channel name (e.g. #general) or channel ID to send messages') }}</li>
                        </ol>
                    </div>
                    @endif

                    {{-- Live Chat --}}
                    @if($configuringChannel === 'chat')
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Company / Brand Name') }}</label>
                        <input type="text" wire:model="chatCompanyName" placeholder="{{ __('e.g. Acme Support') }}" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-brand/40">
                        <p class="text-[10px] text-muted mt-1">{{ __('Shown in the chat header. Leave blank to use workspace name.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Widget Color') }}</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model="chatWidgetColor" class="w-10 h-10 rounded-lg border border-border cursor-pointer">
                            <input type="text" wire:model="chatWidgetColor" class="flex-1 px-4 py-2.5 text-sm border border-border rounded-xl bg-surface">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Position') }}</label>
                        <select wire:model="chatPosition" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-brand/40">
                            <option value="bottom-right">{{ __('Bottom Right') }}</option>
                            <option value="bottom-left">{{ __('Bottom Left') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Welcome Message') }}</label>
                        <textarea wire:model="chatWelcomeMessage" rows="2" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="{{ __('Hi there! How can we help you today?') }}"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Offline Message') }}</label>
                        <textarea wire:model="chatOfflineMessage" rows="2" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface" placeholder="{{ __("We're offline. Leave a message...") }}"></textarea>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-surface rounded-xl">
                        <div>
                            <span class="text-sm text-ink/80">{{ __('AI Auto-Reply in chat') }}</span>
                            <p class="text-[10px] text-muted">{{ __('AI will reply to visitors when enabled') }}</p>
                        </div>
                        <button wire:click="$toggle('chatAiAutoReply')" type="button"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $chatAiAutoReply ? 'bg-brand' : 'bg-gray-200 dark:bg-gray-700' }}">
                            <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $chatAiAutoReply ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </div>
                    <div class="bg-surface rounded-xl p-4">
                        <p class="text-xs font-medium text-ink/80 mb-1">{{ __('Embed Code') }}</p>
                        @if($chatWidgetPublicId)
                            <code class="block text-xs bg-surface-2 border border-border rounded-lg px-3 py-2 text-muted break-all">&lt;script src="{{ url('/widget/v1.js') }}" data-workspace="{{ $chatWidgetPublicId }}" data-color="{{ $chatWidgetColor }}" data-position="{{ $chatPosition }}"&gt;&lt;/script&gt;</code>
                            <p class="text-[10px] text-muted mt-1.5">{{ __('Paste this before') }} &lt;/body&gt; {{ __('on any page where you want the widget.') }}</p>
                        @else
                            <p class="text-xs text-warning">{{ __('Save the channel first to generate the embed code.') }}</p>
                        @endif
                    </div>
                    <div class="bg-blue-500/5 border border-blue-500/10 rounded-xl p-4 space-y-2">
                        <p class="text-xs font-semibold text-blue-400">{{ __('Setup Guide') }}</p>
                        <ol class="text-xs text-muted space-y-1.5 list-decimal list-inside">
                            <li>{{ __('Customize the widget color, welcome message, and position above') }}</li>
                            <li>{{ __('Click Save & Connect') }}</li>
                            <li>{{ __('Copy the embed script code shown above') }}</li>
                            <li>{{ __('Paste it before') }} <code class="bg-white/5 px-1 rounded">&lt;/body&gt;</code> {{ __('on your website pages') }}</li>
                            <li>{{ __('Visitors will see a chat bubble on your website') }}</li>
                            <li>{{ __('Messages appear in your inbox under Live Chat channel') }}</li>
                            <li>{{ __('Enable AI Auto-Reply to auto-respond to visitors') }}</li>
                        </ol>
                    </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-border">
                    <button wire:click="closeConfig" class="px-4 py-2.5 text-sm font-medium text-muted  hover:text-ink dark:text-gray-200">{{ __('Cancel') }}</button>
                    <button wire:click="saveChannel" class="px-6 py-2.5 bg-brand text-white text-sm font-medium rounded-xl hover:bg-brand-strong transition-colors">
                        <span wire:loading.remove wire:target="saveChannel">{{ __('Save & Connect') }}</span>
                        <span wire:loading wire:target="saveChannel" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Saving...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
