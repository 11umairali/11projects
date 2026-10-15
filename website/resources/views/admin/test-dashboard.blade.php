<x-layouts.admin :title="__('Test Dashboard')" :subtitle="__('Hello, Super Admin') . ' — ' . now()->format('l, F j, Y')">

{{-- ════════════════════════════════════════════════════════════════
     DATE RANGE SELECTOR
     ════════════════════════════════════════════════════════════════ --}}
<div class="flex items-end justify-end mb-8">
    <div>

    {{-- Date Range Selector --}}
    <div class="mt-4 sm:mt-0" x-data="{ open: false, selected: 'Last 30 Days' }" x-on:click.outside="open = false">
        <div class="relative">
            <button
                x-on:click="open = !open"
                class="inline-flex items-center gap-2 rounded-xl border border-border bg-surface-2 px-4 py-2.5 text-sm font-medium text-ink shadow-sm transition hover:border-brand/40"
                :aria-expanded="open"
            >
                <svg viewBox="0 0 24 24" class="h-4 w-4 text-muted" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span x-text="selected"></span>
                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-muted" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="absolute right-0 mt-2 w-48 rounded-xl border border-border/70 bg-surface-2 p-1.5 shadow-soft z-50"
                style="display: none;"
            >
                @foreach(['Last 7 Days', 'Last 30 Days', 'This Month', 'This Year'] as $range)
                <button
                    x-on:click="selected = '{{ $range }}'; open = false"
                    class="flex w-full items-center rounded-lg px-3 py-2 text-sm text-ink transition hover:bg-brand/5"
                    :class="{ 'bg-brand/10 font-medium': selected === '{{ $range }}' }"
                >{{ $range }}</button>
                @endforeach
            </div>
        </div>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════════
     STAT CARDS — 4 across
     ════════════════════════════════════════════════════════════════ --}}
@php
$stats = [
    [
        'label' => __('Revenue'),
        'value' => '$12,853.65',
        'change' => '12.5%',
        'up' => true,
        'iconBg' => 'bg-blue-50 dark:bg-blue-500/10',
        'iconColor' => 'text-blue-600 dark:text-blue-400',
        'icon' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    ],
    [
        'label' => __('Total Users'),
        'value' => '1,248',
        'change' => '8',
        'up' => true,
        'iconBg' => 'bg-teal-50 dark:bg-teal-500/10',
        'iconColor' => 'text-teal-600 dark:text-teal-400',
        'icon' => '<circle cx="9" cy="8" r="3"/><path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"/><path d="M15.5 11a3 3 0 1 0 0-6"/><path d="M18 19c.6-1.1 1.6-2.2 3-3"/>',
    ],
    [
        'label' => __('Active Plans'),
        'value' => '342',
        'change' => '3',
        'up' => false,
        'iconBg' => 'bg-amber-50 dark:bg-amber-500/10',
        'iconColor' => 'text-amber-600 dark:text-amber-400',
        'icon' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01"/><path d="M17 12h.01"/><path d="M7 12h.01"/>',
    ],
    [
        'label' => __('AI Replies'),
        'value' => '24.5K',
        'change' => '1.2K',
        'up' => true,
        'iconBg' => 'bg-purple-50 dark:bg-purple-500/10',
        'iconColor' => 'text-purple-600 dark:text-purple-400',
        'icon' => '<path d="M12 2a7 7 0 0 1 7 7c0 2.4-1.2 4.5-3 5.7V17a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2.3C6.2 13.5 5 11.4 5 9a7 7 0 0 1 7-7z"/><path d="M9 22h6"/>',
    ],
];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8 animate-stagger">
    @foreach($stats as $stat)
    <div class="rounded-2xl border border-border/50 bg-surface-2 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $stat['iconBg'] }}">
                <svg viewBox="0 0 24 24" class="h-5 w-5 {{ $stat['iconColor'] }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $stat['icon'] !!}</svg>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $stat['up'] ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10' : 'text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-500/10' }}">
                @if($stat['up'])
                <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 17l5-5 5 5"/><path d="M12 12V4" stroke="none"/></svg>
                @else
                <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 7l5 5 5-5"/></svg>
                @endif
                {{ $stat['up'] ? '+' : '-' }}{{ $stat['change'] }}
            </span>
        </div>
        <p class="mt-4 text-2xl font-bold text-ink">{{ $stat['value'] }}</p>
        <p class="mt-1 text-sm text-muted">{{ $stat['label'] }}</p>
    </div>
    @endforeach
