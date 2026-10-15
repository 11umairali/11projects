<x-layouts.app :title="__('Email Templates')">
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
        <div x-show="!loaded" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="space-y-6" role="status" :aria-busy="!loaded ? 'true' : 'false'" aria-label="Loading email templates">
            {{-- Page header skeleton --}}
            <div class="flex items-center justify-between">
                <div>
                    <div class="h-8 w-44 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                    <div class="h-4 w-72 bg-gray-200 dark:bg-gray-700 rounded mt-2 animate-pulse"></div>
                </div>
                <div class="h-10 w-40 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
            </div>

            {{-- Search skeleton --}}
            <div class="h-10 w-full max-w-md bg-gray-200 dark:bg-gray-700 rounded-xl animate-pulse"></div>

            {{-- Category pills skeleton --}}
            <div class="flex gap-2">
                @for ($i = 0; $i < 6; $i++)
                <div class="h-8 w-24 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse"></div>
                @endfor
            </div>

            {{-- Template grid skeleton --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @for ($i = 0; $i < 6; $i++)
                <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden animate-pulse">
                    <div class="h-52 bg-gray-200 dark:bg-gray-700"></div>
                    <div class="p-4 space-y-2">
                        <div class="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="h-5 w-20 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                    </div>
                </div>
                @endfor
            </div>
        </div>

        {{-- Actual Livewire component --}}
        <div x-show="loaded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
            <livewire:campaigns.email-template-gallery />
        </div>
    </div>
</x-layouts.app>
