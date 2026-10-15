<div class="space-y-6">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-ink">{{ __('Contact Settings') }}</h1>
        <p class="text-sm text-muted mt-1">{{ __('Configure how contacts are managed and organized.') }}</p>
    </div>

    {{-- Contact Automation --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Contact Automation') }}</h2>
        <div class="space-y-4">
            <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                <div>
                    <p class="text-sm font-medium text-ink">{{ __('Auto-create contacts') }}</p>
                    <p class="text-xs text-muted mt-0.5">{{ __('Automatically create contacts from incoming emails') }}</p>
                </div>
                <button wire:click="$toggle('autoCreate')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $autoCreate ? 'bg-primary-600' : 'bg-gray-200' }}">
                    <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $autoCreate ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>

            <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                <div>
                    <p class="text-sm font-medium text-ink">{{ __('Auto-tag contacts') }}</p>
                    <p class="text-xs text-muted mt-0.5">{{ __('Automatically tag contacts based on email content using AI') }}</p>
                </div>
                <button wire:click="$toggle('autoTag')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $autoTag ? 'bg-primary-600' : 'bg-gray-200' }}">
                    <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $autoTag ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>

            <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                <div>
                    <p class="text-sm font-medium text-ink">{{ __('Auto-merge duplicates') }}</p>
                    <p class="text-xs text-muted mt-0.5">{{ __('Automatically merge contacts with matching email addresses') }}</p>
                </div>
                <button wire:click="$toggle('autoMerge')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $autoMerge ? 'bg-primary-600' : 'bg-gray-200' }}">
                    <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $autoMerge ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Import / Export --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Import & Export') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="{{ url('/contacts') }}" wire:navigate class="p-6 border-2 border-dashed border-border rounded-xl text-center hover:border-primary-300 transition-colors cursor-pointer block">
                <svg class="w-8 h-8 text-muted mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <p class="text-sm font-medium text-ink">{{ __('Import Contacts') }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Go to Contacts page to import CSV') }}</p>
            </a>
            <a href="{{ url('/contacts') }}" wire:navigate class="p-6 border-2 border-dashed border-border rounded-xl text-center hover:border-primary-300 transition-colors cursor-pointer block">
                <svg class="w-8 h-8 text-muted mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <p class="text-sm font-medium text-ink">{{ __('Export Contacts') }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Go to Contacts page to export') }}</p>
            </a>
        </div>
    </div>

    {{-- Save --}}
    <div class="flex items-center justify-end">
        <button wire:click="save" wire:loading.attr="disabled"
                class="btn-primary px-6 py-2.5 text-sm disabled:opacity-60">
            <span wire:loading.remove wire:target="save">{{ __('Save Contact Settings') }}</span>
            <span wire:loading wire:target="save" class="flex items-center gap-1.5">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Saving...
            </span>
        </button>
    </div>
</div>
