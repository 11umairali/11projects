<div class="bg-[#11131a] border-t border-[#2d3039] p-4 shrink-0">
    {{-- Mode tabs --}}
    <div class="flex items-center gap-1 mb-3">
        <button wire:click="setMode('reply')"
                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $mode === 'reply' ? 'bg-blue-500/10 text-blue-400' : 'text-gray-500 hover:bg-white/5 hover:text-gray-300' }}">
            <span class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17H4a2 2 0 01-2-2V5a2 2 0 012-2h16a2 2 0 012 2v5M3 7l9 6 9-6"/></svg>
                {{ __('Reply') }}
            </span>
        </button>
        @if($conversationChannel === 'email')
        <button wire:click="setMode('forward')"
                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $mode === 'forward' ? 'bg-blue-500/10 text-blue-400' : 'text-gray-500 hover:bg-white/5 hover:text-gray-300' }}">
            <span class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                {{ __('Forward') }}
            </span>
        </button>
        @endif
        <button wire:click="setMode('note')"
                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $mode === 'note' ? 'bg-yellow-500/10 text-yellow-400' : 'text-gray-500 hover:bg-white/5 hover:text-gray-300' }}">
            <span class="flex items-center gap-1">
                <x-icon name="sticky-note" class="w-3.5 h-3.5" />
                {{ __('Note') }}
            </span>
        </button>
    </div>

    {{-- CC/BCC fields (email conversations only) --}}
    @if($mode !== 'note' && $showCcBcc && $conversationChannel === 'email')
    <div class="space-y-2 mb-3">
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500 w-8">{{ __('CC') }}</label>
            <input type="text" wire:model="ccEmails" placeholder="{{ __('email@example.com, ...') }}"
                   class="flex-1 text-sm bg-[#1a1d27] border border-[#2d3039] rounded-lg px-3 py-1.5 text-gray-300 placeholder-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500/40 focus:border-transparent">
        </div>
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500 w-8">{{ __('BCC') }}</label>
            <input type="text" wire:model="bccEmails" placeholder="{{ __('email@example.com, ...') }}"
                   class="flex-1 text-sm bg-[#1a1d27] border border-[#2d3039] rounded-lg px-3 py-1.5 text-gray-300 placeholder-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500/40 focus:border-transparent">
        </div>
    </div>
    @endif

    {{-- AI Write Prompt --}}
    @if($showAiWritePrompt)
    <div class="mb-3 p-3 bg-cyan-500/5 border border-cyan-500/20 rounded-xl">
        <div class="flex items-center gap-1.5 mb-2">
            <x-icon name="pencil" class="w-4 h-4 text-cyan-400" />
            <span class="text-xs font-medium text-cyan-400">{{ __('AI Write') }}</span>
        </div>
        <textarea wire:model="aiWritePrompt"
                  rows="2"
                  placeholder="{{ __('Describe what you want to write... (e.g., \'Thank them for their order and provide shipping details\')') }}"
                  class="w-full text-sm bg-[#1a1d27] border border-[#2d3039] rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500/40 focus:border-transparent mb-2 text-gray-300 placeholder-gray-600"></textarea>
        <div class="flex items-center gap-2">
            <select wire:model="aiWriteTone" class="text-xs bg-[#1a1d27] border border-[#2d3039] rounded-lg px-2 py-1.5 text-gray-300 focus:outline-none focus:ring-1 focus:ring-blue-500/40">
                <option value="professional">{{ __('Professional') }}</option>
                <option value="friendly">{{ __('Friendly') }}</option>
                <option value="casual">{{ __('Casual') }}</option>
                <option value="persuasive">{{ __('Persuasive') }}</option>
            </select>
            <button wire:click="submitAiWrite" class="px-3 py-1.5 bg-[#3b82f6] text-white text-xs font-medium rounded-lg hover:bg-[#2563eb] transition-colors">{{ __('Generate') }}</button>
            <button wire:click="$set('showAiWritePrompt', false)" class="px-3 py-1.5 text-gray-500 text-xs hover:text-gray-300 transition-colors">{{ __('Cancel') }}</button>
        </div>
    </div>
    @endif

    {{-- AI Suggestion --}}
    @if($showAiSuggestion && $aiSuggestion)
    <div class="mb-3 p-3 bg-cyan-500/5 border border-cyan-500/20 rounded-xl">
        <div class="flex items-center gap-1.5 mb-2">
            <x-icon name="zap" class="w-4 h-4 text-cyan-400" />
            <span class="text-xs font-medium text-cyan-400">{{ __('AI Suggestion') }}</span>
        </div>
        <p class="text-sm text-gray-300 leading-relaxed mb-3 whitespace-pre-wrap">{{ $aiSuggestion }}</p>
        <div class="flex items-center gap-2">
            <button wire:click="acceptAiSuggestion" class="px-3 py-1.5 bg-[#3b82f6] text-white text-xs font-medium rounded-lg hover:bg-[#2563eb] transition-colors">{{ __('Accept') }}</button>
            <button wire:click="regenerateAiSuggestion" class="px-3 py-1.5 bg-white/5 text-gray-300 text-xs font-medium rounded-lg border border-[#2d3039] hover:bg-white/10 transition-colors">{{ __('Regenerate') }}</button>
            <button wire:click="discardAiSuggestion" class="px-3 py-1.5 text-gray-500 text-xs hover:text-gray-300 transition-colors">{{ __('Discard') }}</button>
        </div>
    </div>
    @endif

    {{-- AI loading --}}
    @if($aiLoading)
    <div class="mb-3 p-3 bg-cyan-500/5 border border-cyan-500/20 rounded-xl">
        <div class="flex items-center gap-2 text-sm text-cyan-400">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            {{ __('Generating AI suggestion...') }}
        </div>
    </div>
    @endif

    {{-- Canned responses dropdown --}}
    @if($showCannedResponses && $cannedResponses->isNotEmpty())
    <div class="mb-3 bg-[#1a1d27] border border-[#2d3039] rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] max-h-48 overflow-y-auto">
        @foreach($cannedResponses as $canned)
        <button wire:click="insertCannedResponse({{ $canned->id }})"
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

    {{-- Composer area.
         NOTE: no `overflow-hidden` on the editor card — that used to
         clip the AI-zap and Schedule popovers (which open upwards via
         `absolute bottom-full`) inside the card, making the menu appear
         to overlap the typing area. The popovers now float freely above
         the toolbar. --}}
    <div class="flex items-end gap-3">
        <div class="flex-1 {{ $mode === 'note' ? 'bg-yellow-500/5 border-yellow-500/20' : 'bg-[#1a1d27] border-[#2d3039]' }} border rounded-xl">
            @if($mode === 'note')
            <div class="flex items-center gap-1.5 px-3 pt-2">
                <x-icon name="sticky-note" class="w-3.5 h-3.5 text-yellow-400" />
                <span class="text-xs font-medium text-yellow-400">{{ __('Internal note (not sent to customer)') }}</span>
            </div>
            @endif

            {{-- Quill Rich Text Editor --}}
            <div x-data="quillEditor('body')" wire:ignore class="quill-wrapper">
                {{-- Toolbar --}}
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
                {{-- Editor --}}
                <div x-ref="editor"
                     data-placeholder="{{ $mode === 'note' ? __('Write an internal note...') : ($mode === 'forward' ? __('Add a message...') : __('Type your reply...')) }}"
                     style="min-height: 80px;"></div>
            </div>

            {{-- Attachments preview --}}
            @if(!empty($attachments))
            <div class="flex flex-wrap gap-2 mt-2 pt-2 border-t {{ $mode === 'note' ? 'border-yellow-500/20' : 'border-[#2d3039]' }}">
                @foreach($attachments as $index => $file)
                <div class="flex items-center gap-1.5 px-2.5 py-1.5 bg-white/5 border border-[#2d3039] rounded-lg text-xs">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 002.828 2.828L18 9.828a4 4 0 00-5.656-5.656L5.757 10.757a6 6 0 008.486 8.486L20.5 13"/></svg>
                    <span class="text-gray-300 truncate max-w-[120px]">{{ $file->getClientOriginalName() }}</span>
                    <button wire:click="removeAttachment({{ $index }})" class="text-gray-500 hover:text-red-400 transition-colors" aria-label="Remove attachment">
                        <x-icon name="x" class="w-3 h-3" />
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Schedule popover lives down with the clock-icon button
                 (rendered absolutely just above it) so opening the
                 schedule doesn't push Send / Send & Close to a new
                 line and break their alignment. --}}

            {{-- Toolbar — padded on every side so the Send / Send & Close
                 buttons sit centred in the strip with equal breathing
                 room above, below, and on the sides, instead of hugging
                 the card's bottom edge. --}}
            <div class="flex items-center justify-between mt-2 py-2.5 px-3 border-t {{ $mode === 'note' ? 'border-yellow-500/20' : 'border-[#2d3039]' }}">
                <div class="flex items-center gap-1">
                    {{-- Attach file — only channels that support media
                         attachments. SMS / Slack / Telegram replies in
                         this composer are text-only, so the paperclip
                         (the "pin" icon) is hidden there. --}}
                    @if($mode === 'note' || in_array($conversationChannel, ['email', 'whatsapp']))
                    <label class="p-1.5 text-gray-500 hover:text-gray-300 rounded-lg hover:bg-white/5 cursor-pointer transition-colors" title="{{ __('Attach file') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 002.828 2.828L18 9.828a4 4 0 00-5.656-5.656L5.757 10.757a6 6 0 008.486 8.486L20.5 13"/></svg>
                        <input type="file" wire:model="attachments" multiple class="hidden">
                    </label>
                    @endif

                    {{-- CC/BCC toggle (email only). The "person" icon
                         doesn't apply to SMS / WhatsApp / Slack / Telegram
                         replies — those have one recipient determined by
                         the conversation. --}}
                    @if($mode !== 'note' && $conversationChannel === 'email')
                    <button wire:click="$toggle('showCcBcc')" class="p-1.5 rounded-lg transition-colors {{ $showCcBcc ? 'text-blue-400 bg-blue-500/10' : 'text-gray-500 hover:text-gray-300 hover:bg-white/5' }}" title="{{ __('CC/BCC') }}">
                        <x-icon name="users" class="w-4 h-4" />
                    </button>
                    @endif

                    {{-- AI Dropdown --}}
                    <div x-data="{ aiMenu: false, toneMenu: false, translateMenu: false }" class="relative">
                        <button @click="aiMenu = !aiMenu"
                                :class="aiMenu ? 'text-cyan-400 bg-cyan-500/10' : 'text-gray-500 hover:text-cyan-400 hover:bg-cyan-500/5'"
                                class="p-1.5 rounded-lg transition-colors disabled:opacity-50"
                                :disabled="$wire.aiLoading"
                                title="{{ __('AI Writing Tools') }}">
                            <x-icon name="zap" class="w-4 h-4" />
                        </button>

                        {{-- AI Menu Dropdown --}}
                        <div x-show="aiMenu"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             @click.outside="aiMenu = false; toneMenu = false; translateMenu = false"
                             class="absolute bottom-full left-0 mb-1 w-56 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5 z-[130]"
                             style="display: none;">

                            <button @click="aiMenu = false; $wire.aiSuggest()"
                                    class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                                <x-icon name="zap" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                {{ __('AI Suggest Reply') }}
                            </button>

                            <button @click="aiMenu = false; $wire.openAiWrite()"
                                    class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                                <x-icon name="pencil" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                {{ __('AI Write') }}
                            </button>

                            <div class="h-px bg-white/5 my-1.5"></div>

                            <button @click="aiMenu = false; $wire.aiTransform('improve')"
                                    class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                                <x-icon name="zap" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                {{ __('Improve Writing') }}
                            </button>

                            <button @click="aiMenu = false; $wire.aiTransform('shorter')"
                                    class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                                <svg class="w-3.5 h-3.5 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                {{ __('Make Shorter') }}
                            </button>

                            <button @click="aiMenu = false; $wire.aiTransform('longer')"
                                    class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                                <svg class="w-3.5 h-3.5 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M3 12h18M3 18h18"/></svg>
                                {{ __('Make Longer') }}
                            </button>

                            <button @click="aiMenu = false; $wire.aiTransform('grammar')"
                                    class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                                <x-icon name="check-circle" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                {{ __('Fix Grammar') }}
                            </button>

                            <div class="h-px bg-white/5 my-1.5"></div>

                            {{-- Change Tone (submenu) --}}
                            <div class="relative" @mouseenter="toneMenu = true" @mouseleave="toneMenu = false">
                                <button class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center justify-between">
                                    <span class="flex items-center gap-2.5">
                                        <x-icon name="message-square" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                        {{ __('Change Tone') }}
                                    </span>
                                    <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                                <div x-show="toneMenu"
                                     x-transition
                                     class="absolute left-full top-0 ml-1 w-40 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] py-1.5 z-50"
                                     style="display: none;">
                                    @foreach([__('Professional') => 'professional', __('Friendly') => 'friendly', __('Casual') => 'casual', __('Persuasive') => 'persuasive'] as $label => $value)
                                    <button @click="aiMenu = false; toneMenu = false; $wire.aiTransform('tone', '{{ $value }}')"
                                            class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition">
                                        {{ $label }}
                                    </button>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Translate (submenu) --}}
                            <div class="relative" @mouseenter="translateMenu = true" @mouseleave="translateMenu = false">
                                <button class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center justify-between">
                                    <span class="flex items-center gap-2.5">
                                        <x-icon name="globe" class="w-3.5 h-3.5 text-cyan-400 shrink-0" />
                                        {{ __('Translate') }}
                                    </span>
                                    <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                                <div x-show="translateMenu"
                                     x-transition
                                     class="absolute left-full top-0 ml-1 w-40 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] py-1.5 z-50 max-h-52 overflow-y-auto"
                                     style="display: none;">
                                    @foreach(['English', 'Spanish', 'French', 'German', 'Hindi', 'Portuguese', 'Chinese', 'Japanese', 'Korean', 'Arabic', 'Italian', 'Dutch', 'Russian'] as $lang)
                                    <button @click="aiMenu = false; translateMenu = false; $wire.aiTransform('translate', '{{ $lang }}')"
                                            class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition">
                                        {{ $lang }}
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Schedule send — clock button + floating popover.
                         The popover is `absolute bottom-full left-0` so
                         opening it does NOT shift Send / Send & Close
                         downward like the previous inline block did. --}}
                    @if($mode !== 'note')
                    <div class="relative">
                        <button wire:click="$toggle('showSchedule')" class="p-1.5 rounded-lg transition-colors {{ $showSchedule ? 'text-blue-400 bg-blue-500/10' : 'text-gray-500 hover:text-gray-300 hover:bg-white/5' }}" title="{{ __('Schedule send') }}">
                            <x-icon name="clock" class="w-4 h-4" />
                        </button>
                        @if($showSchedule)
                        <div class="absolute bottom-full left-0 mb-2 w-[280px] bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] p-3 z-[120]">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted mb-2">{{ __('Schedule send') }}</p>
                            <div class="flex items-center gap-2">
                                <input type="datetime-local" wire:model="scheduledAt"
                                       min="{{ now()->format('Y-m-d\TH:i') }}"
                                       class="flex-1 text-sm bg-[#0f1117] border border-[#2d3039] rounded-lg px-3 py-1.5 text-gray-300 focus:outline-none focus:ring-1 focus:ring-blue-500/40">
                                <button wire:click="$set('showSchedule', false)" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">{{ __('Cancel') }}</button>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-2">{{ __('Scheduled messages may be delayed by 1-2 minutes.') }}</p>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    {{-- Send and close --}}
                    @if($mode === 'reply')
                    <button
                        x-data
                        @click.prevent="$wire.call('sendAndClose', window.__activeConversationId || null)"
                        wire:loading.attr="disabled"
                        class="flex items-center gap-1 px-3 py-1.5 text-gray-400 text-sm font-medium rounded-lg border border-[#2d3039] hover:bg-white/5 transition-colors disabled:opacity-50"
                        title="{{ __('Send and close conversation') }}">
                        <x-icon name="check" class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">{{ __('Send & Close') }}</span>
                    </button>
                    @endif

                    {{-- Primary send.
                         We route the click through Alpine so we can pass the
                         current conversation id from `window.__activeConversationId`
                         as a fallback argument. The Livewire send() method only
                         adopts it if its own conversationId is genuinely null,
                         which fixes the intermittent "aborted - no conversation"
                         bug where the listener missed the last select. --}}
                    <button
                        x-data
                        @click.prevent="
                            const fallback = window.__activeConversationId || null;
                            $wire.call({{ $scheduledAt ? "'scheduleSend'" : "'send'" }}, fallback);
                        "
                        wire:loading.attr="disabled"
                        wire:target="send,scheduleSend"
                        class="flex items-center gap-1.5 px-4 py-1.5 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50
                        {{ $mode === 'note' ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-[#3b82f6] hover:bg-[#2563eb]' }}">
                        <span wire:loading.remove wire:target="send,scheduleSend">
                            @if($mode === 'note')
                            <x-icon name="sticky-note" class="w-3.5 h-3.5" />
                            @else
                            <x-icon name="send" class="w-3.5 h-3.5" />
                            @endif
                        </span>
                        <span wire:loading wire:target="send,scheduleSend">
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </span>
                        {{ $mode === 'note' ? __('Add Note') : ($scheduledAt ? __('Schedule') : __('Send')) }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Upload errors --}}
    @error('attachments.*')
    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
    @enderror

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