</div>


{{-- ════════════════════════════════════════════════════════════════
     CHARTS — Revenue Overview (60%) + Plan Distribution (40%)
     ════════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">

    {{-- Revenue Area Chart --}}
    <div class="lg:col-span-3 rounded-2xl border border-border/50 bg-surface-2 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-base font-semibold text-ink">{{ __('Revenue Overview') }}</h2>
            <div class="flex items-center gap-4 text-xs text-muted">
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-blue-500"></span> {{ __('Revenue') }}</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-teal-400"></span> {{ __('Expenses') }}</span>
            </div>
        </div>
        <div class="relative" style="height: 280px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    {{-- Plan Distribution Doughnut --}}
    <div class="lg:col-span-2 rounded-2xl border border-border/50 bg-surface-2 p-6 shadow-sm">
        <h2 class="text-base font-semibold text-ink mb-6">{{ __('Plan Distribution') }}</h2>
        <div class="relative flex items-center justify-center" style="height: 220px;">
            <canvas id="planChart"></canvas>
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <span class="text-2xl font-bold text-ink">1,248</span>
                <span class="text-xs text-muted">{{ __('Total Users') }}</span>
            </div>
        </div>
        <div class="mt-5 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-muted">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span> Free (420)</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span> Starter (380)</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-purple-500"></span> Pro (310)</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Enterprise (138)</span>
        </div>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════════
     TABLE — Recent Users
     ════════════════════════════════════════════════════════════════ --}}
@php
$users = [
    ['name' => 'Sarah Chen', 'email' => 'sarah.chen@acme.io', 'plan' => 'Enterprise', 'status' => 'Active', 'joined' => '2 hours ago'],
    ['name' => 'Marcus Johnson', 'email' => 'marcus@startup.co', 'plan' => 'Pro', 'status' => 'Active', 'joined' => '5 hours ago'],
    ['name' => 'Aisha Patel', 'email' => 'aisha.p@globex.com', 'plan' => 'Starter', 'status' => 'Pending', 'joined' => '1 day ago'],
    ['name' => 'Tom Eriksen', 'email' => 'tom@nordic.dev', 'plan' => 'Pro', 'status' => 'Active', 'joined' => '2 days ago'],
    ['name' => 'Lucia Fernandez', 'email' => 'lucia@designhub.es', 'plan' => 'Free', 'status' => 'Active', 'joined' => '3 days ago'],
    ['name' => 'Wei Zhang', 'email' => 'wei.z@techcorp.cn', 'plan' => 'Enterprise', 'status' => 'Active', 'joined' => '4 days ago'],
    ['name' => 'Olga Novak', 'email' => 'olga@cloudware.cz', 'plan' => 'Starter', 'status' => 'Suspended', 'joined' => '5 days ago'],
    ['name' => 'James Obi', 'email' => 'james.obi@nexgen.ng', 'plan' => 'Pro', 'status' => 'Active', 'joined' => '1 week ago'],
];

$planBadge = [
    'Free' => 'bg-gray-100 text-gray-600 dark:bg-gray-700/40 dark:text-gray-300',
    'Starter' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
    'Pro' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400',
    'Enterprise' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
];

$statusDot = [
    'Active' => 'bg-emerald-500',
    'Suspended' => 'bg-red-500',
    'Pending' => 'bg-amber-500',
];

$avatarColors = ['#6366f1','#8b5cf6','#ec4899','#f43f5e','#f97316','#eab308','#22c55e','#14b8a6','#06b6d4','#3b82f6','#6d28d9','#db2777'];
@endphp

