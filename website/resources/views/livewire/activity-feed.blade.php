{{-- Activity Feed — Timeline-style activity log.
     Auto-refreshes every 60s. Layout breaks the page into three cards:
     stats row, filter bar, timeline — instead of dumping everything into
     one giant panel. --}}
<div wire:poll.60s class="space-y-6">

    {{-- Stats row — only on the dedicated /activity page, not embedded
         versions (e.g. dashboard widgets). --}}
    @if(request()->routeIs('activity'))
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @php $s = $this->stats; @endphp
        <div class="rounded-2xl border border-border bg-surface-2 p-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand/10 text-brand">
                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3 8 4-16 3 8h4"/></svg>
            </div>
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-muted">{{ __('Total events') }}</div>
                <div class="text-xl font-bold text-ink tabular-nums">{{ number_format($s['total']) }}</div>
            </div>
        </div>
        <div class="rounded-2xl border border-border bg-surface-2 p-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-success/10 text-success">
                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-muted">{{ __('Today') }}</div>
                <div class="text-xl font-bold text-ink tabular-nums">{{ number_format($s['today']) }}</div>
            </div>
        </div>
        <div class="rounded-2xl border border-border bg-surface-2 p-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-info/10 text-info">
                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </div>
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-muted">{{ __('Last 7 days') }}</div>
                <div class="text-xl font-bold text-ink tabular-nums">{{ number_format($s['week']) }}</div>
            </div>
        </div>
        <div class="rounded-2xl border border-border bg-surface-2 p-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent/15 text-accent">
                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="text-[11px] font-medium uppercase tracking-wider text-muted">{{ __('Active people') }}</div>
                <div class="text-xl font-bold text-ink tabular-nums">{{ number_format($s['actors']) }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Filter card — only on the dedicated /activity page. --}}
    @if(request()->routeIs('activity'))
    <div class="rounded-2xl border border-border bg-surface-2 p-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="{{ __('Search activities by description or actor…') }}"
                    aria-label="{{ __('Search activities') }}"
                    class="input pl-9 w-full"
                >
            </div>
            <select wire:model.live="typeFilter" aria-label="{{ __('Filter by activity type') }}" class="select w-full sm:w-64">
                @foreach($this->filterOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @if($search || $typeFilter)
            <button wire:click="$set('search', ''); $set('typeFilter', '')"
                    class="btn-secondary whitespace-nowrap">
                <svg viewBox="0 0 24 24" class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                {{ __('Clear') }}
            </button>
            @endif
        </div>
    </div>
    @endif

    {{-- Timeline card --}}
    <div class="rounded-2xl border border-border bg-surface-2 p-6">
        @if(count($this->groupedActivities) > 0)
            <div class="space-y-8">
                @foreach($this->groupedActivities as $dateLabel => $activities)
                    <div>
                        {{-- Date group header --}}
                        <div class="flex items-center gap-3 mb-4">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted">{{ $dateLabel }}</span>
                            <span class="text-[11px] text-muted/60">·  {{ count($activities) }} {{ __('events') }}</span>
                            <div class="flex-1 border-t border-border/60"></div>
                        </div>

                        {{-- Timeline items --}}
                        <div class="relative pl-8">
                            <div class="absolute left-[15px] top-2 bottom-2 w-px bg-border/60" aria-hidden="true"></div>

                            <div class="space-y-1">
                                @foreach($activities as $activity)
                                    @php
                                        $meta = \App\Livewire\ActivityFeed::getMeta($activity);
                                        $iconSvg = \App\Livewire\ActivityFeed::getIconSvg($meta['icon']);
                                        $description = \App\Livewire\ActivityFeed::humanDescription($activity);
                                        $url = \App\Livewire\ActivityFeed::resourceUrl($activity);
                                        $colorMap = [
                                            'brand' => 'bg-brand/10 text-brand',
                                            'success' => 'bg-success/10 text-success',
                                            'danger' => 'bg-danger/10 text-danger',
                                            'info' => 'bg-info/10 text-info',
                                            'warning' => 'bg-warning/10 text-warning',
                                            'accent' => 'bg-accent/15 text-accent',
                                            'muted' => 'bg-surface text-muted',
                                        ];
                                        $dotColor = [
                                            'brand' => 'bg-brand',
                                            'success' => 'bg-success',
                                            'danger' => 'bg-danger',
                                            'info' => 'bg-info',
                                            'warning' => 'bg-warning',
                                            'accent' => 'bg-accent',
                                            'muted' => 'bg-muted/60',
                                        ];
                                        $iconClasses = $colorMap[$meta['color']] ?? $colorMap['muted'];
                                        $dotClass = $dotColor[$meta['color']] ?? $dotColor['muted'];
                                    @endphp

                                    <div class="relative group">
                                        <div class="absolute -left-8 top-3 flex items-center justify-center" aria-hidden="true">
                                            <div class="w-[9px] h-[9px] rounded-full {{ $dotClass }} ring-[3px] ring-surface-2"></div>
                                        </div>

                                        @if($url)
                                        <a href="{{ $url }}" wire:navigate
                                           class="flex items-start gap-3 rounded-xl px-3 py-2.5 transition-colors hover:bg-surface group-hover:bg-surface">
                                        @else
                                        <div class="flex items-start gap-3 rounded-xl px-3 py-2.5">
                                        @endif
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $iconClasses }}">
                                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconSvg !!}</svg>
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-ink leading-snug">{{ $description }}</p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <time
                                                        datetime="{{ $activity->created_at->toIso8601String() }}"
                                                        class="text-xs text-muted"
                                                        title="{{ $activity->created_at->format('M j, Y g:i A') }}"
                                                    >
                                                        {{ $activity->created_at->diffForHumans() }}
                                                    </time>
                                                    @if($activity->causer)
                                                    <span class="text-xs text-muted/60">·</span>
                                                    <span class="text-xs text-muted">{{ $activity->causer->name }}</span>
                                                    @endif
                                                    @if($url)
                                                        <svg viewBox="0 0 24 24" class="h-3 w-3 text-muted/50 opacity-0 group-hover:opacity-100 transition-opacity ml-auto" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 17l9.2-9.2M17 17V7H7"/></svg>
                                                    @endif
                                                </div>
                                            </div>

                                            @if($activity->causer)
                                            <div class="hidden sm:flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/15 text-brand text-[11px] font-bold" title="{{ $activity->causer->name }}">
                                                {{ strtoupper(substr($activity->causer->name, 0, 1)) }}
                                            </div>
                                            @endif
                                        @if($url)
                                        </a>
                                        @else
                                        </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Load more --}}
            <div class="mt-6 pt-6 border-t border-border/60 text-center">
                <button
                    wire:click="loadMore"
                    wire:loading.attr="disabled"
                    class="btn-secondary"
                >
                    <span wire:loading.remove wire:target="loadMore">{{ __('Load older activity') }}</span>
                    <span wire:loading wire:target="loadMore" class="inline-flex items-center gap-2">
                        <span class="loading-spinner loading-sm"></span>
                        {{ __('Loading…') }}
                    </span>
                </button>
            </div>

        @else
            {{-- Empty state --}}
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand/10 text-brand mb-4">
                    <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-ink mb-1">{{ __('No activity yet') }}</h3>
                <p class="text-sm text-muted max-w-sm">
                    @if($search || $typeFilter)
                        {{ __('No activities match your current filters. Try adjusting your search or filter.') }}
                    @else
                        {{ __('Connect an email account or send your first campaign — events will start streaming in here.') }}
                    @endif
                </p>
                @if($search || $typeFilter)
                    <button wire:click="$set('search', ''); $set('typeFilter', '')" class="btn-secondary mt-4">
                        {{ __('Clear filters') }}
                    </button>
                @else
                    <a href="{{ url('/settings/email') }}" wire:navigate class="btn-primary mt-4">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 mr-1" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        {{ __('Connect Email') }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
