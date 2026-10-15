<x-layouts.app :title="__('Deals')">
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
                    <div class="h-8 w-32 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                    <div class="h-4 w-48 bg-gray-200 dark:bg-gray-700 rounded mt-2 animate-pulse"></div>
                </div>
                <div class="flex gap-3">
                    <div class="h-10 w-28 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                    <div class="h-10 w-32 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                </div>
            </div>

            {{-- Pipeline summary skeleton --}}
            <div class="flex gap-2 overflow-x-auto pb-2">
                @for ($i = 0; $i < 5; $i++)
                <div class="h-8 w-24 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse flex-shrink-0"></div>
                @endfor
            </div>

            {{-- Kanban board skeleton: columns --}}
            <div class="flex gap-4 overflow-x-auto pb-4 -mx-6 px-6">
                @php $columnCards = [3, 2, 4, 2, 1]; @endphp
                @foreach ($columnCards as $cardCount)
                <div class="flex-shrink-0 w-72 animate-pulse">
                    {{-- Column header --}}
                    <div class="flex items-center justify-between mb-3 px-1">
                        <div class="flex items-center gap-2">
                            <div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-5 w-6 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                        </div>
                        <div class="h-3.5 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    </div>

                    {{-- Column body --}}
                    <div class="bg-surface /50 rounded-xl p-3 space-y-3 min-h-[400px]">
                        @for ($j = 0; $j < $cardCount; $j++)
                        <div class="bg-surface-2  rounded-lg border border-border dark:border-gray-700 p-4 space-y-3">
                            <div class="h-4 w-3/4 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="flex items-center gap-2">
                                <div class="h-6 w-6 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                                <div class="h-3 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="h-5 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                <div class="h-5 w-14 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                            </div>
                        </div>
                        @endfor
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Actual Livewire component --}}
        <div x-show="loaded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
            <livewire:deals.deal-pipeline />
        </div>
    </div>
</x-layouts.app>
