{{--
    Admin Modal Component
    ---------------------
    A reusable, accessible modal dialog driven by Alpine.js events.
    Uses x-teleport to render at <body> level, preventing overflow/table clipping.

    Usage:
        <x-admin-modal name="import-users" maxWidth="md">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-ink">Modal Title</h3>
                ...
            </div>
        </x-admin-modal>

        <!-- Open via dispatch: -->
        <button @click="$dispatch('open-modal', 'import-users')">Open</button>

    Props:
        name     - Unique identifier for this modal (required)
        maxWidth - sm | md | lg | xl | 2xl (default: md)
--}}

@props(['name', 'maxWidth' => 'md'])

@php
$maxWidthClass = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
@endphp

<template x-teleport="body">
    <div
        x-data="{
            show: false,
            focusables() {
                let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
                return [...$el.querySelectorAll(selector)].filter(el => !el.hasAttribute('disabled'))
            },
            firstFocusable() { return this.focusables()[0] },
            lastFocusable() { return this.focusables().slice(-1)[0] },
            nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
            prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
            nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
            prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) - 1 },
        }"
        x-init="$watch('show', value => {
            if (value) {
                document.body.classList.add('overflow-y-hidden');
            } else {
                document.body.classList.remove('overflow-y-hidden');
            }
        })"
        x-on:open-modal.window="if ($event.detail === '{{ $name }}') { show = true; $nextTick(() => { let f = firstFocusable(); if (f) f.focus(); }); }"
        x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
        x-on:close.stop="show = false"
        x-on:keydown.escape.window="show = false"
        x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
        x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-[9999] flex items-center justify-center px-4 py-6 sm:px-0"
        style="display: none;"
        role="dialog"
        aria-modal="true"
    >
        {{-- Backdrop --}}
        <div
            x-show="show"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm"
            @click="show = false"
            aria-hidden="true"
        ></div>

        {{-- Modal Panel --}}
        <div
            x-show="show"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative max-h-[calc(100vh-3rem)] w-full overflow-y-auto rounded-2xl border border-border bg-surface-2 shadow-soft sm:max-h-[calc(100vh-4rem)] sm:w-full {{ $maxWidthClass }} sm:mx-auto"
            @click.stop
        >
            {{ $slot }}
        </div>
    </div>
</template>
