{{--
    Reusable Insight Card Component

    Props:
    - icon:         string  — icon name for the <x-icon> component
    - title:        string  — bold heading text
    - description:  string  — supporting description (text-muted)
    - actionUrl:    string  — CTA href
    - actionLabel:  string  — CTA button text
    - priority:     string  — urgent|attention|suggestion|positive
    - dismissable:  bool    — whether dismiss (X) button is shown (default true)
    - insightId:    string  — unique ID for dismiss tracking (optional, used by Livewire parent)
--}}
@props([
    'icon' => 'sparkles',
    'title' => '',
    'description' => '',
    'actionUrl' => '#',
    'actionLabel' => 'Take Action',
    'priority' => 'suggestion',
    'dismissable' => true,
    'insightId' => '',
])

@php
    $borderColor = match ($priority) {
        'urgent'    => 'border-l-red-500 dark:border-l-red-400',
        'attention' => 'border-l-amber-500 dark:border-l-amber-400',
        'suggestion'=> 'border-l-blue-500 dark:border-l-blue-400',
        'positive'  => 'border-l-green-500 dark:border-l-green-400',
        default     => 'border-l-blue-500 dark:border-l-blue-400',
    };

    $iconBg = match ($priority) {
        'urgent'    => 'bg-danger/10 dark:bg-red-900/30',
        'attention' => 'bg-warning/10 dark:bg-amber-900/30',
        'suggestion'=> 'bg-info/10 dark:bg-blue-900/30',
        'positive'  => 'bg-success/10 dark:bg-green-900/30',
        default     => 'bg-info/10 dark:bg-blue-900/30',
    };

    $iconColor = match ($priority) {
        'urgent'    => 'text-red-600 dark:text-red-400',
        'attention' => 'text-amber-600 dark:text-amber-400',
        'suggestion'=> 'text-blue-600 dark:text-blue-400',
        'positive'  => 'text-green-600 dark:text-green-400',
        default     => 'text-blue-600 dark:text-blue-400',
    };
@endphp

<div {{ $attributes->merge([
    'class' => "relative bg-surface-2 rounded-xl border border-border border-l-4 {$borderColor} p-4 shadow-sm hover:shadow-md transition-all duration-200 group"
]) }}>
    {{-- Dismiss button --}}
    @if($dismissable && $insightId)
        <button
            wire:click="dismiss('{{ $insightId }}')"
            aria-label="Dismiss insight"
            class="absolute top-2.5 right-2.5 p-1 rounded-lg text-muted hover:text-ink hover:bg-surface transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    @endif

    <div class="flex items-start gap-3">
        {{-- Icon --}}
        <div class="flex-shrink-0 w-9 h-9 rounded-lg {{ $iconBg }} flex items-center justify-center">
            <x-icon :name="$icon" class="w-4.5 h-4.5 {{ $iconColor }}" />
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0 pr-4">
            <h4 class="text-sm font-semibold text-ink leading-tight">{{ $title }}</h4>
            <p class="text-xs text-muted mt-1 leading-relaxed">{{ $description }}</p>

            {{-- CTA Button --}}
            <a href="{{ $actionUrl }}" wire:navigate class="inline-flex items-center gap-1.5 mt-3 px-3 py-1.5 text-xs font-semibold text-white bg-brand rounded-lg hover:bg-brand-strong transition-colors shadow-sm">
                {{ $actionLabel }}
                <x-icon name="arrow-right" class="w-3 h-3" />
            </a>
        </div>
    </div>
</div>
