<div class="space-y-8">
    {{-- Flash Messages --}}
    @if(session()->has('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition
         class="flex items-center gap-3 px-4 py-3 bg-success/10 border border-success/20 rounded-lg text-sm text-success">
        <x-icon name="check-circle" class="w-5 h-5 flex-shrink-0" />
        <span>{{ session('success') }}</span>
        <button type="button" @click="show = false" class="ml-auto text-green-500 hover:text-success">
            <x-icon name="x" class="w-4 h-4" />
        </button>
    </div>
    @endif

    @if(session()->has('error'))
    <div x-data="{ show: true }" x-show="show" x-transition
         class="flex items-center gap-3 px-4 py-3 bg-danger/10 border border-danger/20 rounded-lg text-sm text-danger">
        <x-icon name="alert-triangle" class="w-5 h-5 flex-shrink-0" />
        <span>{{ session('error') }}</span>
        <button type="button" @click="show = false" class="ml-auto text-red-500 hover:text-danger">
            <x-icon name="x" class="w-4 h-4" />
        </button>
    </div>
    @endif

    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Workflow Automation') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('Automate repetitive tasks with visual, no-code workflows.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('workflows.create') }}?guided=1"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-lg hover:bg-surface hover:border-border transition-colors">
                <x-icon name="sparkles" class="w-4 h-4 text-brand" />
                {{ __('Start from Template') }}
            </a>
            <a href="{{ route('workflows.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-brand text-white text-sm font-medium rounded-lg hover:bg-brand-strong transition-colors">
                <x-icon name="plus" class="w-4 h-4" />
                {{ __('Create Workflow') }}
            </a>
        </div>
    </div>

    {{-- Main Content Card --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">

    {{-- Filters: Search + Status dropdown --}}
    <div class="flex items-center gap-3">
        <div class="relative flex-1 max-w-sm">
            <x-icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted w-4 h-4" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search workflows...') }}"
                   class="w-full pl-9 pr-4 py-2 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent placeholder-gray-500">
        </div>

        {{-- Status filter dropdown --}}
        <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-border text-sm font-medium rounded-lg text-ink/80 bg-surface-2 hover:bg-surface transition-colors"
                    aria-haspopup="true"
                    :aria-expanded="open">
                <x-icon name="filter" class="w-3.5 h-3.5" />
                {{ $statusFilter === 'all' ? __('All Statuses') : ucfirst($statusFilter) }}
                <x-icon name="chevron-down" class="w-3.5 h-3.5" />
            </button>
            <div x-show="open" @click.away="open = false" x-transition
                 role="menu"
                 class="absolute right-0 top-full mt-1 z-50 w-40 bg-surface-2 border border-border rounded-lg shadow-lg py-1" style="display: none;">
                @foreach(['all', 'active', 'paused', 'draft', 'error'] as $status)
                <button type="button" role="menuitem" wire:click="$set('statusFilter', '{{ $status }}')" @click="open = false"
                        class="w-full text-left px-3 py-2 text-sm hover:bg-surface  transition-colors
                               {{ ($statusFilter ?? 'all') === $status ? 'bg-surface  font-medium text-ink' : 'text-ink/80' }}">
                    {{ $status === 'all' ? __('All Statuses') : ucfirst($status) }}
                </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Workflows Table.
         Removed `overflow-hidden` from the outer card and made the horizontal
         scroll wrapper only apply below lg so the action-menu dropdown on the
         last column isn't clipped to the table bounds on desktop. --}}
    @if($workflows->count() > 0)
    <div class="rounded-lg border border-border">
        <p class="text-xs text-muted text-center py-1 md:hidden">{{ __('Swipe to see more columns') }}</p>
        <div class="overflow-x-auto lg:overflow-visible">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface">
                        <th class="text-left font-medium text-muted px-4 py-3">{{ __('Name') }}</th>
                        <th class="text-left font-medium text-muted px-4 py-3">{{ __('Status') }}</th>
                        <th class="text-left font-medium text-muted px-4 py-3">{{ __('Trigger') }}</th>
                        <th class="text-right font-medium text-muted px-4 py-3">{{ __('Success Rate') }}</th>
                        <th class="text-left font-medium text-muted px-4 py-3">{{ __('Last Run') }}</th>
                        <th class="text-left font-medium text-muted px-4 py-3">{{ __('Created') }}</th>
                        <th class="text-right font-medium text-muted px-4 py-3">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($workflows as $workflow)
                    <tr class="hover:bg-surface transition-colors" wire:key="workflow-{{ $workflow->id }}">
                        {{-- Name --}}
                        <td class="px-4 py-3">
                            <a href="{{ route('workflows.edit', $workflow->id) }}"
                               class="font-medium text-ink hover:text-brand transition-colors">
                                {{ $workflow->name }}
                            </a>
                            @if($workflow->description)
                            <p class="text-xs text-muted mt-0.5 max-w-[260px] truncate">{{ $workflow->description }}</p>
                            @endif
                        </td>

                        {{-- Status badge: dot + label in colored pill --}}
                        <td class="px-4 py-3">
                            @php
                            $statusConfig = [
                                'active' => ['dot' => 'bg-success/100', 'text' => 'text-success', 'bg' => 'bg-success/10', 'label' => __('Active')],
                                'paused' => ['dot' => 'bg-warning/100', 'text' => 'text-warning', 'bg' => 'bg-warning/10', 'label' => __('Paused')],
                                'draft'  => ['dot' => 'bg-gray-400', 'text' => 'text-muted ', 'bg' => 'bg-surface ', 'label' => __('Draft')],
                                'error'  => ['dot' => 'bg-danger/100', 'text' => 'text-danger', 'bg' => 'bg-danger/10', 'label' => __('Error')],
                            ];
                            $sc = $statusConfig[$workflow->status] ?? $statusConfig['draft'];
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sc['bg'] }} {{ $sc['text'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }}"></span>
                                {{ $sc['label'] }}
                            </span>
                        </td>

                        {{-- Trigger --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 text-muted">
                                @php
                                    $triggerIcon = match($workflow->trigger_subtype) {
                                        'email_received', 'new_email' => 'mail',
                                        'contact_created', 'new_contact' => 'user-plus',
                                        'webhook', 'webhook_received' => 'zap',
                                        'deal_stage_changed' => 'trending-up',
                                        'tag_added' => 'tag',
                                        'schedule', 'scheduled' => 'clock',
                                        default => 'zap',
                                    };
                                @endphp
                                <x-icon :name="$triggerIcon" class="w-3.5 h-3.5" />
                                <span class="text-xs">{{ str_replace('_', ' ', ucfirst($workflow->trigger_subtype)) }}</span>
                            </div>
                        </td>

                        {{-- Success Rate --}}
                        <td class="px-4 py-3 text-right tabular-nums text-ink/80">
                            {{ $workflow->success_rate }}%
                        </td>

                        {{-- Last Run --}}
                        <td class="px-4 py-3 text-muted text-xs">
                            @if($workflow->last_run_at)
                            <span title="{{ $workflow->last_run_at->format('M j, Y g:i A') }}">{{ $workflow->last_run_at->diffForHumans() }}</span>
                            @else
                            --
                            @endif
                        </td>

                        {{-- Created --}}
                        <td class="px-4 py-3 text-muted text-xs">
                            {{ $workflow->created_at->format('M j, Y') }}
                        </td>

                        {{-- Actions dropdown.
                             Uses x-teleport="body" so the menu escapes the table's
                             stacking context and can never be clipped by row
                             borders, overflow, or the card rounding. Position is
                             computed from the trigger button's rect on open and
                             on scroll/resize so it stays anchored. --}}
                        <td class="px-4 py-3 text-right">
                            <div x-data="{
                                    open: false,
                                    menuTop: 0,
                                    menuLeft: 0,
                                    reposition() {
                                        const r = this.$refs.trigger.getBoundingClientRect();
                                        // Align right edge of menu with right edge of button
                                        this.menuLeft = r.right - 176; // menu width 176 (w-44)
                                        this.menuTop = r.bottom + 4;
                                    },
                                    toggle() {
                                        this.open = !this.open;
                                        if (this.open) this.$nextTick(() => this.reposition());
                                    },
                                }"
                                 @scroll.window="open && reposition()"
                                 @resize.window="open && reposition()"
                                 @keydown.escape.window="open = false">
                                <button type="button" x-ref="trigger" @click="toggle()"
                                        class="action-dots" aria-label="More actions"
                                        aria-haspopup="true"
                                        :aria-expanded="open">
                                    <x-icon name="more-horizontal" />
                                </button>
                                <template x-teleport="body">
                                    <div x-show="open"
                                         @click.outside="open = false"
                                         x-transition
                                         role="menu"
                                         :style="`position: fixed; top: ${menuTop}px; left: ${menuLeft}px; z-index: 9999;`"
                                         class="w-44 bg-surface-2 border border-border rounded-lg shadow-lg py-1"
                                         style="display: none;">
                                        <a href="{{ route('workflows.edit', $workflow->id) }}" @click="open = false"
                                           role="menuitem"
                                           class="flex items-center gap-2 px-3 py-2 text-sm text-ink/80 hover:bg-surface  transition-colors w-full">
                                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                                            {{ __('Edit') }}
                                        </a>
                                        <button type="button" role="menuitem" wire:click="duplicateWorkflow({{ $workflow->id }})" @click="open = false"
                                                class="flex items-center gap-2 px-3 py-2 text-sm text-ink/80 hover:bg-surface  transition-colors w-full text-left">
                                            <x-icon name="copy" class="w-3.5 h-3.5" />
                                            {{ __('Duplicate') }}
                                        </button>
                                        <button type="button" role="menuitem" wire:click="toggleStatus({{ $workflow->id }})" @click="open = false"
                                                class="flex items-center gap-2 px-3 py-2 text-sm text-ink/80 hover:bg-surface  transition-colors w-full text-left">
                                            @if($workflow->status === 'active')
                                            <x-icon name="pause" class="w-3.5 h-3.5" />
                                            {{ __('Pause') }}
                                            @else
                                            <x-icon name="play" class="w-3.5 h-3.5" />
                                            {{ __('Activate') }}
                                            @endif
                                        </button>
                                        <a href="{{ route('workflows.logs', $workflow->id) }}" @click="open = false"
                                           role="menuitem"
                                           class="flex items-center gap-2 px-3 py-2 text-sm text-ink/80 hover:bg-surface  transition-colors w-full">
                                            <x-icon name="history" class="w-3.5 h-3.5" />
                                            {{ __('View Logs') }}
                                        </a>
                                        <div class="border-t border-border my-1"></div>
                                        <button type="button" role="menuitem" wire:click="deleteWorkflow({{ $workflow->id }})" wire:confirm="Are you sure you want to delete this workflow? This cannot be undone."
                                                @click="open = false"
                                                class="flex items-center gap-2 px-3 py-2 text-sm text-danger hover:bg-danger/10 transition-colors w-full text-left">
                                            <x-icon name="trash" class="w-3.5 h-3.5" />
                                            {{ __('Delete') }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    {{-- Empty state --}}
    <x-empty-state
        type="workflows"
        :title="$search ? 'No workflows found' : 'No workflows yet'"
        :description="$search ? 'Try adjusting your search or filters.' : 'Create your first workflow to start automating.'"
    />
    @endif

    </div>{{-- end main content card --}}
</div>
