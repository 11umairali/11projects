<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('AI Performance') }}</h1>
            <p class="text-sm text-muted mt-0.5">{{ __('Track AI reply accuracy, confidence, and cost') }}</p>
        </div>

        {{-- Date range selector --}}
        <div class="flex items-center gap-2">
            <div class="flex items-center bg-surface-2 border border-border rounded-xl p-0.5">
                @foreach(['today' => __('Today'), '7d' => __('7 days'), '30d' => __('30 days'), '90d' => __('90 days')] as $key => $label)
                <button wire:click="$set('dateRange', '{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $dateRange === $key ? 'bg-primary-600 text-white shadow-sm' : 'text-muted  hover:text-ink' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex items-center gap-1.5 px-3 py-2 text-sm text-muted  bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ __('Custom') }}
                </button>
                <div x-show="open" @click.away="open = false" x-transition
                     class="absolute right-0 mt-1 w-64 bg-surface-2 rounded-xl shadow-lg border border-border p-4 z-20" style="display: none;">
                    <div class="space-y-2">
                        <div>
                            <label class="text-xs text-muted">{{ __('From') }}</label>
                            <input type="date" wire:model="customFrom" class="w-full px-3 py-1.5 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="text-xs text-muted">{{ __('To') }}</label>
                            <input type="date" wire:model="customTo" class="w-full px-3 py-1.5 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500">
                        </div>
                        <button @click="open = false" wire:click="applyCustomRange" class="btn-primary w-full px-3 py-1.5 text-sm">{{ __('Apply') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('AI Replies Sent') }}</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ number_format($aiRepliesSent) }}</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Accuracy') }}</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $accuracy }}%</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Avg Confidence') }}</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $avgConfidence }}%</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Escalation Rate') }}</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ $escalationRate }}%</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Total Cost') }}</p>
            <p class="text-2xl font-bold text-ink mt-1">@currency($totalCost)</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Topic Performance --}}
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border">
                <h3 class="text-sm font-semibold text-ink">{{ __('Performance by Topic') }}</h3>
                <p class="text-xs text-muted mt-0.5">{{ __('AI accuracy grouped by conversation tags') }}</p>
            </div>
            @if($topicPerformance->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Topic') }}</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Replies') }}</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Accuracy') }}</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Confidence') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @foreach($topicPerformance as $topic)
                        <tr class="hover:bg-surface">
                            <td class="px-4 py-2.5 text-sm font-medium text-ink">{{ $topic['topic'] }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted ">{{ $topic['total'] }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 h-1.5 bg-surface  rounded-full">
                                        <div class="h-full rounded-full {{ $topic['accuracy'] >= 90 ? 'bg-success/100' : ($topic['accuracy'] >= 70 ? 'bg-warning/100' : 'bg-danger/100') }}" style="width: {{ $topic['accuracy'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-ink/80">{{ $topic['accuracy'] }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-sm text-muted ">{{ $topic['avg_confidence'] }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="px-5 py-8 text-center text-sm text-muted">{{ __('No AI topic data available for this period.') }}</div>
            @endif
        </div>

        {{-- KB Gaps --}}
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border">
                <h3 class="text-sm font-semibold text-ink">{{ __('Knowledge Base Gaps') }}</h3>
                <p class="text-xs text-muted mt-0.5">{{ __('Queries where AI confidence was below 50%') }}</p>
            </div>
            @if($kbGaps->count() > 0)
            <div class="divide-y divide-border/60">
                @foreach($kbGaps as $gap)
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-ink truncate flex-1 mr-3">{{ $gap['subject'] }}</p>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-danger/15 text-danger flex-shrink-0">
                            {{ $gap['confidence'] }}% {{ __('confidence') }}
                        </span>
                    </div>
                    <p class="text-xs text-muted mt-0.5">{{ $gap['date'] }}</p>
                </div>
                @endforeach
            </div>
            @else
            <div class="px-5 py-8 text-center text-sm text-muted">{{ __('No low-confidence queries found. AI is performing well.') }}</div>
            @endif
        </div>
    </div>

    {{-- Cost Breakdown --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="px-5 py-4 border-b border-border">
            <h3 class="text-sm font-semibold text-ink">{{ __('Cost Breakdown by Provider & Model') }}</h3>
        </div>
        @if($costBreakdown->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Provider') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Model') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Requests') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Tokens In') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Tokens Out') }}</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-2">{{ __('Total Cost') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($costBreakdown as $row)
                    <tr class="hover:bg-surface">
                        <td class="px-4 py-2.5 text-sm font-medium text-ink">{{ ucfirst($row->ai_provider ?? 'Unknown') }}</td>
                        <td class="px-4 py-2.5 text-sm text-muted ">{{ $row->ai_model ?? '--' }}</td>
                        <td class="px-4 py-2.5 text-sm text-muted ">{{ number_format($row->count) }}</td>
                        <td class="px-4 py-2.5 text-sm text-muted ">{{ number_format($row->total_tokens_in ?? 0) }}</td>
                        <td class="px-4 py-2.5 text-sm text-muted ">{{ number_format($row->total_tokens_out ?? 0) }}</td>
                        <td class="px-4 py-2.5 text-sm font-semibold text-ink">@currency($row->total_cost)</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="px-5 py-8 text-center text-sm text-muted">{{ __('No AI cost data recorded for this period.') }}</div>
        @endif
    </div>
</div>
