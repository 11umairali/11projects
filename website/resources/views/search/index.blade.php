<x-layouts.app :title="__('Search')">
    <div class="max-w-4xl mx-auto space-y-6">
        {{-- Page header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-ink">{{ __('Search') }}</h1>
                <p class="text-sm text-muted mt-1">{{ __('Search across all your conversations, contacts, and knowledge base.') }}</p>
            </div>
            <div class="hidden sm:flex items-center gap-2 text-xs text-muted">
                <kbd class="inline-flex items-center rounded-lg border border-border bg-surface px-2 py-0.5 font-mono text-[10px] font-medium">&uarr;&darr;</kbd>
                {{ __('navigate') }}
                <kbd class="inline-flex items-center rounded-lg border border-border bg-surface px-2 py-0.5 font-mono text-[10px] font-medium">Enter</kbd>
                {{ __('open') }}
            </div>
        </div>

        {{-- Skeleton loader: shown instantly while Livewire component hydrates --}}
        <div x-data="{ loaded: false }" x-init="
            const observer = new MutationObserver((mutations) => {
                for (const m of mutations) {
                    for (const node of m.addedNodes) {
                        if (node.nodeType === 1 && node.hasAttribute('wire:id')) {
                            loaded = true;
                            observer.disconnect();
                            return;
                        }
                    }
                }
            });
            observer.observe($el, { childList: true, subtree: true });
            setTimeout(() => { loaded = true; observer.disconnect(); }, 5000);
        ">
            {{-- Skeleton placeholder --}}
            <div x-show="!loaded" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="space-y-6">
                {{-- Search input skeleton --}}
                <div class="h-14 w-full bg-gray-200 dark:bg-gray-700 rounded-2xl animate-pulse"></div>

                {{-- Filter tabs skeleton --}}
                <div class="flex gap-2">
                    @for ($i = 0; $i < 6; $i++)
                    <div class="h-9 w-24 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                    @endfor
                </div>

                {{-- Search results skeleton --}}
                <div class="space-y-3">
                    @for ($i = 0; $i < 6; $i++)
                    <div class="rounded-xl border border-border bg-surface-2 p-4 animate-pulse">
                        <div class="flex items-start gap-4">
                            <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-xl shrink-0"></div>
                            <div class="flex-1 space-y-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-4 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                    <div class="h-5 w-16 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                                </div>
                                <div class="h-3 w-full bg-gray-200 dark:bg-gray-700 rounded"></div>
                                <div class="flex items-center gap-3 mt-1">
                                    <div class="h-3 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                    <div class="h-3 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endfor
                </div>
            </div>

            {{-- Actual Livewire component --}}
            <div x-show="loaded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                <livewire:search.global-search />
            </div>
        </div>
    </div>
</x-layouts.app>
