<x-layouts.admin :title="__('Security Audit Logs')" :subtitle="__('Monitor security events, login attempts, and threat activity.')">
    <div class="space-y-6">

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ number_format($totalLogs ?? 0) }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Total Logs') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ number_format($todayLogs ?? 0) }}</p>
                <p class="text-xs text-muted mt-1">{{ __("Today's Events") }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ number_format($failedToday ?? 0) }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Failed Today') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ number_format($blockedToday ?? 0) }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Blocked Today') }}</p>
            </div>
        </div>

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Security Audit Logs') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Monitor login attempts, security events, and threat activity.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.security-audit-logs.export', request()->query()) }}" class="btn-secondary">{{ __('Export CSV') }}</a>
                </div>
            </div>

            <form method="get" action="{{ route('admin.security-audit-logs.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by user, IP, or event') }}">
                </div>
                <div>
                    <label class="sr-only" for="event_type">{{ __('Event Type') }}</label>
                    <select id="event_type" name="event_type" class="input-field">
                        <option value="">{{ __('All events') }}</option>
                        @foreach($eventTypes ?? [] as $type)
                            <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="success" @selected(request('status') === 'success')>{{ __('Success') }}</option>
                        <option value="failed" @selected(request('status') === 'failed')>{{ __('Failed') }}</option>
                        <option value="blocked" @selected(request('status') === 'blocked')>{{ __('Blocked') }}</option>
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="from">{{ __('From date') }}</label>
                    <input id="from" type="date" name="from" value="{{ request('from') }}" class="input-field">
                </div>
                <div>
                    <label class="sr-only" for="to">{{ __('To date') }}</label>
                    <input id="to" type="date" name="to" value="{{ request('to') }}" class="input-field">
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.security-audit-logs.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Logs Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            allIds: [{{ $logs->pluck('id')->join(',') }}]
        }" x-init="$watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Log Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }} {{ __('of') }} {{ $logs->total() }} {{ __('log entries.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-delete-logs')" x-text="'Bulk Delete (' + selected.length + ')'"></button>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">
                                <input type="checkbox" x-model="selectAll" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </th>
                            <th class="pb-3">{{ __('Date & Time') }}</th>
                            <th class="pb-3">{{ __('Event Type') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3">{{ __('User') }}</th>
                            <th class="pb-3">{{ __('IP Address') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($logs as $log)
                        <tr>
                            <td class="py-4">
                                <input type="checkbox" value="{{ $log->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </td>
                            <td class="py-4">
                                <span class="text-sm text-ink font-mono">{{ \Carbon\Carbon::parse($log->created_at)->format('M j, Y H:i:s') }}</span>
                            </td>
                            <td class="py-4">
                                @php
                                    $typeColors = [
                                        'login' => 'bg-brand/15 text-brand',
                                        'login_failed' => 'bg-danger/15 text-danger',
                                        'logout' => 'bg-info/15 text-info',
                                        'password_reset' => 'bg-warning/15 text-warning',
                                        'two_factor' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
                                        'ip_blocked' => 'bg-danger/15 text-danger',
                                        'account_locked' => 'bg-danger/15 text-danger',
                                    ];
                                    $typeClass = $typeColors[$log->event_type] ?? 'bg-surface text-ink/80';
                                @endphp
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $typeClass }}">{{ str_replace('_', ' ', ucfirst($log->event_type)) }}</span>
                            </td>
                            <td class="py-4">
                                @if($log->status === 'success')
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-success/15 text-success rounded-full">{{ __('Success') }}</span>
                                @elseif($log->status === 'failed')
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-danger/15 text-danger rounded-full">{{ __('Failed') }}</span>
                                @elseif($log->status === 'blocked')
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-warning/15 text-warning rounded-full">{{ __('Blocked') }}</span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-surface text-ink/80 rounded-full">{{ ucfirst($log->status) }}</span>
                                @endif
                            </td>
                            <td class="py-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-ink">{{ $log->user->name ?? $log->email ?? __('Unknown') }}</span>
                                </div>
                            </td>
                            <td class="py-4">
                                <code class="text-xs font-mono text-muted">{{ $log->ip_address ?? '--' }}</code>
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.security-audit-logs.show', $log->id) }}" class="btn-secondary">{{ __('View') }}</a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-sm text-muted">{{ __('No security audit logs found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $logs->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Bulk Delete Modal --}}
    <x-admin-modal name="bulk-delete-logs">
        <form method="POST" action="{{ route('admin.security-audit-logs.bulk-destroy') }}" class="p-6 space-y-4"
              x-data @submit="
                  const checkboxes = document.querySelectorAll('input[type=checkbox][x-model\\.number=selected]:checked');
                  checkboxes.forEach(cb => {
                      const input = document.createElement('input');
                      input.type = 'hidden'; input.name = 'ids[]'; input.value = cb.value;
                      $el.appendChild(input);
                  });
              ">
            @csrf
            <div>
                <p class="text-lg font-semibold text-ink">{{ __('Delete selected logs?') }}</p>
                <p class="mt-2 text-sm text-muted">{!! __('You are about to <strong class="text-danger">permanently delete</strong> the selected log entries. This action cannot be undone.') !!}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