<div class="rounded-2xl border border-border/50 bg-surface-2 shadow-sm mb-8 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-5 border-b border-border/40">
        <h2 class="text-base font-semibold text-ink">{{ __('Recent Users') }}</h2>
        <a href="{{ url('/admin/users') }}" wire:navigate class="text-sm font-medium text-brand hover:text-brand-strong transition">{{ __('View All') }}</a>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th class="pl-6">{{ __('User') }}</th>
                    <th>{{ __('Plan') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="pr-6">{{ __('Joined') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $i => $user)
                @php $color = $avatarColors[crc32($user['name']) % count($avatarColors)]; @endphp
                <tr>
                    <td class="pl-6">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" style="background-color: {{ $color }}">
                                {{ strtoupper(substr($user['name'], 0, 1)) }}{{ strtoupper(substr(strstr($user['name'], ' '), 1, 1)) }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-ink truncate">{{ $user['name'] }}</p>
                                <p class="text-xs text-muted truncate">{{ $user['email'] }}</p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $planBadge[$user['plan']] }}">
                            {{ $user['plan'] }}
                        </span>
                    </td>
                    <td>
                        <span class="inline-flex items-center gap-1.5 text-sm text-ink">
                            <span class="h-2 w-2 rounded-full {{ $statusDot[$user['status']] }}"></span>
                            {{ $user['status'] }}
                        </span>
                    </td>
                    <td class="pr-6 text-sm text-muted whitespace-nowrap">{{ $user['joined'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════════
     TABLE — Recent Payments
     ════════════════════════════════════════════════════════════════ --}}
@php
$payments = [
    ['invoice' => 'INV-2024-0847', 'email' => 'sarah.chen@acme.io', 'amount' => '$299.00', 'status' => 'Succeeded', 'date' => 'Mar 22, 2026'],
    ['invoice' => 'INV-2024-0846', 'email' => 'marcus@startup.co', 'amount' => '$49.00', 'status' => 'Succeeded', 'date' => 'Mar 21, 2026'],
    ['invoice' => 'INV-2024-0845', 'email' => 'tom@nordic.dev', 'amount' => '$49.00', 'status' => 'Pending', 'date' => 'Mar 21, 2026'],
    ['invoice' => 'INV-2024-0844', 'email' => 'olga@cloudware.cz', 'amount' => '$19.00', 'status' => 'Failed', 'date' => 'Mar 20, 2026'],
    ['invoice' => 'INV-2024-0843', 'email' => 'lucia@designhub.es', 'amount' => '$49.00', 'status' => 'Refunded', 'date' => 'Mar 19, 2026'],
    ['invoice' => 'INV-2024-0842', 'email' => 'wei.z@techcorp.cn', 'amount' => '$299.00', 'status' => 'Succeeded', 'date' => 'Mar 18, 2026'],
];

$paymentBadge = [
    'Succeeded' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
    'Failed' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
    'Pending' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
    'Refunded' => 'bg-gray-100 text-gray-600 dark:bg-gray-700/40 dark:text-gray-300',
];
@endphp

<div class="rounded-2xl border border-border/50 bg-surface-2 shadow-sm mb-8 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-5 border-b border-border/40">
        <h2 class="text-base font-semibold text-ink">{{ __('Recent Payments') }}</h2>
        <a href="{{ url('/admin/payments') }}" wire:navigate class="text-sm font-medium text-brand hover:text-brand-strong transition">{{ __('Show More') }}</a>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th class="pl-6">{{ __('Invoice') }}</th>
                    <th>{{ __('User Email') }}</th>
                    <th>{{ __('Amount') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="pr-6">{{ __('Date') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $payment)
                <tr>
                    <td class="pl-6">
                        <span class="text-sm font-mono font-medium text-ink">{{ $payment['invoice'] }}</span>
                    </td>
                    <td class="text-sm text-muted">{{ $payment['email'] }}</td>
                    <td class="text-sm font-semibold text-ink">{{ $payment['amount'] }}</td>
                    <td>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $paymentBadge[$payment['status']] }}">
                            {{ $payment['status'] }}
                        </span>
                    </td>
                    <td class="pr-6 text-sm text-muted whitespace-nowrap">{{ $payment['date'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════════
     STATS FOOTER — Weekly / Monthly / Yearly Users
     ════════════════════════════════════════════════════════════════ --}}
@php
$footerStats = [
    [
        'title' => __('Weekly Users'),
        'value' => '482',
        'change' => '+12%',
        'previous' => '430 prev. week',
        'up' => true,
        'iconBg' => 'bg-blue-50 dark:bg-blue-500/10',
        'iconColor' => 'text-blue-600 dark:text-blue-400',
        'icon' => '<circle cx="9" cy="8" r="3"/><path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"/>',
    ],
    [
        'title' => __('Monthly Users'),
        'value' => '1,847',
        'change' => '+8.3%',
        'previous' => '1,705 prev. month',
        'up' => true,
        'iconBg' => 'bg-teal-50 dark:bg-teal-500/10',
        'iconColor' => 'text-teal-600 dark:text-teal-400',
        'icon' => '<path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/>',
    ],
    [
        'title' => __('Yearly Users'),
        'value' => '8,142',
        'change' => '-2.1%',
        'previous' => '8,316 prev. year',
        'up' => false,
        'iconBg' => 'bg-rose-50 dark:bg-rose-500/10',
        'iconColor' => 'text-rose-600 dark:text-rose-400',
        'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    ],
];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
    @foreach($footerStats as $fs)
    <div class="rounded-2xl border border-border/50 bg-surface-2 p-5 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $fs['iconBg'] }}">
                <svg viewBox="0 0 24 24" class="h-5 w-5 {{ $fs['iconColor'] }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $fs['icon'] !!}</svg>
            </div>
            <span class="text-sm font-medium text-muted">{{ $fs['title'] }}</span>
        </div>
        <div class="flex items-end gap-3">
            <span class="text-2xl font-bold text-ink">{{ $fs['value'] }}</span>
            <span class="inline-flex items-center gap-1 text-xs font-medium {{ $fs['up'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }} mb-0.5">
                @if($fs['up'])
                <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 17l5-5 5 5"/></svg>
                @else
                <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 7l5 5 5-5"/></svg>
                @endif
                {{ $fs['change'] }}
            </span>
        </div>
        <p class="text-xs text-muted mt-1">{{ $fs['previous'] }}</p>
    </div>
    @endforeach
</div>


{{-- ════════════════════════════════════════════════════════════════
     FORM SECTION — Quick Settings (form components showcase)
     ════════════════════════════════════════════════════════════════ --}}
<div class="rounded-2xl border border-border/50 bg-surface-2 p-6 shadow-sm mb-8" x-data="{ autoReply: true, maintenanceMode: false, selectedPlan: 'pro', notifications: ['email', 'slack'] }">
    <h2 class="text-base font-semibold text-ink mb-6">{{ __('Quick Settings') }}</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">

        {{-- Text Input --}}
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('App Name') }}</label>
            <input type="text" class="input-field" value="{{ config('app.name') }}" placeholder="{{ __('Enter app name') }}">
        </div>

        {{-- Select Dropdown --}}
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Default Plan') }}</label>
            <select class="select" x-model="selectedPlan">
                <option value="free">Free</option>
                <option value="starter">Starter</option>
                <option value="pro">Pro</option>
                <option value="enterprise">Enterprise</option>
            </select>
        </div>

        {{-- Toggle Switch — Auto Reply --}}
        <div class="flex items-center justify-between rounded-xl border border-border/40 bg-surface/60 px-4 py-3">
            <div>
                <p class="text-sm font-medium text-ink">{{ __('Auto Reply') }}</p>
                <p class="text-xs text-muted">{{ __('Enable AI-powered auto responses') }}</p>
            </div>
            <button
                type="button"
                role="switch"
                :aria-checked="autoReply.toString()"
                x-on:click="autoReply = !autoReply"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
                :class="autoReply ? 'bg-brand' : 'bg-muted/30'"
            >
                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="autoReply ? 'translate-x-5' : 'translate-x-0'"></span>
            </button>
        </div>

        {{-- Toggle Switch — Maintenance Mode --}}
        <div class="flex items-center justify-between rounded-xl border border-border/40 bg-surface/60 px-4 py-3">
            <div>
                <p class="text-sm font-medium text-ink">{{ __('Maintenance Mode') }}</p>
                <p class="text-xs text-muted">{{ __('Take the app offline temporarily') }}</p>
            </div>
            <button
                type="button"
                role="switch"
                :aria-checked="maintenanceMode.toString()"
                x-on:click="maintenanceMode = !maintenanceMode"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
                :class="maintenanceMode ? 'bg-brand' : 'bg-muted/30'"
            >
                <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform" :class="maintenanceMode ? 'translate-x-5' : 'translate-x-0'"></span>
            </button>
        </div>

        {{-- Number Input --}}
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Max Upload Size (MB)') }}</label>
            <input type="number" class="input-field" value="25" min="1" max="100">
        </div>

        {{-- Color Picker --}}
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Brand Color') }}</label>
            <div class="flex items-center gap-3">
                <input type="color" value="#6366f1" class="h-10 w-10 shrink-0 cursor-pointer rounded-lg border border-border bg-transparent p-0.5">
                <input type="text" class="input-field" value="#6366f1" placeholder="#hex">
            </div>
        </div>

        {{-- Textarea --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('System Announcement') }}</label>
            <textarea class="textarea" rows="3" placeholder="{{ __('Enter an optional announcement to display across the app...') }}"></textarea>
        </div>

        {{-- File Upload --}}
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Logo Upload') }}</label>
            <div class="flex items-center gap-3">
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-border bg-surface/60 px-4 py-2.5 text-sm text-muted transition hover:border-brand/40 hover:text-ink">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    {{ __('Choose file') }}
                    <input type="file" class="hidden" accept="image/*">
                </label>
                <span class="text-xs text-muted">{{ __('No file chosen') }}</span>
            </div>
        </div>

        {{-- Radio Buttons --}}
        <div>
            <label class="block text-sm font-medium text-ink/80 mb-2.5">{{ __('Email Provider') }}</label>
            <div class="flex flex-col gap-2.5">
                @foreach(['SMTP', 'Mailgun', 'Amazon SES'] as $provider)
                <label class="flex items-center gap-2.5 cursor-pointer group">
                    <input type="radio" name="email_provider" value="{{ strtolower(str_replace(' ', '_', $provider)) }}" {{ $loop->first ? 'checked' : '' }}
                        class="h-4 w-4 shrink-0 rounded-full border-2 border-border text-brand accent-brand focus:ring-2 focus:ring-brand/30">
                    <span class="text-sm text-ink group-hover:text-ink">{{ $provider }}</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Checkbox Group --}}
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-ink/80 mb-2.5">{{ __('Notification Channels') }}</label>
            <div class="flex flex-wrap gap-4">
                @foreach(['Email' => 'email', 'Slack' => 'slack', 'In-App' => 'in_app', 'SMS' => 'sms', 'Webhook' => 'webhook'] as $label => $val)
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" value="{{ $val }}" {{ in_array($val, ['email', 'slack']) ? 'checked' : '' }}
                        class="h-4 w-4 shrink-0 rounded border-2 border-border text-brand accent-brand focus:ring-2 focus:ring-brand/30">
                    <span class="text-sm text-ink group-hover:text-ink">{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Save Button --}}
    <div class="mt-6 flex items-center gap-3 border-t border-border/40 pt-5">
        <button type="button" class="btn-primary">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            {{ __('Save Settings') }}
        </button>
        <button type="button" class="btn-secondary">{{ __('Cancel') }}</button>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════════
     ACTION CARDS — System Health, Pending Tickets, Updates
     ════════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-2">

    {{-- System Health --}}
    <div class="rounded-2xl border border-border/50 bg-surface-2 p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-500/10">
                <svg viewBox="0 0 24 24" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-ink">{{ __('System Health') }}</h3>
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                    </span>
                </div>
                <p class="text-xs text-muted mt-1">{{ __('All systems operational') }}</p>
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
                    <span class="inline-flex items-center justify-center h-5 min-w-5 rounded-full bg-amber-100 dark:bg-amber-500/20 px-1.5 text-[10px] font-bold text-amber-700 dark:text-amber-400">3</span>
                </div>
                <p class="text-xs text-muted mt-1">{{ __('Support tickets awaiting response') }}</p>
            </div>
        </div>
        <a href="{{ url('/admin/tickets') }}" wire:navigate class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-strong transition">
            {{ __('Review Tickets') }}
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
        </a>
    </div>

    {{-- Updates Available --}}
    <div class="rounded-2xl border border-border/50 bg-surface-2 p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/10">
                <svg viewBox="0 0 24 24" class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Updates Available') }}</h3>
                    <span class="inline-flex items-center justify-center rounded-full bg-blue-50 dark:bg-blue-500/20 px-2 py-0.5 text-[10px] font-bold text-blue-600 dark:text-blue-400">v2.1</span>
                </div>
                <p class="text-xs text-muted mt-1">{{ __('New version available for download') }}</p>
            </div>
        </div>
        <a href="{{ url('/admin/system') }}" wire:navigate class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-strong transition">
            {{ __('View Details') }}
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
        </a>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════════
     CHART.JS INITIALIZATION
     ════════════════════════════════════════════════════════════════ --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    initCharts();
});
document.addEventListener('livewire:navigated', function () {
    initCharts();
});

