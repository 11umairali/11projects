@extends('layouts.onboarding', ['currentStep' => 4])
@section('title', __('Step 4'))
@section('content')
    <div class="bg-surface-2 rounded-2xl border border-border/60 p-4 sm:p-6 shadow-sm max-w-2xl mx-auto">
        {{-- Header --}}
        <div class="text-center mb-4">
            <div class="w-10 h-10 bg-brand/10 rounded-xl flex items-center justify-center mx-auto mb-2">
                <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-ink">{{ __('Configure auto-reply rules') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('Set when and how :app should respond to incoming emails', ['app' => config('app.name')]) }}</p>
        </div>

        <form method="POST" action="{{ route('onboarding.store-step-4') }}"
              x-data="{
                  autoReplyEnabled: true,
                  confidenceThreshold: {{ old('confidence_threshold', 75) }},
                  sendMode: '{{ old('send_mode', 'approval') }}',
                  schedule: {
                      monday:    { enabled: true, start: '09:00', end: '17:00' },
                      tuesday:   { enabled: true, start: '09:00', end: '17:00' },
                      wednesday: { enabled: true, start: '09:00', end: '17:00' },
                      thursday:  { enabled: true, start: '09:00', end: '17:00' },
                      friday:    { enabled: true, start: '09:00', end: '17:00' },
                      saturday:  { enabled: false, start: '10:00', end: '14:00' },
                      sunday:    { enabled: false, start: '10:00', end: '14:00' },
                  }
              }"
              class="space-y-6">
            @csrf

            {{-- AI Auto-Reply Toggle --}}
            <div class="flex items-center justify-between p-4 bg-surface border border-border rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-brand/10 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-ink">{{ __('Enable AI Auto-Reply') }}</h3>
                        <p class="text-xs text-muted">{{ __('Let :app automatically draft or send responses', ['app' => config('app.name')]) }}</p>
                    </div>
                </div>
                <button type="button" @click="autoReplyEnabled = !autoReplyEnabled"
                        :class="autoReplyEnabled ? 'bg-brand' : 'bg-gray-300'"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                        role="switch" :aria-checked="autoReplyEnabled" aria-label="{{ __('Toggle AI auto-reply') }}">
                    <span :class="autoReplyEnabled ? 'translate-x-6' : 'translate-x-1'"
                          class="inline-block h-4 w-4 rounded-full bg-surface-2 transition-transform duration-200 shadow-sm"></span>
                </button>
                <input type="hidden" name="auto_reply_enabled" :value="autoReplyEnabled ? '1' : '0'">
            </div>

            {{-- Content shown when auto-reply is enabled --}}
            <div x-show="autoReplyEnabled" x-transition class="space-y-6">

                {{-- Business Hours Schedule --}}
                <div class="bg-surface border border-border rounded-xl p-4">
                    <h3 class="text-sm font-semibold text-ink mb-1">{{ __('Business Hours') }}</h3>
                    <p class="text-xs text-muted mb-2">{{ __('AI will only auto-reply during these hours') }}</p>

                    <div class="flex items-center gap-1.5 px-3 py-2 mb-4 bg-brand/10 border border-indigo-100 rounded-lg">
                        <svg class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-xs font-medium text-brand">Timezone: {{ auth()->user()->activeWorkspace?->timezone ?? auth()->user()->timezone ?? 'UTC' }}</span>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(day, dayName) in schedule" :key="dayName">
                            <div class="flex items-center gap-3">
                                {{-- Day checkbox --}}
                                <label class="flex items-center gap-2 w-28 cursor-pointer">
                                    <input type="checkbox" x-model="day.enabled"
                                           :name="'schedule[' + dayName + '][enabled]'"
                                           class="w-4 h-4 text-brand border-border rounded focus:ring-primary-500">
                                    <span class="text-sm font-medium text-ink/80 capitalize" x-text="dayName"></span>
                                </label>

                                {{-- Time range --}}
                                <div class="flex items-center gap-2 flex-1" :class="!day.enabled && 'opacity-40 pointer-events-none'">
                                    <select :name="'schedule[' + dayName + '][start]'" x-model="day.start"
                                            class="px-3 py-2 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                                        @for($h = 0; $h < 24; $h++)
                                            @foreach(['00', '30'] as $m)
                                                <option value="{{ sprintf('%02d:%s', $h, $m) }}">{{ sprintf('%02d:%s', $h, $m) }}</option>
                                            @endforeach
                                        @endfor
                                    </select>
                                    <span class="text-xs text-muted">{{ __('to') }}</span>
                                    <select :name="'schedule[' + dayName + '][end]'" x-model="day.end"
                                            class="px-3 py-2 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                                        @for($h = 0; $h < 24; $h++)
                                            @foreach(['00', '30'] as $m)
                                                <option value="{{ sprintf('%02d:%s', $h, $m) }}">{{ sprintf('%02d:%s', $h, $m) }}</option>
                                            @endforeach
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </template>
                    </div>

                    <p class="text-xs text-muted mt-3">{{ __('All times shown above use your workspace timezone.') }}</p>
                </div>

                {{-- Confidence Threshold Slider --}}
                <div class="bg-surface border border-border rounded-xl p-4">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-sm font-semibold text-ink">{{ __('Confidence Threshold') }}</h3>
                        <span class="text-sm font-bold text-brand" x-text="confidenceThreshold + '%'"></span>
                    </div>
                    <p class="text-xs text-muted mb-4">{{ __("AI will only auto-reply when it's this confident in its answer") }}</p>

                    <input type="range" name="confidence_threshold" min="0" max="100" step="5"
                           x-model="confidenceThreshold"
                           class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-brand">

                    <div class="flex justify-between text-xs text-muted mt-2">
                        <span>{{ __('0% (Reply to everything)') }}</span>
                        <span>{{ __('100% (Only sure answers)') }}</span>
                    </div>

                    {{-- Threshold guidance --}}
                    <div class="mt-3 p-3 rounded-lg text-xs"
                         :class="{
                             'bg-danger/10 text-danger': confidenceThreshold < 40,
                             'bg-warning/10 text-warning': confidenceThreshold >= 40 && confidenceThreshold < 65,
                             'bg-success/10 text-success': confidenceThreshold >= 65 && confidenceThreshold <= 85,
                             'bg-info/10 text-info': confidenceThreshold > 85
                         }">
                        <span x-show="confidenceThreshold < 40">Low threshold: AI will reply frequently, but may send inaccurate responses.</span>
                        <span x-show="confidenceThreshold >= 40 && confidenceThreshold < 65">Moderate threshold: Good balance for general use cases.</span>
                        <span x-show="confidenceThreshold >= 65 && confidenceThreshold <= 85">Recommended: AI replies only when reasonably confident.</span>
                        <span x-show="confidenceThreshold > 85">High threshold: Very accurate, but fewer automated replies.</span>
                    </div>
                </div>

                {{-- Send Mode --}}
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-3">{{ __('Send Mode') }}</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        {{-- Fully Autonomous --}}
                        <label class="cursor-pointer">
                            <input type="radio" name="send_mode" value="autonomous" class="peer sr-only" x-model="sendMode">
                            <div class="relative flex flex-col p-4 border-2 rounded-xl transition-all duration-200 peer-checked:border-brand peer-checked:bg-brand/5 peer-checked:ring-1 peer-checked:ring-brand border-border hover:border-border">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5" :class="sendMode === 'autonomous' ? 'text-brand' : 'text-muted'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                    <span class="text-sm font-semibold text-ink">{{ __('Fully Autonomous') }}</span>
                                </div>
                                <p class="text-xs text-muted">{{ __('AI sends replies automatically without approval') }}</p>
                            </div>
                        </label>

                        {{-- Approval Required --}}
                        <label class="cursor-pointer">
                            <input type="radio" name="send_mode" value="approval" class="peer sr-only" x-model="sendMode">
                            <div class="relative flex flex-col p-4 border-2 rounded-xl transition-all duration-200 peer-checked:border-brand peer-checked:bg-brand/5 peer-checked:ring-1 peer-checked:ring-brand border-border hover:border-border">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5" :class="sendMode === 'approval' ? 'text-brand' : 'text-muted'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-sm font-semibold text-ink">{{ __('Approval Required') }}</span>
                                </div>
                                <p class="text-xs text-muted">{{ __('AI drafts replies for you to review and send') }}</p>
                                <span class="mt-2 inline-flex items-center px-2 py-0.5 text-xs font-medium bg-success/15 text-success rounded-full w-fit">{{ __('Recommended') }}</span>
                            </div>
                        </label>

                        {{-- Suggestions Only --}}
                        <label class="cursor-pointer">
                            <input type="radio" name="send_mode" value="suggestions" class="peer sr-only" x-model="sendMode">
                            <div class="relative flex flex-col p-4 border-2 rounded-xl transition-all duration-200 peer-checked:border-brand peer-checked:bg-brand/5 peer-checked:ring-1 peer-checked:ring-brand border-border hover:border-border">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5" :class="sendMode === 'suggestions' ? 'text-brand' : 'text-muted'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                    <span class="text-sm font-semibold text-ink">{{ __('Suggestions Only') }}</span>
                                </div>
                                <p class="text-xs text-muted">{{ __('AI shows suggestions you can copy and edit freely') }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Reply Delay --}}
                <div>
                    <label for="reply_delay" class="block text-sm font-medium text-ink/80 mb-1">{{ __('Reply Delay') }}</label>
                    <p class="text-xs text-muted mb-2">{{ __('Wait before sending to seem more natural') }}</p>
                    <select id="reply_delay" name="reply_delay"
                            class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                        <option value="0" {{ old('reply_delay', '0') === '0' ? 'selected' : '' }}>{{ __('No delay (instant)') }}</option>
                        <option value="30" {{ old('reply_delay') === '30' ? 'selected' : '' }}>{{ __('30 seconds') }}</option>
                        <option value="60" {{ old('reply_delay') === '60' ? 'selected' : '' }}>{{ __('1 minute') }}</option>
                        <option value="120" {{ old('reply_delay') === '120' ? 'selected' : '' }}>{{ __('2 minutes') }}</option>
                        <option value="300" {{ old('reply_delay') === '300' ? 'selected' : '' }}>{{ __('5 minutes') }}</option>
                        <option value="600" {{ old('reply_delay') === '600' ? 'selected' : '' }}>{{ __('10 minutes') }}</option>
                        <option value="random" {{ old('reply_delay') === 'random' ? 'selected' : '' }}>{{ __('Random (1-5 minutes)') }}</option>
                    </select>
                </div>
            </div>

            {{-- Disabled state message --}}
            <div x-show="!autoReplyEnabled" x-transition
                 class="text-center py-8 text-muted" style="display: none;">
                <svg class="w-12 h-12 mx-auto text-muted/50 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                <p class="text-sm font-medium">{{ __('Auto-reply is disabled') }}</p>
                <p class="text-xs mt-1">{{ __('You can enable it later in Settings') }} &rarr; {{ __('AI Configuration') }}</p>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-4">
                <a href="{{ route('onboarding.step-3') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-muted hover:text-ink transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ __('Back') }}
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 py-3 px-6 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                    {{ __('Continue') }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </form>
    </div>
@endsection
