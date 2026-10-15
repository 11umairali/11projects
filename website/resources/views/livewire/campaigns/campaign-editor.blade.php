<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('campaigns') }}" class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-ink">{{ $campaignId ? __('Edit Campaign') : __('Create Campaign') }}</h1>
            </div>
        </div>
        <button wire:click="saveDraft" class="px-4 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors">
            {{ __('Save Draft') }}
        </button>
    </div>

    {{-- Step Progress --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-4">
        <div class="flex items-center justify-between">
            @foreach([1 => __('Basics'), 2 => __('Content'), 3 => __('Audience'), 4 => __('Schedule')] as $step => $label)
            <button wire:click="goToStep({{ $step }})"
                    class="flex items-center gap-2 {{ $currentStep === $step ? 'text-primary-700 dark:text-primary-300' : ($currentStep > $step ? 'text-success' : 'text-muted') }}">
                <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold border-2 {{ $currentStep === $step ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300' : ($currentStep > $step ? 'border-green-500 bg-success/10 text-success' : 'border-border text-muted') }}">
                    @if($currentStep > $step)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @else
                    {{ $step }}
                    @endif
                </span>
                <span class="text-sm font-medium hidden sm:inline">{{ $label }}</span>
            </button>
            @if($step < 4)
            <div class="flex-1 h-px mx-3 {{ $currentStep > $step ? 'bg-green-300' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
            @endif
            @endforeach
        </div>
    </div>

    {{-- Step 1: Basics --}}
    @if($currentStep === 1)
    @if($channel === 'email')
        <x-deliverability-guide />
    @endif
    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
        <h2 class="text-lg font-semibold text-ink">{{ __('Campaign Details') }}</h2>

        {{-- ════════════════════════════════════════════════════════════════
             CHANNEL PICKER — first decision in the wizard.
             Switching channels resets the conflicting fields (subject/body)
             and reshapes the rest of the editor (drag-drop builder for
             email vs plain text + Twilio number picker for SMS).
             ════════════════════════════════════════════════════════════════ --}}
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-2">{{ __('Send via') }}</label>
            <div class="grid grid-cols-2 gap-3">
                <label class="relative cursor-pointer">
                    <input type="radio" wire:model.live="channel" value="email" class="peer sr-only">
                    <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border hover:border-border">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900/40 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-ink">{{ __('Email Campaign') }}</p>
                                <p class="text-xs text-muted mt-0.5">{{ __('Drag-drop builder, A/B tests') }}</p>
                            </div>
                        </div>
                    </div>
                </label>
                @php $canSms = $planFeatures['sms_campaigns'] ?? false; @endphp
                <label class="relative {{ $canSms ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                    <input type="radio" wire:model.live="channel" value="sms" @disabled(!$canSms) class="peer sr-only">
                    <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border {{ $canSms ? 'hover:border-border' : 'opacity-60' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-success/10 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-ink">{{ __('SMS Campaign') }}</p>
                                    @if(!$canSms)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-warning/15 text-warning rounded-full">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            {{ __('Pro') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-muted mt-0.5">
                                    @if($canSms)
                                        {{ __('Twilio bulk text messages') }}
                                    @else
                                        {{ __('Upgrade to Pro to send bulk SMS') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </label>
            </div>
            @error('channel') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Campaign Name') }}</label>
            <input type="text" wire:model="name" placeholder="e.g., Spring Product Launch"
                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        @if($channel === 'email')
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Campaign Type') }}</label>
            <div class="grid grid-cols-2 gap-3">
                <label class="relative cursor-pointer">
                    <input type="radio" wire:model.live="type" value="regular" class="peer sr-only">
                    <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border hover:border-border">
                        <p class="text-sm font-semibold text-ink">{{ __('Regular Campaign') }}</p>
                        <p class="text-xs text-muted mt-0.5">{{ __('Send the same email to all recipients') }}</p>
                    </div>
                </label>
                <label class="relative cursor-pointer">
                    <input type="radio" wire:model.live="type" value="ab_test" class="peer sr-only">
                    <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border hover:border-border">
                        <p class="text-sm font-semibold text-ink">{{ __('A/B Test') }}</p>
                        <p class="text-xs text-muted mt-0.5">{{ __('Test different subject lines') }}</p>
                    </div>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Subject Line') }}</label>
            <input type="text" wire:model="subject" placeholder="{{ __('Enter email subject...') }}"
                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            @error('subject') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Preview Text') }} <span class="text-muted font-normal">({{ __('optional') }})</span></label>
            <input type="text" wire:model="previewText" placeholder="{{ __('Brief summary shown in inbox preview...') }}"
                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
        </div>

        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('From Account') }}</label>
            <select wire:model="emailAccountId"
                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                <option value="">{{ __('Select email account...') }}</option>
                @foreach($emailAccounts as $account)
                <option value="{{ $account->id }}">{{ $account->display_name }} ({{ $account->email }})</option>
                @endforeach
            </select>
        </div>
        @else
            {{-- SMS path: Step 1 only collects channel + name. The Twilio
                 number picker and message body live on Step 2 (content)
                 to mirror the email flow's information density. --}}
            <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl p-4">
                <p class="text-xs text-primary-700 dark:text-primary-300">
                    <strong>{{ __('SMS Campaign:') }}</strong>
                    {{ __('Next step lets you pick your Twilio number and write the message. Recipients are filtered to contacts who have a phone number on file.') }}
                </p>
            </div>
        @endif

        {{-- A/B Test Fields --}}
        @if($type === 'ab_test')
        <div class="border-t border-border pt-5 space-y-4">
            <h3 class="text-base font-semibold text-ink">{{ __('A/B Test Configuration') }}</h3>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Subject Line A') }}</label>
                <input type="text" wire:model="subjectA" placeholder="{{ __('First subject variation...') }}"
                       class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                @error('subjectA') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Subject Line B') }}</label>
                <input type="text" wire:model="subjectB" placeholder="{{ __('Second subject variation...') }}"
                       class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                @error('subjectB') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Split Percentage (Variant A)') }}</label>
                <div class="flex items-center gap-4">
                    <input type="range" wire:model.live="splitPercentage" min="10" max="90" step="5"
                           class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-primary-600">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <span class="text-primary-700">A: {{ $splitPercentage }}%</span>
                        <span class="text-muted">/</span>
                        <span class="text-secondary-700">B: {{ 100 - $splitPercentage }}%</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="flex justify-end pt-2">
            <button wire:click="goToStep(2)" class="btn-primary px-6 py-2.5 text-sm">
                {{ __('Next: Content') }}
            </button>
        </div>
    </div>
    @endif

    {{-- Step 2: Content --}}
    @if($currentStep === 2)

    @if($channel === 'sms')
    {{-- ════════════════════════════════════════════════════════════════
         SMS CONTENT STEP
         Twilio number selector + plain-text body with live char counter
         and segment estimation. No drag-drop builder, no HTML, no preview
         pane — SMS is fundamentally a 160-char primitive.
         ════════════════════════════════════════════════════════════════ --}}
    <div wire:key="step2-sms" class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5"
         x-data="{
             body: @js($bodyText),
             get len() { return this.body.length; },
             get isUnicode() { return /[^\x00-\x7F]/.test(this.body); },
             get segmentSize() { return this.isUnicode ? 70 : 160; },
             get segments() { return this.body.length === 0 ? 0 : Math.ceil(this.body.length / this.segmentSize); }
         }">
        <h2 class="text-lg font-semibold text-ink">{{ __('SMS Content') }}</h2>

        {{-- Twilio "From" number selector --}}
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Send from number') }}</label>
            @if($smsNumbers->isEmpty())
                <div class="border border-warning/40 bg-warning/10 rounded-xl p-4">
                    <p class="text-sm text-warning font-medium">{{ __('No active SMS integration found.') }}</p>
                    <p class="text-xs text-muted mt-1">
                        {{ __('Connect a Twilio number first:') }}
                        <a href="{{ url('/settings/channels') }}" class="text-brand hover:underline">{{ __('Settings → Channels → SMS') }}</a>
                    </p>
                </div>
            @else
                <select wire:model="fromNumber"
                        class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 bg-surface-2">
                    <option value="">{{ __('— Choose a Twilio number —') }}</option>
                    @foreach($smsNumbers as $n)
                        <option value="{{ $n->phone_number }}">
                            {{ $n->phone_number }}@if($n->account_name) — {{ $n->account_name }}@endif
                        </option>
                    @endforeach
                </select>
                @error('fromNumber') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            @endif
        </div>

        {{-- SMS body --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="block text-sm font-medium text-ink/80">{{ __('Message') }}</label>
                <div class="text-xs text-muted">
                    <span x-text="len"></span> {{ __('chars') }} ·
                    <span x-text="segments"></span> {{ __('segment(s)') }}
                    <span x-show="isUnicode" class="text-warning ml-1">({{ __('Unicode — 70/segment') }})</span>
                </div>
            </div>
            <textarea wire:model.live.debounce.300ms="bodyText"
                      x-model="body"
                      rows="6"
                      maxlength="1600"
                      placeholder="Hi {first_name}, this is a quick update from {company}..."
                      class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 bg-surface font-mono resize-y"></textarea>
            <div class="flex items-start justify-between gap-3 mt-1">
                <p class="text-[10px] text-muted">
                    {{ __('Variables:') }} <code>{first_name}</code> <code>{last_name}</code>
                    <code>{full_name}</code> <code>{company}</code> <code>{phone}</code> <code>{email}</code>
                </p>
                <p class="text-[10px] text-muted">
                    {{ __('Carriers split long SMS into multiple billed segments.') }}
                </p>
            </div>
            @error('bodyText') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-border/50">
            <button wire:click="goToStep(1)" class="px-4 py-2 text-sm font-medium text-ink/80 bg-surface border border-border rounded-xl hover:bg-surface-2 transition-colors">
                {{ __('Back') }}
            </button>
            <button wire:click="goToStep(3)" class="btn-primary px-6 py-2.5 text-sm">
                {{ __('Next: Audience') }}
            </button>
        </div>
    </div>
    @else

    {{-- Full-screen Email Builder Overlay.
         wire:key on both branches tells Livewire morphdom these are distinct
         DOM nodes. Without it, morphdom tried to mutate one into the other
         in-place, which occasionally collapsed the page layout (header +
         step wizard disappeared after the builder closed). --}}
    @if($showEmailBuilder)
    <div wire:key="builder-overlay" class="fixed inset-0 z-50 bg-surface-2 flex flex-col" wire:ignore.self>
        <livewire:campaigns.email-builder />
    </div>
    @else

    <div wire:key="step2-content" class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-ink">{{ __('Email Content') }}</h2>
            {{-- Editor Mode Toggle --}}
            <div class="flex items-center bg-surface  rounded-xl p-1">
                <button
                    wire:click="$set('editorMode', 'visual')"
                    class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $editorMode === 'visual' ? 'bg-surface-2 text-ink shadow-sm' : 'text-muted hover:text-ink/80' }}"
                >
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                        {{ __('Visual Builder') }}
                    </span>
                </button>
                <button
                    wire:click="$set('editorMode', 'html')"
                    class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $editorMode === 'html' ? 'bg-surface-2 text-ink shadow-sm' : 'text-muted hover:text-ink/80' }}"
                >
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        {{ __('HTML Editor') }}
                    </span>
                </button>
            </div>
        </div>

        @if($editorMode === 'visual')
            {{-- Visual Builder Mode --}}
            <div class="space-y-4">
                @if($bodyHtml && !empty($bodyBlocks))
                    {{-- Content already built - show summary --}}
                    <div class="border border-success/20 bg-success/10 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-success/15 rounded-lg flex items-center justify-center">
                                    <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-green-900">{{ __('Email content ready') }}</p>
                                    <p class="text-xs text-success">{{ count($bodyBlocks) }} block(s) built with the visual editor</p>
                                </div>
                            </div>
                            <button
                                wire:click="$set('showEmailBuilder', true)"
                                class="px-4 py-2 text-sm font-medium text-success bg-surface-2 border border-green-300 rounded-xl hover:bg-success/10 transition-colors"
                            >
                                {{ __('Edit in Builder') }}
                            </button>
                        </div>
                    </div>

                    {{-- Mini Preview.
                         We pass the RAW $bodyHtml (not the sanitized version)
                         so inline styles and <style> blocks survive and the
                         email renders with its intended colors/layout.
                         HtmlSanitizer::sanitize() strips `style=""` and the
                         entire `<style>` block as a CSS-injection safeguard
                         — safe for inline rendering but useless for a visual
                         preview. Inside this iframe, `sandbox="allow-same-
                         origin"` (no allow-scripts) blocks all JS execution,
                         so <script>/onerror-style XSS can't fire regardless
                         of what's in the body. --}}
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Preview') }}</label>
                        <div class="border border-border rounded-xl bg-surface" style="height: 600px;">
                            <iframe
                                srcdoc="{{ $bodyHtml }}"
                                class="w-full h-full border-0 rounded-xl"
                                sandbox="allow-same-origin"
                                title="Email preview"
                            ></iframe>
                        </div>
                    </div>
                @else
                    {{-- No content yet - prompt to open builder --}}
                    <div class="border-2 border-dashed border-border rounded-xl p-8 text-center">
                        <div class="w-16 h-16 bg-primary-50 dark:bg-primary-900/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <h3 class="text-base font-semibold text-ink mb-1">{{ __('Design your email visually') }}</h3>
                        <p class="text-sm text-muted mb-4 max-w-sm mx-auto">{{ __('Drag and drop content blocks to build beautiful, responsive emails without writing any code.') }}</p>
                        <button
                            wire:click="$set('showEmailBuilder', true)"
                            class="btn-primary inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ __('Open Email Builder') }}
                        </button>
                    </div>
                @endif
            </div>
        @else
            {{-- HTML Editor Mode --}}
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Email Body (HTML)') }}</label>
                <textarea wire:model="bodyHtml" rows="16" placeholder="{{ __('Enter your email HTML content here...') }}"
                          class="w-full px-4 py-3 text-sm font-mono border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-y">
                </textarea>
                @error('bodyHtml') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-muted mt-1">You can use HTML tags. Use @{{first_name}}, @{{last_name}}, @{{email}}, @{{company}} as merge tags.</p>
            </div>

            {{-- Preview --}}
            @if($bodyHtml)
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">Preview</label>
                <div class="border border-border rounded-xl p-4 bg-surface max-h-64 overflow-y-auto">
                    <div class="bg-surface-2 rounded-lg p-4 shadow-sm">
                        {!! $this->safeBodyHtml !!}
                    </div>
                </div>
            </div>
            @endif
        @endif

        @error('bodyHtml') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror

        <div class="flex justify-between pt-2">
            <button wire:click="goToStep(1)" class="px-6 py-2.5 text-ink/80 bg-surface-2 border border-border text-sm font-medium rounded-xl hover:bg-surface transition-colors">
                {{ __('Back') }}
            </button>
            <button wire:click="goToStep(3)" class="btn-primary px-6 py-2.5 text-sm">
                {{ __('Next: Audience') }}
            </button>
        </div>
    </div>

    @endif
    @endif {{-- end channel === 'email' branch (close the @if($channel === 'sms') @else --}}
    @endif

    {{-- Step 3: Audience --}}
    @if($currentStep === 3)
    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
        <h2 class="text-lg font-semibold text-ink">{{ __('Select Audience') }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <label class="relative cursor-pointer">
                <input type="radio" wire:model.live="audienceType" value="all" class="peer sr-only">
                <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border hover:border-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ __('All Contacts') }}</p>
                            <p class="text-xs text-muted">{{ number_format($totalContacts) }} subscribers</p>
                        </div>
                    </div>
                </div>
            </label>
            <label class="relative cursor-pointer">
                <input type="radio" wire:model.live="audienceType" value="list" class="peer sr-only">
                <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border hover:border-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ __('Contact Group') }}</p>
                            <p class="text-xs text-muted">{{ __('Send to a specific group') }}</p>
                        </div>
                    </div>
                </div>
            </label>
            <label class="relative cursor-pointer">
                <input type="radio" wire:model.live="audienceType" value="segment" class="peer sr-only">
                <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border hover:border-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-secondary-100 dark:bg-secondary-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ __('Segment') }}</p>
                            <p class="text-xs text-muted">{{ __('Dynamic rule-based filter') }}</p>
                        </div>
                    </div>
                </div>
            </label>
            <label class="relative cursor-pointer">
                <input type="radio" wire:model.live="audienceType" value="contacts" class="peer sr-only">
                <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border hover:border-border">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ __('Specific Contacts') }}</p>
                            <p class="text-xs text-muted">{{ __('Pick individual recipients') }}</p>
                        </div>
                    </div>
                </div>
            </label>
        </div>

        @if($audienceType === 'list')
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Select Contact Group') }}</label>
            <select wire:model.live="audienceId"
                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                <option value="">{{ __('Choose a group...') }}</option>
                @foreach($contactLists as $list)
                <option value="{{ $list->id }}">{{ $list->name }} ({{ number_format($list->contacts_count) }} contacts)</option>
                @endforeach
            </select>
            @if($contactLists->isEmpty())
            <p class="text-xs text-muted mt-1">No groups yet. <a href="{{ url('/contacts/groups') }}" class="text-brand hover:underline">Create one</a></p>
            @endif
        </div>
        @endif

        @if($audienceType === 'segment')
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Select Segment') }}</label>
            <select wire:model.live="audienceId"
                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                <option value="">{{ __('Choose a segment...') }}</option>
                @foreach($segments as $segment)
                <option value="{{ $segment->id }}">{{ $segment->name }} ({{ number_format($segment->contacts_count) }} contacts)</option>
                @endforeach
            </select>
        </div>
        @endif

        @if($audienceType === 'contacts')
        <div class="space-y-3">
            <label class="block text-sm font-medium text-ink/80">{{ __('Search and add contacts') }}</label>

            {{-- Search box with live autocomplete --}}
            <div class="relative">
                <input type="text"
                       wire:model.live.debounce.300ms="contactSearch"
                       placeholder="{{ __('Type a name, email, or company…') }}"
                       class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">

                @if(!empty($contactSearchResults))
                <div class="absolute z-20 mt-1 w-full bg-surface-2 border border-border rounded-xl shadow-lg max-h-64 overflow-y-auto">
                    @foreach($contactSearchResults as $c)
                    <button type="button"
                            wire:click="addSpecificContact({{ $c['id'] }})"
                            class="w-full text-left px-4 py-2.5 hover:bg-primary-50 dark:hover:bg-primary-900/20 border-b border-border last:border-b-0">
                        <p class="text-sm font-medium text-ink">{{ $c['name'] }}</p>
                        <p class="text-xs text-muted">{{ $c['email'] }}@if($c['company']) · {{ $c['company'] }}@endif</p>
                    </button>
                    @endforeach
                </div>
                @elseif(strlen(trim($contactSearch)) >= 2)
                <p class="text-xs text-muted mt-1">{{ __('No matching contacts.') }}</p>
                @endif
            </div>

            {{-- Selected contacts chip list --}}
            @php($selected = $this->selectedContactsList)
            @if(!empty($selected))
            <div>
                <p class="text-xs text-muted mb-2">{{ count($selected) }} {{ __('selected') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($selected as $c)
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full text-xs">
                        <span>{{ $c['name'] }} &lt;{{ $c['email'] }}&gt;</span>
                        <button type="button" wire:click="removeSpecificContact({{ $c['id'] }})" class="hover:text-red-600" title="{{ __('Remove') }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                    @endforeach
                </div>
            </div>
            @else
            <p class="text-xs text-muted">{{ __('No contacts picked yet. Use the search above to add one.') }}</p>
            @endif

            @error('audienceType') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
        @endif

        {{-- Audience count --}}
        <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-primary-900 dark:text-primary-100">{{ number_format($audienceCount) }} {{ __('recipients') }}</p>
                    <p class="text-xs text-primary-600 dark:text-primary-400">{{ __('will receive this campaign') }}</p>
                </div>
            </div>
        </div>

        <div class="flex justify-between pt-2">
            <button wire:click="goToStep(2)" class="px-6 py-2.5 text-ink/80 bg-surface-2 border border-border text-sm font-medium rounded-xl hover:bg-surface transition-colors">
                {{ __('Back') }}
            </button>
            <button wire:click="goToStep(4)" class="btn-primary px-6 py-2.5 text-sm">
                {{ __('Next: Schedule') }}
            </button>
        </div>
    </div>
    @endif

    {{-- Step 4: Schedule & Review --}}
    @if($currentStep === 4)
    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
        <h2 class="text-lg font-semibold text-ink">{{ __('Schedule & Send') }}</h2>

        <div class="space-y-3">
            <label class="relative cursor-pointer block">
                <input type="radio" wire:model.live="sendOption" value="now" class="peer sr-only">
                <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border hover:border-border">
                    <p class="text-sm font-semibold text-ink">{{ __('Send Now') }}</p>
                    <p class="text-xs text-muted">{{ __('Send the campaign immediately to all recipients') }}</p>
                </div>
            </label>
            <label class="relative cursor-pointer block">
                <input type="radio" wire:model.live="sendOption" value="schedule" class="peer sr-only">
                <div class="p-4 border-2 rounded-xl transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 border-border hover:border-border">
                    <p class="text-sm font-semibold text-ink">{{ __('Schedule for Later') }}</p>
                    <p class="text-xs text-muted">{{ __('Pick a specific date and time to send') }}</p>
                </div>
            </label>
        </div>

        @if($sendOption === 'schedule')
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Scheduled Date & Time') }}</label>
            <input type="datetime-local" wire:model="scheduledAt"
                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            @error('scheduledAt') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            <p class="text-xs text-muted mt-1.5">{{ __('Scheduled campaigns may be delayed by 1-2 minutes.') }}</p>
        </div>
        @endif

        {{-- Delivery throttle (anti-spam) --}}
        <div x-data="{ open: false }" class="border border-border rounded-xl overflow-hidden">
            <button @click="open = !open" type="button"
                    class="w-full flex items-center justify-between px-4 py-3 bg-surface hover:bg-surface transition-colors text-left">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span class="text-sm font-semibold text-ink">{{ __('Delivery Speed & Batching') }}</span>
                    <span class="text-xs text-muted">{{ __('(optional — reduce spam risk)') }}</span>
                </div>
                <svg class="w-4 h-4 text-muted transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="open" x-collapse x-cloak class="px-4 py-4 space-y-4 border-t border-border">
                <p class="text-xs text-muted">
                    {{ __('Sending too fast can trigger spam filters and get your account temporarily banned. Slower rates are safer.') }}
                </p>

                {{-- Emails per minute --}}
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">
                        {{ __('Send rate (emails per minute)') }}
                    </label>
                    <div class="flex items-center gap-4">
                        <input type="range" wire:model.live="emailsPerMinute" min="1" max="600" step="1"
                               class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-primary-600">
                        <div class="min-w-[120px] text-right">
                            <input type="number" wire:model.lazy="emailsPerMinute" min="1" max="600"
                                   class="w-20 px-2 py-1 text-sm border border-border rounded-lg text-right focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <span class="text-xs text-muted ml-1">/min</span>
                        </div>
                    </div>
                    <p class="text-[11px] text-muted mt-1">
                        @if($emailsPerMinute <= 30)
                            {{ __('Very slow — safest for new accounts or cold outreach.') }}
                        @elseif($emailsPerMinute <= 120)
                            {{ __('Normal — works for most established sender accounts.') }}
                        @elseif($emailsPerMinute <= 300)
                            {{ __('Fast — only use if your sender reputation is warm.') }}
                        @else
                            {{ __('Very fast — may hit provider rate limits or spam flags.') }}
                        @endif
                    </p>
                    @error('emailsPerMinute') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Batching --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-border">
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">
                            {{ __('Batch size') }}
                        </label>
                        <input type="number" wire:model.lazy="batchSize" min="0" max="5000" step="10"
                               placeholder="0 = no batching"
                               class="w-full px-3 py-2 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <p class="text-[11px] text-muted mt-1">{{ __('Emails per batch. 0 = send continuously with no batch pauses.') }}</p>
                        @error('batchSize') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">
                            {{ __('Pause between batches') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="number" wire:model.lazy="batchDelaySeconds" min="0" max="3600" step="5"
                                   placeholder="0"
                                   {{ $batchSize == 0 ? 'disabled' : '' }}
                                   class="w-full px-3 py-2 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 {{ $batchSize == 0 ? 'opacity-50 cursor-not-allowed' : '' }}">
                            <span class="text-xs text-muted shrink-0">{{ __('seconds') }}</span>
                        </div>
                        <p class="text-[11px] text-muted mt-1">
                            @if($batchSize == 0)
                                {{ __('Only used when batch size > 0.') }}
                            @else
                                {{ __('e.g. 30 seconds pause every :n emails.', ['n' => $batchSize]) }}
                            @endif
                        </p>
                        @error('batchDelaySeconds') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Estimate — value is computed in CampaignEditor::
                     getEstimatedSendDurationProperty() so the view is pure
                     output. No more in-template @php blocks (which were
                     intermittently losing variables during re-renders). --}}
                @if($audienceCount > 0 && $this->estimatedSendDuration)
                <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl p-3">
                    <p class="text-xs text-primary-900 dark:text-primary-100">
                        {{ __('Estimated total send time:') }}
                        <span class="font-semibold">{{ $this->estimatedSendDuration }}</span>
                        <span class="text-primary-600 dark:text-primary-400">
                            ({{ number_format($audienceCount) }} {{ __('recipients') }})
                        </span>
                    </p>
                </div>
                @endif
            </div>
        </div>

        {{-- Test Before Sending --}}
        <div x-data="{ testOpen: false }" class="border border-border rounded-xl overflow-hidden">
            <button @click="testOpen = !testOpen" type="button"
                    class="w-full flex items-center justify-between px-4 py-3 bg-surface hover:bg-surface  transition-colors text-left">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span class="text-sm font-semibold text-ink">{{ __('Test Before Sending') }}</span>
                </div>
                <svg class="w-4 h-4 text-muted transition-transform" :class="{ 'rotate-180': testOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="testOpen" x-collapse x-cloak class="px-4 py-4 space-y-3 border-t border-border">
                <p class="text-xs text-muted">Send a preview to yourself. Template variables will use sample data. Subject will be prefixed with [TEST].</p>

                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <input type="email" wire:model="testEmail" placeholder="your@email.com"
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                               {{ $testSending ? 'disabled' : '' }}>
                    </div>
                    <button wire:click="sendTestEmail"
                            wire:loading.attr="disabled"
                            wire:target="sendTestEmail"
                            {{ $testSending ? 'disabled' : '' }}
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-medium rounded-xl transition-colors
                                   {{ $testSending ? 'bg-gray-200 dark:bg-gray-700 text-muted cursor-not-allowed' : 'bg-warning/100 text-white hover:bg-amber-600' }}">
                        <span wire:loading wire:target="sendTestEmail">
                            <svg class="animate-spin h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </span>
                        <span wire:loading.remove wire:target="sendTestEmail">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        {{ $testSending ? __('Sending...') : __('Send Test Email') }}
                    </button>
                </div>

                @error('testEmail')
                    <p class="text-xs text-red-500">{{ $message }}</p>
                @enderror

                @if($testResult)
                    @if(str_starts_with($testResult, 'success:'))
                        <div class="flex items-start gap-2 p-3 bg-success/10 border border-success/20 rounded-lg">
                            <svg class="w-4 h-4 text-success mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <p class="text-xs text-success">{{ str_replace('success:', '', $testResult) }}</p>
                        </div>
                    @elseif(str_starts_with($testResult, 'error:'))
                        <div class="flex items-start gap-2 p-3 bg-danger/10 border border-danger/20 rounded-lg">
                            <svg class="w-4 h-4 text-danger mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <p class="text-xs text-danger">{{ str_replace('error:', '', $testResult) }}</p>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Review Summary --}}
        <div class="border-t border-border pt-5">
            <h3 class="text-base font-semibold text-ink mb-3">{{ __('Campaign Summary') }}</h3>
            <div class="bg-surface rounded-xl p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-muted">{{ __('Campaign Name') }}</span>
                    <span class="font-medium text-ink">{{ $name ?: '--' }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-muted">{{ __('Type') }}</span>
                    <span class="font-medium text-ink">{{ $type === 'ab_test' ? __('A/B Test') : __('Regular') }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-muted">{{ __('Subject') }}</span>
                    <span class="font-medium text-ink">{{ $subject ?: '--' }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-muted">{{ __('Content') }}</span>
                    <span class="font-medium text-ink">
                        @if($editorMode === 'visual' && !empty($bodyBlocks))
                            {{ __('Visual Builder') }} ({{ count($bodyBlocks) }} blocks)
                        @elseif(!empty($bodyHtml))
                            {{ __('Custom HTML') }}
                        @else
                            --
                        @endif
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-muted">{{ __('Recipients') }}</span>
                    <span class="font-medium text-ink">{{ number_format($audienceCount) }}</span>
                </div>
                @if($type === 'ab_test')
                <div class="flex justify-between text-sm">
                    <span class="text-muted">{{ __('Subject A') }}</span>
                    <span class="font-medium text-ink">{{ $subjectA ?: '--' }} ({{ $splitPercentage }}%)</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-muted">{{ __('Subject B') }}</span>
                    <span class="font-medium text-ink">{{ $subjectB ?: '--' }} ({{ 100 - $splitPercentage }}%)</span>
                </div>
                @endif
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-between gap-3 pt-2">
            <button wire:click="goToStep(3)" class="px-6 py-2.5 text-ink/80 bg-surface-2 border border-border text-sm font-medium rounded-xl hover:bg-surface transition-colors">
                {{ __('Back') }}
            </button>
            <div class="flex items-center gap-3">
                <button wire:click="saveDraft" class="px-6 py-2.5 text-ink/80 bg-surface-2 border border-border text-sm font-medium rounded-xl hover:bg-surface transition-colors">
                    {{ __('Save Draft') }}
                </button>
                @if($sendOption === 'schedule')
                <button wire:click="schedule" wire:confirm="Schedule this campaign? It will be sent at the selected time."
                        class="px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition-colors">
                    {{ __('Schedule Campaign') }}
                </button>
                @else
                <button wire:click="sendNow" wire:confirm="Send this campaign now to {{ number_format($audienceCount) }} recipients?"
                        class="btn-primary px-6 py-2.5 text-sm">
                    {{ __('Send Now') }}
                </button>
                @endif
            </div>
        </div>

        {{-- Drip Sequence Section (shown on Step 4 if campaign is saved) --}}
        @if($campaignId)
        <div class="mt-6 border-t border-border pt-6">
            <livewire:campaigns.drip-sequence-editor :campaignId="$campaignId" :key="'drip-'.$campaignId" />
        </div>
        @endif
    </div>
    @endif
</div>
