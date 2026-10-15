<x-layouts.app :title="__('Activity')">
    {{-- The Activity page used to wrap the full feed in a single bare panel,
         which made the stats, filters, timeline, and load-more all squash into
         one big card with no visual hierarchy. The new layout follows the
         same dashboard / workflows pattern: header bar, stats row, then a
         dedicated card for filters + timeline. The Livewire ActivityFeed
         component carries everything below the stats row. --}}
    <div class="space-y-6">
        {{-- Page header --}}
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-ink">{{ __('Activity') }}</h1>
                <p class="text-sm text-muted mt-1">{{ __('A live timeline of everything happening across your workspace — campaigns, contacts, conversations, deals, automations.') }}</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-success/10 text-success border border-success/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-success animate-pulse"></span>
                    {{ __('Live') }}
                </span>
                <span>{{ __('Auto-refreshes every minute') }}</span>
            </div>
        </div>

        {{-- The Livewire component renders the stats row + filters + timeline. --}}
        <livewire:activity-feed />
    </div>
</x-layouts.app>
