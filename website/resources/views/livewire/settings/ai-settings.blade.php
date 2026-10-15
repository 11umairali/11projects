<div class="space-y-6">
    {{-- Page header --}}
    <div>
        <h1 class="text-2xl font-bold text-ink">{{ __('AI Configuration') }}</h1>
        <p class="text-sm text-muted mt-1">
            {{ __('Configure how AI responds across each channel. Provider and billing apply globally; behavior is per-channel.') }}
        </p>
    </div>

    @if(session('success'))
        <div class="bg-success/10 border border-success/20 text-success rounded-xl px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-danger/10 border border-danger/20 text-danger rounded-xl px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════
         PLAN STATUS — explains which premium AI features the user's
         current plan unlocks. Shown only when at least one premium
         feature is locked, so paying customers don't see clutter.
         ════════════════════════════════════════════════════════════════ --}}
    @php
        $allUnlocked = ($planFeatures['ai_own_key'] ?? false)
            && ($planFeatures['ai_per_channel'] ?? false)
            && ($planFeatures['ai_auto_escalation'] ?? false);
    @endphp
    @if(!$allUnlocked)
    <div class="bg-warning/10 border border-warning/30 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-warning flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-ink">{{ __('Some AI features require a higher plan') }}</p>
            <ul class="text-xs text-muted mt-1 space-y-0.5">
                @if(!($planFeatures['ai_own_key'] ?? false))
                    <li>• {{ __('Use your own OpenAI / Anthropic API key — Pro plan') }}</li>
                @endif
                @if(!($planFeatures['ai_per_channel'] ?? false))
                    <li>• {{ __('Per-channel AI customization (different prompts/modes for email vs WhatsApp etc.) — Pro plan') }}</li>
                @endif
                @if(!($planFeatures['ai_auto_escalation'] ?? false))
                    <li>• {{ __('Auto-escalation to human when AI confidence is low — Pro plan') }}</li>
                @endif
            </ul>
            <a href="{{ url('/settings/billing') }}" wire:navigate class="inline-flex items-center gap-1 mt-2 text-xs font-semibold text-warning hover:underline">
                {{ __('Upgrade plan →') }}
            </a>
        </div>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════
         GLOBAL: PROVIDER & BILLING
         ════════════════════════════════════════════════════════════════ --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
        <div>
            <h2 class="text-lg font-semibold text-ink">{{ __('AI Provider & Billing') }}</h2>
            <p class="text-sm text-muted mt-1">
                {{ __('These settings apply to every channel — they control which LLM you pay for.') }}
            </p>
        </div>

        @php $canOwnKey = $planFeatures['ai_own_key'] ?? false; @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">
                    {{ __('Provider') }}
                    @if(!$canOwnKey)
                        <span class="inline-flex items-center gap-1 ml-1 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider bg-warning/15 text-warning rounded-full align-middle">
                            {{ __('Platform Default') }}
                        </span>
                    @endif
                </label>
                <select wire:model.live="provider"
                        @disabled(!$canOwnKey)
                        class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-60 disabled:cursor-not-allowed">
                    <option value="openai">OpenAI</option>
                    <option value="anthropic">Anthropic</option>
                    <option value="gemini">Google Gemini</option>
                    <option value="mistral">Mistral</option>
                </select>
                @if(!$canOwnKey)
                    <p class="text-[10px] text-muted mt-1">
                        {{ __('Locked to platform default. Upgrade to Pro to choose your own provider.') }}
                    </p>
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">
                    {{ __('Model') }}
                    @if(!$canOwnKey)
                        <span class="inline-flex items-center gap-1 ml-1 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider bg-warning/15 text-warning rounded-full align-middle">
                            {{ __('Platform Default') }}
                        </span>
                    @endif
                </label>
                @php
                    // Source models directly from each provider's AVAILABLE_MODELS
                    // constant — that's the same list AIManager hands to the API,
                    // so the dropdown can't drift from what's actually callable.
                    $providerClasses = [
                        'openai'    => \App\Services\AI\Providers\OpenAIProvider::class,
                        'anthropic' => \App\Services\AI\Providers\AnthropicProvider::class,
                        'gemini'    => \App\Services\AI\Providers\GeminiProvider::class,
                        'mistral'   => \App\Services\AI\Providers\MistralProvider::class,
                    ];
                    $providerClass = $providerClasses[$provider] ?? $providerClasses['openai'];
                    $availableModels = $providerClass::AVAILABLE_MODELS;
                @endphp
                <select wire:model.live="model"
                        @disabled(!$canOwnKey)
                        class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-60 disabled:cursor-not-allowed">
                    @foreach($availableModels as $id => $meta)
                        <option value="{{ $id }}">{{ $meta['name'] }} — {{ $meta['description'] }}</option>
                    @endforeach
                </select>
                @if(!$canOwnKey)
                    <p class="text-[10px] text-muted mt-1">
                        {{ __('Set by your administrator. Upgrade to Pro to override the model.') }}
                    </p>
                @endif
            </div>
        </div>

        <div>
            @php $canOwnKey = $planFeatures['ai_own_key'] ?? false; @endphp
            <label class="flex items-center gap-2 text-sm {{ $canOwnKey ? '' : 'opacity-60' }}">
                <input type="checkbox" wire:model.live="useOwnKey"
                       @disabled(!$canOwnKey)
                       class="h-4 w-4 rounded border-border text-primary-600 disabled:cursor-not-allowed">
                <span class="text-ink/80">{{ __('Use my own API key') }}</span>
                @if(!$canOwnKey)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-warning/15 text-warning rounded-full">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        {{ __('Pro') }}
                    </span>
                @endif
            </label>
            <p class="text-xs text-muted mt-1 ml-6">
                @if($canOwnKey)
                    {{ __('When ON: your key is used (you pay the LLM bill). When OFF: platform admin keys are used (we pay).') }}
                @else
                    {{ __('Your plan uses our platform API keys. Upgrade to Pro to plug in your own OpenAI / Anthropic key.') }}
                @endif
            </p>
        </div>

        @if($useOwnKey && $canOwnKey)
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('API Key') }}</label>
            <div class="flex gap-2">
                <input type="password" wire:model="apiKey"
                       class="flex-1 px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 font-mono"
                       placeholder="sk-...">
                <button type="button" wire:click="testApiKey" wire:loading.attr="disabled"
                        class="px-4 py-2.5 text-sm font-medium bg-surface border border-border rounded-xl hover:bg-surface-2">
                    <span wire:loading.remove wire:target="testApiKey">{{ __('Test') }}</span>
                    <span wire:loading wire:target="testApiKey">{{ __('Testing...') }}</span>
                </button>
            </div>
            @if($testResult)
                @php [$status, $msg] = explode(':', $testResult, 2) + [null, null]; @endphp
                <p class="text-xs mt-1 {{ $status === 'success' ? 'text-success' : 'text-danger' }}">{{ $msg }}</p>
            @endif
        </div>
        @endif

        <div class="max-w-xs">
            <label class="text-sm font-medium text-ink/80 mb-1 block">{{ __('Monthly cost cap (USD)') }}</label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm">$</span>
                <input type="number" step="1" min="0" wire:model="monthlyCostLimit"
                       class="w-full pl-7 pr-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500"
                       placeholder="10.00">
            </div>
            <p class="text-xs text-muted mt-1">{{ __('Set to 0 for unlimited. Applies across all channels.') }}</p>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════
         PER-CHANNEL CARDS
         One card per supported channel. Each has its own enable toggle,
         prompt, send mode, confidence threshold, escalation, and skip
         filters. Cards collapse when not expanded so the page stays scan-able.
         ════════════════════════════════════════════════════════════════ --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-ink">{{ __('Per-Channel AI Behavior') }}</h2>
                <p class="text-xs text-muted mt-0.5">
                    {{ __('Each channel has its own AI settings. Click a card to expand.') }}
                </p>
            </div>
        </div>

        @foreach($channelMeta as $ch => $meta)
        @php
            $state = $channels[$ch] ?? [];
            $isExpanded = $expanded === $ch;
            $isOn = $state['enabled'] ?? false;
            // Plan-gate: lower plans get email-only customization. Other
            // channel cards are locked behind Pro.
            $channelLocked = $ch !== 'email' && !($planFeatures['ai_per_channel'] ?? false);
            $iconMap = [
                'email' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
                'whatsapp' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
                'sms' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>',
                'live_chat' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
                'telegram' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12l4-4m-4 4l4 4"/>',
            ];
        @endphp

        <div class="bg-surface-2 rounded-2xl border {{ $channelLocked ? 'border-border opacity-70' : ($isOn ? 'border-success/40' : 'border-border') }} overflow-hidden">
            {{-- Card header — always visible --}}
            <div class="flex items-center gap-3 p-4 {{ $channelLocked ? 'cursor-not-allowed' : 'cursor-pointer hover:bg-surface/50' }} transition-colors"
                 @if(!$channelLocked) wire:click="toggleExpanded('{{ $ch }}')" @endif>
                <span class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center {{ $channelLocked ? 'bg-border/40 text-muted' : ($isOn ? 'bg-success/15 text-success' : 'bg-brand/10 text-brand') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $iconMap[$ch] ?? '' !!}</svg>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-ink">{{ $meta['label'] }}</p>
                        @if($channelLocked)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-warning/15 text-warning rounded-full">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                {{ __('Pro') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-muted line-clamp-1">
                        @if($channelLocked)
                            {{ __('Upgrade to Pro to enable per-channel AI on') }} {{ $meta['label'] }}.
                        @else
                            {{ $meta['blurb'] }}
                        @endif
                    </p>
                </div>
                {{-- Per-channel ENABLE toggle (disabled when locked) --}}
                <label class="relative inline-flex items-center {{ $channelLocked ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }} flex-shrink-0" @click.stop>
                    <input type="checkbox" wire:model.live="channels.{{ $ch }}.enabled"
                           @disabled($channelLocked)
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-border rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-success"></div>
                </label>
                @if(!$channelLocked)
                <span class="text-muted">
                    <svg class="w-4 h-4 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
                @endif
            </div>

            {{-- Expanded body --}}
            @if($isExpanded)
            <div class="border-t border-border/50 p-5 space-y-5">

                {{-- Send mode + Personality --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Send mode') }}</label>
                        <select wire:model.live="channels.{{ $ch }}.send_mode"
                                class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="approval">{{ __('Approval — human reviews before sending') }}</option>
                            <option value="autonomous">{{ __('Autonomous — AI sends directly') }}</option>
                            <option value="suggestions">{{ __('Suggestions only — internal note') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Personality') }}</label>
                        <select wire:model.live="channels.{{ $ch }}.personality_preset"
                                class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="professional">{{ __('Professional') }}</option>
                            <option value="friendly">{{ __('Friendly') }}</option>
                            <option value="casual">{{ __('Casual') }}</option>
                            <option value="sales">{{ __('Sales') }}</option>
                            <option value="support">{{ __('Support') }}</option>
                            <option value="custom">{{ __('Custom prompt') }}</option>
                        </select>
                    </div>
                </div>

                {{-- Custom prompt --}}
                @if(($state['personality_preset'] ?? '') === 'custom')
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Custom prompt') }}</label>
                    <textarea wire:model="channels.{{ $ch }}.custom_prompt" rows="3" maxlength="5000"
                              class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500"
                              placeholder="{{ __('Describe how the AI should respond on this channel...') }}"></textarea>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Additional instructions') }}</label>
                    <textarea wire:model="channels.{{ $ch }}.additional_instructions" rows="2" maxlength="5000"
                              class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500"
                              placeholder="{{ __('Any extra rules — tone, signoff, what to avoid...') }}"></textarea>
                </div>

                {{-- Confidence + reply length + delay --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">
                            {{ __('Confidence threshold') }} <span class="text-brand">({{ $state['confidence_threshold'] ?? 75 }}%)</span>
                        </label>
                        <input type="range" wire:model.live="channels.{{ $ch }}.confidence_threshold" min="0" max="100" step="5" class="w-full accent-primary-600">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Reply length') }}</label>
                        <select wire:model.live="channels.{{ $ch }}.max_reply_length"
                                class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="short">{{ __('Short') }}</option>
                            <option value="medium">{{ __('Medium') }}</option>
                            <option value="long">{{ __('Long') }}</option>
                            <option value="very_long">{{ __('Very long') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Reply delay') }}</label>
                        <select wire:model.live="channels.{{ $ch }}.reply_delay"
                                class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="none">{{ __('Send immediately') }}</option>
                            <option value="30s">{{ __('30 seconds') }}</option>
                            <option value="1m">{{ __('1 minute') }}</option>
                            <option value="2m">{{ __('2 minutes') }}</option>
                            <option value="5m">{{ __('5 minutes') }}</option>
                            <option value="random">{{ __('Random 30s–3m (looks human)') }}</option>
                        </select>
                    </div>
                </div>

                {{-- Reliability filters (channel-specific behavior) --}}
                <div class="bg-surface rounded-xl p-4 space-y-3">
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __('Reliability — when to SKIP replying') }}</p>
                        <p class="text-xs text-muted mt-0.5">
                            {{ __("Avoid replying to messages that don't deserve a response. Email needs strict filters; chat usually doesn't.") }}
                        </p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" wire:model.live="channels.{{ $ch }}.filter_skip_noreply" class="h-4 w-4 rounded border-border text-primary-600">
                            <span class="text-ink/80">{{ __('Skip noreply / mailer-daemon / system senders') }}</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" wire:model.live="channels.{{ $ch }}.filter_skip_autoreply" class="h-4 w-4 rounded border-border text-primary-600">
                            <span class="text-ink/80">{{ __('Skip auto-replies / out-of-office') }}</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" wire:model.live="channels.{{ $ch }}.filter_skip_bounces" class="h-4 w-4 rounded border-border text-primary-600">
                            <span class="text-ink/80">{{ __('Skip bounces / delivery failures') }}</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" wire:model.live="channels.{{ $ch }}.filter_skip_promotional" class="h-4 w-4 rounded border-border text-primary-600">
                            <span class="text-ink/80">{{ __('Skip promotional / newsletters') }}</span>
                        </label>
                        @if($ch === 'email')
                        <label class="flex items-center gap-2 text-sm cursor-pointer md:col-span-2">
                            <input type="checkbox" wire:model.live="channels.{{ $ch }}.filter_require_valid_from" class="h-4 w-4 rounded border-border text-primary-600">
                            <span class="text-ink/80">{{ __('Require a valid sender email address') }}</span>
                        </label>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink/80 mb-1">{{ __('Custom blocklist') }} <span class="text-muted">({{ __('one per line — match against from + subject') }})</span></label>
                        <textarea wire:model="channels.{{ $ch }}.filter_custom_blocklist" rows="2"
                                  class="w-full px-3 py-2 text-xs bg-surface-2 border border-border rounded-lg font-mono"
                                  placeholder="@noreply.yourdomain.com&#10;[automated]"></textarea>
                    </div>
                </div>

                {{-- Conversation rules --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" wire:model.live="channels.{{ $ch }}.first_message_only" class="h-4 w-4 rounded border-border text-primary-600">
                        <span class="text-ink/80">{{ __('First message only') }}</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" wire:model.live="channels.{{ $ch }}.skip_own_threads" class="h-4 w-4 rounded border-border text-primary-600">
                        <span class="text-ink/80">{{ __('Skip threads we started') }}</span>
                    </label>
                    <div>
                        <label class="block text-xs font-medium text-ink/80 mb-1">{{ __('Max replies / conversation') }}</label>
                        <input type="number" min="1" max="99" wire:model="channels.{{ $ch }}.max_replies_per_conversation"
                               class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg">
                    </div>
                </div>

                {{-- Auto-escalation (per-channel, plan-gated) --}}
                @php $canEscalate = $planFeatures['ai_auto_escalation'] ?? false; @endphp
                <div class="bg-warning/5 border border-warning/20 rounded-xl p-4 space-y-3 {{ $canEscalate ? '' : 'opacity-70' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-ink">{{ __('Escalate to human when unsure') }}</p>
                                @if(!$canEscalate)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-warning/15 text-warning rounded-full">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        {{ __('Pro') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-muted mt-0.5">
                                @if($canEscalate)
                                    {{ __('When confidence drops below the threshold: assign conversation, tag, and notify the assignee.') }}
                                @else
                                    {{ __('Upgrade to Pro to auto-hand-off low-confidence conversations to a human teammate.') }}
                                @endif
                            </p>
                        </div>
                        <label class="relative inline-flex items-center {{ $canEscalate ? 'cursor-pointer' : 'cursor-not-allowed opacity-60' }} flex-shrink-0">
                            <input type="checkbox" wire:model.live="channels.{{ $ch }}.escalation_enabled"
                                   @disabled(!$canEscalate)
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-border rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-warning"></div>
                        </label>
                    </div>
                    @if(($state['escalation_enabled'] ?? false) && $canEscalate)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2 border-t border-warning/20">
                        <div>
                            <label class="block text-xs font-medium text-ink/80 mb-1">
                                {{ __('Below') }} <span class="text-warning">({{ $state['escalate_below_confidence'] ?? 50 }}%)</span>
                            </label>
                            <input type="range" wire:model.live="channels.{{ $ch }}.escalate_below_confidence" min="0" max="100" step="5" class="w-full accent-warning">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink/80 mb-1">{{ __('Assign to') }}</label>
                            <select wire:model.live="channels.{{ $ch }}.escalation_assignee_id"
                                    class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg">
                                <option value="">{{ __('Round-robin') }}</option>
                                @foreach($assigneeOptions as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink/80 mb-1">{{ __('Tag') }}</label>
                            <input type="text" maxlength="64" wire:model="channels.{{ $ch }}.escalation_tag"
                                   class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-lg"
                                   placeholder="needs_human">
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Save --}}
    <div class="flex justify-end pt-2">
        <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="btn-primary px-6 py-2.5 text-sm">
            <span wire:loading.remove wire:target="save">{{ __('Save Configuration') }}</span>
            <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
        </button>
    </div>
</div>
