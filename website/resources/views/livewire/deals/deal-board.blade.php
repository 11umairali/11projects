<div class="space-y-6">
    {{-- Flash messages --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button @click="show = false" class="text-green-500 hover:text-success" aria-label="Dismiss">&times;</button>
    </div>
    @endif
    @if(session('error'))
    <div class="p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-sm">{{ session('error') }}</div>
    @endif

    {{-- ── Header ─────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-4">
        {{-- Top row: title + pipeline selector + add deal --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-ink">{{ __('Deals') }}</h1>
                    <p class="text-sm text-muted mt-0.5">
                        @if($totalValue > 0)
                            @currency($totalValue) {{ __('total pipeline value') }}
                        @else
                            {{ __('Manage your sales pipeline') }}
                        @endif
                    </p>
                </div>

                {{-- Pipeline selector --}}
                <div x-data="{ open: false }" class="relative ml-2">
                    <button @click="open = !open"
                            class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-ink bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors">
                        <span class="w-2 h-2 rounded-full bg-brand"></span>
                        {{ $pipeline?->name ?? __('Select Pipeline') }}
                        <svg class="w-4 h-4 text-muted transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" @click.away="open = false" x-transition
                         class="absolute right-0 mt-1 w-64 bg-surface-2 rounded-xl shadow-lg border border-border py-1 z-30 max-h-80 overflow-y-auto"
                         style="display: none;">
                        @foreach($pipelines as $p)
                        <button wire:click="switchPipeline({{ $p->id }})" @click="open = false"
                                class="w-full text-left px-4 py-2.5 text-sm flex items-center justify-between gap-2 hover:bg-surface transition-colors {{ $p->id === $pipelineId ? 'text-brand font-semibold bg-brand/5' : 'text-ink/80' }}">
                            <div class="flex items-center gap-2 min-w-0">
                                @if($p->id === $pipelineId)
                                <svg class="w-4 h-4 shrink-0 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                @else
                                <span class="w-4"></span>
                                @endif
                                <span class="truncate">{{ $p->name }}</span>
                                @if($p->is_default)
                                <span class="text-[10px] font-medium text-muted bg-surface px-1.5 py-0.5 rounded-full shrink-0">{{ __('Default') }}</span>
                                @endif
                            </div>
                            <span class="text-xs text-muted shrink-0">{{ $p->deals_count }} {{ __('deals') }}</span>
                        </button>
                        @endforeach

                        <div class="border-t border-border my-1"></div>

                        {{-- New pipeline --}}
                        <button @click="open = false; $wire.set('showPipelineForm', true)"
                                class="w-full text-left px-4 py-2.5 text-sm text-brand hover:bg-brand/5 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ __('New Pipeline') }}
                        </button>

                        {{-- Delete current pipeline (only if more than 1 pipeline) --}}
                        @if($pipelines->count() > 1 && $pipeline)
                        <button wire:click="deletePipeline({{ $pipelineId }})"
                                wire:confirm="Delete pipeline '{{ $pipeline->name }}' and all its deals? This cannot be undone."
                                @click="open = false"
                                class="w-full text-left px-4 py-2.5 text-sm text-danger hover:bg-danger/10 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            {{ __('Delete This Pipeline') }}
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            <button wire:click="openDealForm" wire:loading.attr="disabled" class="btn-primary flex items-center gap-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed self-start">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Add Deal') }}
            </button>
        </div>

        {{-- Status filter tabs --}}
        <div class="flex items-center gap-1 bg-surface-2 border border-border rounded-xl p-1 self-start">
            @php
                $filters = [
                    'open' => ['label' => __('Open'), 'count' => $statusCounts->open_count ?? 0],
                    'won' => ['label' => __('Won'), 'count' => $statusCounts->won_count ?? 0],
                    'lost' => ['label' => __('Lost'), 'count' => $statusCounts->lost_count ?? 0],
                    'all' => ['label' => __('All'), 'count' => $statusCounts->total ?? 0],
                ];
            @endphp
            @foreach($filters as $filterKey => $filter)
            <button wire:click="$set('statusFilter', '{{ $filterKey }}')"
                    class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors flex items-center gap-1.5
                    {{ $statusFilter === $filterKey
                        ? 'bg-surface text-ink shadow-sm'
                        : 'text-muted hover:text-ink' }}">
                {{ $filter['label'] }}
                <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full
                    {{ $statusFilter === $filterKey ? 'bg-brand/10 text-brand' : 'bg-surface text-muted' }}">{{ $filter['count'] }}</span>
            </button>
            @endforeach
        </div>
    </div>

    {{-- ── Revenue Forecast Bar ──────────────────────────────────── --}}
    @if($stages->isNotEmpty())
    <div class="flex items-center gap-6 px-4 py-2 border-b border-border bg-surface-2/50 rounded-xl">
        <div>
            <span class="text-xs text-muted">{{ __('Pipeline Value') }}</span>
            <p class="text-sm font-semibold text-ink">@currency($pipelineForecast['total'])</p>
        </div>
        <div>
            <span class="text-xs text-muted">{{ __('Weighted Forecast') }}</span>
            <p class="text-sm font-semibold text-success">@currency($pipelineForecast['weighted'])</p>
        </div>
        <div>
            <span class="text-xs text-muted">{{ __('Open Deals') }}</span>
            <p class="text-sm font-semibold text-ink">{{ $pipelineForecast['open_deals'] }}</p>
        </div>
    </div>
    @endif

    {{-- ── Stage summary ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach($stages as $stage)
        @php
            $stageDeals = $deals->get($stage->id, collect());
            $stageTotal = $stageDeals->sum('value');
            $stageCount = $stageDeals->count();
        @endphp
        <div class="bg-surface-2 rounded-xl border border-border p-3 text-center">
            <div class="flex items-center justify-center gap-1.5 mb-1">
                <span class="w-2 h-2 rounded-full" style="background-color: {{ $stage->color }}"></span>
                <span class="text-xs font-semibold text-muted uppercase">{{ $stage->name }}</span>
            </div>
            <p class="text-lg font-bold text-ink">@currency($stageTotal)</p>
            <p class="text-xs text-muted">{{ $stageCount }} {{ $stageCount === 1 ? __('deal') : __('deals') }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── Mobile: Stacked list view ──────────────────────────────── --}}
    <div class="lg:hidden space-y-4">
        {{-- Status filter is already shown above --}}
        @foreach($stages as $stage)
        @php $stageDeals = $deals->get($stage->id, collect()); @endphp
        <div class="bg-surface-2 rounded-xl border border-border overflow-hidden">
            <div class="px-4 py-3 border-b border-border/50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $stage->color }}"></span>
                    <h3 class="text-sm font-semibold text-ink">{{ $stage->name }}</h3>
                    <span class="text-xs font-medium text-muted bg-surface px-1.5 py-0.5 rounded-full">{{ $stageDeals->count() }}</span>
                </div>
                <span class="text-xs font-medium text-muted">@currency($stageDeals->sum('value'))</span>
            </div>
            <div class="divide-y divide-border/50">
                @forelse($stageDeals as $deal)
                <div class="px-4 py-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-surface transition-colors
                    {{ $deal->status === 'won' ? 'border-l-3 border-l-green-500' : '' }}
                    {{ $deal->status === 'lost' ? 'border-l-3 border-l-red-500 opacity-70' : '' }}"
                     wire:click="openDealForm({{ $deal->id }})">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            @if($deal->status === 'won')
                            <svg class="w-3.5 h-3.5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($deal->status === 'lost')
                            <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                            <p class="text-sm font-semibold text-ink truncate">{{ $deal->title }}</p>
                        </div>
                        <p class="text-xs text-muted mt-0.5">{{ $deal->contact?->company ?? $deal->contact?->full_name ?? __('No contact') }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-bold text-ink">@currency($deal->value)</p>
                        @if($deal->expected_close_date)
                        @php $dl = intval(now()->diffInDays($deal->expected_close_date, false)); @endphp
                        <p class="text-[10px] {{ $dl < 0 ? 'text-red-400' : 'text-muted' }}">
                            {{ $dl < 0 ? abs($dl).'d overdue' : ($dl === 0 ? 'Today' : $dl.'d left') }}
                        </p>
                        @endif
                    </div>
                </div>
                @empty
                <div class="px-4 py-6 text-center">
                    <p class="text-xs text-muted">{{ __('No deals in this stage') }}</p>
                </div>
                @endforelse
            </div>
            {{-- Add deal to stage (mobile) --}}
            <button wire:click="openDealForm(null, {{ $stage->id }})"
                    class="w-full px-4 py-2.5 border-t border-border/50 text-sm text-muted hover:text-ink hover:bg-surface transition-colors flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Add deal') }}
            </button>
        </div>
        @endforeach
    </div>

    {{-- Deal move loading indicator --}}
    <div wire:loading wire:target="updateDealPosition, moveDeal" class="text-center py-2">
        <div class="inline-flex items-center gap-2 text-sm text-muted bg-surface-2 border border-border rounded-xl px-4 py-2">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            {{ __('Moving deal...') }}
        </div>
    </div>

    {{-- ── Desktop: Kanban board with drag & drop ────────────────── --}}
    <div class="hidden lg:block">
        <div class="overflow-x-auto -mx-6 px-6 pb-4">
            <div class="flex gap-4 min-w-max">
                @foreach($stages as $stage)
                @php $stageDeals = $deals->get($stage->id, collect()); @endphp
                <div class="w-72 flex-shrink-0" wire:key="stage-{{ $stage->id }}"
                     x-data="{ dragOver: false, dragCounter: 0 }"
                     x-on:dragenter.prevent="dragCounter++; dragOver = true"
                     x-on:dragover.prevent
                     x-on:dragleave.prevent="dragCounter--; if (dragCounter <= 0) { dragOver = false; dragCounter = 0; }"
                     x-on:drop.prevent="
                         dragOver = false; dragCounter = 0;
                         const dealId = parseInt($event.dataTransfer.getData('text/plain'));
                         if (dealId) {
                             $dispatch('deal-moving');
                             $wire.updateDealPosition(dealId, {{ $stage->id }});
                         }
                     ">

                    {{-- Stage header with color bar --}}
                    <div class="mb-3">
                        <div class="h-[3px] rounded-full mb-3" style="background-color: {{ $stage->color }}"></div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full" style="background-color: {{ $stage->color }}"></span>
                                <h3 class="text-sm font-semibold text-ink/80">{{ $stage->name }}</h3>
                                <span class="text-xs font-medium text-muted bg-surface px-1.5 py-0.5 rounded-full">{{ $stageDeals->count() }}</span>
                            </div>
                            <span class="text-xs font-medium text-muted">@currency($stageDeals->sum('value'))</span>
                        </div>
                    </div>

                    {{-- Deal cards container (drop zone) --}}
                    <div class="space-y-3 min-h-[120px] rounded-xl transition-colors duration-150 p-1 -m-1"
                         :class="dragOver ? 'bg-brand/5 ring-2 ring-dashed ring-brand/30' : ''">

                        {{-- Drop indicator --}}
                        <div x-show="dragOver" x-transition class="flex items-center justify-center py-4 text-brand text-sm font-medium">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                            {{ __('Drop here') }}
                        </div>

                        @foreach($stageDeals as $deal)
                        <div wire:key="deal-{{ $deal->id }}"
                             x-data="{ dragging: false }"
                             draggable="true"
                             x-on:dragstart="dragging = true; $event.dataTransfer.setData('text/plain', '{{ $deal->id }}'); $event.dataTransfer.effectAllowed = 'move'"
                             x-on:dragend="dragging = false"
                             :class="dragging && 'opacity-50 scale-[0.98]'"
                             class="bg-surface-2 rounded-xl border border-border p-4 hover:shadow-md transition-all duration-150 cursor-grab active:cursor-grabbing group relative
                                {{ $deal->status === 'won' ? 'border-l-3 border-l-green-500' : '' }}
                                {{ $deal->status === 'lost' ? 'border-l-3 border-l-red-500 opacity-70' : '' }}">

                            {{-- Drag handle (visible on hover) --}}
                            <div class="absolute left-1.5 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-40 transition-opacity pointer-events-none">
                                <svg class="w-3 h-5 text-muted" viewBox="0 0 6 16" fill="currentColor">
                                    <circle cx="1.5" cy="2" r="1.2"/>
                                    <circle cx="4.5" cy="2" r="1.2"/>
                                    <circle cx="1.5" cy="6" r="1.2"/>
                                    <circle cx="4.5" cy="6" r="1.2"/>
                                    <circle cx="1.5" cy="10" r="1.2"/>
                                    <circle cx="4.5" cy="10" r="1.2"/>
                                    <circle cx="1.5" cy="14" r="1.2"/>
                                    <circle cx="4.5" cy="14" r="1.2"/>
                                </svg>
                            </div>

                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                    @if($deal->status === 'won')
                                    <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @elseif($deal->status === 'lost')
                                    <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @endif
                                    <h4 wire:click="openDealForm({{ $deal->id }})"
                                        class="text-sm font-semibold text-ink group-hover:text-primary-700 dark:group-hover:text-primary-300 transition-colors cursor-pointer truncate">{{ $deal->title }}</h4>
                                </div>

                                {{-- Three-dot menu --}}
                                <div x-data="{ open: false, menuStyle: '', toggleMenu(el) { if (!this.open) { var r = el.getBoundingClientRect(); var t = r.bottom + 4; var l = r.right - 192; if (t + 320 > window.innerHeight) t = Math.max(8, r.top - 320); if (l < 8) l = 8; this.menuStyle = 'top:'+t+'px;left:'+l+'px;max-height:'+(window.innerHeight-t-8)+'px;'; } this.open = !this.open; } }" class="relative shrink-0">
                                    <button @click="toggleMenu($el)" class="action-dots opacity-0 group-hover:opacity-100" aria-label="More actions" :aria-expanded="open">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                                    </button>
                                    {{-- Teleport to body so a transformed ancestor
                                         card can't hijack position:fixed. --}}
                                    <template x-teleport="body">
                                    <div x-show="open" @click.outside="open = false" @scroll.window="open = false" x-transition
                                         role="menu"
                                         class="w-48 bg-surface-2 rounded-xl shadow-2xl border border-border py-1 z-[9999] overflow-y-auto"
                                         :style="'position: fixed;' + menuStyle"
                                         style="display: none;">
                                        <button role="menuitem" wire:click="openDealForm({{ $deal->id }})" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-ink/80 hover:bg-surface flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            {{ __('Edit') }}
                                        </button>
                                        <div class="border-t border-border my-1"></div>
                                        @foreach($stages as $targetStage)
                                            @if($targetStage->id !== $stage->id)
                                            <button role="menuitem" wire:click="moveDeal({{ $deal->id }}, {{ $targetStage->id }})" @click="open = false" class="w-full text-left px-3 py-1.5 text-sm text-ink/80 hover:bg-surface flex items-center gap-2">
                                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $targetStage->color }}"></span>
                                                {{ $targetStage->name }}
                                            </button>
                                            @endif
                                        @endforeach
                                        <div class="border-t border-border my-1"></div>
                                        <button role="menuitem" wire:click="deleteDeal({{ $deal->id }})" wire:confirm="Delete this deal?" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-danger hover:bg-danger/10 flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            {{ __('Delete') }}
                                        </button>
                                    </div>
                                    </template>
                                </div>
                            </div>

                            @if($deal->contact)
                            <p class="text-xs text-muted mb-3">{{ $deal->contact->company ?? $deal->contact->full_name }}</p>
                            @endif

                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-ink">@currency($deal->value)</span>
                                <div class="flex items-center gap-2">
                                    @if($deal->expected_close_date)
                                    @php $daysLeft = intval(now()->diffInDays($deal->expected_close_date, false)); @endphp
                                    <span class="text-xs {{ $daysLeft < 0 ? 'text-red-400' : 'text-muted' }} flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ abs($daysLeft) }}d {{ $daysLeft < 0 ? __('overdue') : '' }}
                                    </span>
                                    @endif
                                    @if($deal->assignedTo)
                                    <div class="w-6 h-6 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-xs font-semibold text-muted" title="{{ $deal->assignedTo->name }}">
                                        {{ $deal->assignedTo->initials }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach

                        {{-- Empty stage drop zone --}}
                        @if($stageDeals->isEmpty())
                        <div class="flex flex-col items-center justify-center py-8 text-center"
                             :class="dragOver ? 'opacity-0' : 'opacity-100'">
                            <p class="text-xs text-muted">{{ __('No deals in this stage.') }}</p>
                            <p class="text-xs text-muted mt-0.5">{{ __('Drag a deal here or click + to add one.') }}</p>
                        </div>
                        @endif

                        {{-- Drop zone indicator (visible during drag) --}}
                        <div x-show="dragOver" x-transition
                             class="border-2 border-dashed border-brand/40 rounded-xl py-6 text-center"
                             style="display: none;">
                            <svg class="w-6 h-6 text-brand/50 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                            <p class="text-xs text-brand/60 font-medium">{{ __('Drop deal here') }}</p>
                        </div>
                    </div>

                    {{-- Add deal to stage --}}
                    <button wire:click="openDealForm(null, {{ $stage->id }})" class="w-full mt-3 py-2.5 border-2 border-dashed border-border rounded-xl text-sm text-muted hover:text-ink hover:border-border transition-colors flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('Add deal') }}
                    </button>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    @if($stages->isEmpty())
    <x-empty-state
        type="deals"
        :title="__('No pipeline configured')"
        :description="__('A default pipeline will be created automatically.')"
    />
    @endif

    {{-- ── Pipeline Create Modal ──────────────────────────────────── --}}
    @if($showPipelineForm)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="pipeline-form-title">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-surface/50 backdrop-blur-sm" wire:click="closePipelineForm"></div>
            <div class="relative bg-surface-2 rounded-2xl shadow-xl max-w-md w-full p-6" x-trap="$wire.showPipelineForm">
                <div class="flex items-center justify-between mb-5">
                    <h2 id="pipeline-form-title" class="text-lg font-semibold text-ink">{{ __('New Pipeline') }}</h2>
                    <button wire:click="closePipelineForm" class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="createPipeline" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Pipeline Name') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="newPipelineName" autofocus
                               class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="{{ __('e.g. Enterprise Sales, Partnerships...') }}">
                        @error('newPipelineName') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-xs text-muted">
                        {{ __('Default stages will be created: Lead, Qualified, Proposal, Negotiation, Won, Lost. You can customize them later.') }}
                    </p>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closePipelineForm" class="btn-secondary text-sm">{{ __('Cancel') }}</button>
                        <button type="submit" wire:loading.attr="disabled" class="btn-primary px-5 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="createPipeline">{{ __('Create Pipeline') }}</span>
                            <span wire:loading wire:target="createPipeline" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ __('Creating...') }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Deal Form Modal ────────────────────────────────────────── --}}
    @if($showDealForm)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="deal-form-modal-title">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-surface/50 backdrop-blur-sm" wire:click="closeDealForm"></div>
            <div class="relative bg-surface-2 rounded-2xl shadow-xl max-w-lg w-full p-6" x-trap="$wire.showDealForm">
                <div class="flex items-center justify-between mb-5">
                    <h2 id="deal-form-modal-title" class="text-lg font-semibold text-ink">{{ $editingDealId ? __('Edit Deal') : __('New Deal') }}</h2>
                    <button wire:click="closeDealForm" class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="saveDeal" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Deal Title') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="dealTitle" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="{{ __('Enterprise License') }}">
                        @error('dealTitle') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Value') }} ($)</label>
                            <input type="number" wire:model="dealValue" step="0.01" min="0" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="10000">
                            @error('dealValue') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Expected Close') }}</label>
                            <input type="date" wire:model="dealExpectedClose" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            @error('dealExpectedClose') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Stage') }} <span class="text-red-500">*</span></label>
                        <select wire:model="dealStageId" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="">{{ __('Select stage...') }}</option>
                            @foreach($stages as $stage)
                            <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                            @endforeach
                        </select>
                        @error('dealStageId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Contact') }}</label>
                        <select wire:model="dealContactId" class="w-full px-3 py-2 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="">{{ __('No contact') }}</option>
                            @foreach($contacts as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->full_name }} {{ $contact->company ? '(' . $contact->company . ')' : '' }}</option>
                            @endforeach
                        </select>
                        @error('dealContactId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeDealForm" class="btn-secondary text-sm">{{ __('Cancel') }}</button>
                        <button type="submit" wire:loading.attr="disabled" class="btn-primary px-5 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="saveDeal">{{ $editingDealId ? __('Update') : __('Create') }} {{ __('Deal') }}</span>
                            <span wire:loading wire:target="saveDeal" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ __('Saving...') }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
