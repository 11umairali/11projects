<div wire:poll.30s.visible class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('campaigns') }}" class="p-2 text-muted hover:text-ink rounded-xl hover:bg-surface transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-bold text-ink">{{ $campaign->name }}</h1>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ match($campaign->status) {
                        'sent' => 'bg-success/15 text-success',
                        'sending' => 'bg-warning/15 text-warning',
                        'scheduled' => 'bg-info/15 text-info',
                        'draft' => 'bg-surface text-muted',
                        'paused' => 'bg-orange-100 text-orange-700',
                        default => 'bg-surface text-muted'
                    } }}">{{ ucfirst($campaign->status) }}</span>
                </div>
                <p class="text-sm text-muted mt-0.5">{{ $campaign->subject }}</p>
            </div>
        </div>
        @if($campaign->status === 'sending')
        <span class="flex items-center gap-2 px-3 py-1.5 bg-warning/15 text-warning text-sm font-medium rounded-full">
            <span class="w-2 h-2 bg-warning rounded-full animate-pulse"></span>
            {{ __('Sending in progress...') }}
        </span>
        @endif
    </div>

    {{-- Tab Navigation --}}
    <div class="flex items-center gap-1 border-b border-border">
        @foreach(['overview' => __('Overview'), 'recipients' => __('Messages'), 'opens' => __('Opens'), 'clicks' => __('Clicks'), 'bounces' => __('Bounces')] as $tabKey => $tabLabel)
        <button wire:click="$set('activeTab', '{{ $tabKey }}')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $activeTab === $tabKey ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink hover:border-border' }}">
            {{ $tabLabel }}
            @if($tabKey === 'recipients')
                <span class="ml-1 text-xs text-muted">{{ number_format($stats['recipients']) }}</span>
            @elseif($tabKey === 'opens')
                <span class="ml-1 text-xs text-muted">{{ number_format($stats['opened']) }}</span>
            @elseif($tabKey === 'clicks')
                <span class="ml-1 text-xs text-muted">{{ number_format($stats['clicked']) }}</span>
            @elseif($tabKey === 'bounces')
                <span class="ml-1 text-xs text-muted">{{ number_format($stats['bounced']) }}</span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- Overview Tab --}}
    @if($activeTab === 'overview')

    {{-- Delivery Section --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h3 class="text-base font-semibold text-ink">{{ __('Delivery') }}</h3>
            <p class="text-sm text-muted mt-0.5">
                {{ number_format($stats['delivered']) }} emails were successfully delivered{{ $stats['bounced'] > 0 ? ', with ' . $stats['bounce_rate'] . '% bounced' : '' }}.
            </p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-border">
            <div class="p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Total recipients') }}</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-bold text-ink">{{ number_format($stats['recipients']) }}</span>
                    <span class="text-sm font-semibold text-success">100%</span>
                </div>
            </div>
            <div class="p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Delivered') }}</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-bold text-ink">{{ number_format($stats['delivered']) }}</span>
                    <span class="text-sm font-semibold text-success">{{ $stats['recipients'] > 0 ? round(($stats['delivered'] / $stats['recipients']) * 100, 1) : 0 }}%</span>
                </div>
            </div>
            <div class="p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Rejected') }}</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-bold text-ink">{{ number_format($stats['failed'] ?? 0) }}</span>
                    <span class="text-sm font-semibold text-danger">{{ $stats['recipients'] > 0 ? round((($stats['failed'] ?? 0) / $stats['recipients']) * 100, 1) : 0 }}%</span>
                </div>
            </div>
            <div class="p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Bounced') }}</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-bold text-ink">{{ number_format($stats['bounced']) }}</span>
                    <span class="text-sm font-semibold text-danger">{{ $stats['bounce_rate'] }}%</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Engagement Chart + Stats --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h3 class="text-base font-semibold text-ink">{{ __('Engagement') }}</h3>
            <p class="text-sm text-muted mt-0.5">
                Your email was delivered to {{ number_format($stats['delivered']) }} recipients, opened by {{ $stats['open_rate'] }}% and clicked by {{ $stats['click_rate'] }}%.
            </p>
        </div>

        {{-- Engagement Funnel Chart --}}
        @php
            $funnelMax = max($stats['delivered'], 1);
            $openPct = round(($stats['opened'] / $funnelMax) * 100);
            $clickPct = round(($stats['clicked'] / $funnelMax) * 100);
            $unsubPct = round(($stats['unsubscribed'] / $funnelMax) * 100);
        @endphp
        <div class="px-6 py-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- Donut chart --}}
                <div class="flex items-center justify-center">
                    @php
                        $openDeg = round(($stats['open_rate'] / 100) * 360);
                        $clickDeg = round(($stats['click_rate'] / 100) * 360);
                    @endphp
                    <div class="relative w-48 h-48">
                        <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke-width="3" class="stroke-border"/>
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke-width="3" class="stroke-brand"
                                    stroke-dasharray="{{ $stats['open_rate'] }} {{ 100 - $stats['open_rate'] }}"
                                    stroke-linecap="round"/>
                            <circle cx="18" cy="18" r="12" fill="none" stroke-width="3" class="stroke-border"/>
                            <circle cx="18" cy="18" r="12" fill="none" stroke-width="3" class="stroke-success"
                                    stroke-dasharray="{{ $stats['click_rate'] }} {{ 100 - $stats['click_rate'] }}"
                                    stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold text-ink">{{ $stats['open_rate'] }}%</span>
                            <span class="text-xs text-muted">{{ __('opened') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Funnel bars --}}
                <div class="space-y-4 flex flex-col justify-center">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-ink">{{ __('Delivered') }}</span>
                            <span class="text-sm font-semibold text-ink">{{ number_format($stats['delivered']) }}</span>
                        </div>
                        <div class="w-full h-3 bg-surface rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-ink">{{ __('Opened') }}</span>
                            <span class="text-sm font-semibold text-brand">{{ number_format($stats['opened']) }} ({{ $stats['open_rate'] }}%)</span>
                        </div>
                        <div class="w-full h-3 bg-surface rounded-full overflow-hidden">
                            <div class="h-full bg-brand rounded-full transition-all duration-700" style="width: {{ $openPct }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-ink">{{ __('Clicked') }}</span>
                            <span class="text-sm font-semibold text-success">{{ number_format($stats['clicked']) }} ({{ $stats['click_rate'] }}%)</span>
                        </div>
                        <div class="w-full h-3 bg-surface rounded-full overflow-hidden">
                            <div class="h-full bg-success rounded-full transition-all duration-700" style="width: {{ $clickPct }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-ink">{{ __('Unsubscribed') }}</span>
                            <span class="text-sm font-semibold text-orange-500">{{ number_format($stats['unsubscribed']) }} ({{ $stats['unsub_rate'] }}%)</span>
                        </div>
                        <div class="w-full h-3 bg-surface rounded-full overflow-hidden">
                            <div class="h-full bg-orange-500 rounded-full transition-all duration-700" style="width: {{ max($unsubPct, $stats['unsubscribed'] > 0 ? 2 : 0) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stat Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-border border-t border-border">
            <div class="p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Opened') }}</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-bold text-ink">{{ $stats['open_rate'] }}%</span>
                    <span class="text-sm font-semibold text-success">{{ number_format($stats['opened']) }}</span>
                </div>
            </div>
            <div class="p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Clicked') }}</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-bold text-ink">{{ $stats['click_rate'] }}%</span>
                    <span class="text-sm font-semibold text-success">{{ number_format($stats['clicked']) }}</span>
                </div>
            </div>
            <div class="p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Unsubscribed') }}</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-bold text-ink">{{ $stats['unsub_rate'] }}%</span>
                    <span class="text-sm font-semibold text-orange-600">{{ number_format($stats['unsubscribed']) }}</span>
                </div>
            </div>
            <div class="p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Spam reports') }}</p>
                <div class="flex items-baseline gap-2 mt-2">
                    <span class="text-3xl font-bold text-ink">0%</span>
                    <span class="text-sm font-semibold text-muted">0</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Details Section --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h3 class="text-base font-semibold text-ink">{{ __('Details') }}</h3>
            <p class="text-sm text-muted mt-0.5">{{ __('An overview of this campaign\'s details, message and settings.') }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-border">
            <div class="p-6 space-y-4">
                <div>
                    <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('From') }}</p>
                    <p class="text-sm font-semibold text-ink mt-1">{{ $campaign->emailAccount?->display_name ?? $campaign->emailAccount?->email ?? __('N/A') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('"Reply to" email') }}</p>
                    <p class="text-sm font-semibold text-ink mt-1">{{ $campaign->emailAccount?->email ?? __('N/A') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Send time') }}</p>
                    <p class="text-sm font-semibold text-ink mt-1">{{ $campaign->sent_at?->format('M j, Y \a\t g:i A') ?? ($campaign->scheduled_at?->format('M j, Y \a\t g:i A') ?? __('Not sent yet')) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Campaign type') }}</p>
                    <p class="text-sm font-semibold text-ink mt-1">{{ ucfirst(str_replace('_', ' ', $campaign->type ?? 'regular')) }}</p>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Subject') }}</p>
                    <p class="text-sm font-semibold text-ink mt-1">{{ $campaign->subject }}</p>
                </div>
                @if($campaign->preview_text)
                <div>
                    <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Preview text') }}</p>
                    <p class="text-sm text-muted mt-1">{{ $campaign->preview_text }}</p>
                </div>
                @endif
                <div>
                    <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Audience') }}</p>
                    <p class="text-sm font-semibold text-ink mt-1">{{ ucfirst($campaign->audience_type ?? 'all') }} &mdash; {{ number_format($stats['recipients']) }} {{ __('recipients') }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Created by') }}</p>
                    <p class="text-sm font-semibold text-ink mt-1">{{ $campaign->createdBy?->name ?? __('Unknown') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Link Performance --}}
    @if($links->isNotEmpty())
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-6 py-4 border-b border-border">
            <h3 class="text-base font-semibold text-ink">{{ __('Link Performance') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-5 py-3">{{ __('URL') }}</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-5 py-3 w-32">{{ __('Clicks') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($links as $link)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="px-5 py-3">
                            <p class="text-sm text-brand truncate max-w-lg" title="{{ $link->original_url }}">{{ $link->original_url }}</p>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-sm font-semibold text-ink">{{ number_format($link->clicks_count) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @endif

    {{-- Recipients Tab (Messages) --}}
    @if($activeTab === 'recipients')
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <h3 class="text-sm font-semibold text-ink">{{ __('All Recipients') }}</h3>
            <select wire:model.live="recipientFilter" class="text-sm border border-border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface-2">
                <option value="all">{{ __('All statuses') }}</option>
                <option value="sent">{{ __('Sent') }}</option>
                <option value="delivered">{{ __('Delivered') }}</option>
                <option value="opened">{{ __('Opened') }}</option>
                <option value="clicked">{{ __('Clicked') }}</option>
                <option value="bounced">{{ __('Bounced') }}</option>
                <option value="unsubscribed">{{ __('Unsubscribed') }}</option>
                <option value="failed">{{ __('Failed') }}</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Contact') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Email') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Status') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">{{ __('Sent') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">{{ __('Opened') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">{{ __('Clicked') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @forelse($recipients as $recipient)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium text-ink">{{ $recipient->contact?->full_name ?? __('Unknown') }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-sm text-muted">{{ $recipient->email ?? $recipient->contact?->email ?? '--' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-surface text-muted',
                                    'sent' => 'bg-info/15 text-info',
                                    'delivered' => 'bg-success/15 text-success',
                                    'opened' => 'bg-emerald-100 text-emerald-700',
                                    'clicked' => 'bg-teal-100 text-teal-700',
                                    'bounced' => 'bg-danger/15 text-danger',
                                    'unsubscribed' => 'bg-orange-100 text-orange-700',
                                    'failed' => 'bg-danger/15 text-danger',
                                ];
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusColors[$recipient->status] ?? 'bg-surface text-muted' }}">
                                {{ ucfirst($recipient->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted hidden md:table-cell">{{ $recipient->sent_at?->format('M j, g:i A') ?? '--' }}</td>
                        <td class="px-4 py-3 text-xs text-muted hidden md:table-cell">{{ $recipient->opened_at?->format('M j, g:i A') ?? '--' }}</td>
                        <td class="px-4 py-3 text-xs text-muted hidden lg:table-cell">{{ $recipient->clicked_at?->format('M j, g:i A') ?? '--' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-muted">{{ __('No recipients found.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recipients->hasPages())
        <div class="px-4 py-3 border-t border-border">
            {{ $recipients->links() }}
        </div>
        @endif
    </div>
    @endif

    {{-- Opens Tab --}}
    @if($activeTab === 'opens')
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-5 py-4 border-b border-border">
            <h3 class="text-sm font-semibold text-ink">{{ __('Who opened this campaign') }}</h3>
            <p class="text-xs text-muted mt-0.5">{{ number_format($stats['opened']) }} recipients opened your email ({{ $stats['open_rate'] }}% open rate)</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Contact') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Email') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Opened at') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @forelse($openedRecipients as $recipient)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="px-4 py-3 text-sm font-medium text-ink">{{ $recipient->contact?->full_name ?? __('Unknown') }}</td>
                        <td class="px-4 py-3 text-sm text-muted">{{ $recipient->email ?? $recipient->contact?->email ?? '--' }}</td>
                        <td class="px-4 py-3 text-sm text-muted">{{ $recipient->opened_at?->format('M j, Y \a\t g:i A') ?? '--' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-muted">{{ __('No opens recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($openedRecipients->hasPages())
        <div class="px-4 py-3 border-t border-border">{{ $openedRecipients->links() }}</div>
        @endif
    </div>
    @endif

    {{-- Clicks Tab --}}
    @if($activeTab === 'clicks')
    <div class="space-y-6">
        {{-- Link performance --}}
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border">
                <h3 class="text-sm font-semibold text-ink">{{ __('Link clicks') }}</h3>
                <p class="text-xs text-muted mt-0.5">{{ number_format($stats['clicked']) }} recipients clicked a link ({{ $stats['click_rate'] }}% click rate)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('URL') }}</th>
                            <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 w-32">{{ __('Clicks') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($links as $link)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-4 py-3"><p class="text-sm text-brand truncate max-w-lg" title="{{ $link->original_url }}">{{ $link->original_url }}</p></td>
                            <td class="px-4 py-3 text-right"><span class="text-sm font-semibold text-ink">{{ number_format($link->clicks_count) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="px-4 py-8 text-center text-sm text-muted">{{ __('No link clicks yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Who clicked --}}
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border">
                <h3 class="text-sm font-semibold text-ink">{{ __('Who clicked') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Contact') }}</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Email') }}</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Clicked at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($clickedRecipients as $recipient)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-4 py-3 text-sm font-medium text-ink">{{ $recipient->contact?->full_name ?? __('Unknown') }}</td>
                            <td class="px-4 py-3 text-sm text-muted">{{ $recipient->email ?? $recipient->contact?->email ?? '--' }}</td>
                            <td class="px-4 py-3 text-sm text-muted">{{ $recipient->clicked_at?->format('M j, Y \a\t g:i A') ?? '--' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-muted">{{ __('No clicks recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($clickedRecipients->hasPages())
            <div class="px-4 py-3 border-t border-border">{{ $clickedRecipients->links() }}</div>
            @endif
        </div>
    </div>
    @endif

    {{-- Bounces Tab --}}
    @if($activeTab === 'bounces')
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-5 py-4 border-b border-border">
            <h3 class="text-sm font-semibold text-ink">{{ __('Bounced & Failed') }}</h3>
            <p class="text-xs text-muted mt-0.5">{{ number_format($stats['bounced'] + ($stats['failed'] ?? 0)) }} emails bounced or failed to deliver</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Contact') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Email') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Status') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">{{ __('Error') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @forelse($bouncedRecipients as $recipient)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="px-4 py-3 text-sm font-medium text-ink">{{ $recipient->contact?->full_name ?? __('Unknown') }}</td>
                        <td class="px-4 py-3 text-sm text-muted">{{ $recipient->email ?? $recipient->contact?->email ?? '--' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-danger/15 text-danger">{{ ucfirst($recipient->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted hidden md:table-cell max-w-xs truncate">{{ $recipient->error_message ?? '--' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-muted">{{ __('No bounces or failures.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($bouncedRecipients->hasPages())
        <div class="px-4 py-3 border-t border-border">{{ $bouncedRecipients->links() }}</div>
        @endif
    </div>
    @endif
</div>
