<x-layouts.app :title="__('Workflows')">
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
                    <div class="h-8 w-40 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                    <div class="h-4 w-72 bg-gray-200 dark:bg-gray-700 rounded mt-2 animate-pulse"></div>
                </div>
                <div class="h-10 w-40 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
            </div>

            {{-- Filters / search bar skeleton --}}
            <div class="flex items-center gap-3">
                <div class="h-10 flex-1 max-w-sm bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                <div class="h-10 w-28 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                <div class="h-10 w-28 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
            </div>

            {{-- Table skeleton --}}
            <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 overflow-hidden animate-pulse">
                {{-- Table header --}}
                <div class="grid grid-cols-12 gap-4 px-6 py-3 border-b border-border dark:border-gray-700 bg-surface ">
                    <div class="col-span-4 h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="col-span-2 h-4 w-14 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="col-span-2 h-4 w-14 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="col-span-2 h-4 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="col-span-2 h-4 w-14 bg-gray-200 dark:bg-gray-700 rounded"></div>
                </div>
                {{-- Table rows --}}
                @for ($i = 0; $i < 6; $i++)
                <div class="grid grid-cols-12 gap-4 px-6 py-4 border-b border-border dark:border-gray-700/50 items-center">
                    <div class="col-span-4 flex items-center gap-3">
                        <div class="h-9 w-9 bg-gray-200 dark:bg-gray-700 rounded-lg flex-shrink-0"></div>
                        <div class="space-y-1.5 flex-1">
                            <div class="h-3.5 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-3 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                    </div>
                    <div class="col-span-2 h-6 w-16 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                    <div class="col-span-2 h-6 w-12 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                    <div class="col-span-2 h-3.5 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="col-span-2 flex gap-2 justify-end">
                        <div class="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                        <div class="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                    </div>
                </div>
                @endfor
            </div>

            {{-- Pagination skeleton --}}
            <div class="flex items-center justify-between">
                <div class="h-4 w-40 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                <div class="flex gap-1">
                    @for ($i = 0; $i < 5; $i++)
                    <div class="h-9 w-9 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                    @endfor
                </div>
            </div>
        </div>

        {{-- Actual Livewire component --}}
        <div x-show="loaded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
            <livewire:workflows.workflow-list />
        </div>
    </div>
</x-layouts.app>
