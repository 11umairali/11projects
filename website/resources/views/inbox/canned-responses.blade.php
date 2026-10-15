<x-layouts.app :title="__('Quick Replies')">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('inbox') }}" wire:navigate class="p-2 hover:bg-surface rounded-xl transition-colors" aria-label="Back to inbox">
                <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-ink">{{ __('Quick Replies') }}</h1>
                <p class="text-sm text-muted">{{ __('Manage reusable response templates') }}</p>
            </div>
        </div>
        <div class="bg-surface-2 rounded-2xl border border-border shadow-sm p-6">
            <livewire:inbox.canned-response-manager />
        </div>
    </div>
</x-layouts.app>
