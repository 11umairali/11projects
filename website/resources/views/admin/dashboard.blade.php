<x-layouts.admin :title="__('Dashboard')" :subtitle="__('Platform overview and key metrics')">
    <div class="space-y-6">
        @if(auth()->user()->isSuperAdmin())
            <a href="{{ route('admin.frontend-settings.index') }}#account-features" class="btn-secondary inline-flex">
                {{ __('Manage account features') }}
            </a>
        @endif

        {{-- ═══════════════════════════════════════════════
             STAT CARDS — ROW 1
             ═══════════════════════════════════════════════ --}}
        @php
            $statCards = [
                ['label' => __('Total Users'), 'value' => number_format($totalUsers), 'sub' => number_format($activeUsers) . ' ' . __('Active'), 'badge' => ($userGrowth > 0 ? '+' : '') . $userGrowth . '%', 'badgeClass' => $userGrowth >= 0 ? 'badge-success' : 'badge-error', 'icon' => '<path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50 dark:bg-blue-900/20'],
                ['label' => __('Workspaces'), 'value' => number_format($totalWorkspaces ?? 0), 'sub' => __('Across all users'), 'badge' => null, 'icon' => '<path d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>', 'color' => 'text-purple-600', 'bg' => 'bg-purple-50 dark:bg-purple-900/20'],
                ['label' => __('Active Subscriptions'), 'value' => number_format($payingUsers), 'sub' => number_format($totalUsers > 0 ? ($payingUsers / $totalUsers) * 100 : 0, 1) . '% ' . __('conversion'), 'badge' => __('Active'), 'badgeClass' => 'badge-primary', 'icon' => '<path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50 dark:bg-emerald-900/20'],
                ['label' => __('Open Tickets'), 'value' => number_format($openTicketsCount), 'sub' => __('Support queue'), 'badge' => $openTicketsCount > 0 ? $openTicketsCount . ' ' . __('Open') : __('Clear'), 'badgeClass' => $openTicketsCount > 0 ? 'badge-warning' : 'badge-success', 'icon' => '<path d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"/>', 'color' => 'text-amber-600', 'bg' => 'bg-amber-50 dark:bg-amber-900/20'],
                ['label' => __('Total Revenue'), 'value' => \App\Helpers\CurrencyHelper::display($mrr * 12), 'sub' => __('All time'), 'badge' => null, 'icon' => '<path d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>', 'color' => 'text-green-600', 'bg' => 'bg-green-50 dark:bg-green-900/20'],
                ['label' => __('This Month'), 'value' => \App\Helpers\CurrencyHelper::display($revenueThisMonth), 'sub' => __('Last') . ': ' . \App\Helpers\CurrencyHelper::display($revenueLastMonth), 'badge' => ($revenueGrowth > 0 ? '+' : '') . $revenueGrowth . '%', 'badgeClass' => $revenueGrowth >= 0 ? 'badge-success' : 'badge-error', 'icon' => '<path d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/>', 'color' => 'text-indigo-600', 'bg' => 'bg-indigo-50 dark:bg-indigo-900/20'],
                ['label' => __('Payments'), 'value' => number_format($recentPayments->count()), 'sub' => __('Processed'), 'badge' => null, 'icon' => '<path d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.745 3.745 0 011.043 3.296A3.745 3.745 0 0121 12z"/>', 'color' => 'text-cyan-600', 'bg' => 'bg-cyan-50 dark:bg-cyan-900/20'],
                ['label' => __('AI Replies'), 'value' => number_format($aiUsageThisMonth->total_requests ?? 0), 'sub' => \App\Helpers\CurrencyHelper::display($aiUsageThisMonth->total_cost ?? 0) . ' ' . __('cost'), 'badge' => 'MTD', 'badgeClass' => 'badge-primary', 'icon' => '<path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>', 'color' => 'text-violet-600', 'bg' => 'bg-violet-50 dark:bg-violet-900/20'],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
            @foreach(array_slice($statCards, 0, 4) as $card)
            <div class="rounded-2xl border border-border/50 bg-surface-2 p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $card['bg'] }}">
                        <svg class="h-5 w-5 {{ $card['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $card['icon'] !!}</svg>
                    </div>
                    @if($card['badge'] ?? null)
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ 
                        str_contains($card['badgeClass'] ?? '', 'success') ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10' : 
                        (str_contains($card['badgeClass'] ?? '', 'error') ? 'text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-500/10' :
                        (str_contains($card['badgeClass'] ?? '', 'warning') ? 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10' :
                        'text-brand-600 dark:text-brand-400 bg-brand/10')) 
                    }}">
                        {{ $card['badge'] }}
                    </span>
                    @endif
                </div>
                <p class="mt-4 text-2xl font-bold text-ink tracking-tight">{{ $card['value'] }}</p>
                <p class="mt-1 text-sm text-muted">{{ $card['label'] }} &middot; {{ $card['sub'] }}</p>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
            @foreach(array_slice($statCards, 4) as $card)
            <div class="rounded-2xl border border-border/50 bg-surface-2 p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $card['bg'] }}">
                        <svg class="h-5 w-5 {{ $card['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $card['icon'] !!}</svg>
                    </div>
                    @if($card['badge'] ?? null)
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ 
                        str_contains($card['badgeClass'] ?? '', 'success') ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10' : 
                        (str_contains($card['badgeClass'] ?? '', 'error') ? 'text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-500/10' :
                        (str_contains($card['badgeClass'] ?? '', 'warning') ? 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10' :
                        'text-brand-600 dark:text-brand-400 bg-brand/10')) 
                    }}">
                        {{ $card['badge'] }}
                    </span>
                    @endif
                </div>
                <p class="mt-4 text-2xl font-bold text-ink tracking-tight">{{ $card['value'] }}</p>
                <p class="mt-1 text-sm text-muted">{{ $card['label'] }} &middot; {{ $card['sub'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════
             CHARTS
             ═══════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">

            {{-- Revenue Area Chart --}}
            <div class="lg:col-span-3 rounded-2xl border border-border/50 bg-surface-2 p-6 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-base font-semibold text-ink">{{ __('Revenue Trend') }}</h2>
                        <p class="text-sm text-muted mt-0.5">{{ __('Last 6 months performance') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold text-ink tracking-tight">@currency($arr)</p>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-muted">{{ __('Projected ARR') }}</p>
                    </div>
                </div>
                <div class="w-full relative min-h-[280px] flex-1">
                    <canvas id="revenueChart"
                        data-labels='@json(collect($monthlyRevenue)->pluck("month"))'
                        data-values='@json(collect($monthlyRevenue)->pluck("revenue"))'>
                    </canvas>
                </div>
            </div>

            {{-- Plan Distribution Doughnut --}}
            <div class="lg:col-span-2 rounded-2xl border border-border/50 bg-surface-2 p-6 shadow-sm flex flex-col">
                <div class="mb-6">
                    <h2 class="text-base font-semibold text-ink">{{ __('Plan Distribution') }}</h2>
                    <p class="text-sm text-muted mt-0.5">{{ __('Active subscription breakdown') }}</p>
                </div>
                @if($planDistribution->isEmpty())
                    <div class="flex-1 flex items-center justify-center">
                        <p class="text-sm text-muted text-center">{{ __('No active subscriptions.') }}</p>
                    </div>
                @else
                    <div class="relative w-full flex justify-center mb-5 flex-1 min-h-[220px]">
                        <canvas id="planDoughnutChart"
                            data-labels='@json($planDistribution->keys())'
                            data-values='@json($planDistribution->values())'>
                        </canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-3xl font-bold text-ink tracking-tight">{{ number_format($planDistribution->sum()) }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-muted">{{ __('Active') }}</span>
                        </div>
                    </div>

                    {{-- Plan breakdown --}}
                    <div class="mt-auto flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-muted">
                        @php
                            $planColorsMap = [
                                'Free' => 'bg-gray-400',
                                'Starter' => 'bg-blue-500',
                                'Pro' => 'bg-purple-500',
                                'Enterprise' => 'bg-amber-500',
                            ];
                        @endphp
                        @foreach($planDistribution as $plan => $count)
                            @php $c = $planColorsMap[$plan] ?? 'bg-brand'; @endphp
                            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full {{ $c }}"></span> {{ $plan }} ({{ $count }})</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
             RECENT USERS TABLE
             ═══════════════════════════════════════════════ --}}
        <div class="rounded-2xl border border-border/50 bg-surface-2 shadow-sm mb-8 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-5 border-b border-border/40">
                <h2 class="text-base font-semibold text-ink">{{ __('Recent Users') }}</h2>
                <a href="{{ url('/admin/users') }}" wire:navigate class="text-sm font-medium text-brand hover:text-brand-strong transition">{{ __('View All') }}</a>
            </div>
            <div class="overflow-x-auto">
                @if($recentUsers->isEmpty())
                    <div class="px-6 py-12 text-center">
                        <svg viewBox="0 0 24 24" class="h-10 w-10 text-muted/30 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="8" r="3"></circle><path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"></path></svg>
                        <p class="text-sm text-muted">{{ __('No users found.') }}</p>
                    </div>
                @else
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-xs uppercase tracking-widest text-muted border-b border-border/40">
                            <th class="px-6 py-4 font-semibold bg-surface-2">{{ __('User') }}</th>
                            <th class="px-6 py-4 font-semibold bg-surface-2">{{ __('Plan') }}</th>
                            <th class="px-6 py-4 font-semibold bg-surface-2">{{ __('Status') }}</th>
                            <th class="px-6 py-4 font-semibold bg-surface-2">{{ __('Joined') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        @php
                            $avatarColors = ['#6366f1','#8b5cf6','#ec4899','#f43f5e','#f97316','#eab308','#22c55e','#14b8a6','#06b6d4','#3b82f6','#6d28d9','#db2777'];
                        @endphp
                        @foreach($recentUsers->take(8) as $user)
                        @php 
                            $color = $avatarColors[crc32($user->name) % count($avatarColors)];
                            $planBadge = match($user->currentPlan->name ?? 'Free') {
                                'Free' => 'bg-gray-100 text-gray-600 dark:bg-gray-700/40 dark:text-gray-300',
                                'Starter' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
                                'Pro' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400',
                                'Enterprise' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                                default => 'bg-gray-100 text-gray-600 dark:bg-gray-700/40 dark:text-gray-300',
                            };
                            $statusDot = match($user->status) {
                                'active' => 'bg-emerald-500',
                                'suspended' => 'bg-red-500',
                                'pending_deletion' => 'bg-amber-500',
                                default => 'bg-gray-500',
                            };
                        @endphp
                        <tr class="hover:bg-surface-1 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white shadow-inner" style="background-color: {{ $color }}">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}{{ strtoupper(substr(strstr($user->name . ' ', ' '), 1, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ url('/admin/users/' . $user->id) }}" wire:navigate class="text-sm font-medium text-ink hover:text-brand transition-colors block truncate">{{ $user->name }}</a>
                                        <p class="text-xs text-muted truncate">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>   
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $planBadge }}">
                                    {{ $user->currentPlan->name ?? 'Free' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 text-sm text-ink capitalize">
                                    <span class="h-2 w-2 rounded-full {{ $statusDot }}"></span>
                                    {{ $user->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-muted whitespace-nowrap">{{ \Carbon\Carbon::parse($user->created_at)->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
             RECENT PAYMENTS TABLE
             ═══════════════════════════════════════════════ --}}
        <div class="rounded-2xl border border-border/50 bg-surface-2 shadow-sm mb-8 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-5 border-b border-border/40">
                <h2 class="text-base font-semibold text-ink">{{ __('Recent Payments') }}</h2>
                <a href="{{ url('/admin/payments') }}" wire:navigate class="text-sm font-medium text-brand hover:text-brand-strong transition">{{ __('Show More') }}</a>
            </div>
            <div class="overflow-x-auto">
                @if($recentPayments->isEmpty())
                    <div class="px-6 py-12 text-center">
                        <svg viewBox="0 0 24 24" class="h-10 w-10 text-muted/30 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        <p class="text-sm text-muted">{{ __('No payments recorded.') }}</p>
                    </div>
                @else
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-xs uppercase tracking-widest text-muted border-b border-border/40">
                            <th class="px-6 py-4 font-semibold bg-surface-2">{{ __('Invoice') }}</th>
                            <th class="px-6 py-4 font-semibold bg-surface-2">{{ __('User') }}</th>
                            <th class="px-6 py-4 font-semibold bg-surface-2">{{ __('Amount') }}</th>
                            <th class="px-6 py-4 font-semibold bg-surface-2">{{ __('Status') }}</th>
                            <th class="px-6 py-4 font-semibold bg-surface-2">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        @foreach($recentPayments->take(6) as $payment)
                        @php
                            $paymentBadge = match($payment->status) {
                                'succeeded' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
                                'failed' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
                                'pending' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                                'refunded' => 'bg-gray-100 text-gray-600 dark:bg-gray-700/40 dark:text-gray-300',
                                default => 'bg-gray-100 text-gray-600 dark:bg-gray-700/40 dark:text-gray-300',
                            };
                        @endphp
                        <tr class="hover:bg-surface-1 transition-colors">
                            <td class="px-6 py-4">
                                <span class="text-sm font-mono font-medium text-ink">#{{ $payment->id }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-ink font-medium">{{ $payment->workspace?->name ?? __('Deleted Workspace') }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-semibold text-ink">@currency($payment->amount)</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $paymentBadge }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-muted whitespace-nowrap">{{ \Carbon\Carbon::parse($payment->created_at)->format('M j, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
             ACTION CARDS — System Health, Pending Tickets, Updates
             ═══════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            {{-- System Health --}}
            <div class="rounded-2xl border border-border/50 bg-surface-2 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ ($systemSnapshot['failed_jobs'] ?? 0) == 0 ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-red-50 dark:bg-red-500/10' }}">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 {{ ($systemSnapshot['failed_jobs'] ?? 0) == 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-ink">{{ __('System Health') }}</h3>
                            <span class="relative flex h-2.5 w-2.5">
                                @if(($systemSnapshot['failed_jobs'] ?? 0) == 0)
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                @else
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                @endif
                            </span>
                        </div>
                        <p class="text-xs text-muted mt-1">{{ ($systemSnapshot['failed_jobs'] ?? 0) == 0 ? __('All systems operational') : __(':count failed jobs detected', ['count' => $systemSnapshot['failed_jobs'] ?? 0]) }}</p>
                    </div>
                </div>
                <a href="{{ url('/admin/system') }}" wire:navigate class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-strong transition">
                    {{ __('View Status') }}
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                </a>
            </div>

            {{-- Pending Tickets --}}
            <div class="rounded-2xl border border-border/50 bg-surface-2 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/10">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-ink">{{ __('Pending Tickets') }}</h3>
                            <span class="inline-flex items-center justify-center h-5 min-w-5 rounded-full bg-amber-100 dark:bg-amber-500/20 px-1.5 text-[10px] font-bold text-amber-700 dark:text-amber-400">{{ $openTicketsCount ?? 0 }}</span>
                        </div>
                        <p class="text-xs text-muted mt-1">{{ __('Support tickets awaiting response') }}</p>
                    </div>
                </div>
                <a href="{{ url('/admin/tickets') }}" wire:navigate class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-strong transition">
                    {{ __('Review Tickets') }}
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                </a>
            </div>

            {{-- App Info / Updates --}}
            <div class="rounded-2xl border border-border/50 bg-surface-2 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/10">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-ink">{{ __('App Version') }}</h3>
                            <span class="inline-flex items-center justify-center rounded-full bg-blue-50 dark:bg-blue-500/20 px-2 py-0.5 text-[10px] font-bold text-blue-600 dark:text-blue-400">v{{ app()->version() }}</span>
                        </div>
                        <p class="text-xs text-muted mt-1">{{ __('SaaS Core System Version') }}</p>
                    </div>
                </div>
                <a href="{{ url('/admin/system') }}" wire:navigate class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-strong transition">
                    {{ __('View Details') }}
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════
         CHART.JS INITIALIZATION
         ═══════════════════════════════════════════════ --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        initCharts();
    });

    function initCharts() {
        if (typeof Chart === 'undefined') {
            setTimeout(initCharts, 100);
            return;
        }

        Chart.helpers.each(Chart.instances, function (instance) {
            if (instance.canvas.id === 'revenueChart' || instance.canvas.id === 'planDoughnutChart') {
                instance.destroy();
            }
        });

        const brandColor = getComputedStyle(document.documentElement).getPropertyValue('--color-brand').trim() || '#6366f1';
        const isDark = document.documentElement.classList.contains('dark');
        const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)';
        const textColor = isDark ? '#9CA3AF' : '#9CA3AF';

        const coreOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark ? '#1a1d27' : '#fff',
                    titleColor: isDark ? '#e5e7eb' : '#1E293B',
                    bodyColor: isDark ? '#9CA3AF' : '#64748b',
                    borderColor: isDark ? '#2d3039' : '#e5e7eb',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 12,
                    displayColors: true,
                    boxPadding: 4,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: textColor, font: { size: 11, family: "'Outfit', sans-serif" } },
                    border: { display: false }
                },
                y: {
                    grid: { color: gridColor, drawBorder: false },
                    ticks: { color: textColor, font: { size: 11, family: "'Outfit', sans-serif" } },
                    border: { display: false },
                    beginAtZero: true
                },
            },
        };

        // Revenue Area Chart
        const revCanvas = document.getElementById('revenueChart');
        if (revCanvas) {
            const ctx = revCanvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 280);
            gradient.addColorStop(0, isDark ? 'rgba(99,102,241,0.25)' : 'rgba(99,102,241,0.15)');
            gradient.addColorStop(1, isDark ? 'rgba(99,102,241,0)' : 'rgba(99,102,241,0)');

            new Chart(revCanvas, {
                type: 'line',
                data: {
                    labels: JSON.parse(revCanvas.dataset.labels),
                    datasets: [{
                        label: '{{ __("Revenue") }}',
                        data: JSON.parse(revCanvas.dataset.values),
                        borderColor: brandColor,
                        backgroundColor: gradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: brandColor,
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                    }],
                },
                options: {
                    ...coreOptions,
                    scales: {
                        ...coreOptions.scales,
                        y: { ...coreOptions.scales.y, ticks: { ...coreOptions.scales.y.ticks, callback: (v) => '$' + v.toLocaleString() } },
                    },
                    plugins: {
                        ...coreOptions.plugins,
                        tooltip: { ...coreOptions.plugins.tooltip, callbacks: { label: (ctx) => 'Revenue: $' + ctx.parsed.y.toLocaleString() } },
                    },
                },
            });
        }

        // Plan Doughnut
        const planCanvas = document.getElementById('planDoughnutChart');
        if (planCanvas) {
            new Chart(planCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: JSON.parse(planCanvas.dataset.labels),
                    datasets: [{
                        data: JSON.parse(planCanvas.dataset.values),
                        backgroundColor: [
                            isDark ? '#6b7280' : '#9ca3af',
                            isDark ? '#60a5fa' : '#3b82f6',
                            isDark ? '#a78bfa' : '#8b5cf6',
                            isDark ? '#fbbf24' : '#f59e0b',
                        ],
                        borderColor: isDark ? '#1a1d27' : '#ffffff',
                        borderWidth: 3,
                        hoverOffset: 6
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: coreOptions.plugins.tooltip
                    }
                }
            });
        }
    }

    initCharts();
    if (typeof Livewire !== 'undefined') {
        Livewire.hook('morph.updated', () => setTimeout(initCharts, 100));
    }
    document.addEventListener('livewire:navigated', () => setTimeout(initCharts, 100));
    </script>
</x-layouts.admin>
