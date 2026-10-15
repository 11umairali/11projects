<div class="space-y-6" x-data="{ dirty: false }" @change="dirty = true" @saved.window="dirty = false"
     x-on:livewire:navigating.window="if(dirty && !confirm('{{ __('You have unsaved changes. Leave anyway?') }}')) $event.preventDefault()"
     x-init="window.addEventListener('beforeunload', (e) => { if(dirty) { e.preventDefault(); e.returnValue = '' } })">
    @if(session('message'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm">{{ session('message') }}</div>
    @endif
    @if(session('error'))
    <div class="p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-ink">{{ __('AI Configuration') }}</h1>
        <p class="text-sm text-muted mt-1">{{ __('Configure how your AI assistant generates replies.') }}</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Provider & Model --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
            <h2 class="text-lg font-semibold text-ink">{{ __('AI Provider & Model') }}</h2>

            {{-- Provider Cards --}}
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-2">{{ __('Provider') }}</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @php
                    $providerCards = [
                        ['id' => 'openai', 'name' => 'OpenAI', 'desc' => 'GPT-4o, o1, GPT-3.5', 'bg' => 'bg-success/15', 'text' => 'text-success'],
                        ['id' => 'anthropic', 'name' => 'Anthropic', 'desc' => 'Claude Opus 4, Sonnet 4', 'bg' => 'bg-orange-100', 'text' => 'text-orange-600'],
                        ['id' => 'gemini', 'name' => 'Google Gemini', 'desc' => 'Gemini 2.0 Flash, 1.5 Pro', 'bg' => 'bg-info/15', 'text' => 'text-blue-600'],
                        ['id' => 'mistral', 'name' => 'Mistral', 'desc' => 'Large, Small, Codestral', 'bg' => 'bg-brand/15', 'text' => 'text-brand'],
                    ];
                    @endphp
                    @foreach($providerCards as $p)
                    <button type="button" wire:click="$set('provider', '{{ $p['id'] }}')"
                            class="p-3 border-2 rounded-xl text-center transition-all {{ $provider === $p['id'] ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-500' : 'border-border hover:border-border' }}">
                        <div class="w-8 h-8 {{ $p['bg'] }} rounded-lg flex items-center justify-center mx-auto mb-1.5">
                            <span class="text-xs font-bold {{ $p['text'] }}">{{ substr($p['name'], 0, 2) }}</span>
                        </div>
                        <p class="text-sm font-medium text-ink">{{ $p['name'] }}</p>
                        <p class="text-[10px] text-muted mt-0.5">{{ $p['desc'] }}</p>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Model Selection --}}
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-2">{{ __('Model') }}</label>
                <div class="space-y-1.5 max-h-[340px] overflow-y-auto pr-1">
                    @forelse($models as $modelId => $modelLabel)
                    <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition-all {{ $model === $modelId ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-500' : 'border-border hover:border-border' }}">
                        <input type="radio" wire:model="model" value="{{ $modelId }}" class="sr-only">
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-semibold text-ink">{{ $modelLabel }}</span>
                        </div>
                        <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0 {{ $model === $modelId ? 'border-primary-500' : 'border-border' }}">
                            @if($model === $modelId)
                            <div class="w-2 h-2 rounded-full bg-primary-500"></div>
                            @endif
                        </div>
                    </label>
                    @empty
                    <p class="text-sm text-muted p-3">{{ __('No models available for this provider.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Creativity Level') }} ({{ $temperature }})</label>
                    <input type="range" wire:model.live="temperature" min="0" max="2" step="0.1" class="w-full accent-primary-600">
                    <p class="text-xs text-muted mt-1">{{ __('Controls how creative the AI\'s responses are. Lower = more predictable and safe, higher = more varied.') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Maximum Response Length') }}</label>
                    <select wire:model="max_reply_length" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="short">{{ __('Short (a few sentences)') }}</option>
                        <option value="medium">{{ __('Medium (a paragraph or two)') }}</option>
                        <option value="long">{{ __('Long (detailed response)') }}</option>
                        <option value="very_long">{{ __('Very Long (comprehensive response)') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Confidence Level') }} ({{ $confidence_threshold }}%)</label>
                    <input type="range" wire:model.live="confidence_threshold" min="0" max="100" step="5" class="w-full accent-primary-600">
                    <p class="text-xs text-muted mt-1">{{ __('How sure the AI must be before suggesting a reply. Higher = fewer but more accurate replies.') }}</p>
                </div>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════
             AI AUTO-ESCALATION
             When AI confidence falls below the configured threshold, the
             conversation is auto-assigned to a human, tagged for review,
             and the assignee gets an in-app notification. The reply stays
             as a draft so the human can review/edit before sending.
             ════════════════════════════════════════════════════════════════ --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Auto-Escalation to Humans') }}</h2>
                    <p class="text-sm text-muted mt-1">
                        {{ __("When AI is unsure, automatically hand the conversation to a human teammate — they get notified instantly and the AI's draft reply waits for their review.") }}
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                    <input type="checkbox" wire:model.live="escalation_enabled" class="sr-only peer">
                    <div class="w-11 h-6 bg-border rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
            </div>

            @if($escalation_enabled)
            <div class="border-t border-border/50 pt-4 space-y-4">
                {{-- Confidence threshold slider --}}
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">
                        {{ __('Escalate when confidence below') }}
                        <span class="text-brand font-semibold">({{ $escalate_below_confidence }}%)</span>
                    </label>
                    <input type="range" wire:model.live="escalate_below_confidence" min="0" max="100" step="5" class="w-full accent-primary-600">
                    <div class="flex justify-between text-[10px] text-muted mt-0.5">
                        <span>0% (never)</span>
                        <span>50% (balanced)</span>
                        <span>100% (always)</span>
                    </div>
                    <p class="text-xs text-muted mt-1">
                        {{ __('Recommended: 50% — escalates when the AI is unsure but lets it handle confident replies on its own.') }}
                    </p>
                </div>

                {{-- Assignee picker --}}
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Assign escalated conversations to') }}</label>
                    <select wire:model.live="escalation_assignee_id" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">{{ __('— Round-robin (any available agent) —') }}</option>
                        @foreach($assigneeOptions as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->email }})</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-muted mt-1">
                        {{ __('Round-robin picks a random member who has "Available for assignment" enabled. Choose a specific agent to always send escalations to them.') }}
                    </p>
                </div>

                {{-- Tag name --}}
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Tag escalated conversations as') }}</label>
                    <input type="text" wire:model="escalation_tag" maxlength="64" placeholder="needs_human"
                           class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <p class="text-xs text-muted mt-1">
                        {{ __('A workspace tag with this name is created (or reused) and attached to the conversation, so you can filter your inbox by it later.') }}
                    </p>
                </div>

                {{-- Live preview / what happens --}}
                <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl p-4">
                    <p class="text-xs font-semibold text-primary-700 dark:text-primary-300 mb-2">{{ __('When this fires, MailTrixy will:') }}</p>
                    <ul class="text-xs text-primary-700 dark:text-primary-300 space-y-1 list-disc list-inside">
                        <li>{{ __('Assign the conversation to the chosen agent (or round-robin)') }}</li>
                        <li>{{ __('Bump the conversation priority to "high"') }}</li>
                        <li>{{ __('Tag it with') }} <code class="px-1 bg-primary-100 dark:bg-primary-800 rounded">{{ $escalation_tag ?: 'needs_human' }}</code></li>
                        <li>{{ __('Send an in-app notification (bell icon) to the assignee with a deep link') }}</li>
                        <li>{{ __("Keep the AI's reply as a draft — the human reviews and sends it") }}</li>
                    </ul>
                </div>
            </div>
            @endif
        </div>

        {{-- Personality --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-4">
            <h2 class="text-lg font-semibold text-ink">{{ __('Personality & Tone') }}</h2>

            <div>
                <label class="block text-sm font-medium text-ink/80 mb-2">{{ __('Personality Preset') }}</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach(['professional' => 'Professional', 'friendly' => 'Friendly', 'casual' => 'Casual', 'sales' => 'Sales', 'support' => 'Support', 'custom' => 'Custom'] as $key => $label)
                    <label class="flex items-center gap-2 p-3 border rounded-xl cursor-pointer transition-colors {{ $personality_preset === $key ? 'border-primary-500 bg-primary-50' : 'border-border hover:border-border' }}">
                        <input type="radio" wire:model.live="personality_preset" value="{{ $key }}" class="text-primary-600 focus:ring-primary-500">
                        <span class="text-sm font-medium text-ink/80">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            @if($personality_preset === 'custom')
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Custom Personality Prompt') }}</label>
                <div x-data="{ count: 0 }" x-init="count = ($wire.custom_prompt || '').length">
                    <textarea wire:model="custom_prompt" rows="3" maxlength="5000" @input="count = $event.target.value.length" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none" placeholder="{{ __('Describe the AI personality...') }}"></textarea>
                    <p class="text-xs mt-1 text-right" :class="count > 4500 ? 'text-danger' : 'text-muted'" x-text="count + ' / 5,000'"></p>
                </div>
            </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Additional Instructions') }}</label>
                <textarea wire:model="additional_instructions" rows="3" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none" placeholder="{{ __('Any extra instructions for the AI...') }}"></textarea>
            </div>
        </div>

        {{-- Behavior --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-4">
            <h2 class="text-lg font-semibold text-ink">{{ __('Reply Behavior') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Send Mode') }}</label>
                    <select wire:model="send_mode" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="autonomous">{{ __('Fully Automatic (above confidence level)') }}</option>
                        <option value="approval">{{ __('Review Before Sending') }}</option>
                        <option value="suggestions">{{ __('Suggestions Only') }}</option>
                    </select>
                    <p class="text-xs text-muted mt-1">
                        @if($send_mode === 'autonomous')
                            {{ __('AI will send replies automatically when confidence is above your threshold. Best for high-volume support.') }}
                        @elseif($send_mode === 'approval')
                            {{ __('AI will draft replies for you to review and approve before sending. Recommended for most teams.') }}
                        @else
                            {{ __('AI will only show suggestions — you write and send the final reply yourself.') }}
                        @endif
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Reply Language') }}</label>
                    <select wire:model="reply_language" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="auto">{{ __('Auto-detect') }}</option>
                        <option value="en">{{ __('English') }}</option>
                        <option value="es">{{ __('Spanish') }}</option>
                        <option value="fr">{{ __('French') }}</option>
                        <option value="de">{{ __('German') }}</option>
                        <option value="pt">{{ __('Portuguese') }}</option>
                        <option value="it">{{ __('Italian') }}</option>
                        <option value="nl">{{ __('Dutch') }}</option>
                        <option value="ja">{{ __('Japanese') }}</option>
                        <option value="zh">{{ __('Chinese') }}</option>
                        <option value="ko">{{ __('Korean') }}</option>
                        <option value="ar">{{ __('Arabic') }}</option>
                        <option value="hi">{{ __('Hindi') }}</option>
                        <option value="ru">{{ __('Russian') }}</option>
                        <option value="tr">{{ __('Turkish') }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Include Greeting') }}</label>
                    <select wire:model="include_greeting" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="always">{{ __('Always') }}</option>
                        <option value="never">{{ __('Never') }}</option>
                        <option value="ai_decides">{{ __('AI Decides') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Include Sign-off') }}</label>
                    <select wire:model="include_signoff" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="always">{{ __('Always') }}</option>
                        <option value="never">{{ __('Never') }}</option>
                        <option value="ai_decides">{{ __('AI Decides') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Sign-off Text') }}</label>
                    <input type="text" wire:model="signoff_text" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="{{ __('Best regards,') }}">
                </div>
            </div>

            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="auto_reply_enabled" class="w-4 h-4 mt-0.5 text-primary-600 border-border rounded focus:ring-primary-500">
                    <div>
                        <span class="text-sm text-ink/80">{{ __('Enable auto-reply') }}</span>
                        <p class="text-xs text-muted">{{ __('When enabled, AI will automatically generate replies to incoming messages based on your settings above.') }}</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="use_html_formatting" class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
                    <span class="text-sm text-ink/80">{{ __('Use rich text formatting in replies') }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="use_bullet_points" class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
                    <span class="text-sm text-ink/80">{{ __('Use bullet points for lists') }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="include_sender_name" class="w-4 h-4 text-primary-600 border-border rounded focus:ring-primary-500">
                    <span class="text-sm text-ink/80">{{ __('Include your name in the sign-off') }}</span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="business_hours_only" class="w-4 h-4 mt-0.5 text-primary-600 border-border rounded focus:ring-primary-500">
                    <div>
                        <span class="text-sm text-ink/80">{{ __('Only auto-reply during business hours') }}</span>
                        <p class="text-xs text-muted">{{ __('AI will not send automatic replies outside your workspace\'s business hours.') }}</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="first_message_only" class="w-4 h-4 mt-0.5 text-primary-600 border-border rounded focus:ring-primary-500">
                    <div>
                        <span class="text-sm text-ink/80">{{ __('Only reply to the first message in a conversation') }}</span>
                        <p class="text-xs text-muted">{{ __('Prevents AI from replying to every follow-up. Useful to avoid overwhelming customers.') }}</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="skip_own_threads" class="w-4 h-4 mt-0.5 text-primary-600 border-border rounded focus:ring-primary-500">
                    <div>
                        <span class="text-sm text-ink/80">{{ __('Skip conversations started by your team') }}</span>
                        <p class="text-xs text-muted">{{ __('AI will not generate replies for outbound conversations initiated by your team members.') }}</p>
                    </div>
                </label>
            </div>
        </div>

        <button type="submit" class="btn-primary px-5 py-2.5 text-sm">
            <span wire:loading.remove wire:target="save">{{ __('Save Configuration') }}</span>
            <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Saving...') }}
            </span>
        </button>
    </form>

    {{-- Test AI --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-4">
        <h2 class="text-lg font-semibold text-ink">{{ __('Test AI Reply') }}</h2>
        <p class="text-xs text-muted">{{ __('Send a test message to see how your AI would respond with the current configuration.') }}</p>

        <div>
            <textarea wire:model="testMessage" rows="3" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none" placeholder="{{ __('Type a test customer message...') }}"></textarea>
        </div>

        <button wire:click="testAi" type="button" class="px-5 py-2 bg-secondary-600 text-white text-sm font-medium rounded-xl hover:bg-secondary-700 transition-colors" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="testAi">{{ __('Test AI Reply') }}</span>
            <span wire:loading wire:target="testAi" class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Generating...') }}
            </span>
        </button>

        @if($testResponse)
        <div class="bg-secondary-50 border border-secondary-200 rounded-xl p-4">
            <div class="flex items-center gap-1.5 mb-2">
                <svg class="w-4 h-4 text-secondary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span class="text-sm font-medium text-secondary-700">{{ __('AI Response') }}</span>
            </div>
            <pre class="text-sm text-ink/80 whitespace-pre-wrap font-sans">{{ $testResponse }}</pre>
        </div>
        @endif
    </div>
</div>
