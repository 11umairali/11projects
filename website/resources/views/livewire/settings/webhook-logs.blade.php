<div>
    {{-- Status filter tabs --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        @foreach(['' => __('All'), 'success' => __('Success'), 'failed' => __('Failed'), 'retrying' => __('Retrying'), 'pending' => __('Pending')] as $value => $label)
            <button wire:click="$set('statusFilter', '{{ $value }}')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition-colors
                        {{ $statusFilter === $value
                            ? 'bg-brand/10 text-brand dark:bg-brand/20'
                            : 'text-muted hover:text-ink hover:bg-surface-3' }}">
                {{ $label }}
                <span class="text-xs text-muted">({{ $counts[$value ?: 'all'] }})</span>
            </button>
        @endforeach
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <div class="relative max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
            </svg>
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="{{ __('Search by URL...') }}"
                   class="input pl-10 w-full">
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Table --}}
    <div class="panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left">
                        <th class="px-4 py-3 font-medium text-muted">
                            <button wire:click="sortBy('url')" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ __('URL') }}
                                @if($sortField === 'url')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium text-muted w-20">{{ __('Method') }}</th>
                        <th class="px-4 py-3 font-medium text-muted w-24">
                            <button wire:click="sortBy('status')" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ __('Status') }}
                                @if($sortField === 'status')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium text-muted w-20">
                            <button wire:click="sortBy('response_status')" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ __('Code') }}
                                @if($sortField === 'response_status')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium text-muted w-24">
                            <button wire:click="sortBy('duration_ms')" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ __('Duration') }}
                                @if($sortField === 'duration_ms')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium text-muted w-20">
                            <button wire:click="sortBy('attempts')" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ __('Tries') }}
                                @if($sortField === 'attempts')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium text-muted w-36">
                            <button wire:click="sortBy('created_at')" class="inline-flex items-center gap-1 hover:text-ink">
                                {{ __('Time') }}
                                @if($sortField === 'created_at')
                                    <svg class="w-3 h-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"/></svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium text-muted w-28 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($logs as $log)
                        <tr class="hover:bg-surface-3/50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 max-w-xs">
                                    <span class="truncate text-ink font-mono text-xs" title="{{ $log->url }}">{{ $log->url }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold
                                    {{ match($log->method) {
                                        'GET' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                        'POST' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                        'PUT', 'PATCH' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                        'DELETE' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400',
                                    } }}">
                                    {{ $log->method }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ match($log->status) {
                                        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                        'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                        'retrying' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                        'pending' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                        default => 'bg-gray-100 text-gray-600',
                                    } }}">
                                    @if($log->status === 'retrying')
                                        <svg class="w-3 h-3 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                                    @endif
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-muted">
                                {{ $log->response_status ?? '---' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted">
                                @if($log->duration_ms !== null)
                                    {{ $log->duration_ms >= 1000 ? number_format($log->duration_ms / 1000, 1) . 's' : $log->duration_ms . 'ms' }}
                                @else
                                    ---
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-muted">
                                {{ $log->attempts }}/{{ $log->max_attempts }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted" title="{{ $log->created_at?->toDateTimeString() }}">
                                {{ $log->created_at?->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="viewLog({{ $log->id }})"
                                            class="p-1.5 rounded-md text-muted hover:text-ink hover:bg-surface-3 transition-colors"
                                            :title="__('View details')">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    @if(in_array($log->status, ['failed', 'retrying']))
                                        <button wire:click="retryWebhook({{ $log->id }})"
                                                wire:confirm="{{ __('Are you sure you want to retry this webhook?') }}"
                                                class="p-1.5 rounded-md text-muted hover:text-brand hover:bg-brand/10 transition-colors"
                                                :title="__('Retry')">
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="23 4 23 10 17 10"></polyline>
                                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-10 h-10 text-muted/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                    </svg>
                                    <p class="text-sm text-muted">{{ __('No webhook logs found.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-border">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal --}}
    @if($viewingLog)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-trap.noscroll="true">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="closeModal"></div>

            {{-- Modal content --}}
            <div class="relative bg-surface-2 rounded-2xl border border-border shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto">
                {{-- Header --}}
                <div class="sticky top-0 bg-surface-2 border-b border-border px-6 py-4 flex items-center justify-between z-10">
                    <h3 class="text-lg font-semibold text-ink">{{ __('Webhook Details') }}</h3>
                    <button wire:click="closeModal" class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-surface-3 transition-colors">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-5">
                    {{-- Summary --}}
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('URL') }}</span>
                            <span class="text-ink font-mono text-xs break-all">{{ $viewingLog->url }}</span>
                        </div>
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('Method') }}</span>
                            <span class="text-ink font-semibold">{{ $viewingLog->method }}</span>
                        </div>
                        <div>
                            <span class="text-muted block text-xs mb-1">Status</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                {{ match($viewingLog->status) {
                                    'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                    'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'retrying' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    'pending' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    default => 'bg-gray-100 text-gray-600',
                                } }}">{{ ucfirst($viewingLog->status) }}</span>
                        </div>
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('Response Code') }}</span>
                            <span class="text-ink font-mono">{{ $viewingLog->response_status ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-muted block text-xs mb-1">Duration</span>
                            <span class="text-ink">{{ $viewingLog->duration_ms ? $viewingLog->duration_ms . 'ms' : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('Attempts') }}</span>
                            <span class="text-ink">{{ $viewingLog->attempts }}/{{ $viewingLog->max_attempts }}</span>
                        </div>
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('Direction') }}</span>
                            <span class="text-ink">{{ ucfirst($viewingLog->direction) }}</span>
                        </div>
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('Timestamp') }}</span>
                            <span class="text-ink">{{ $viewingLog->created_at?->format('M j, Y H:i:s') }}</span>
                        </div>
                    </div>

                    @if($viewingLog->error_message)
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('Error') }}</span>
                            <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/10 text-red-700 dark:text-red-400 text-xs font-mono break-all">
                                {{ $viewingLog->error_message }}
                            </div>
                        </div>
                    @endif

                    {{-- Request Headers --}}
                    @if($viewingLog->headers)
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('Request Headers') }}</span>
                            <pre class="p-3 rounded-lg bg-surface-3 text-xs font-mono text-ink overflow-x-auto max-h-40">{{ json_encode($viewingLog->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endif

                    {{-- Request Payload --}}
                    @if($viewingLog->payload)
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('Request Payload') }}</span>
                            <pre class="p-3 rounded-lg bg-surface-3 text-xs font-mono text-ink overflow-x-auto max-h-60">{{ json_encode($viewingLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endif

                    {{-- Response Body --}}
                    @if($viewingLog->response_body)
                        <div>
                            <span class="text-muted block text-xs mb-1">{{ __('Response Body') }}</span>
                            <pre class="p-3 rounded-lg bg-surface-3 text-xs font-mono text-ink overflow-x-auto max-h-60">{{ $viewingLog->response_body }}</pre>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="sticky bottom-0 bg-surface-2 border-t border-border px-6 py-3 flex justify-end gap-2">
                    @if(in_array($viewingLog->status, ['failed', 'retrying']))
                        <button wire:click="retryWebhook({{ $viewingLog->id }})"
                                wire:confirm="{{ __('Retry this webhook delivery?') }}"
                                class="btn btn-primary text-sm">
                            <svg class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                            Retry
                        </button>
                    @endif
                    <button wire:click="closeModal" class="btn btn-secondary text-sm">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
