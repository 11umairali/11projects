<x-layouts.admin :title="__('Email Deliverability')" :subtitle="__('Monitor email delivery rates, bounces, and failures.')">
    <div class="space-y-6">

        @php
            $total = (int) ($stats->total ?? 0);
            $delivered = (int) ($stats->delivered ?? 0);
            $bounced = (int) ($stats->bounced ?? 0);
            $failed = (int) ($stats->failed ?? 0);
            $deliveryRate = $total > 0 ? round(($delivered / $total) * 100, 1) : 0;
            $bounceRate = $total > 0 ? round(($bounced / $total) * 100, 1) : 0;
            $failRate = $total > 0 ? round(($failed / $total) * 100, 1) : 0;
        @endphp

        @if($total === 0)
            {{-- Empty state --}}
            <div class="panel p-12 text-center">
                <div class="w-16 h-16 bg-cyan-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-ink mb-1">{{ __('No email data yet') }}</h3>
                <p class="text-sm text-muted max-w-md mx-auto">{{ __('Outbound email delivery metrics will appear here once emails are sent through the platform.') }}</p>
            </div>
        @else
            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @php
                $deliveryCards = [
                    ['label' => __('Total Emails Sent'), 'value' => number_format($total), 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'from-indigo-500 to-blue-600', 'shadow' => 'shadow-soft'],
                    ['label' => __('Delivery Rate'), 'value' => $deliveryRate . '%', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'from-green-500 to-emerald-600', 'shadow' => 'shadow-green-200'],
                    ['label' => __('Bounce Rate'), 'value' => $bounceRate . '%', 'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'from-amber-500 to-orange-600', 'shadow' => 'shadow-amber-200'],
                    ['label' => __('Failure Rate'), 'value' => $failRate . '%', 'icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636', 'color' => 'from-red-500 to-rose-600', 'shadow' => 'shadow-red-200'],
                ];
                @endphp

                @foreach($deliveryCards as $card)
                <div class="panel p-5 hover:shadow-md transition-all duration-200">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-gradient-to-br {{ $card['color'] }} rounded-xl flex items-center justify-center shadow-lg {{ $card['shadow'] }}">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/></svg>
                        </div>
                        <span class="text-xs font-medium text-muted">{{ $card['label'] }}</span>
                    </div>
                    <p class="text-2xl font-extrabold text-ink">{{ $card['value'] }}</p>
                </div>
                @endforeach
            </div>

            {{-- Breakdown --}}
            <div class="panel p-6">
                <h2 class="text-lg font-bold text-ink mb-1">{{ __('Delivery Breakdown') }}</h2>
                <p class="text-xs text-muted mb-4">{{ __('All outbound messages by status') }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-success/10 border border-success/20 rounded-xl p-4 text-center">
                        <p class="text-2xl font-extrabold text-success">{{ number_format($delivered) }}</p>
                        <p class="text-xs font-semibold text-success mt-1">{{ __('Delivered / Sent') }}</p>
                    </div>
                    <div class="bg-warning/10 border border-warning/20 rounded-xl p-4 text-center">
                        <p class="text-2xl font-extrabold text-warning">{{ number_format($bounced) }}</p>
                        <p class="text-xs font-semibold text-amber-600 mt-1">{{ __('Bounced') }}</p>
                    </div>
                    <div class="bg-danger/10 border border-danger/20 rounded-xl p-4 text-center">
                        <p class="text-2xl font-extrabold text-danger">{{ number_format($failed) }}</p>
                        <p class="text-xs font-semibold text-danger mt-1">{{ __('Failed') }}</p>
                    </div>
                </div>
            </div>

            {{-- Recent Bounces --}}
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <h2 class="text-lg font-bold text-ink">{{ __('Recent Bounced Messages') }}</h2>
                    <p class="text-xs text-muted mt-0.5">{{ __('Last') }} {{ $recentBounces->count() }} {{ __('bounced emails') }}</p>
                </div>
                @if($recentBounces->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-surface border-b border-border/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('ID') }}</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Subject') }}</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Error') }}</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted uppercase tracking-wider">{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @foreach($recentBounces as $msg)
                            <tr class="hover:bg-surface transition-colors">
                                <td class="px-4 py-2.5 text-xs font-mono text-ink/80">#{{ $msg->id }}</td>
                                <td class="px-4 py-2.5 text-xs text-ink/80 max-w-xs truncate">{{ $msg->subject ?? __('(no subject)') }}</td>
                                <td class="px-4 py-2.5 text-xs text-danger max-w-xs truncate">{{ $msg->delivery_error ?? __('Unknown error') }}</td>
                                <td class="px-4 py-2.5 text-xs text-muted">{{ $msg->created_at?->format('M j, Y H:i') ?? '--' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="p-8 text-center">
                    <p class="text-sm text-muted">{{ __('No bounced messages found.') }}</p>
                </div>
                @endif
            </div>
        @endif

    </div>
</x-layouts.admin>
