<div class="space-y-6">
    {{-- Header with date range --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Analytics') }}</h1>
            <p class="text-sm text-muted mt-0.5">{{ __('Monitor performance metrics across your workspace') }}</p>
        </div>

        {{-- Date range selector --}}
        <div class="flex items-center gap-2">
            <div class="flex items-center bg-surface-2 border border-border rounded-xl p-1 shadow-sm">
                @foreach(['today' => __('Today'), '7d' => __('7 days'), '30d' => __('30 days'), '90d' => __('90 days')] as $key => $label)
                <button wire:click="$set('dateRange', '{{ $key }}')"
                        class="px-4 py-2 text-xs font-semibold rounded-lg transition-all duration-200 {{ $dateRange === $key ? 'bg-gradient-to-r from-primary-600 to-primary-700 text-white shadow-sm' : 'text-muted  hover:text-ink hover:bg-surface' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex items-center gap-1.5 px-4 py-2.5 text-sm text-muted  bg-surface-2 border border-border rounded-xl hover:bg-surface hover:shadow-sm transition-all duration-200 {{ $dateRange === 'custom' ? 'ring-2 ring-primary-500/20 border-primary-500' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span class="text-xs font-semibold">{{ __('Custom') }}</span>
                </button>
                <div x-show="open" @click.away="open = false"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="absolute right-0 mt-2 w-72 bg-surface-2 rounded-xl shadow-xl border border-border p-5 z-20" style="display: none;">
                    <p class="text-xs font-bold text-ink/80 mb-3">{{ __('Custom Date Range') }}</p>
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs text-muted font-medium">{{ __('From') }}</label>
                            <input type="date" wire:model="customFrom" class="w-full px-3 py-2 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 mt-1 transition-all duration-200">
                        </div>
                        <div>
                            <label class="text-xs text-muted font-medium">{{ __('To') }}</label>
                            <input type="date" wire:model="customTo" class="w-full px-3 py-2 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 mt-1 transition-all duration-200">
                        </div>
                        <button @click="open = false" wire:click="applyCustomRange" class="w-full px-4 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 text-white text-sm font-semibold rounded-xl hover:from-primary-700 hover:to-primary-800 shadow-sm transition-all duration-200">{{ __('Apply') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary stat cards with gradients & sparklines --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        @php
            $summaryCards = [
                ['label' => __('Total Conversations'), 'value' => number_format($totalConversations), 'icon' => 'inbox', 'gradient' => 'from-blue-500/10 to-indigo-500/10', 'icon_bg' => 'bg-blue-500/15', 'icon_color' => 'text-blue-400', 'bar_color' => 'bg-blue-500/30'],
                ['label' => __('New Conversations'), 'value' => number_format($newConversations), 'icon' => 'plus-circle', 'gradient' => 'from-green-500/10 to-emerald-500/10', 'icon_bg' => 'bg-green-500/15', 'icon_color' => 'text-green-400', 'bar_color' => 'bg-green-500/30'],
                ['label' => __('Resolved'), 'value' => number_format($resolvedConversations), 'icon' => 'check-circle', 'gradient' => 'from-emerald-500/10 to-teal-500/10', 'icon_bg' => 'bg-emerald-500/15', 'icon_color' => 'text-emerald-400', 'bar_color' => 'bg-emerald-500/30'],
                ['label' => __('Avg First Response'), 'value' => $avgFirstResponse, 'icon' => 'clock', 'gradient' => 'from-amber-500/10 to-orange-500/10', 'icon_bg' => 'bg-amber-500/15', 'icon_color' => 'text-amber-400', 'bar_color' => 'bg-amber-500/30'],
                ['label' => __('Avg Resolution'), 'value' => $avgResolution, 'icon' => 'trending-down', 'gradient' => 'from-purple-500/10 to-violet-500/10', 'icon_bg' => 'bg-purple-500/15', 'icon_color' => 'text-purple-400', 'bar_color' => 'bg-purple-500/30'],
                ['label' => __('CSAT Score'), 'value' => $csatScore, 'icon' => 'star', 'gradient' => 'from-yellow-500/10 to-amber-500/10', 'icon_bg' => 'bg-yellow-500/15', 'icon_color' => 'text-yellow-400', 'bar_color' => 'bg-yellow-500/30'],
            ];
        @endphp
        @foreach($summaryCards as $stat)
        <div class="bg-gradient-to-br {{ $stat['gradient'] }} rounded-xl border border-border/80 p-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center justify-between mb-3">
                <span class="w-9 h-9 {{ $stat['icon_bg'] }} rounded-lg flex items-center justify-center shadow-sm">
                    @switch($stat['icon'])
                        @case('inbox')
                            <svg class="w-4.5 h-4.5 {{ $stat['icon_color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                            @break
                        @case('plus-circle')
                            <svg class="w-4.5 h-4.5 {{ $stat['icon_color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @break
                        @case('check-circle')
                            <svg class="w-4.5 h-4.5 {{ $stat['icon_color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @break
                        @case('clock')
                            <svg class="w-4.5 h-4.5 {{ $stat['icon_color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @break
                        @case('trending-down')
                            <svg class="w-4.5 h-4.5 {{ $stat['icon_color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                            @break
                        @case('star')
                            <svg class="w-4.5 h-4.5 {{ $stat['icon_color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            @break
                    @endswitch
                </span>
            </div>
            <p class="text-xl font-bold text-ink">{{ $stat['value'] }}</p>
            <p class="text-xs text-muted font-medium mt-0.5">{{ $stat['label'] }}</p>
            {{-- Mini sparkline bars --}}
            <div class="flex items-end gap-0.5 mt-3 h-5">
                @for($i = 0; $i < 8; $i++)
                @php $h = rand(20, 100); @endphp
                <div class="flex-1 {{ $stat['bar_color'] }} rounded-sm transition-all duration-300" style="height: {{ $h }}%"></div>
                @endfor
            </div>
        </div>
        @endforeach
    </div>

    {{-- Charts row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Channel Breakdown --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6 shadow-sm hover:shadow-md transition-all duration-200">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                    </div>
                    <h3 class="text-sm font-bold text-ink">{{ __('Channel Breakdown') }}</h3>
                </div>
            </div>
            @if($channelData->count() > 0)
            <div class="space-y-4">
                @foreach($channelData as $ch)
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full {{ $ch['color'] }} shadow-sm"></span>
                            <span class="text-sm text-ink/80 font-medium">{{ $ch['name'] }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-ink">{{ $ch['pct'] }}%</span>
                            <span class="text-xs text-muted">({{ number_format($ch['count']) }})</span>
                        </div>
                    </div>
                    <div class="w-full h-2.5 bg-surface  rounded-full overflow-hidden">
                        <div class="{{ $ch['color'] }} h-full rounded-full transition-all duration-700 ease-out" style="width: {{ $ch['pct'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="h-44 flex flex-col items-center justify-center">
                <div class="w-12 h-12 bg-surface  rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-muted/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/></svg>
                </div>
                <p class="text-sm text-muted font-medium">{{ __('No data for this period') }}</p>
            </div>
            @endif
        </div>

        {{-- Response by Type --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6 shadow-sm hover:shadow-md transition-all duration-200">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-secondary-100 dark:bg-secondary-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-secondary-600 dark:text-secondary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <h3 class="text-sm font-bold text-ink">{{ __('Messages by Sender Type') }}</h3>
                </div>
            </div>
            @if($senderData->count() > 0)
            <div class="space-y-4">
                @foreach($senderData as $s)
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full {{ $s['color'] }} shadow-sm"></span>
                            <span class="text-sm text-ink/80 font-medium">{{ $s['name'] }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-ink">{{ $s['pct'] }}%</span>
                            <span class="text-xs text-muted">({{ number_format($s['count']) }})</span>
                        </div>
                    </div>
                    <div class="w-full h-2.5 bg-surface  rounded-full overflow-hidden">
                        <div class="{{ $s['color'] }} h-full rounded-full transition-all duration-700 ease-out" style="width: {{ $s['pct'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="h-44 flex flex-col items-center justify-center">
                <div class="w-12 h-12 bg-surface  rounded-xl flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-muted/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <p class="text-sm text-muted font-medium">{{ __('No data for this period') }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Recent Activity --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6 shadow-sm">
        <div class="flex items-center gap-2.5 mb-5">
            <div class="w-8 h-8 bg-surface  rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-muted " fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-sm font-bold text-ink">{{ __('Recent Activity') }}</h3>
        </div>
        @if($recentActivity->count() > 0)
        <div class="space-y-1">
            @foreach($recentActivity as $activity)
            @php
                $activityIcon = match(true) {
                    str_contains(strtolower($activity['event'] ?? ''), 'resolved') || str_contains(strtolower($activity['event'] ?? ''), 'closed') => ['bg' => 'bg-success/15', 'color' => 'text-success', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                    str_contains(strtolower($activity['event'] ?? ''), 'new') || str_contains(strtolower($activity['event'] ?? ''), 'created') => ['bg' => 'bg-info/15', 'color' => 'text-blue-600', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                    str_contains(strtolower($activity['event'] ?? ''), 'ai') || str_contains(strtolower($activity['event'] ?? ''), 'auto') => ['bg' => 'bg-brand/15', 'color' => 'text-purple-600', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>'],
                    str_contains(strtolower($activity['event'] ?? ''), 'assign') => ['bg' => 'bg-warning/15', 'color' => 'text-amber-600', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
                    default => ['bg' => 'bg-surface ', 'color' => 'text-muted', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                };
            @endphp
            <div class="flex items-start gap-3 px-3 py-3 rounded-xl hover:bg-surface transition-all duration-200 group">
                <div class="mt-0.5 flex-shrink-0">
                    <span class="w-8 h-8 {{ $activityIcon['bg'] }} rounded-lg flex items-center justify-center shadow-sm">
                        <svg class="w-4 h-4 {{ $activityIcon['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">@safeSvg($activityIcon['icon'])</svg>
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-ink/80 leading-relaxed">
                        <span class="font-semibold">{{ $activity['actor'] }}</span>
                        {{ $activity['event'] }}
                        @if($activity['type'])
                        <span class="text-muted">{{ __('on') }} {{ $activity['type'] }}</span>
                        @endif
                    </p>
                    <p class="text-xs text-muted mt-0.5 font-medium">{{ $activity['time'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-10">
            <div class="w-14 h-14 bg-surface  rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-muted/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm text-muted font-medium">{{ __('No recent activity found.') }}</p>
        </div>
        @endif
    </div>
</div>
