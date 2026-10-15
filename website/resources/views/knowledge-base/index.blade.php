<x-layouts.app :title="__('Knowledge Base')">
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
            {{-- Page header skeleton --}}
            <div class="flex items-center justify-between">
                <div>
                    <div class="h-8 w-44 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                    <div class="h-4 w-72 bg-gray-200 dark:bg-gray-700 rounded mt-2 animate-pulse"></div>
                </div>
                <div class="flex gap-3">
                    <div class="h-10 w-32 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                    <div class="h-10 w-36 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                </div>
            </div>

            {{-- Search + filter bar skeleton --}}
            <div class="flex items-center gap-3">
                <div class="h-10 flex-1 max-w-md bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                <div class="h-10 w-28 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
            </div>

            {{-- Stats cards skeleton --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @for ($i = 0; $i < 3; $i++)
                <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 p-5 animate-pulse">
                    <div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
                    <div class="h-7 w-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                </div>
                @endfor
            </div>

            {{-- Document grid skeleton --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @for ($i = 0; $i < 9; $i++)
                <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 p-5 animate-pulse">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-lg flex-shrink-0"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="h-4 w-3/4 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-3 w-full bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="h-5 w-16 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                        <div class="h-3 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    </div>
                </div>
                @endfor
            </div>
        </div>

        {{-- Actual Livewire component --}}
        <div x-show="loaded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
            <livewire:knowledge-base.document-manager />
        </div>
    </div>
</x-layouts.app>
