<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('workflows') }}" class="p-1.5 text-muted/60 hover:text-ink rounded-lg hover:bg-surface">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-ink">{{ __('Execution Log') }}</h1>
                <p class="text-sm text-muted mt-0.5">{{ $workflowName }}</p>
            </div>
        </div>
    </div>

    {{-- Summary stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Total') }}</p>
            <p class="text-2xl font-bold text-ink mt-1">{{ number_format($totalExecutions) }}</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Completed') }}</p>
            <p class="text-2xl font-bold text-success mt-1">{{ number_format($completedCount) }}</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Failed') }}</p>
            <p class="text-2xl font-bold text-danger mt-1">{{ number_format($failedCount) }}</p>
        </div>
        <div class="bg-surface-2 rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wider">{{ __('Running') }}</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($runningCount) }}</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="flex items-center gap-2">
        <span class="text-sm text-muted">{{ __('Filter:') }}</span>
        @foreach(['all' => __('All'), 'completed' => __('Completed'), 'failed' => __('Failed'), 'running' => __('Running'), 'waiting' => __('Waiting')] as $key => $label)
        <button wire:click="$set('statusFilter', '{{ $key }}')"
                class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors {{ $statusFilter === $key ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : 'bg-surface-2 text-muted  border border-border hover:bg-surface' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Executions Table --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="bg-surface border-b border-border">
                    <th class="w-8 px-4 py-3"></th>
                    <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Contact') }}</th>
                    <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">{{ __('Status') }}</th>
                    <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">{{ __('Started') }}</th>
                    <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">{{ __('Completed') }}</th>
                    <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">{{ __('Duration') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border/60">
                @forelse($executions as $execution)
                <tr class="hover:bg-surface transition-colors cursor-pointer" wire:click="toggleExpand({{ $execution->id }})" wire:key="exec-{{ $execution->id }}">
                    <td class="px-4 py-3">
                        <svg class="w-4 h-4 text-muted transition-transform {{ $expandedExecutionId === $execution->id ? 'rotate-90' : '' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </td>
                    <td class="px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $execution->contact?->full_name ?? __('System') }}</p>
                            <p class="text-xs text-muted">{{ $execution->contact?->email ?? '--' }}</p>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $statusColors = [
                                'running' => 'bg-info/15 text-info',
                                'completed' => 'bg-success/15 text-success',
                                'failed' => 'bg-danger/15 text-danger',
                                'waiting' => 'bg-warning/15 text-warning',
                                'canceled' => 'bg-surface  text-muted ',
                            ];
                        @endphp
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$execution->status] ?? 'bg-surface  text-muted ' }}">
                            {{ ucfirst($execution->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-muted  hidden md:table-cell">
                        {{ $execution->started_at?->format('M j, g:i A') ?? '--' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-muted  hidden md:table-cell">
                        {{ $execution->completed_at?->format('M j, g:i A') ?? '--' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-muted  hidden lg:table-cell">
                        @if($execution->started_at && $execution->completed_at)
                            {{ $execution->started_at->diffForHumans($execution->completed_at, true) }}
                        @elseif($execution->started_at)
                            {{ $execution->started_at->diffForHumans(now(), true) }} ({{ __('ongoing') }})
                        @else
                            --
                        @endif
                    </td>
                </tr>

                {{-- Expanded step logs --}}
                @if($expandedExecutionId === $execution->id)
                <tr wire:key="exec-detail-{{ $execution->id }}">
                    <td colspan="6" class="px-4 py-4 bg-surface">
                        <div class="ml-8 space-y-2">
                            <p class="text-xs font-semibold text-muted uppercase tracking-wider mb-3">{{ __('Step-by-Step Log') }}</p>
                            @forelse($stepLogs as $log)
                            <div class="flex items-start gap-3 bg-surface-2 rounded-lg p-3 border border-border">
                                {{-- Status icon --}}
                                <div class="mt-0.5 flex-shrink-0">
                                    @if($log->status === 'success')
                                    <span class="w-6 h-6 bg-success/15 rounded-full flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                    @elseif($log->status === 'failed')
                                    <span class="w-6 h-6 bg-danger/15 rounded-full flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </span>
                                    @elseif($log->status === 'waiting')
                                    <span class="w-6 h-6 bg-warning/15 rounded-full flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </span>
                                    @else
                                    <span class="w-6 h-6 bg-surface  rounded-full flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-ink">
                                            {{ ucfirst($log->node?->type ?? 'unknown') }}: {{ str_replace('_', ' ', ucfirst($log->node?->subtype ?? 'unknown')) }}
                                        </p>
                                        <span class="text-xs text-muted">
                                            @if($log->duration_ms){{ $log->duration_ms }}ms @endif
                                        </span>
                                    </div>
                                    @if($log->error_message)
                                    <p class="text-xs text-danger mt-0.5">{{ $log->error_message }}</p>
                                    @endif
                                    <p class="text-xs text-muted mt-0.5">
                                        {{ $log->executed_at?->format('M j, g:i:s A') ?? __('Pending') }}
                                    </p>
                                </div>
                            </div>
                            @empty
                            <p class="text-sm text-muted">{{ __('No step logs recorded yet.') }}</p>
                            @endforelse
                        </div>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-sm text-muted">{{ __('No executions found.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($executions->hasPages())
        <div class="px-4 py-3 border-t border-border">
            {{ $executions->links() }}
        </div>
        @endif
    </div>
</div>