function initCharts() {
    // Destroy existing chart instances to prevent canvas reuse errors
    Chart.helpers.each(Chart.instances, function (instance) {
        if (instance.canvas.id === 'revenueChart' || instance.canvas.id === 'planChart') {
            instance.destroy();
        }
    });

    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)';
    const textColor = isDark ? '#9CA3AF' : '#9CA3AF';

    // ── Revenue Chart ──
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        const rCtx = revenueCtx.getContext('2d');

        // Gradient for revenue
        const revGrad = rCtx.createLinearGradient(0, 0, 0, 280);
        revGrad.addColorStop(0, isDark ? 'rgba(99,102,241,0.25)' : 'rgba(59,130,246,0.15)');
        revGrad.addColorStop(1, isDark ? 'rgba(99,102,241,0)' : 'rgba(59,130,246,0)');

        // Gradient for expenses
        const expGrad = rCtx.createLinearGradient(0, 0, 0, 280);
        expGrad.addColorStop(0, isDark ? 'rgba(45,212,191,0.2)' : 'rgba(20,184,166,0.1)');
        expGrad.addColorStop(1, isDark ? 'rgba(45,212,191,0)' : 'rgba(20,184,166,0)');

        new Chart(rCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [
                    {
                        label: 'Revenue',
                        data: [4200, 5100, 4800, 6200, 5800, 7400, 8100, 7600, 9200, 10100, 11400, 12853],
                        borderColor: isDark ? '#818cf8' : '#3b82f6',
                        backgroundColor: revGrad,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: isDark ? '#818cf8' : '#3b82f6',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                    },
                    {
                        label: 'Expenses',
                        data: [2800, 3200, 3100, 3800, 3500, 4100, 4400, 4200, 4800, 5200, 5600, 6100],
                        borderColor: isDark ? '#5eead4' : '#14b8a6',
                        backgroundColor: expGrad,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: isDark ? '#5eead4' : '#14b8a6',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                    }
                ]
            },
            options: {
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
                        callbacks: {
                            label: function(ctx) {
                                return ctx.dataset.label + ': $' + ctx.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { size: 11, family: "'Outfit', sans-serif" } },
                        border: { display: false }
                    },
                    y: {
                        grid: { color: gridColor, drawBorder: false },
                        ticks: {
                            color: textColor,
                            font: { size: 11, family: "'Outfit', sans-serif" },
                            callback: function(v) { return '$' + (v / 1000) + 'K'; }
                        },
                        border: { display: false },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // ── Plan Distribution Doughnut ──
    const planCtx = document.getElementById('planChart');
    if (planCtx) {
        new Chart(planCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Free', 'Starter', 'Pro', 'Enterprise'],
                datasets: [{
                    data: [420, 380, 310, 138],
                    backgroundColor: [
                        isDark ? '#6b7280' : '#9ca3af',
                        isDark ? '#60a5fa' : '#3b82f6',
                        isDark ? '#a78bfa' : '#8b5cf6',
                        isDark ? '#fbbf24' : '#f59e0b',
                    ],
                    borderColor: isDark ? '#1a1d27' : '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
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
                        callbacks: {
                            label: function(ctx) {
                                const pct = ((ctx.parsed / 1248) * 100).toFixed(1);
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
}
</script>

</x-layouts.admin>
