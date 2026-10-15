<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Auto-Reply Rules') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('Set up keyword-based auto-replies for incoming messages. Rules are checked before AI auto-reply.') }}</p>
        </div>
        <button wire:click="openForm"
                class="px-4 py-2.5 bg-brand text-white text-sm font-medium rounded-xl hover:bg-brand-strong transition-colors inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            {{ __('New Rule') }}
        </button>
    </div>

    {{-- Test rules --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-5">
        <h3 class="text-sm font-semibold text-ink mb-2">{{ __('Test Your Rules') }}</h3>
        <p class="text-xs text-muted mb-3">{{ __('Enter a sample message to see which rule would match.') }}</p>
        <div class="flex gap-3">
            <input type="text" wire:model="testInput" wire:keydown.enter="testRules"
                   class="flex-1 px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 bg-surface"
                   placeholder="{{ __("Type a test message, e.g. 'I need help with pricing'") }}">
            <button wire:click="testRules" class="px-4 py-2.5 bg-brand text-white text-sm font-medium rounded-xl hover:bg-brand-strong transition-colors shrink-0">
                Test
            </button>
        </div>
        @if($testResult)
        @php
            $isMatched = str_contains($testResult, 'Matched');
            $resultClass = $isMatched ? 'text-success border border-success/20' : 'text-muted border border-border';
        @endphp
        <div class="mt-3 p-3 bg-surface rounded-xl text-sm {{ $resultClass }}">
            {{ $testResult }}
        </div>
        @endif
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ session('error') }}
    </div>
    @endif

    {{-- Rules list --}}
    @if($rules->isEmpty())
    <div class="bg-surface-2 rounded-2xl border border-border p-10 text-center">
        <div class="w-14 h-14 bg-brand/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                <path d="M13 8H7M17 12H7"/>
            </svg>
        </div>
        <h3 class="text-sm font-semibold text-ink">{{ __('No auto-reply rules yet') }}</h3>
        <p class="text-xs text-muted mt-1 max-w-sm mx-auto">{{ __('Create your first rule to automatically reply to messages that contain specific keywords.') }}</p>
        <button wire:click="openForm"
                class="mt-4 px-4 py-2 bg-brand text-white text-sm font-medium rounded-xl hover:bg-brand-strong transition-colors">
            {{ __('Create First Rule') }}
        </button>
    </div>
    @else
    <div class="space-y-3">
        @foreach($rules as $rule)
        <div class="bg-surface-2 rounded-2xl border border-border p-5 hover:shadow-md transition-all duration-200">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3">
                        <h3 class="text-sm font-semibold text-ink truncate">{{ $rule->name }}</h3>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $rule->is_active ? 'text-success' : 'text-muted' }}">
                            <span class="w-2 h-2 rounded-full {{ $rule->is_active ? 'bg-success' : 'bg-gray-300' }}"></span>
                            {{ $rule->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach($rule->keywords ?? [] as $kw)
                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-brand/10 text-brand rounded-lg">{{ $kw }}</span>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-4 mt-3 text-xs text-muted">
                        <span class="capitalize">{{ __('Match:') }} {{ $rule->match_type }}</span>
                        <span class="capitalize">{{ __('Channel:') }} {{ $rule->channel }}</span>
                        @if($rule->first_message_only)
                        <span>{{ __('First message only') }}</span>
                        @endif
                        <span>{{ __('Priority:') }} {{ $rule->priority }}</span>
                        <span>{{ __('Used') }} {{ number_format($rule->usage_count) }} {{ Str::plural('time', $rule->usage_count) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    {{-- Toggle active --}}
                    <button wire:click="toggleActive({{ $rule->id }})" type="button"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $rule->is_active ? 'bg-brand' : 'bg-gray-200 dark:bg-gray-700' }}"
                            title="{{ $rule->is_active ? 'Deactivate' : 'Activate' }}">
                        <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $rule->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                    </button>

                    {{-- Edit --}}
                    <button wire:click="openForm({{ $rule->id }})"
                            class="p-2 text-muted hover:text-ink rounded-lg hover:bg-surface transition-colors"
                            :title="__('Edit rule')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>

                    {{-- Delete --}}
                    <button wire:click="delete({{ $rule->id }})"
                            wire:confirm="{{ __('Delete this auto-reply rule?') }}"
                            class="p-2 text-muted hover:text-danger rounded-lg hover:bg-danger/10 transition-colors"
                            :title="__('Delete rule')">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Create/Edit Modal --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="auto-reply-rule-modal-title">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-surface/50 backdrop-blur-sm" wire:click="closeForm"></div>
            <div class="relative bg-surface-2 rounded-2xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto" x-trap="$wire.showForm">

                <div class="flex items-center justify-between mb-5">
                    <h2 id="auto-reply-rule-modal-title" class="text-lg font-semibold text-ink">
                        {{ $editingId ? 'Edit Rule' : 'New Auto-Reply Rule' }}
                    </h2>
                    <button wire:click="closeForm" class="p-2 text-muted hover:text-ink rounded-lg hover:bg-surface transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    {{-- Name --}}
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Rule Name') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name"
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface"
                               placeholder="{{ __('e.g. Pricing inquiry') }}">
                        @error('name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Keywords --}}
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Keywords') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="keywordsInput"
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface"
                               placeholder="{{ __('price, pricing, cost, how much') }}">
                        <p class="text-xs text-muted mt-1">{{ __('Comma-separated. Case-insensitive matching.') }}</p>
                        @error('keywordsInput') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Match Type --}}
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Match Type') }}</label>
                        <select wire:model="matchType"
                                class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface">
                            <option value="any">{{ __('Any keyword (OR)') }}</option>
                            <option value="all">{{ __('All keywords (AND)') }}</option>
                            <option value="exact">{{ __('Exact phrase match') }}</option>
                        </select>
                        <p class="text-xs text-muted mt-1">
                            @if($matchType === 'any') {{ __('Triggers if the message contains') }} <strong>{{ __('any') }}</strong> {{ __('of the keywords.') }}
                            @elseif($matchType === 'all') {{ __('Triggers only if the message contains') }} <strong>{{ __('all') }}</strong> {{ __('keywords.') }}
                            @else {{ __('Triggers only if the full message text exactly equals one of the keywords.') }}
                            @endif
                        </p>
                    </div>

                    {{-- Channel --}}
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Channel') }}</label>
                        <select wire:model="channel"
                                class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface">
                            <option value="all">{{ __('All Channels') }}</option>
                            <option value="email">{{ __('Email only') }}</option>
                            <option value="whatsapp">{{ __('WhatsApp only') }}</option>
                            <option value="sms">{{ __('SMS only') }}</option>
                            <option value="chat">{{ __('Live Chat only') }}</option>
                            <option value="telegram">{{ __('Telegram only') }}</option>
                        </select>
                    </div>

                    {{-- Reply Subject --}}
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Reply Subject') }} <span class="text-xs text-muted">{{ __('(optional)') }}</span></label>
                        <input type="text" wire:model="replySubject"
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface"
                               placeholder="{{ __("Leave empty to use 'Re: original subject'") }}">
                        @error('replySubject') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Reply Body --}}
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Reply Body') }} <span class="text-red-500">*</span></label>
                        <textarea wire:model="replyBody" rows="6"
                                  class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface"
                                  placeholder="{{ __('Hi there! Thank you for your inquiry about pricing...') }}"></textarea>
                        <p class="text-xs text-muted mt-1">{{ __('HTML is supported for email replies.') }}</p>
                        @error('replyBody') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Priority --}}
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Priority') }}</label>
                        <input type="number" wire:model="priority" min="0" max="999"
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface"
                               placeholder="0">
                        <p class="text-xs text-muted mt-1">{{ __('Higher priority rules are checked first. Default is 0.') }}</p>
                    </div>

                    {{-- Toggles --}}
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-surface rounded-xl">
                            <div>
                                <span class="text-sm text-ink/80">{{ __('Active') }}</span>
                                <p class="text-xs text-muted mt-0.5">{{ __('Enable or disable this rule') }}</p>
                            </div>
                            <button wire:click="$toggle('isActive')" type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $isActive ? 'bg-brand' : 'bg-gray-200 dark:bg-gray-700' }}">
                                <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $isActive ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-surface rounded-xl">
                            <div>
                                <span class="text-sm text-ink/80">{{ __('First message only') }}</span>
                                <p class="text-xs text-muted mt-0.5">{{ __('Only trigger on the first inbound message in a conversation') }}</p>
                            </div>
                            <button wire:click="$toggle('firstMessageOnly')" type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $firstMessageOnly ? 'bg-brand' : 'bg-gray-200 dark:bg-gray-700' }}">
                                <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $firstMessageOnly ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-border">
                    <button wire:click="closeForm" class="px-4 py-2.5 text-sm font-medium text-muted hover:text-ink">{{ __('Cancel') }}</button>
                    <button wire:click="save" class="px-6 py-2.5 bg-brand text-white text-sm font-medium rounded-xl hover:bg-brand-strong transition-colors">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Update Rule' : 'Create Rule' }}</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
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
