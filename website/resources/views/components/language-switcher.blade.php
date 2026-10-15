{{-- Language Switcher Dropdown --}}
@php
    $languages = \App\Models\Language::getActive();
    $currentLang = $currentLanguage ?? \App\Models\Language::findByCode(app()->getLocale());
@endphp

@if($languages->count() > 1)
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button @click="open = !open"
            class="{{ $buttonClass ?? 'flex items-center gap-1.5 px-2.5 py-1.5 text-sm font-medium text-muted hover:text-ink rounded-lg hover:bg-surface transition-colors' }}"
            :aria-expanded="open"
            aria-label="{{ __('Change language') }}">
        @if($currentLang?->flag)
        <span class="text-base leading-none">{{ $currentLang->flag }}</span>
        @endif
        <span class="hidden sm:inline">{{ $currentLang?->native_name ?? $currentLang?->name ?? 'EN' }}</span>
        <span class="sm:hidden">{{ strtoupper($currentLang?->code ?? 'EN') }}</span>
        <svg class="w-3.5 h-3.5 text-muted transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute {{ $dropdownAlign ?? 'right' }}-0 mt-2 w-48 bg-surface-2 rounded-xl border border-border shadow-lg z-50 py-1 max-h-64 overflow-y-auto"
         style="display: none;">
        @foreach($languages as $lang)
        <form method="POST" action="{{ route('locale.switch') }}">
            @csrf
            <input type="hidden" name="locale" value="{{ $lang->code }}">
            <button type="submit"
                    class="w-full text-left px-3 py-2 text-sm flex items-center gap-2.5 transition-colors
                           {{ $lang->code === app()->getLocale()
                               ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400 font-medium'
                               : 'text-ink hover:bg-surface' }}">
                @if($lang->flag)
                <span class="text-base leading-none">{{ $lang->flag }}</span>
                @endif
                <span class="flex-1">{{ $lang->native_name ?? $lang->name }}</span>
                @if($lang->direction === 'rtl')
                <span class="text-[10px] text-muted font-mono">RTL</span>
                @endif
                @if($lang->code === app()->getLocale())
                <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                @endif
            </button>
        </form>
        @endforeach
    </div>
</div>
@endif
