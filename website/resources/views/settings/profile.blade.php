<x-layouts.settings :title="__('Profile Settings')">
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
        <div x-show="!loaded" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="space-y-8">
            {{-- Profile photo / avatar section skeleton --}}
            <div class="bg-surface-2 rounded-xl border border-border dark:border-gray-700 p-6 animate-pulse">
                <div class="h-5 w-32 bg-gray-200 dark:bg-gray-700 rounded mb-6"></div>
                <div class="flex items-center gap-6">
                    <div class="h-20 w-20 bg-gray-200 dark:bg-gray-700 rounded-full flex-shrink-0"></div>
                    <div class="space-y-2">
                        <div class="h-9 w-32 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                        <div class="h-3 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    </div>
                </div>
            </div>

            {{-- Personal information form skeleton --}}
            <div class="bg-surface-2 rounded-xl border border-border dark:border-gray-700 p-6 animate-pulse">
                <div class="h-5 w-44 bg-gray-200 dark:bg-gray-700 rounded mb-6"></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @for ($i = 0; $i < 4; $i++)
                    <div class="space-y-2">
                        <div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="h-10 w-full bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                    </div>
                    @endfor
                </div>
                <div class="space-y-2 mt-6">
                    <div class="h-4 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="h-24 w-full bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                </div>
            </div>

            {{-- Email preferences section skeleton --}}
            <div class="bg-surface-2 rounded-xl border border-border dark:border-gray-700 p-6 animate-pulse">
                <div class="h-5 w-40 bg-gray-200 dark:bg-gray-700 rounded mb-6"></div>
                <div class="space-y-4">
                    @for ($i = 0; $i < 3; $i++)
                    <div class="flex items-center justify-between py-2">
                        <div class="space-y-1.5">
                            <div class="h-4 w-36 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-3 w-56 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                        <div class="h-6 w-11 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                    </div>
                    @endfor
                </div>
            </div>

            {{-- Save button skeleton --}}
            <div class="flex justify-end">
                <div class="h-10 w-32 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
            </div>
        </div>

        {{-- Actual Livewire component --}}
        <div x-show="loaded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
            <livewire:settings.profile-form />
        </div>
    </div>
</x-layouts.settings>
