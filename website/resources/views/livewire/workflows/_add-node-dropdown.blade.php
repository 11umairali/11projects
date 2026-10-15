{{-- Add Node Dropdown Menu --}}
{{-- Used on connector lines in the visual flow builder (Step 3) --}}
{{-- Requires Alpine.js x-data="{ showMenu: false }" on the parent --}}

<div x-show="showMenu" @click.away="showMenu = false"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95 translate-y-2"
     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     class="absolute left-1/2 -translate-x-1/2 top-full mt-2 w-[520px] max-w-[90vw] bg-surface-2 rounded-xl shadow-2xl border border-border p-0 z-30 overflow-hidden"
     style="display: none;">

    {{-- Header --}}
    <div class="px-5 py-3 bg-surface border-b border-border">
        <p class="text-sm font-semibold text-ink">{{ __('Add a Step') }}</p>
        <p class="text-xs text-muted mt-0.5">{{ __('Choose what happens next in your workflow') }}</p>
    </div>

    <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">

        {{-- ============================================================ --}}
        {{-- CONDITIONS (Yellow) --}}
        {{-- ============================================================ --}}
        <div>
            <div class="flex items-center gap-2 mb-2 pb-2 border-b border-yellow-100">
                <span class="w-6 h-6 bg-warning/15 rounded-md flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="text-xs font-bold text-yellow-600 uppercase tracking-wider">{{ __('Conditions') }}</span>
            </div>
            <div class="space-y-0.5">
                @foreach($conditionSubtypes as $key => $label)
                <button @click="showMenu = false" wire:click="addNode('condition', '{{ $key }}')"
                        class="w-full text-left px-3 py-2 text-sm text-ink/80 hover:bg-warning/10 hover:text-warning rounded-lg transition-all duration-150 flex items-center gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-yellow-400 flex-shrink-0"></span>
                    <span class="text-xs font-medium">{{ $label }}</span>
                </button>
                @endforeach
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- ACTIONS (Green) --}}
        {{-- ============================================================ --}}
        <div>
            <div class="flex items-center gap-2 mb-2 pb-2 border-b border-green-100">
                <span class="w-6 h-6 bg-success/15 rounded-md flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                </span>
                <span class="text-xs font-bold text-success uppercase tracking-wider">{{ __('Actions') }}</span>
            </div>
            <div class="space-y-0.5 max-h-64 overflow-y-auto">
                @foreach($actionSubtypes as $key => $label)
                    @if($key !== 'wait_delay')
                    <button @click="showMenu = false" wire:click="addNode('action', '{{ $key }}')"
                            class="w-full text-left px-3 py-2 text-sm text-ink/80 hover:bg-success/10 hover:text-success rounded-lg transition-all duration-150 flex items-center gap-2.5">
                        <span class="w-2 h-2 rounded-full bg-green-400 flex-shrink-0"></span>
                        <span class="text-xs font-medium">{{ $label }}</span>
                    </button>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- DELAYS (Purple) --}}
        {{-- ============================================================ --}}
        <div>
            <div class="flex items-center gap-2 mb-2 pb-2 border-b border-purple-100">
                <span class="w-6 h-6 bg-brand/15 rounded-md flex items-center justify-center flex-shrink-0">
                    <svg class="w-3.5 h-3.5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="text-xs font-bold text-purple-600 uppercase tracking-wider">{{ __('Delays') }}</span>
            </div>
            <div class="space-y-0.5">
                <button @click="showMenu = false" wire:click="addNode('action', 'wait_delay')"
                        class="w-full text-left px-3 py-2 text-sm text-ink/80 hover:bg-brand/10 hover:text-brand rounded-lg transition-all duration-150 flex items-center gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-purple-400 flex-shrink-0"></span>
                    <span class="text-xs font-medium">{{ __('Wait Duration') }}</span>
                </button>
            </div>

            {{-- Quick tips --}}
            <div class="mt-4 p-3 bg-surface rounded-lg border border-border">
                <p class="text-[10px] font-semibold text-muted uppercase tracking-wider mb-1.5">{{ __('Tips') }}</p>
                <ul class="text-[10px] text-muted space-y-1">
                    <li class="flex items-start gap-1.5">
                        <span class="w-1 h-1 rounded-full bg-gray-300 mt-1 flex-shrink-0"></span>
                        {{ __('Use conditions to branch logic') }}
                    </li>
                    <li class="flex items-start gap-1.5">
                        <span class="w-1 h-1 rounded-full bg-gray-300 mt-1 flex-shrink-0"></span>
                        {{ __('Add delays between actions') }}
                    </li>
                    <li class="flex items-start gap-1.5">
                        <span class="w-1 h-1 rounded-full bg-gray-300 mt-1 flex-shrink-0"></span>
                        {{ __('Chain multiple actions together') }}
                    </li>
                </ul>
            </div>
        </div>

    </div>
</div>
