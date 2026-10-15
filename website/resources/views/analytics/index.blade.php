<x-layouts.app title="Analytics">
    <div x-data="{ activeTab: 'overview' }" class="space-y-6">
        {{-- Tabs --}}
        <div class="border-b border-border">
            <nav class="flex gap-6 -mb-px overflow-x-auto">
                @foreach(['overview' => __('Overview'), 'ai' => __('AI Performance'), 'team' => __('Team')] as $key => $label)
                <button @click="activeTab = '{{ $key }}'"
                        :class="activeTab === '{{ $key }}' ? 'border-primary-600 text-primary-700' : 'border-transparent text-muted hover:text-ink/80 hover:border-border'"
                        class="pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex-shrink-0">
                    {{ $label }}
                </button>
                @endforeach
            </nav>
        </div>

        {{-- Overview Tab --}}
        <div x-show="activeTab === 'overview'" x-transition>
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
                {{-- Skeleton --}}
                <div x-show="!loaded" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="space-y-6">
                    {{-- Stats cards --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @for ($i = 0; $i < 4; $i++)
                        <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 p-5 animate-pulse">
                            <div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded mb-3"></div>
                            <div class="h-7 w-16 bg-gray-200 dark:bg-gray-700 rounded mb-1"></div>
                            <div class="h-3 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                        @endfor
                    </div>
                    {{-- Charts --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        @for ($i = 0; $i < 2; $i++)
                        <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 p-6 animate-pulse">
                            <div class="h-5 w-36 bg-gray-200 dark:bg-gray-700 rounded mb-6"></div>
                            <div class="h-56 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                        </div>
                        @endfor
                    </div>
                    {{-- Bottom chart --}}
                    <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 p-6 animate-pulse">
                        <div class="h-5 w-44 bg-gray-200 dark:bg-gray-700 rounded mb-6"></div>
                        <div class="h-64 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                    </div>
                </div>
                {{-- Actual component --}}
                <div x-show="loaded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <livewire:analytics.overview-dashboard />
                </div>
            </div>
        </div>

        {{-- AI Performance Tab --}}
        <div x-show="activeTab === 'ai'" x-transition style="display: none;">
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
                {{-- Skeleton --}}
                <div x-show="!loaded" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="space-y-6">
                    {{-- AI stats --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @for ($i = 0; $i < 4; $i++)
                        <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 p-5 animate-pulse">
                            <div class="h-4 w-28 bg-gray-200 dark:bg-gray-700 rounded mb-3"></div>
                            <div class="h-7 w-20 bg-gray-200 dark:bg-gray-700 rounded mb-1"></div>
                            <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                        @endfor
                    </div>
                    {{-- AI performance charts --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 p-6 animate-pulse">
                            <div class="h-5 w-40 bg-gray-200 dark:bg-gray-700 rounded mb-6"></div>
                            <div class="h-56 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                        </div>
                        <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 p-6 animate-pulse">
                            <div class="h-5 w-36 bg-gray-200 dark:bg-gray-700 rounded mb-6"></div>
                            <div class="space-y-3">
                                @for ($i = 0; $i < 5; $i++)
                                <div class="flex items-center gap-3">
                                    <div class="h-3 w-24 bg-gray-200 dark:bg-gray-700 rounded flex-shrink-0"></div>
                                    <div class="h-4 flex-1 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                                    <div class="h-3 w-10 bg-gray-200 dark:bg-gray-700 rounded flex-shrink-0"></div>
                                </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Actual component --}}
                <div x-show="loaded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <livewire:analytics.a-i-performance />
                </div>
            </div>
        </div>

        {{-- Team Tab --}}
        <div x-show="activeTab === 'team'" x-transition style="display: none;">
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
                {{-- Skeleton --}}
                <div x-show="!loaded" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="space-y-6">
                    {{-- Team stats --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @for ($i = 0; $i < 4; $i++)
                        <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 p-5 animate-pulse">
                            <div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded mb-3"></div>
                            <div class="h-7 w-14 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                        @endfor
                    </div>
                    {{-- Team member rows --}}
                    <div class="bg-surface-2  rounded-xl border border-border dark:border-gray-700 overflow-hidden animate-pulse">
                        <div class="px-6 py-3 border-b border-border dark:border-gray-700 bg-surface ">
                            <div class="h-4 w-28 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                        @for ($i = 0; $i < 5; $i++)
                        <div class="flex items-center gap-4 px-6 py-4 border-b border-border dark:border-gray-700/50">
                            <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-full flex-shrink-0"></div>
                            <div class="flex-1 space-y-1.5">
                                <div class="h-3.5 w-28 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                <div class="h-3 w-36 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            </div>
                            <div class="h-3.5 w-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-3.5 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-6 w-14 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                        </div>
                        @endfor
                    </div>
                </div>
                {{-- Actual component --}}
                <div x-show="loaded" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-cloak>
                    <livewire:analytics.team-performance />
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
