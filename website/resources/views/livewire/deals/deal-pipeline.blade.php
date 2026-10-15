<div class="space-y-6">
    {{-- Flash messages --}}
    @if (session()->has('success'))
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-success/20 text-success px-4 py-3.5 rounded-xl text-sm font-medium flex items-center gap-3 shadow-sm"
             x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="w-8 h-8 bg-success/15 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Deals') }}</h1>
            <p class="text-sm text-muted mt-0.5">{{ __('Track your sales pipeline and deal progress') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($this->pipelines->isNotEmpty())
            <div x-data="{ pipeOpen: false, showNewForm: false, newName: '', submitPipeline(wire) { if (this.newName.trim()) { wire.createNewPipeline(this.newName); this.showNewForm = false; this.newName = ''; } } }" class="relative">
                <button @click="pipeOpen = !pipeOpen" class="flex items-center gap-2 text-sm bg-surface-2 border border-border rounded-xl px-4 py-2.5 hover:border-primary-300 transition-all">
                    <span class="font-medium text-ink">{{ $this->pipelines->firstWhere('id', $activePipelineId)?->name ?? __('Pipeline') }}</span>
                    <svg class="w-4 h-4 text-muted" :class="pipeOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="pipeOpen" @click.away="pipeOpen = false" x-transition class="absolute right-0 mt-1 w-64 bg-surface-2 rounded-xl shadow-2xl border border-border py-1 z-50" style="display:none">
                    @foreach($this->pipelines as $p)
                    <button wire:click="switchPipeline({{ $p->id }})" @click="pipeOpen=false" class="w-full text-left px-4 py-2 text-sm hover:bg-surface flex items-center justify-between {{ $p->id == $activePipelineId ? 'text-brand font-semibold bg-brand/5' : 'text-ink/80' }}">
                        <span>{{ $p->name }}</span>
                        @if($p->id == $activePipelineId)<svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>@endif
                    </button>
                    @endforeach
                    <div class="border-t border-border my-1"></div>
                    <button @click="pipeOpen=false;showNewForm=true" class="w-full text-left px-4 py-2 text-sm text-brand hover:bg-brand/5 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('New Pipeline') }}
                    </button>
                </div>
                {{-- New pipeline inline form --}}
                <div x-show="showNewForm" x-transition @click.away="showNewForm=false" class="absolute right-0 mt-1 w-72 bg-surface-2 rounded-xl shadow-2xl border border-border p-4 z-50" style="display:none">
                    <p class="text-sm font-semibold text-ink mb-3">{{ __('Create New Pipeline') }}</p>
                    <input x-ref="pipeInput" x-model="newName" @keydown.enter="submitPipeline($wire)" type="text" placeholder="{{ __('Pipeline name...') }}" class="w-full px-3 py-2 text-sm border border-border rounded-xl bg-surface focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 mb-3" x-init="$watch('showNewForm', v => { if(v) setTimeout(() => $refs.pipeInput.focus(), 100) })">
                    <div class="flex gap-2">
                        <button @click="submitPipeline($wire)" class="btn-primary flex-1 px-3 py-2 text-sm">{{ __('Create') }}</button>
                        <button @click="showNewForm=false;newName=''" class="px-3 py-2 text-sm text-muted border border-border rounded-xl hover:bg-surface">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </div>
            @endif
            <div class="flex items-center bg-surface-2 border border-border rounded-xl p-0.5">
                @foreach(['open' => __('Open'), 'won' => __('Won'), 'lost' => __('Lost'), 'all' => __('All')] as $fKey => $fLabel)
                <button wire:click="$set('statusFilter', '{{ $fKey }}')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200 {{ $statusFilter === $fKey ? 'bg-primary-600 text-white shadow-sm' : 'text-muted  hover:text-ink hover:bg-surface' }}">
                    {{ $fLabel }}
                </button>
                @endforeach
            </div>
        </div>
    </div>

    @if($this->stages->isNotEmpty())
    {{-- Pipeline summary --}}
    <div class="grid gap-3" style="grid-template-columns: repeat({{ min($this->stages->count(), 6) }}, minmax(0, 1fr));">

        @foreach($this->stages as $stage)
        <div class="bg-surface-2 rounded-xl border border-border p-4 text-center hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 relative overflow-hidden group">
            {{-- Colored top bar --}}
            <div class="absolute top-0 left-0 right-0 h-1 rounded-t-xl" style="background-color: {{ $stage->color }}"></div>
            <div class="flex items-center justify-center gap-1.5 mb-1.5 mt-1">
                <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $stage->color }}"></span>
                <span class="text-xs font-bold text-muted uppercase tracking-wider">{{ $stage->name }}</span>
            </div>
            <p class="text-xl font-bold text-ink flex items-center justify-center gap-1">
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @currency($stage->total_value)
            </p>
            <p class="text-xs text-muted mt-0.5">{{ $stage->deal_count }} {{ $stage->deal_count === 1 ? __('deal') : __('deals') }}</p>
        </div>
        @endforeach
    </div>

    {{-- Getting started hint (shown when pipeline has no deals) --}}
    @if($this->stages->every(fn ($s) => $s->deal_count === 0))
    <div class="bg-brand/5 border border-brand/20 rounded-xl px-5 py-4 flex items-start gap-3" x-data="{ show: true }" x-show="show">
        <svg class="w-5 h-5 text-brand shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="flex-1">
            <p class="text-sm font-semibold text-ink">{{ __('Getting started with Deals') }}</p>
            <p class="text-xs text-muted mt-1">{{ __('Click') }} <strong>"+ {{ __('Add deal') }}"</strong> {{ __('in any stage column to create your first deal. You can drag deals between stages, mark them as Won/Lost, and track your pipeline value.') }}</p>
        </div>
        <button @click="show = false" class="text-muted hover:text-ink shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    {{-- Kanban Board --}}
    <div class="overflow-x-auto -mx-6 px-6 pb-4">
        <div class="flex gap-4 min-w-max">
            @foreach($this->stages as $stage)
            @php
                $stageSlug = \Illuminate\Support\Str::slug($stage->name);
                $isWon = $stageSlug === 'won' || $stageSlug === 'closed-won';
                $isLost = $stageSlug === 'lost' || $stageSlug === 'closed-lost';
            @endphp
            <div class="w-80 flex-shrink-0" wire:key="stage-{{ $stage->id }}">
                {{-- Stage header --}}
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2.5">
                        <span class="w-3.5 h-3.5 rounded-lg shadow-sm" style="background-color: {{ $stage->color }}"></span>
                        <h3 class="text-sm font-bold text-ink/80">{{ $stage->name }}</h3>
                        <span class="text-xs font-bold text-muted bg-surface  px-2 py-0.5 rounded-full border border-border/50">{{ $stage->deal_count }}</span>
                    </div>
                    <span class="text-xs font-bold text-muted bg-surface px-2.5 py-1 rounded-lg border border-border/50">
                        <svg class="w-3 h-3 text-muted inline mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @currency($stage->total_value)
                    </span>
                </div>

                {{-- Cards container (drop zone) --}}
                <div class="space-y-3 rounded-xl p-2 min-h-[120px] transition-all duration-150
                    {{ $isWon ? 'bg-success/5 border border-dashed border-success/20' : ($isLost ? 'bg-danger/5 border border-dashed border-danger/20' : 'bg-surface') }}"
                     x-data="{ dragOver: false, dragCount: 0 }"
                     x-on:dragenter.prevent="dragCount++; dragOver = true"
                     x-on:dragover.prevent
                     x-on:dragleave.prevent="dragCount--; if(dragCount<=0){dragOver=false;dragCount=0}"
                     x-on:drop.prevent="dragOver=false;dragCount=0;let id=parseInt($event.dataTransfer.getData('text/plain'));if(id)$wire.updateDealStage(id,{{ $stage->id }})"
                     :class="dragOver && 'ring-2 ring-dashed ring-brand/40 bg-brand/5'">

                    {{-- Drop indicator --}}
                    <div x-show="dragOver" x-transition class="flex items-center justify-center py-3 text-brand text-xs font-semibold gap-1.5 rounded-lg border-2 border-dashed border-brand/30 bg-brand/5 mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                        {{ __('Drop here') }} — {{ $stage->name }}
                    </div>

                    @foreach($stage->loaded_deals as $deal)
                    <div class="bg-surface-2 rounded-xl border border-border shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-grab active:cursor-grabbing group overflow-hidden" wire:key="deal-{{ $deal->id }}"
                         draggable="true"
                         x-data="{ isDragging: false }"
                         x-on:dragstart="isDragging=true;$event.dataTransfer.setData('text/plain','{{ $deal->id }}');$event.dataTransfer.effectAllowed='move'"
                         x-on:dragend="isDragging=false"
                         :class="isDragging && 'opacity-40 scale-95 rotate-1'">
                        {{-- Colored left border via pseudo element --}}
                        <div class="border-l-[3px] p-4" style="border-left-color: {{ $stage->color }}">
                            <div class="flex items-start justify-between mb-2">
                                <button wire:click="expandDeal({{ $deal->id }})" class="text-sm font-bold text-ink group-hover:text-primary-700 dark:group-hover:text-primary-300 transition-colors text-left leading-snug">{{ $deal->title }}</button>
                                <div x-data="{ open: false, mStyle: '' }" class="relative">
                                    <button @click="
                                        let r = $el.getBoundingClientRect();
                                        let h = window.innerHeight;
                                        let top = r.bottom + 4;
                                        let left = r.right - 200;
                                        let maxH = h - top - 8;
                                        if (maxH < 200) { top = Math.max(8, r.top - 300); maxH = r.top - 16; }
                                        if (left < 8) left = 8;
                                        mStyle = 'top:'+top+'px;left:'+left+'px;max-height:'+Math.max(200,maxH)+'px';
                                        open = !open;
                                    " class="action-dots opacity-0 group-hover:opacity-100" aria-label="More actions" :aria-expanded="open">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                                    </button>
                                    <template x-teleport="body">
                                    <div x-show="open" @click.away="open = false" @scroll.window="open = false" x-transition class="fixed w-52 bg-surface-2 rounded-xl shadow-2xl border border-border py-1.5 z-[9999] overflow-y-auto" :style="mStyle" style="display: none;">
                                        @foreach($this->stages as $ts)
                                            @if($ts->id !== $stage->id)
                                            <button wire:click="updateDealStage({{ $deal->id }}, {{ $ts->id }})" @click="open = false" class="w-full text-left px-4 py-1.5 text-sm text-ink/80 hover:bg-surface flex items-center gap-2.5 transition-colors">
                                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $ts->color }}"></span>
                                                {{ __('Move to') }} {{ $ts->name }}
                                            </button>
                                            @endif
                                        @endforeach
                                        <div class="border-t border-border my-1"></div>
                                        @if($deal->status === 'open')
                                        <button wire:click="markAsWon({{ $deal->id }})" @click="open = false" class="w-full text-left px-4 py-1.5 text-sm text-success hover:bg-success/10 flex items-center gap-2 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ __('Mark as Won') }}
                                        </button>
                                        <button wire:click="markAsLost({{ $deal->id }})" @click="open = false" class="w-full text-left px-4 py-1.5 text-sm text-orange-600 hover:bg-orange-50 flex items-center gap-2 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ __('Mark as Lost') }}
                                        </button>
                                        @endif
                                        <button wire:click="deleteDeal({{ $deal->id }})" wire:confirm="Delete this deal? This action cannot be undone." @click="open = false" class="w-full text-left px-4 py-1.5 text-sm text-danger hover:bg-danger/10 flex items-center gap-2 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            {{ __('Delete') }}
                                        </button>
                                    </div>
                                    </template>
                                </div>
                            </div>
                            <p class="text-xs text-muted mb-3">{{ $deal->contact?->company ?? $deal->contact?->full_name ?? __('No contact') }}</p>
                            <div class="flex items-center justify-between">
                                <span class="text-base font-bold text-ink flex items-center gap-1">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    @currency($deal->value)
                                </span>
                                <div class="flex items-center gap-2">
                                    @php $daysInStage = intval($deal->updated_at->diffInDays(now())); @endphp
                                    @if($daysInStage > 0)
                                    <span class="text-xs text-muted flex items-center gap-1 bg-surface px-1.5 py-0.5 rounded">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $daysInStage }}d
                                    </span>
                                    @endif
                                    @if($deal->assignedTo)
                                    <div class="w-7 h-7 bg-gradient-to-br from-primary-500 to-primary-600 rounded-full flex items-center justify-center text-white text-xs font-bold ring-2 ring-white shadow-sm" title="{{ $deal->assignedTo->name }}">{{ $deal->assignedTo->initials }}</div>
                                    @endif
                                </div>
                            </div>
                            @if($deal->status !== 'open')
                            <div class="mt-2.5 pt-2 border-t border-border">
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full {{ $deal->status === 'won' ? 'bg-success/15 text-success border border-success/20' : 'bg-danger/15 text-danger border border-danger/20' }}">{{ ucfirst($deal->status) }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach

                    {{-- Add deal --}}
                    @if($addingToStageId === $stage->id)
                    <div class="bg-surface-2 rounded-xl border-2 border-primary-300 p-4 space-y-3 shadow-sm">
                        <input type="text" wire:model="newDealTitle" placeholder="{{ __('Deal title... (e.g. Acme Corp Website)') }}" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-200" autofocus>
                        @error('newDealTitle') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted font-medium">$</span>
                            <input type="number" wire:model="newDealValue" placeholder="0" step="0.01" min="0" class="w-full pl-7 pr-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-200">
                        </div>
                        <select wire:model="newDealContactId" class="w-full px-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-200">
                            <option value="">{{ __('No contact') }}</option>
                            @foreach($this->contacts as $c)
                            <option value="{{ $c->id }}">{{ $c->first_name }} {{ $c->last_name }} {{ $c->company ? "({$c->company})" : '' }}</option>
                            @endforeach
                        </select>
                        <div class="flex items-center gap-2">
                            <button wire:click="createDeal" class="btn-primary flex-1 px-4 py-2 text-sm font-semibold">{{ __('Add') }}</button>
                            <button wire:click="cancelAddDeal" class="px-4 py-2 text-sm text-muted  border border-border rounded-xl hover:bg-surface transition-all duration-200">{{ __('Cancel') }}</button>
                        </div>
                    </div>
                    @else
                    <button wire:click="startAddDeal({{ $stage->id }})" class="w-full py-3 border-2 border-dashed border-border rounded-xl text-sm text-muted hover:text-primary-600 dark:hover:text-primary-400 hover:border-primary-400 hover:bg-primary-50/50 dark:hover:bg-primary-900/20 transition-all duration-200 flex items-center justify-center gap-2 group">
                        <div class="w-6 h-6 bg-surface  group-hover:bg-primary-100 rounded-full flex items-center justify-center transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        {{ __('Add deal') }}
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 rounded-2xl bg-surface flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-ink mb-1">{{ __('No pipeline found') }}</h3>
        <p class="text-sm text-muted mb-5 max-w-xs">{{ __('Create a pipeline with stages to start tracking your deals and revenue.') }}</p>
        <button wire:click="createDefaultPipeline"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand text-white text-sm font-medium rounded-xl hover:bg-brand-strong transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('Create Sales Pipeline') }}
        </button>
    </div>
    @endif

    {{-- Expanded Deal Detail Modal --}}
    @if($expandedDealId && $this->expandedDeal)
    @php $deal = $this->expandedDeal; @endphp
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="deal-detail-modal-title">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-surface/60 backdrop-blur-sm" wire:click="expandDeal(null)"></div>
            <div class="relative bg-surface-2 rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden"
                 x-data x-trap="true" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                {{-- Modal header with gradient --}}
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-4 flex items-center justify-between">
                    <h2 id="deal-detail-modal-title" class="text-lg font-bold text-white">{{ $deal->title }}</h2>
                    <button wire:click="expandDeal(null)" class="p-1.5 text-white/70 hover:text-white rounded-lg hover:bg-surface/10 transition-all duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-2 gap-5">
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-4 border border-green-100">
                            <p class="text-xs text-success font-semibold uppercase tracking-wider">{{ __('Value') }}</p>
                            <p class="text-2xl font-bold text-ink mt-1 flex items-center gap-1">
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @currency($deal->value)
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-gray-50 to-gray-100/50 rounded-xl p-4 border border-border/80">
                            <p class="text-xs text-muted font-semibold uppercase tracking-wider">{{ __('Status') }}</p>
                            <div class="mt-2">
                                <span class="text-sm font-bold px-3 py-1 rounded-full {{ match($deal->status) { 'won' => 'bg-success/15 text-success border border-success/20', 'lost' => 'bg-danger/15 text-danger border border-danger/20', default => 'bg-info/15 text-info border border-info/20' } }}">{{ ucfirst($deal->status) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <p class="text-xs text-muted font-semibold uppercase tracking-wider">{{ __('Stage') }}</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="w-3 h-3 rounded-full shadow-sm" style="background-color: {{ $deal->dealStage?->color }}"></span>
                                <span class="text-sm font-semibold text-ink">{{ $deal->dealStage?->name }}</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs text-muted font-semibold uppercase tracking-wider">{{ __('Pipeline') }}</p>
                            <p class="text-sm font-semibold text-ink mt-1.5">{{ $deal->pipeline?->name }}</p>
                        </div>
                    </div>
                    @if($deal->contact)
                    <div class="bg-surface rounded-xl p-4 border border-border">
                        <p class="text-xs text-muted font-semibold uppercase tracking-wider mb-1.5">{{ __('Contact') }}</p>
                        <p class="text-sm font-semibold text-ink">{{ $deal->contact->full_name }}</p>
                        @if($deal->contact->company)<p class="text-xs text-muted mt-0.5">{{ $deal->contact->company }}</p>@endif
                    </div>
                    @endif
                    @if($deal->assignedTo)
                    <div>
                        <p class="text-xs text-muted font-semibold uppercase tracking-wider">{{ __('Assigned To') }}</p>
                        <div class="flex items-center gap-2 mt-1.5">
                            <div class="w-6 h-6 bg-gradient-to-br from-primary-500 to-primary-600 rounded-full flex items-center justify-center text-white text-xs font-bold">{{ $deal->assignedTo->initials }}</div>
                            <p class="text-sm font-semibold text-ink">{{ $deal->assignedTo->name }}</p>
                        </div>
                    </div>
                    @endif
                    @if($deal->expected_close_date)
                    <div>
                        <p class="text-xs text-muted font-semibold uppercase tracking-wider">{{ __('Expected Close') }}</p>
                        <p class="text-sm font-semibold text-ink mt-1">{{ $deal->expected_close_date->format('M j, Y') }}</p>
                    </div>
                    @endif
                    @if($deal->notes)
                    <div>
                        <p class="text-xs text-muted font-semibold uppercase tracking-wider">{{ __('Notes') }}</p>
                        <p class="text-sm text-muted  mt-1 leading-relaxed">{{ $deal->notes }}</p>
                    </div>
                    @endif
                    <div class="text-xs text-muted flex items-center gap-3">
                        <span>{{ __('Created') }} {{ $deal->created_at->format('M j, Y') }}</span>
                        <span class="w-1 h-1 bg-gray-300 dark:bg-gray-600 rounded-full"></span>
                        <span title="{{ $deal->updated_at->format('M j, Y g:i A') }}">{{ __('Updated') }} {{ $deal->updated_at->diffForHumans() }}</span>
                    </div>
                    @if($deal->status === 'open')
                    <div class="flex items-center gap-2 pt-3 border-t border-border">
                        <button wire:click="markAsWon({{ $deal->id }})" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-semibold rounded-xl hover:from-green-700 hover:to-emerald-700 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('Won') }}
                        </button>
                        <button wire:click="markAsLost({{ $deal->id }})" class="flex-1 px-4 py-2.5 bg-gradient-to-r from-orange-500 to-orange-600 text-white text-sm font-semibold rounded-xl hover:from-orange-600 hover:to-orange-700 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('Lost') }}
                        </button>
                        <button wire:click="deleteDeal({{ $deal->id }})" wire:confirm="Delete this deal? This action cannot be undone." class="px-4 py-2.5 text-sm text-danger border border-danger/20 rounded-xl hover:bg-danger/10 transition-all duration-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
