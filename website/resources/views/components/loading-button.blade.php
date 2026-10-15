{{--
    Loading Button Component
    ------------------------
    A button with built-in loading/spinner state. Prevents layout shift by
    preserving the button's intrinsic width during the loading transition.

    Usage:
        <!-- Basic -->
        <x-loading-button wire:click="save" wire:target="save">
            Save Changes
        </x-loading-button>

        <!-- With variant and size -->
        <x-loading-button variant="danger" size="sm" wire:click="delete" wire:target="delete">
            Delete
        </x-loading-button>

        <!-- Manual loading control (Alpine) -->
        <x-loading-button :loading="$isProcessing" @click="submit()">
            Submit
        </x-loading-button>

        <!-- Custom loading text -->
        <x-loading-button wire:click="export" wire:target="export" loading-text="Exporting...">
            Export CSV
        </x-loading-button>

    Props:
        type        - HTML button type: button | submit | reset (default: "button")
        variant     - Visual style: primary | secondary | danger (default: "primary")
        size        - Button size: sm | md | lg (default: "md")
        loading     - Boolean, force loading state (default: false)
        disabled    - Boolean, disable the button (default: false)
        loadingText - Text shown during loading (default: null, shows spinner only with original text)
--}}

@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'loading' => false,
    'disabled' => false,
    'loadingText' => null,
])

@php
    $variantClass = match($variant) {
        'primary'   => 'btn-primary',
        'secondary' => 'btn-secondary',
        'danger'    => 'btn-danger',
        default     => 'btn-primary',
    };

    $sizeClass = match($size) {
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };

    // Detect wire:target for automatic Livewire loading state
    $wireTarget = $attributes->get('wire:target') ?? $attributes->get('wire:click');
@endphp

<button
    {{ $attributes->merge([
        'type' => $type,
        'class' => "$variantClass $sizeClass",
    ])->except(['loading', 'loadingText']) }}
    @if($wireTarget)
        x-data="{ get isLoading() { return {{ $loading ? 'true' : 'false' }} || $wire.__instance?.snapshot === undefined ? false : $wire.__instance?.effects?.dispatches?.length > 0; } }"
        wire:loading.attr="disabled"
        wire:target="{{ $wireTarget }}"
    @else
        x-data="{ isLoading: {{ $loading ? 'true' : 'false' }} }"
    @endif
    :disabled="isLoading || {{ $disabled ? 'true' : 'false' }}"
    :aria-busy="isLoading"
    style="min-width: var(--btn-min-w, auto);"
    x-init="$nextTick(() => $el.style.setProperty('--btn-min-w', $el.offsetWidth + 'px'))"
>
    {{-- Spinner --}}
    @if($wireTarget)
        <svg wire:loading wire:target="{{ $wireTarget }}" class="h-4 w-4 loading-spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
            <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
    @else
        <svg x-show="isLoading" x-cloak class="h-4 w-4 loading-spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
            <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
    @endif

    {{-- Button content --}}
    @if($loadingText)
        @if($wireTarget)
            <span wire:loading.remove wire:target="{{ $wireTarget }}">{{ $slot }}</span>
            <span wire:loading wire:target="{{ $wireTarget }}">{{ $loadingText }}</span>
        @else
            <span x-show="!isLoading">{{ $slot }}</span>
            <span x-show="isLoading" x-cloak>{{ $loadingText }}</span>
        @endif
    @else
        <span>{{ $slot }}</span>
    @endif
</button>
