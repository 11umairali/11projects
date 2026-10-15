@extends('layouts.onboarding', ['currentStep' => 6])
@section('title', __('Complete'))
@section('content')
    <div class="bg-surface-2 rounded-2xl border border-border/60 p-4 sm:p-6 shadow-sm">
        {{-- Celebration --}}
        <div class="text-center py-8" x-data="{ show: false }" x-init="setTimeout(() => show = true, 100)">
            {{-- Animated checkmark --}}
            <div class="relative mx-auto w-24 h-24 mb-8"
                 x-show="show"
                 x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0 scale-50"
                 x-transition:enter-end="opacity-100 scale-100">
                @if ($completedCount === 5)
                    <div class="absolute inset-0 bg-success/15 rounded-full animate-ping opacity-20"></div>
                    <div class="relative w-24 h-24 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-lg shadow-green-200">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                @else
                    <div class="absolute inset-0 bg-primary-100 rounded-full animate-ping opacity-20"></div>
                    <div class="relative w-24 h-24 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center shadow-lg shadow-primary-200">
                        <span class="text-2xl font-bold text-white">{{ $completedCount }}/5</span>
                    </div>
                @endif
            </div>

            {{-- Heading --}}
            <h1 class="text-3xl font-bold text-ink mb-3"
                x-show="show"
                x-transition:enter="transition ease-out duration-500 delay-200"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0">
                @if ($completedCount === 5)
                    {{ __("You're all set!") }}
                @else
                    {{ __('Almost there!') }}
                @endif
            </h1>
            <p class="text-muted max-w-md mx-auto"
               x-show="show"
               x-transition:enter="transition ease-out duration-500 delay-300"
               x-transition:enter-start="opacity-0 translate-y-4"
               x-transition:enter-end="opacity-100 translate-y-0">
                @if ($completedCount === 5)
                    {{ __('Your workspace is fully configured and ready to go.') }} {{ config('app.name') }} {{ __('will start learning from your communications right away.') }}
                @else
                    {{ __('You completed :count of 5 setup steps. You can finish the remaining steps anytime from your settings, or jump straight into your inbox.', ['count' => $completedCount]) }}
                @endif
            </p>
        </div>

        {{-- Action Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8">
            {{-- Go to Inbox --}}
            <a href="{{ route('dashboard') }}"
               class="group flex flex-col items-center justify-center p-6 bg-primary-600 text-white rounded-2xl hover:bg-primary-700 transition-all duration-200 shadow-lg shadow-primary-500/20 hover:shadow-xl hover:-translate-y-0.5">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mb-3 group-hover:bg-white/30 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold mb-1">{{ __('Go to Inbox') }}</h3>
                <p class="text-xs text-white/70 text-center">{{ __('Start managing your emails with AI') }}</p>
            </a>

            {{-- Explore Dashboard --}}
            <a href="{{ route('dashboard') }}"
               class="group flex flex-col items-center justify-center p-6 bg-surface-2 border border-border rounded-2xl hover:border-primary-600 hover:bg-primary-900/20 transition-all duration-200 hover:-translate-y-0.5">
                <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-3 group-hover:bg-primary-200 transition-colors">
                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-ink mb-1">{{ __('Explore Dashboard') }}</h3>
                <p class="text-xs text-muted text-center">{{ __('Discover all features at a glance') }}</p>
            </a>

            {{-- Read the Docs --}}
            <a href="{{ route('knowledge-base') }}"
               class="group flex flex-col items-center justify-center p-6 bg-surface-2 border border-border rounded-2xl hover:border-primary-600 hover:bg-primary-900/20 transition-all duration-200 hover:-translate-y-0.5">
                <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mb-3 group-hover:bg-primary-200 transition-colors">
                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-ink mb-1">{{ __('Read the Docs') }}</h3>
                <p class="text-xs text-muted text-center">{{ __('Learn advanced features and tips') }}</p>
            </a>
        </div>

        {{-- Sample data notice --}}
        <div class="mt-6 p-4 bg-primary-900/20 border border-primary-800 rounded-xl text-center">
            <p class="text-sm text-primary-700 dark:text-primary-300">
                {{ __("We've added") }} <strong>{{ __('5 sample contacts') }}</strong> {{ __('and') }} <strong>{{ __('3 quick reply templates') }}</strong> {{ __('to help you explore. You can delete them anytime.') }}
            </p>
        </div>

        {{-- Setup Summary --}}
        <div class="mt-8 p-5 bg-surface border border-border rounded-xl">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-ink">{{ __('Setup Summary') }}</h4>
                <span class="text-xs font-medium px-2 py-1 rounded-full {{ $completedCount === 5 ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning' }}">
                    {{ $completedCount }}/5 {{ __('completed') }}
                </span>
            </div>
            <div class="space-y-2">
                {{-- Step 1: Workspace --}}
                @if ($steps['workspace'])
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-ink/80">{{ __('Workspace created') }}</span>
                    </div>
                @else
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full border-2 border-border flex-shrink-0"></div>
                            <span class="text-muted">{{ __('Workspace created') }}</span>
                        </div>
                        <a href="{{ route('onboarding.step-1') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">{{ __('Complete now') }}</a>
                    </div>
                @endif

                {{-- Step 2: Email --}}
                @if ($steps['email'])
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-ink/80">{{ __('Email account connected') }}</span>
                    </div>
                @else
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full border-2 border-border flex-shrink-0"></div>
                            <span class="text-muted">{{ __('Email account connected') }}</span>
                        </div>
                        <a href="{{ route('onboarding.step-2') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">{{ __('Complete now') }}</a>
                    </div>
                @endif

                {{-- Step 3: AI --}}
                @if ($steps['ai'])
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-ink/80">{{ __('AI assistant trained') }}</span>
                    </div>
                @else
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full border-2 border-border flex-shrink-0"></div>
                            <span class="text-muted">{{ __('AI assistant trained') }}</span>
                        </div>
                        <a href="{{ route('onboarding.step-3') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">{{ __('Complete now') }}</a>
                    </div>
                @endif

                {{-- Step 4: Auto-reply --}}
                @if ($steps['auto_reply'])
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-ink/80">{{ __('Auto-reply rules set') }}</span>
                    </div>
                @else
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full border-2 border-border flex-shrink-0"></div>
                            <span class="text-muted">{{ __('Auto-reply rules set') }}</span>
                        </div>
                        <a href="{{ route('onboarding.step-4') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">{{ __('Complete now') }}</a>
                    </div>
                @endif

                {{-- Step 5: Team --}}
                @if ($steps['team'])
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-ink/80">{{ __('Team members invited') }}</span>
                    </div>
                @else
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded-full border-2 border-border flex-shrink-0"></div>
                            <span class="text-muted">{{ __('Team members invited') }}</span>
                        </div>
                        <a href="{{ route('onboarding.step-5') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">{{ __('Complete now') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
