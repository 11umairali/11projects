<div class="space-y-4">
    @if(count($insights) > 0)
        {{-- Section header --}}
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 bg-brand/10 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center">
                <x-icon name="sparkles" class="w-3.5 h-3.5 text-brand dark:text-indigo-400" />
            </div>
            <h2 class="text-sm font-bold text-ink">{{ __('Actionable Insights') }}</h2>
        </div>

        {{-- Cards grid: 2x2 on desktop, horizontal scroll on mobile --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($insights as $insight)
                <div
                    wire:key="insight-{{ $insight['id'] }}"
                    x-data="{ visible: true }"
                    x-show="visible"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-x-0"
                    x-transition:leave-end="opacity-0 -translate-x-4"
                >
                    <x-insight-card
                        :icon="$insight['icon']"
                        :title="$insight['title']"
                        :description="$insight['description']"
                        :actionUrl="$insight['actionUrl']"
                        :actionLabel="$insight['actionLabel']"
                        :priority="$insight['priority']"
                        :insightId="$insight['id']"
                        :dismissable="true"
                        x-on:click.self="visible = false"
                    />
                </div>
            @endforeach
        </div>
    @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-8 px-4 bg-surface-2 rounded-2xl border border-border">
            <div class="relative w-14 h-14 mb-3">
                <div class="absolute inset-0 bg-success/10 dark:bg-green-900/20 rounded-full animate-pulse"></div>
                <div class="relative w-14 h-14 bg-success/15 dark:bg-green-900/30 rounded-full flex items-center justify-center">
                    <svg class="w-7 h-7 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>
            <p class="text-sm font-semibold text-ink">{{ __("Everything's looking great!") }}</p>
            <p class="text-xs text-muted mt-1">{{ __('No actions needed right now.') }}</p>
        </div>
    @endif
</div>
