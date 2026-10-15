<div class="max-w-4xl mx-auto" x-data="{ showSchedule: @entangle('showSchedule') }">

    {{-- Test Mode Banner --}}
    @if(config('services.channel_test_mode') && $channel !== 'email')
    <div class="mb-4 p-3 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
        <span><strong>Test Mode Active</strong> — {{ ucfirst($channel) }} messages will be saved locally but NOT actually delivered. Disable <code>CHANNEL_TEST_MODE</code> in .env for real delivery.</span>
    </div>
    @endif

    {{-- Flash Messages --}}
    @if(session('compose-success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="mb-4 p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('compose-success') }}
    </div>
    @endif
    @if(session('compose-error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
         class="mb-4 p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('compose-error') }}
    </div>
    @endif
    {{-- Validation / permission / plan-limit errors (previously never displayed, so a failed Send looked like "nothing happened") --}}
    @if($errors->any() || session('error'))
    <div class="mb-4 p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm">
        @if(session('error'))<p>{{ session('error') }}</p>@endif
        @foreach($errors->all() as $compose_validation_error)
            <p>{{ $compose_validation_error }}</p>
        @endforeach
    </div>
    @endif


    {{-- Compose Form --}}
    <div class="bg-surface-2 rounded-2xl border border-border shadow-sm overflow-hidden">

        {{-- Channel Selector — when $lockChannel is true (the user
             clicked Compose from a specific-channel inbox like SMS),
             only the active channel's tab renders. Otherwise the
             usual full strip of connected-channel tabs shows. --}}
        <div class="px-6 py-3 border-b border-border bg-surface">
            <div class="flex items-center gap-4">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Channel') }}</span>
                <div class="flex items-center gap-1.5">
                    @if(! $lockChannel || $channel === 'email')
                    <button type="button" wire:click="$set('channel', 'email')"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $channel === 'email' ? 'bg-brand/15 text-brand' : 'text-muted hover:bg-surface ' }}">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            {{ __('Email') }}
                        </span>
                    </button>
                    @endif
                    @if((! $lockChannel || $channel === 'sms') && in_array('sms', $connectedChannels))
                    <button type="button" wire:click="$set('channel', 'sms')"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $channel === 'sms' ? 'bg-info/15 text-info' : 'text-muted hover:bg-surface ' }}">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            {{ __('SMS') }}
                        </span>
                    </button>
                    @endif
                    @if((! $lockChannel || $channel === 'whatsapp') && in_array('whatsapp', $connectedChannels))
                    <button type="button" wire:click="$set('channel', 'whatsapp')"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $channel === 'whatsapp' ? 'bg-success/15 text-success' : 'text-muted hover:bg-surface ' }}">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            {{ __('WhatsApp') }}
                        </span>
                    </button>
                    @endif
                    @if((! $lockChannel || $channel === 'telegram') && in_array('telegram', $connectedChannels))
                    <button type="button" wire:click="$set('channel', 'telegram')"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $channel === 'telegram' ? 'bg-sky-100 text-sky-700' : 'text-muted hover:bg-surface ' }}">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            {{ __('Telegram') }}
                        </span>
                    </button>
                    @endif
                    @if((! $lockChannel || $channel === 'slack') && in_array('slack', $connectedChannels))
                    <button type="button" wire:click="switchToSlack"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $channel === 'slack' ? 'bg-purple-100 text-purple-700' : 'text-muted hover:bg-surface ' }}">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            {{ __('Slack') }}
                        </span>
                    </button>
                    @endif
                </div>
            </div>
        </div>

        @if($channel === 'email')
        {{-- From --}}
        <div class="px-6 py-3 border-b border-border flex items-center gap-3">
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0">{{ __('From') }}</label>
            <select wire:model="fromAccountId"
                    class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink">
                @foreach($emailAccounts as $account)
                <option value="{{ $account->id }}">
                    {{ $account->display_name ? "{$account->display_name} <{$account->email}>" : $account->email }}
                </option>
                @endforeach
                @if($emailAccounts->isEmpty())
                <option value="" disabled>{{ __('No connected email accounts') }}</option>
                @endif
            </select>
        </div>

        {{-- To --}}
        <div class="px-6 py-3 border-b border-border" x-data="{ focused: false }">
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-muted w-12 shrink-0">{{ __('To') }}</label>
                <div class="flex-1 relative">
                    @if($selectedGroupId)
                    {{-- Selected group display --}}
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-50 text-green-800 text-sm font-medium rounded-lg border border-green-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584M15 6.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $selectedGroupName }}
                            <span class="text-green-600 text-xs">({{ $selectedGroupCount }} {{ __('contacts') }})</span>
                            <button type="button" wire:click="clearGroup" class="ml-1 text-green-500 hover:text-green-800 text-lg leading-none">&times;</button>
                        </span>
                    </div>
                    @else
                    <input type="text"
                           wire:model.live.debounce.300ms="toSearch"
                           wire:blur="dismissSuggestions"
                           @focus="focused = true"
                           placeholder="{{ __('Search contacts, groups, or type email...') }}"
                           class="w-full text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 pr-8 text-ink placeholder-gray-400">
                    @endif

                    {{-- Loading spinner while searching contacts --}}
                    <div wire:loading wire:target="toSearch" class="absolute right-3 top-1/2 -translate-y-1/2">
                        <svg class="w-4 h-4 animate-spin text-muted" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </div>

                    {{-- Contact & Group Autocomplete Dropdown --}}
                    @if(!$selectedGroupId && $showContactSuggestions && (!empty($contactSuggestions) || !empty($groupSuggestions)))
                    <div class="absolute top-full left-0 right-0 mt-1 bg-surface-2 rounded-xl border border-border shadow-lg z-50 max-h-64 overflow-y-auto">
                        {{-- Group suggestions --}}
                        @if(!empty($groupSuggestions))
                        <div class="px-3 pt-2 pb-1"><span class="text-[10px] font-semibold uppercase tracking-wider text-muted">{{ __('Groups') }}</span></div>
                        @foreach($groupSuggestions as $group)
                        <button type="button"
                                wire:mousedown.prevent="selectGroup({{ $group['id'] }})"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-surface transition-colors">
                            <span class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-xs font-semibold text-green-700 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584M15 6.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-ink truncate">{{ $group['name'] }}</p>
                                <p class="text-xs text-muted">{{ $group['count'] }} {{ __('contacts') }}</p>
                            </div>
                            <span class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-medium">{{ __('GROUP') }}</span>
                        </button>
                        @endforeach
                        @endif

                        {{-- Contact suggestions --}}
                        @if(!empty($contactSuggestions))
                        @if(!empty($groupSuggestions))
                        <div class="px-3 pt-2 pb-1 border-t border-border mt-1"><span class="text-[10px] font-semibold uppercase tracking-wider text-muted">{{ __('Contacts') }}</span></div>
                        @endif
                        @foreach($contactSuggestions as $suggestion)
                        <button type="button"
                                wire:mousedown.prevent="selectContact('{{ $suggestion['email'] }}', '{{ addslashes($suggestion['name']) }}')"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-surface transition-colors">
                            <span class="w-8 h-8 bg-primary-100 dark:bg-primary-900/30 rounded-full flex items-center justify-center text-xs font-semibold text-primary-700 dark:text-primary-300 flex-shrink-0">
                                {{ $suggestion['initials'] }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-ink truncate">{{ $suggestion['name'] }}</p>
                                <p class="text-xs text-muted truncate">{{ $suggestion['email'] }}{{ $suggestion['company'] ? " - {$suggestion['company']}" : '' }}</p>
                            </div>
                        </button>
                        @endforeach
                        @endif
                    </div>
                    @endif

                </div>

                {{-- CC/BCC Toggle --}}
                <button type="button" wire:click="$toggle('showCcBcc')"
                        class="text-xs text-muted hover:text-muted  font-medium flex-shrink-0">
                    {{ $showCcBcc ? __('Hide') : __('Cc/Bcc') }}
                </button>
            </div>
        </div>

        {{-- CC / BCC --}}
        @if($showCcBcc)
        <div class="px-6 py-3 border-b border-border flex items-center gap-3" wire:transition>
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0">{{ __('Cc') }}</label>
            <input type="text" wire:model="ccEmails"
                   placeholder="{{ __('Comma-separated emails') }}"
                   class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink placeholder-gray-400">
        </div>
        <div class="px-6 py-3 border-b border-border flex items-center gap-3" wire:transition>
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0">{{ __('Bcc') }}</label>
            <input type="text" wire:model="bccEmails"
                   placeholder="{{ __('Comma-separated emails') }}"
                   class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink placeholder-gray-400">
        </div>
        @endif

        {{-- Subject --}}
        <div class="px-6 py-3 border-b border-border flex items-center gap-3">
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0">{{ __('Subject') }}</label>
            <input type="text" wire:model="subject"
                   placeholder="{{ __('Email subject') }}"
                   class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink placeholder-gray-400 font-medium">
        </div>

        {{-- Canned responses dropdown (triggered by typing "/" in the body) --}}
        @if($showCannedResponses && $cannedResponses->isNotEmpty())
        <div class="mx-2 mt-2 bg-[#1a1d27] border border-[#2d3039] rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] max-h-48 overflow-y-auto">
            @foreach($cannedResponses as $canned)
            <button wire:click="insertCannedResponseById({{ $canned->id }})"
                    class="w-full text-left px-4 py-2.5 hover:bg-white/5 border-b border-[#2d3039] last:border-0 transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-200">{{ $canned->title }}</span>
                    @if($canned->shortcut)
                    <span class="text-xs text-gray-500 font-mono">/{{ $canned->shortcut }}</span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 truncate mt-0.5">{{ \Illuminate\Support\Str::limit($canned->content, 80) }}</p>
            </button>
            @endforeach
        </div>
        @endif

        {{-- Body (Quill Rich Text Editor) --}}
        <div class="px-2 py-2">
            <div x-data="quillEditor('body')" wire:ignore class="quill-wrapper">
                <div x-ref="toolbar">
                    <span class="ql-formats">
                        <select class="ql-font">
                            <option value="">Sans Serif</option>
                            <option value="serif">Serif</option>
                            <option value="monospace">Monospace</option>
                        </select>
                        <select class="ql-size">
                            <option value="small">Small</option>
                            <option selected>Normal</option>
                            <option value="large">Large</option>
                        </select>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-bold"></button>
                        <button class="ql-italic"></button>
                        <button class="ql-underline"></button>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-align" value=""></button>
                        <button class="ql-align" value="center"></button>
                        <button class="ql-align" value="right"></button>
                    </span>
                    <span class="ql-formats">
                        <button class="ql-list" value="bullet"></button>
                        <button class="ql-link"></button>
                    </span>
                </div>
                <div x-ref="editor"
                     data-placeholder="{{ __('Write your message here...') }}"
                     style="min-height: 300px;"></div>
            </div>
        </div>
        @else
        {{-- Non-email: To (phone / username / slack channel) --}}
        <div class="px-6 py-3 border-b border-border flex items-center gap-3">
            <label class="text-sm font-medium text-muted w-12 flex-shrink-0">{{ __('To') }}</label>
            @if($channel === 'slack')
                {{-- Slack sends need a channel ID (e.g. C0123) or user ID (U0123), never a phone.
                     Use a dropdown pre-populated from the bot's visible channels so users can't
                     fat-finger an invalid ID. --}}
                <select wire:model.live="toPhone"
                        class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink">
                    <option value="">{{ __('Select a Slack channel...') }}</option>
                    @foreach(($slackChannelsList ?? []) as $ch)
                    <option value="{{ $ch['id'] }}">{{ $ch['is_private'] ? '🔒' : '#' }} {{ $ch['name'] }}</option>
                    @endforeach
                </select>
            @else
                <input type="text" wire:model="toPhone"
                       placeholder="{{ $channel === 'sms' ? '+1234567890' : ($channel === 'whatsapp' ? __( '+1234567890 (with country code)') : __('@username or chat ID')) }}"
                       class="flex-1 text-sm border-0 bg-transparent focus:ring-0 focus:outline-none py-0 pl-0 text-ink placeholder-gray-400">
            @endif
        </div>
        @error('toPhone') <div class="px-6 py-1"><p class="text-xs text-red-500">{{ $message }}</p></div> @enderror

        {{-- Message body for non-email --}}
        <div class="px-6 py-4">
            <textarea wire:model="messageBody" rows="12"
                      placeholder="{{ __('Type your') }} {{ $channel === 'sms' ? __('SMS') : ($channel === 'whatsapp' ? __('WhatsApp') : ($channel === 'slack' ? __('Slack') : __('Telegram'))) }} {{ __('message...') }}"
                      class="w-full text-sm border-0 bg-transparent focus:ring-0 focus:outline-none resize-none text-ink placeholder-gray-400 leading-relaxed"></textarea>
            @error('messageBody') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            @if($channel === 'sms')
            <p class="text-xs text-muted mt-2">{{ strlen($messageBody) }}/160 {{ __('characters') }} ({{ max(1, ceil(strlen($messageBody) / 160)) }} {{ __('SMS segment') }}{{ max(1, ceil(strlen($messageBody) / 160)) > 1 ? 's' : '' }})</p>
            @endif
        </div>
        @endif

        {{-- AI Write Panel (email only) --}}
        @if($channel === 'email' && $showAiWrite)
        <div class="mx-6 mb-4 bg-[#1a1d27] rounded-xl border border-[#2d3039] p-5" wire:transition>
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#3b82f6]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <h3 class="text-sm font-semibold text-gray-200">{{ __('AI Email Writer') }}</h3>
                </div>
                <button type="button" wire:click="toggleAiWrite" class="text-gray-500 hover:text-gray-300 transition" aria-label="Close AI writer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="space-y-3">
                {{-- Prompt --}}
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1">{{ __('What would you like to write about?') }}</label>
                    <textarea wire:model="aiPrompt" rows="3"
                              class="w-full px-3 py-2 text-sm bg-[#0c0d12] text-gray-200 border border-[#2d3039] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/40 focus:border-[#3b82f6]/50 resize-none placeholder-gray-600"
                              placeholder="{{ __('e.g., Follow up on our meeting last week about the Q1 marketing budget. Mention the 15% increase we discussed and ask them to confirm the timeline.') }}"></textarea>
                </div>

                {{-- Tone Selector --}}
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('Tone') }}</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['professional' => __('Professional'), 'friendly' => __('Friendly'), 'casual' => __('Casual'), 'persuasive' => __('Persuasive')] as $toneKey => $toneLabel)
                        <button type="button" wire:click="$set('aiTone', '{{ $toneKey }}')"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $aiTone === $toneKey ? 'bg-[#3b82f6] text-white' : 'bg-[#0c0d12] text-gray-400 border border-[#2d3039] hover:bg-white/5 hover:text-gray-200' }}">
                            {{ $toneLabel }}
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Generate Button --}}
                <button type="button" wire:click="aiWrite"
                        class="w-full px-4 py-2.5 bg-[#3b82f6] text-white text-sm font-medium rounded-xl hover:bg-[#2563eb] transition-colors flex items-center justify-center gap-2"
                        wire:loading.attr="disabled" wire:target="aiWrite">
                    <span wire:loading.remove wire:target="aiWrite" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        {{ __('Generate Email') }}
                    </span>
                    <span wire:loading wire:target="aiWrite" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ __('Generating...') }}
                    </span>
                </button>

                {{-- AI Generated Content --}}
                @if($aiGeneratedContent)
                <div class="bg-[#0c0d12] rounded-xl border border-[#2d3039] p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-[#3b82f6] uppercase tracking-wider">{{ __('Generated Email') }}</span>
                    </div>
                    <div class="text-sm text-gray-300 whitespace-pre-wrap leading-relaxed max-h-64 overflow-y-auto mb-3 hide-scroll">{{ $aiGeneratedContent }}</div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="acceptAiContent"
                                class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition-colors">
                            {{ __('Replace Body') }}
                        </button>
                        <button type="button" wire:click="insertAiContent"
                                class="px-3 py-1.5 bg-[#3b82f6] text-white text-xs font-medium rounded-lg hover:bg-[#2563eb] transition-colors">
                            {{ __('Append to Body') }}
                        </button>
                        <button type="button" wire:click="regenerateAi"
                                class="px-3 py-1.5 bg-white/5 text-gray-400 text-xs font-medium rounded-lg border border-[#2d3039] hover:bg-white/10 hover:text-gray-200 transition-colors">
                            {{ __('Regenerate') }}
                        </button>
                        <button type="button" wire:click="discardAiContent"
                                class="px-3 py-1.5 text-gray-500 text-xs font-medium hover:text-gray-300 transition-colors">
                            {{ __('Discard') }}
                        </button>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Attachments (email only) --}}
        @if($channel === 'email' && !empty($attachments))
        <div class="mx-6 mb-4">
            <div class="flex flex-wrap gap-2">
                @foreach($attachments as $index => $file)
                @if($file && method_exists($file, 'getSize') && $file->exists())
                <div class="flex items-center gap-2 px-3 py-2 bg-surface rounded-lg border border-border text-sm">
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    <span class="text-ink/80 truncate max-w-[200px]">{{ $file->getClientOriginalName() }}</span>
                    <span class="text-xs text-muted">({{ number_format(($file->getSize() ?: 0) / 1024, 0) }}KB)</span>
                    <button type="button" wire:click="removeAttachment({{ $index }})" class="text-muted hover:text-red-500" aria-label="Remove attachment">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                @endif
                @endforeach
            </div>
        </div>
        @endif

        {{-- Schedule Panel (email only) --}}
        @if($channel === 'email' && $showSchedule)
        <div class="mx-6 mb-4 bg-warning/10 rounded-xl border border-warning/20 p-4" wire:transition>
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm font-semibold text-amber-900">{{ __('Schedule Send') }}</span>
                </div>
                <button type="button" wire:click="$set('showSchedule', false)"
                        class="p-1 text-muted hover:text-ink/80 rounded-lg hover:bg-warning/10 transition-colors" aria-label="Close schedule panel">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Quick-pick Presets --}}
            <div class="flex flex-wrap gap-2 mb-3">
                <button type="button" wire:click="setSchedulePreset('in_2_hours')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-warning/20 bg-surface-2 text-amber-800 hover:bg-warning/15 transition-colors">
                    {{ __('In 2 hours') }}
                </button>
                <button type="button" wire:click="setSchedulePreset('in_4_hours')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-warning/20 bg-surface-2 text-amber-800 hover:bg-warning/15 transition-colors">
                    {{ __('In 4 hours') }}
                </button>
                <button type="button" wire:click="setSchedulePreset('tomorrow_9am')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-warning/20 bg-surface-2 text-amber-800 hover:bg-warning/15 transition-colors">
                    {{ __('Tomorrow 9 AM') }}
                </button>
                <button type="button" wire:click="setSchedulePreset('monday_9am')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg border border-warning/20 bg-surface-2 text-amber-800 hover:bg-warning/15 transition-colors">
                    {{ __('Monday 9 AM') }}
                </button>
            </div>

            {{-- Custom Date/Time Picker --}}
            <div class="flex items-center gap-3">
                <input type="datetime-local" wire:model="scheduledAt"
                       min="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}"
                       class="flex-1 px-3 py-2 text-sm bg-surface-2 border border-warning/20 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent">
                <button type="button" wire:click="scheduleSend"
                        class="px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-xl hover:bg-amber-700 transition-colors flex items-center gap-2"
                        wire:loading.attr="disabled" wire:target="scheduleSend">
                    <span wire:loading.remove wire:target="scheduleSend">{{ __('Schedule') }}</span>
                    <span wire:loading wire:target="scheduleSend" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ __('Scheduling...') }}
                    </span>
                </button>
            </div>

            @error('scheduledAt')
            <p class="text-xs text-danger mt-1.5">{{ $message }}</p>
            @enderror

            <p class="text-xs text-muted mt-2">
                @if($scheduledAt)
                    {{ __('Scheduled for') }} <span class="font-medium text-amber-700">{{ \Carbon\Carbon::parse($scheduledAt)->format('M j, Y \a\t g:i A') }}</span>
                @else
                    {{ __('Pick a time or use a preset above') }}
                @endif
            </p>

            {{-- Honest expectation: the scheduler runs once per minute, so the
                 actual send can lag the chosen time by up to ~60 seconds. --}}
            <p class="text-[11px] text-muted/80 mt-1 flex items-start gap-1">
                <svg class="w-3 h-3 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ __('Scheduled messages may be delayed by 1-2 minutes.') }}</span>
            </p>
        </div>
        @endif

        {{-- Validation errors --}}
        @if($errors->any())
        <div class="mx-6 mb-3">
            <div class="p-3 bg-danger/10 rounded-xl border border-danger/20">
                @foreach($errors->all() as $error)
                <p class="text-xs text-danger">{{ $error }}</p>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Action Bar --}}
        <div class="px-6 py-4 border-t border-border bg-surface flex items-center justify-between">
            <div class="flex items-center gap-2">
                @if($channel === 'email')
                {{-- Attach (email only) --}}
                <label class="p-2 text-muted hover:text-muted  hover:bg-surface  rounded-lg cursor-pointer transition-colors" title="{{ __('Attach file') }}"" aria-label="Attach file">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    <input type="file" wire:model="attachments" multiple class="hidden" accept="*/*">
                </label>

                {{-- AI Write (email only) --}}
                <button type="button" wire:click="toggleAiWrite"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $showAiWrite ? 'bg-brand/15 text-brand' : 'text-muted hover:text-purple-600 hover:bg-brand/10' }}"
                        title="{{ __('AI Write') }}"">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    {{ __('AI Write') }}
                </button>

                {{-- Schedule (email only) --}}
                <button type="button" wire:click="$toggle('showSchedule')"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $showSchedule ? 'bg-warning/15 text-warning' : 'text-muted hover:text-amber-600 hover:bg-warning/10' }}"
                        title="{{ __('Schedule send') }}"">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('Schedule') }}
                </button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if($channel === 'email')
                {{-- Save Draft (email only) --}}
                <button type="button" wire:click="saveDraft"
                        class="px-4 py-2 text-sm font-medium text-muted  bg-surface  rounded-xl hover:bg-gray-200 dark:bg-gray-700 transition-colors"
                        wire:loading.attr="disabled" wire:target="saveDraft">
                    <span wire:loading.remove wire:target="saveDraft">{{ __('Save Draft') }}</span>
                    <span wire:loading wire:target="saveDraft" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ __('Saving...') }}
                    </span>
                </button>
                @endif

                {{-- Send --}}
                <button type="button" wire:click="sendMessage"
                        class="px-5 py-2 text-sm font-medium text-white bg-primary-600 rounded-xl hover:bg-primary-700 transition-colors flex items-center gap-2"
                        wire:loading.attr="disabled" wire:target="sendMessage">
                    <span wire:loading.remove wire:target="sendMessage" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        {{ $channel === 'email' ? __('Send Email') : ($channel === 'sms' ? __('Send SMS') : ($channel === 'whatsapp' ? __('Send WhatsApp') : __('Send Message'))) }}
                    </span>
                    <span wire:loading wire:target="sendMessage" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        {{ __('Sending...') }}
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Session expiry warning --}}
    <div x-data="{ showWarning: false }"
         x-init="setTimeout(() => showWarning = true, {{ (config('session.lifetime', 120) - 5) * 60 * 1000 }})"
         x-show="showWarning" x-transition
         class="fixed bottom-4 right-4 z-50 bg-warning/10 border border-warning/30 rounded-xl p-4 shadow-lg max-w-sm"
         x-cloak>
        <p class="text-sm font-medium text-ink">{{ __('Session expiring soon') }}</p>
        <p class="text-xs text-muted mt-1">{{ __('Your session is about to expire. Click anywhere to stay logged in. Your draft is saved.') }}</p>
        <button type="button" @click="fetch('/sanctum/csrf-cookie'); showWarning = false" class="mt-2 px-3 py-1 bg-brand text-white text-xs font-medium rounded-lg hover:bg-brand-strong transition-colors">
            {{ __('Stay Logged In') }}
        </button>
    </div>

    {{-- Undo Send Bar --}}
    @if($showUndoBar)
    <div class="fixed top-4 right-4 z-50"
         x-data="{ countdown: @entangle('undoCountdown'), timer: null }"
         x-init="timer = setInterval(() => { countdown--; if (countdown <= 0) { clearInterval(timer); $wire.confirmSend(); } }, 1000)"
         x-on:undo-cancelled.window="clearInterval(timer)">
        <div class="bg-[#1a1d27] text-gray-200 rounded-xl px-5 py-3 shadow-[0_10px_30px_rgba(0,0,0,0.5)] border border-white/10 flex items-center gap-4">
            <span class="text-sm">{{ __('Email sending in') }} <strong x-text="countdown"></strong>{{ __('s...') }}</span>
            <button wire:click="undoSend" @click="clearInterval(timer); $dispatch('undo-cancelled')" class="text-sm font-semibold text-yellow-400 hover:text-yellow-300 underline">
                {{ __('Undo') }}
            </button>
        </div>
    </div>
    @endif
</div>