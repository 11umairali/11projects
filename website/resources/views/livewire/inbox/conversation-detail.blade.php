<div class="flex-1 flex min-w-0 min-h-0 h-full bg-[#07090e] overflow-hidden" wire:poll.5s.visible="pollMessages"
     x-data="{ actionsDrop: false, snoozeOpen: false, rightPanel: false }">

    @if(!$conversation)
    {{-- Empty state: select a conversation --}}
    <div class="flex-1 flex items-center justify-center">
        <div class="text-center max-w-sm px-6">
            <div class="w-20 h-20 rounded-2xl bg-white/[0.03] border border-white/[0.05] flex items-center justify-center mx-auto mb-5">
                <x-icon name="mail" class="w-9 h-9 text-gray-600" />
            </div>
            <h3 class="text-xl font-bold text-gray-200 mb-2">{{ __('Select a conversation') }}</h3>
            <p class="text-[13px] text-gray-500 leading-relaxed">
                {{ __('Choose a conversation from the list to view its messages and collaborate with your team.') }}
            </p>
        </div>
    </div>
    @else
    <div class="flex-1 flex flex-col relative min-w-0 min-h-0 h-full">

        {{-- AI Setup Warning Banner --}}
        @if(!$this->isAiConfigured)
        <div x-data="{ show: !localStorage.getItem('ai_setup_dismissed') }" x-show="show" x-transition class="mx-4 mt-3 mb-1">
            <div class="bg-cyan-500/5 border border-cyan-500/20 rounded-xl p-3 flex items-center gap-3">
                <x-icon name="zap" class="w-5 h-5 text-cyan-400 shrink-0" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-200 font-medium">{{ __('AI replies not configured') }}</p>
                    <p class="text-xs text-gray-500">{{ __('Set up AI to get smart reply suggestions for your conversations.') }}</p>
                </div>
                <a href="{{ url('/settings/ai') }}" wire:navigate class="shrink-0 px-3 py-1.5 bg-[#3b82f6] text-white text-xs font-medium rounded-lg hover:bg-[#2563eb] transition-colors">{{ __('Set Up AI') }}</a>
                <button type="button" @click="localStorage.setItem('ai_setup_dismissed', 'true'); show = false" class="shrink-0 p-1 text-gray-500 hover:text-gray-300 transition-colors" aria-label="Dismiss">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>
        </div>
        @endif

        {{-- Header actions --}}
        <div class="h-12 flex items-center px-6 justify-between pt-2 z-10 w-full shrink-0">
            <div class="flex items-center gap-2 flex-1">
                {{-- Back button (mobile) --}}
                <button wire:click="$dispatch('close-conversation')" class="md:hidden p-1 text-gray-400 hover:text-gray-200 rounded-md" aria-label="Back">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </button>

                {{-- More actions button --}}
                <div class="relative" @click.away="actionsDrop = false">
                    <button @click="actionsDrop = !actionsDrop"
                            class="w-6 h-6 rounded-full border border-[#2d3039] bg-[#1a1d27] hover:bg-white/10 flex items-center justify-center text-gray-400 transition">
                        <x-icon name="more-horizontal" class="w-3 h-3" />
                    </button>
                    <div x-show="actionsDrop" x-transition
                         class="absolute left-0 top-full mt-1 z-50 w-56 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] py-1.5"
                         style="display: none;">

                        {{-- Star --}}
                        <button wire:click="toggleStar" @click="actionsDrop = false"
                                class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                            <x-icon name="star" class="w-3.5 h-3.5 shrink-0 {{ $conversation->is_starred ? 'text-yellow-400 fill-yellow-400' : 'text-gray-500' }}" />
                            {{ $conversation->is_starred ? __('Unstar') : __('Star') }}
                        </button>

                        {{-- Snooze submenu --}}
                        <div class="relative" @mouseenter="snoozeOpen = true" @mouseleave="snoozeOpen = false">
                            <button class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center justify-between">
                                <span class="flex items-center gap-2.5">
                                    <x-icon name="clock" class="w-3.5 h-3.5 text-gray-500 shrink-0" />
                                    {{ __('Snooze') }}
                                </span>
                                <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                            <div x-show="snoozeOpen" x-transition
                                 class="absolute left-full top-0 ml-1 w-48 bg-[#1a1d27]/95 backdrop-blur-xl border border-white/10 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] py-1.5 z-50"
                                 style="display: none;">
                                <button wire:click="snoozeConversation('1h')" @click="actionsDrop = false" class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition">{{ __('1 hour') }}</button>
                                <button wire:click="snoozeConversation('3h')" @click="actionsDrop = false" class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition">{{ __('3 hours') }}</button>
                                <button wire:click="snoozeConversation('tomorrow')" @click="actionsDrop = false" class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition">{{ __('Tomorrow 9 AM') }}</button>
                                <button wire:click="snoozeConversation('next_week')" @click="actionsDrop = false" class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition">{{ __('Next Monday') }}</button>
                            </div>
                        </div>

                        <div class="h-px bg-white/5 my-1.5"></div>

                        {{-- Close/Reopen --}}
                        @if($conversation->status !== 'closed')
                        <button wire:click="closeConversation" @click="actionsDrop = false"
                                class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                            <x-icon name="check" class="w-3.5 h-3.5 text-green-400 shrink-0" />
                            {{ __('Close conversation') }}
                        </button>
                        @else
                        <button wire:click="reopenConversation" @click="actionsDrop = false"
                                class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                            <x-icon name="refresh-cw" class="w-3.5 h-3.5 text-blue-400 shrink-0" />
                            {{ __('Reopen conversation') }}
                        </button>
                        @endif

                        <button wire:click="moveToSpam" @click="actionsDrop = false"
                                class="w-full text-left px-3 py-2 text-[12px] font-medium text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2.5">
                            <x-icon name="alert-triangle" class="w-3.5 h-3.5 text-orange-400 shrink-0" />
                            {{ __('Move to spam') }}
                        </button>

                        <button wire:click="moveToTrash" wire:confirm="Move this conversation to trash?" @click="actionsDrop = false"
                                class="w-full text-left px-3 py-2 text-[12px] font-medium text-red-400 hover:bg-red-500/10 hover:text-red-300 transition flex items-center gap-2.5">
                            <x-icon name="trash" class="w-3.5 h-3.5 shrink-0" />
                            {{ __('Move to trash') }}
                        </button>
                    </div>
                </div>

                {{-- Contact name + status --}}
                <div class="flex items-center gap-2 min-w-0 ml-2">
                    @php
                        $avatarColors = ['bg-pink-500/20 text-pink-400', 'bg-blue-500/20 text-blue-400', 'bg-emerald-500/20 text-emerald-400', 'bg-amber-500/20 text-amber-400', 'bg-violet-500/20 text-violet-400'];
                        $colorIndex = ($conversation->contact_id ?? 0) % count($avatarColors);
                    @endphp
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold shrink-0 {{ $avatarColors[$colorIndex] }}">
                        {{ $conversation->contact?->initials ?? '??' }}
                    </div>
                    <span class="text-[13px] font-semibold text-gray-200 truncate">{{ $conversation->contact?->full_name ?? __('Unknown') }}</span>

                    {{-- Status --}}
                    @php
                    $statusColors = [
                        'open' => 'bg-green-500/10 text-green-400 border-green-500/20',
                        'closed' => 'bg-white/5 text-gray-500 border-white/10',
                        'pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
                        'snoozed' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                        'spam' => 'bg-orange-500/10 text-orange-400 border-orange-500/20',
                    ];
                    $sColor = $statusColors[$conversation->status] ?? $statusColors['open'];
                    @endphp
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full border {{ $sColor }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                        {{ ucfirst($conversation->status) }}
                    </span>

                    {{-- Channel --}}
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full bg-white/5 text-gray-500 border border-white/10">
                        <x-icon name="mail" class="w-[10px] h-[10px]" />
                        {{ ucfirst($conversation->channel ?? __('Email')) }}
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-1">
                {{-- Toggle right panel --}}
                <button @click="rightPanel = !rightPanel" class="p-1 rounded transition" :class="rightPanel ? 'text-blue-400' : 'text-gray-400 hover:text-blue-400'" title="{{ __('Details') }}">
                    <x-icon name="panel-right" class="w-[18px] h-[18px] stroke-[2]" />
                </button>
            </div>
        </div>

        {{-- Content Area Scrollable --}}
        <div class="flex-1 min-h-0 overflow-y-auto" id="messages-container" aria-live="polite" aria-relevant="additions">
            <div class="px-6 pt-4 pb-6 text-gray-300 antialiased font-sans">

                {{-- Subject + AI Recap button --}}
                <div class="flex items-start justify-between gap-3 mb-6">
                    <h1 class="text-[26px] font-bold text-gray-100 font-sans flex-1 min-w-0">
                        {{ $conversation->subject ?? __('(no subject)') }}
                    </h1>
                    {{-- AI Recap — generates a 2-line summary of the entire
                         thread so an agent can catch up instantly. Cached
                         for 30 min server-side so re-clicking on an
                         unchanged thread is free. --}}
                    @if($this->isAiConfigured)
                    <button wire:click="summarizeThread"
                            wire:loading.attr="disabled"
                            wire:target="summarizeThread"
                            type="button"
                            class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-semibold text-brand bg-brand/10 border border-brand/20 hover:bg-brand/20 transition disabled:opacity-60 disabled:cursor-wait"
                            title="{{ __('Get a 2-line AI summary of this thread') }}">
                        <svg wire:loading.remove wire:target="summarizeThread"
                             class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m12.728 12.728l-.707-.707M5.636 18.364l.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg wire:loading wire:target="summarizeThread"
                             class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25"/>
                            <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span wire:loading.remove wire:target="summarizeThread">{{ __('AI Recap') }}</span>
                        <span wire:loading wire:target="summarizeThread">{{ __('Summarizing…') }}</span>
                    </button>
                    @endif
                </div>

                {{-- AI Recap result panel --}}
                @if($aiSummary)
                <div class="mb-6 p-4 rounded-xl bg-[var(--color-control)] border border-[var(--color-border)]"
                     role="region" aria-label="{{ __('AI thread summary') }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <svg class="w-3.5 h-3.5 text-brand" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"/>
                                </svg>
                                <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-brand">{{ __('AI Recap') }}</span>
                            </div>
                            <div class="text-[13px] text-gray-200 leading-relaxed whitespace-pre-line">{{ $aiSummary }}</div>
                        </div>
                        <button wire:click="dismissAiSummary"
                                type="button"
                                class="shrink-0 text-gray-500 hover:text-gray-300 transition"
                                aria-label="{{ __('Dismiss summary') }}"
                                title="{{ __('Dismiss') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                @endif

                {{-- Tags inline --}}
                @if($conversation->tagModels->isNotEmpty())
                <div class="flex flex-wrap gap-1.5 mb-6">
                    @foreach($conversation->tagModels as $tag)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium border"
                          style="background-color: {{ $tag->color ?? '#6B7280' }}15; color: {{ $tag->color ?? '#6B7280' }}; border-color: {{ $tag->color ?? '#6B7280' }}30;">
                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $tag->color ?? '#6B7280' }}"></span>
                        {{ $tag->name }}
                        <button wire:click="removeTag({{ $tag->id }})" class="ml-0.5 hover:opacity-70" aria-label="Remove tag">&times;</button>
                    </span>
                    @endforeach
                </div>
                @endif

                {{-- Assigned to --}}
                @if($conversation->assignedTo)
                <p class="text-xs text-gray-500 mb-4">{{ __('Assigned to') }} <span class="font-medium text-gray-400">{{ $conversation->assignedTo->name }}</span></p>
                @endif

                {{-- Messages --}}
                <div class="space-y-5">
                    @foreach($messages as $index => $message)
                    {{-- Date divider --}}
                    @if($index === 0 || !$message->created_at->isSameDay($messages[$index - 1]->created_at ?? now()))
                    <div class="flex items-center gap-3 py-2" role="separator">
                        <div class="flex-1 h-px bg-[#2d3039]"></div>
                        <span class="text-[11px] text-gray-500 font-medium px-2">{{ $message->created_at->format('M j, Y') }}</span>
                        <div class="flex-1 h-px bg-[#2d3039]"></div>
                    </div>
                    @endif

                    <div wire:key="msg-{{ $message->id }}">
                        @if($message->type === 'note')
                        {{-- Internal Note --}}
                        <div class="mx-auto w-full max-w-[92%]">
                            <div class="rounded-xl border border-yellow-500/20 bg-yellow-500/5 px-4 py-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-bold rounded bg-yellow-500/10 text-yellow-400 uppercase tracking-wider">
                                        <x-icon name="sticky-note" class="w-[10px] h-[10px]" />
                                        {{ __('Internal Note') }}
                                    </span>
                                    <span class="text-xs font-medium text-gray-300">{{ $message->sender?->name ?? __('Unknown') }}</span>
                                    <span class="text-[11px] text-gray-500" title="{{ $message->created_at->format('M j, Y g:i A') }}">{{ $message->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="text-sm leading-relaxed whitespace-pre-wrap text-gray-300">
                                    {!! $message->safe_body_html !!}
                                </div>
                                <p class="mt-2 text-[11px] text-gray-600 italic">{{ __('(This note is only visible to your team)') }}</p>
                            </div>
                        </div>

                        @elseif($message->type === 'ai_draft' && $message->ai_status === 'draft')
                        {{-- AI Draft Pending Approval --}}
                        <div class="mx-auto w-full max-w-[92%]">
                            <div class="rounded-xl border-2 border-dashed border-cyan-500/20 bg-cyan-500/5 px-4 py-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] font-bold rounded-md bg-cyan-500/10 text-cyan-400 uppercase tracking-wider">
                                        <x-icon name="bot" class="w-3 h-3" />
                                        {{ __('AI Draft -- Pending Your Approval') }}
                                    </span>
                                </div>
                                <div class="rounded-lg bg-[#1a1d27] border border-[#2d3039] px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap mb-3 text-gray-300">
                                    {!! $message->safe_body_html !!}
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500 mb-3">
                                    @if($message->ai_confidence)
                                    <span>{{ __('Confidence:') }} {{ $message->ai_confidence }}%</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button wire:click="approveAiDraft"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white text-xs font-medium rounded-lg transition-colors">
                                        <x-icon name="check" class="w-3.5 h-3.5" />
                                        {{ __('Approve & Send') }}
                                    </button>
                                    <button wire:click="editAiDraft"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#3b82f6]/30 text-[#3b82f6] hover:bg-[#3b82f6]/10 text-xs font-medium rounded-lg transition-colors">
                                        <x-icon name="message-square" class="w-3.5 h-3.5" />
                                        {{ __('Edit') }}
                                    </button>
                                    <button wire:click="regenerateAiDraft"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#2d3039] text-gray-400 hover:bg-white/5 text-xs font-medium rounded-lg transition-colors">
                                        <x-icon name="refresh-cw" class="w-3.5 h-3.5" />
                                        {{ __('Regenerate') }}
                                    </button>
                                    <button wire:click="rejectAiDraft"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-500/20 text-red-400 hover:bg-red-500/10 text-xs font-medium rounded-lg transition-colors">
                                        <x-icon name="x" class="w-3.5 h-3.5" />
                                        {{ __('Reject') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        @elseif($message->sender_type === 'ai')
                        {{-- AI Message --}}
                        <div class="flex gap-3 max-w-[85%] ml-auto flex-row-reverse">
                            <div class="w-8 h-8 rounded-full bg-cyan-500/10 text-cyan-400 flex items-center justify-center shrink-0 mt-0.5">
                                <x-icon name="bot" class="w-4 h-4" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 mb-1 justify-end">
                                    <span class="text-[11px] text-gray-500" title="{{ $message->created_at->format('M j, Y g:i A') }}">{{ $message->created_at->diffForHumans() }}</span>
                                    <span class="text-[11px] text-gray-600">{{ __('via') }} {{ ucfirst($conversation->channel ?? __('email')) }}</span>
                                    <span class="text-sm font-semibold text-gray-200">{{ __('AI Assistant') }}</span>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                                        <x-icon name="bot" class="w-[10px] h-[10px]" />
                                        {{ __('AI') }}
                                    </span>
                                </div>
                                <div class="rounded-xl rounded-tr-sm bg-cyan-500/5 border border-cyan-500/15 px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap text-gray-300">
                                    {!! $message->safe_body_html !!}
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500">
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon name="bot" class="w-[11px] h-[11px]" />
                                        {{ __('AI Generated') }}
                                    </span>
                                    @if($message->ai_confidence)
                                    <span>{{ __('Confidence:') }} {{ $message->ai_confidence }}%</span>
                                    @endif
                                </div>
                                <div class="mt-2 flex items-center gap-1">
                                    <button class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] text-gray-500 hover:bg-white/5 hover:text-gray-300 transition-colors" aria-label="Helpful">
                                        <x-icon name="thumbs-up" class="w-3 h-3" />
                                    </button>
                                    <button class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] text-gray-500 hover:bg-white/5 hover:text-gray-300 transition-colors" aria-label="Unhelpful">
                                        <x-icon name="thumbs-down" class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        @elseif($message->direction === 'outbound')
                        {{-- Outgoing message --}}
                        <div class="flex gap-3 max-w-[85%] ml-auto flex-row-reverse">
                            <div class="w-8 h-8 rounded-full bg-[var(--color-control)] text-[var(--color-ink)] flex items-center justify-center text-xs font-semibold shrink-0 mt-0.5">
                                {{ $message->sender?->initials ?? __('Me') }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 mb-1 justify-end">
                                    <span class="text-[11px] text-gray-500" title="{{ $message->created_at->format('M j, Y g:i A') }}">{{ $message->created_at->diffForHumans() }}</span>
                                    <span class="text-[11px] text-gray-600">{{ __('via') }} {{ ucfirst($conversation->channel ?? __('email')) }}</span>
                                    <span class="text-sm font-semibold text-gray-200">{{ $message->sender?->name ?? __('You') }}</span>
                                </div>
                                <div class="rounded-xl rounded-tr-sm bg-white border border-[var(--color-border)] overflow-hidden" wire:ignore>
                                    <iframe
                                        src="{{ route('message.html', $message->id) }}"
                                        class="w-full border-0"
                                        title="Email content" style="height:60vh;min-height:240px;max-height:800px;background:#fff;"
                                    ></iframe>
                                </div>
                                @php $realAttachments = $message->attachments?->filter(fn ($a) => empty($a->content_id)) ?? collect(); @endphp
                                @if($realAttachments->isNotEmpty())
                                <div class="mt-2 space-y-1.5">
                                    @foreach($realAttachments as $attachment)
                                    <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-[#2d3039] bg-white/[0.02] text-xs group">
                                        <x-icon name="file" class="w-3.5 h-3.5 text-gray-500 shrink-0" />
                                        <span class="font-medium truncate max-w-[180px] text-gray-300">{{ $attachment->original_filename }}</span>
                                        <span class="text-gray-600">{{ $attachment->size_for_humans }}</span>
                                        <a href="{{ asset('storage/' . $attachment->storage_path) }}" target="_blank" class="text-[#3b82f6] hover:text-blue-300 ml-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <x-icon name="download" class="w-[13px] h-[13px]" />
                                        </a>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                                <div class="flex items-center gap-1 justify-end mt-1 text-[11px] text-gray-500">
                                    <x-icon name="check-check" class="w-[13px] h-[13px] text-green-400" />
                                    {{ __('Sent') }}
                                </div>
                            </div>
                        </div>

                        @else
                        {{-- Incoming Message --}}
                        <div class="flex gap-3 max-w-[92%]">
                            <div class="w-8 h-8 rounded-full {{ $avatarColors[$colorIndex] }} flex items-center justify-center text-xs font-semibold shrink-0 mt-0.5">
                                {{ $conversation->contact?->initials ?? '??' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-semibold text-gray-200">{{ $message->senderName ?? $conversation->contact?->full_name ?? __('Unknown') }}</span>
                                    <span class="text-[11px] text-gray-600">{{ __('via') }} {{ ucfirst($conversation->channel ?? __('email')) }}</span>
                                    <span class="text-[11px] text-gray-500" title="{{ $message->created_at->format('M j, Y g:i A') }}">{{ $message->created_at->diffForHumans() }}</span>
                                </div>
                                @if($message->subject && $message->subject !== $conversation->subject && $index === 0)
                                <div class="text-sm font-semibold text-gray-200 mb-1.5">{{ $message->subject }}</div>
                                @endif
                                @if($message->from_email)
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500 mb-2">
                                    <span>{{ __('From:') }} <span class="text-gray-400">{{ $message->from_name ?? '' }} &lt;{{ $message->from_email }}&gt;</span></span>
                                    @if($message->to_emails)
                                    <span>{{ __('To:') }} <span class="text-gray-400">{{ is_array($message->to_emails) ? implode(', ', $message->to_emails) : $message->to_emails }}</span></span>
                                    @endif
                                    <span>{{ $message->created_at->format('M j, Y \\a\\t g:i A') }}</span>
                                </div>
                                @endif
                                <div class="rounded-xl rounded-tl-sm bg-white border border-[#2d3039] overflow-hidden" wire:ignore>
                                    <iframe
                                        src="{{ route('message.html', $message->id) }}"
                                        class="w-full border-0"
                                        title="Email content" style="height:60vh;min-height:240px;max-height:800px;background:#fff;"
                                    ></iframe>
                                </div>
                                @php $realAttachments = $message->attachments?->filter(fn ($a) => empty($a->content_id)) ?? collect(); @endphp
                                @if($realAttachments->isNotEmpty())
                                <div class="mt-2 space-y-1.5">
                                    @foreach($realAttachments as $attachment)
                                    <div class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-[#2d3039] bg-white/[0.02] text-xs group">
                                        <x-icon name="file" class="w-3.5 h-3.5 text-gray-500 shrink-0" />
                                        <span class="font-medium truncate max-w-[180px] text-gray-300">{{ $attachment->original_filename }}</span>
                                        <span class="text-gray-600">{{ $attachment->size_for_humans }}</span>
                                        <a href="{{ asset('storage/' . $attachment->storage_path) }}" target="_blank" class="text-[#3b82f6] hover:text-blue-300 ml-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <x-icon name="download" class="w-[13px] h-[13px]" />
                                        </a>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                                @if($message->sentiment && $message->direction === 'inbound')
                                @php
                                    $sentimentStyle = match($message->sentiment) {
                                        'positive' => 'text-green-400 bg-green-500/10',
                                        'negative' => 'text-red-400 bg-red-500/10',
                                        default => 'text-gray-500 bg-white/5',
                                    };
                                @endphp
                                <div class="mt-1.5">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-medium rounded {{ $sentimentStyle }}">
                                        {{ ucfirst($message->sentiment) }}
                                    </span>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach

                    @if($messages->isEmpty())
                    <div class="text-center py-8">
                        <p class="text-sm text-gray-500">{{ __('No messages in this conversation yet.') }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- AI loading indicator --}}
        @if($aiLoading ?? false)
        <div class="px-6 py-3 bg-cyan-500/5 border-t border-cyan-500/20 flex-shrink-0">
            <div class="flex items-center gap-2 text-sm text-cyan-400">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Generating AI reply...') }}
            </div>
        </div>
        @endif

        {{-- Reply Composer with toggle --}}
        <div wire:key="composer-toggle-{{ $conversationId }}" x-data="{ composerOpen: false }" class="shrink-0">
            <button @click="composerOpen = !composerOpen"
                    class="m-3 inline-flex items-center justify-center gap-2 rounded-full bg-blue-600 px-6 py-2.5 text-white hover:bg-blue-500 transition-colors text-sm font-semibold">
                <svg class="w-3.5 h-3.5 transition-transform" :class="composerOpen ? '' : 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
                <span x-text="composerOpen ? 'Hide reply' : 'Reply'"></span>
            </button>
            <div x-show="composerOpen" x-cloak x-collapse>
                <livewire:inbox.reply-composer :conversationId="$conversationId" :key="'reply-'.$conversationId" />
            </div>
        </div>
    </div>

    {{-- Right Panel: Conversation Details --}}
    <div x-show="rightPanel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0"
         class="w-[280px] bg-[#11131a]/95 border-l border-[#2d3039] flex flex-col shrink-0 font-sans backdrop-blur-xl overflow-y-auto hide-scroll"
         style="display:none">
        <div class="h-6"></div>
        <div class="p-4 space-y-[6px]">

            {{-- Tags --}}
            <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3">
                    <x-icon name="tag" class="w-[14px] h-[14px]" />
                    {{ __('Tags') }}
                    @if($conversation->tagModels->count() > 0)
                    <span class="bg-white/10 text-gray-300 text-[10px] px-1.5 py-0.5 rounded-full font-medium">{{ $conversation->tagModels->count() }}</span>
                    @endif
                </div>
                <div class="space-y-2 pl-[22px]">
                    @foreach($conversation->tagModels as $tag)
                    <div class="flex items-center justify-between text-[11px] font-medium text-gray-300 group">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $tag->color ?? '#6B7280' }}"></span>
                            {{ $tag->name }}
                        </div>
                        <button wire:click="removeTag({{ $tag->id }})" class="text-gray-600 hover:text-red-400 opacity-0 group-hover:opacity-100 transition">
                            <x-icon name="x" class="w-3 h-3" />
                        </button>
                    </div>
                    @endforeach
                    <details class="relative">
                        <summary class="text-[11px] text-[#3b82f6] hover:text-blue-300 font-medium flex items-center gap-1 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            <x-icon name="plus" class="w-3 h-3" /> {{ __('Add tag') }}
                        </summary>
                        <div class="absolute left-0 top-full mt-1 w-44 bg-[#1a1d27] border border-white/10 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] py-1.5 z-50 max-h-48 overflow-y-auto">
                            @foreach($tags as $tag)
                            @if(!$conversation->tagModels->contains('id', $tag->id))
                            <button wire:click="addTag({{ $tag->id }})" onclick="this.closest('details').removeAttribute('open')"
                                    class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full" style="background-color: {{ $tag->color ?? '#6B7280' }}"></span>
                                {{ $tag->name }}
                            </button>
                            @endif
                            @endforeach
                        </div>
                    </details>
                </div>
            </div>

            {{-- Assignment --}}
            <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3">
                    <x-icon name="user-plus" class="w-[14px] h-[14px]" />
                    {{ __('Assignment') }}
                </div>
                <div class="pl-[22px]">
                    @if($conversation->assignedTo)
                    <div class="flex items-center gap-2 text-[11px] font-medium text-gray-300 mb-2">
                        <span class="w-5 h-5 rounded-full bg-[#3b82f6]/10 text-[#3b82f6] flex items-center justify-center text-[8px] font-bold">{{ $conversation->assignedTo->initials }}</span>
                        {{ $conversation->assignedTo->name }}
                    </div>
                    @else
                    <p class="text-[11px] text-gray-600 mb-2">{{ __('Unassigned') }}</p>
                    @endif
                    <details class="relative">
                        <summary class="text-[11px] text-[#3b82f6] hover:text-blue-300 font-medium flex items-center gap-1 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            <x-icon name="user-plus" class="w-3 h-3" /> {{ $conversation->assignedTo ? __('Reassign') : __('Assign') }}
                        </summary>
                        <div class="absolute left-0 top-full mt-1 w-48 bg-[#1a1d27] border border-white/10 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.5)] py-1.5 z-50 max-h-48 overflow-y-auto">
                            <button wire:click="assignTo(null)" onclick="this.closest('details').removeAttribute('open')"
                                    class="w-full text-left px-3 py-1.5 text-[12px] text-gray-400 hover:bg-white/5 hover:text-white transition">{{ __('Unassigned') }}</button>
                            @foreach($teamMembers as $member)
                            <button wire:click="assignTo({{ $member->id }})" onclick="this.closest('details').removeAttribute('open')"
                                    class="w-full text-left px-3 py-1.5 text-[12px] text-gray-300 hover:bg-white/5 hover:text-white transition flex items-center gap-2 {{ $conversation->assigned_to === $member->id ? 'bg-white/5' : '' }}">
                                <span class="w-4 h-4 rounded-full bg-[#3b82f6]/10 text-[#3b82f6] flex items-center justify-center text-[8px] font-bold shrink-0">{{ $member->initials }}</span>
                                {{ $member->name }}
                            </button>
                            @endforeach
                        </div>
                    </details>
                </div>
            </div>

            {{-- Priority --}}
            <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3">
                    <x-icon name="flag" class="w-[14px] h-[14px]" />
                    {{ __('Priority') }}
                    @php $priorityColors = ['low' => 'text-gray-500', 'normal' => 'text-blue-400', 'high' => 'text-yellow-400', 'urgent' => 'text-red-400']; @endphp
                    <span class="text-[10px] font-medium {{ $priorityColors[$conversation->priority] ?? 'text-blue-400' }}">{{ ucfirst($conversation->priority) }}</span>
                </div>
                <div class="pl-[22px] space-y-1">
                    @foreach([['value' => 'low', 'label' => __('Low'), 'dot' => 'bg-gray-400'], ['value' => 'normal', 'label' => __('Normal'), 'dot' => 'bg-blue-400'], ['value' => 'high', 'label' => __('High'), 'dot' => 'bg-yellow-400'], ['value' => 'urgent', 'label' => __('Urgent'), 'dot' => 'bg-red-400']] as $opt)
                    <button wire:click="setPriority('{{ $opt['value'] }}')"
                            class="w-full text-left flex items-center gap-2 px-2 py-1 rounded-md text-[11px] font-medium transition-colors {{ $conversation->priority === $opt['value'] ? 'bg-white/5 text-gray-200' : 'text-gray-500 hover:bg-white/5 hover:text-gray-300' }}">
                        <span class="w-2 h-2 rounded-full {{ $opt['dot'] }}"></span>
                        {{ $opt['label'] }}
                        @if($conversation->priority === $opt['value'])
                        <x-icon name="check" class="w-3 h-3 ml-auto text-[#3b82f6]" />
                        @endif
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Stripe --}}
            @if($stripeCustomer)
            <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl p-3">
                <div class="flex items-center gap-2 text-[12px] font-semibold text-gray-400 mb-3">
                    <svg class="w-[14px] h-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    {{ __('Stripe') }}
                    @if($stripeCustomer['has_active_subscription'])
                    <span class="bg-green-500/10 text-green-400 text-[10px] px-1.5 py-0.5 rounded-full font-medium border border-green-500/20">{{ __('Active') }}</span>
                    @endif
                </div>
                <div class="pl-[22px] space-y-2">
                    <div class="flex justify-between text-[11px]">
                        <span class="text-gray-500">{{ __('ID') }}</span>
                        <span class="text-gray-300 font-mono text-[10px]">{{ $stripeCustomer['customer']['id'] }}</span>
                    </div>
                    <div class="flex justify-between text-[11px]">
                        <span class="text-gray-500">{{ __('Spent') }}</span>
                        <span class="text-gray-200 font-semibold">${{ number_format($stripeCustomer['total_spent'], 2) }}</span>
                    </div>
                    @if($stripeCustomer['customer']['delinquent'] ?? false)
                    <div class="p-2 bg-red-500/10 border border-red-500/20 rounded-lg">
                        <p class="text-[11px] text-red-400 font-medium">{{ __('Payment delinquent') }}</p>
                    </div>
                    @endif
                    @if(!empty($stripeCustomer['subscriptions']))
                    <div class="pt-1">
                        <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1.5">{{ __('Subscriptions') }}</p>
                        @foreach($stripeCustomer['subscriptions'] as $sub)
                        <div class="flex items-center justify-between py-1.5 border-b border-[#2d3039] last:border-0">
                            <div>
                                <p class="text-[11px] font-medium text-gray-300">{{ implode(', ', $sub['plan_names']) }}</p>
                                <p class="text-[10px] text-gray-500">@currency($sub['amount_total'])/{{ $sub['interval'] }}</p>
                            </div>
                            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded-full
                                {{ $sub['status'] === 'active' ? 'bg-green-500/10 text-green-400' : '' }}
                                {{ $sub['status'] === 'past_due' ? 'bg-red-500/10 text-red-400' : '' }}
                                {{ $sub['status'] === 'trialing' ? 'bg-blue-500/10 text-blue-400' : '' }}
                                {{ !in_array($sub['status'], ['active', 'past_due', 'trialing']) ? 'bg-white/5 text-gray-500' : '' }}">
                                {{ ucfirst($sub['status']) }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    @if(!empty($stripeCustomer['payments']))
                    <div class="pt-1">
                        <p class="text-[10px] font-medium text-gray-500 uppercase tracking-wider mb-1.5">{{ __('Recent Payments') }}</p>
                        @foreach(array_slice($stripeCustomer['payments'], 0, 5) as $payment)
                        <div class="flex items-center justify-between py-1.5 border-b border-[#2d3039] last:border-0">
                            <div>
                                <p class="text-[11px] text-gray-300">@currency($payment['amount'])</p>
                                <p class="text-[10px] text-gray-500">{{ $payment['created_human'] }}</p>
                            </div>
                            @if($payment['refunded'] ?? false)
                            <span class="px-1.5 py-0.5 text-[10px] font-medium bg-yellow-500/10 text-yellow-400 rounded-full">{{ __('Refunded') }}</span>
                            @elseif($payment['paid'] ?? false)
                            <span class="px-1.5 py-0.5 text-[10px] font-medium bg-green-500/10 text-green-400 rounded-full">{{ __('Paid') }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Details --}}
            <div class="pt-4 pb-10">
                <div class="flex items-center gap-2 mb-4 text-[12px] font-semibold text-gray-400 px-3">
                    <svg class="w-[14px] h-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('Details') }}
                </div>
                <div class="space-y-4 px-3">
                    <div class="space-y-2">
                        <span class="text-[11px] text-gray-500 font-medium">{{ __('Channel') }}</span>
                        <div class="bg-blue-500/10 border border-blue-500/20 text-blue-300 rounded-lg p-2.5 flex items-center gap-2 text-[11px] font-medium">
                            <div class="w-4 h-4 rounded bg-[#3b82f6] shrink-0 flex items-center justify-center">
                                <x-icon name="mail" class="w-2.5 h-2.5 text-white" />
                            </div>
                            <span class="truncate">{{ ucfirst($conversation->channel ?? 'email') }}{{ $conversation->emailAccount ? ' — ' . $conversation->emailAccount->email : '' }}</span>
                        </div>
                    </div>
                    @if($conversation->contact?->email)
                    <div class="space-y-2">
                        <span class="text-[11px] text-gray-500 font-medium">{{ __('Contact') }}</span>
                        <div class="text-[11px] text-gray-300">{{ $conversation->contact->email }}</div>
                    </div>
                    @endif
                    <div class="space-y-3 pt-2">
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">{{ __('Last message:') }}</span>
                            <span class="text-gray-300">{{ $conversation->last_message_at?->format('M j Y, g:i A') ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">{{ __('Created:') }}</span>
                            <span class="text-gray-300">{{ $conversation->created_at?->format('M j Y, g:i A') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @endif
</div>
