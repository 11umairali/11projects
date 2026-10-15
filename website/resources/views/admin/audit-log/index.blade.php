<x-layouts.admin :title="__('Audit Log')" :subtitle="__('Track all admin actions and system events for security and compliance.')">
    <div class="space-y-6">

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Audit Log') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Track admin actions, system events, and security changes.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.audit-log.export', request()->query()) }}" class="btn-secondary">{{ __('Export CSV') }}</a>
                </div>
            </div>

            <form method="get" action="{{ route('admin.audit-log') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by actor or event') }}">
                </div>
                <div>
                    <label class="sr-only" for="event">{{ __('Event') }}</label>
                    <select id="event" name="event" class="input-field">
                        <option value="">{{ __('All events') }}</option>
                        @foreach($eventTypes ?? [] as $eventType)
                            <option value="{{ $eventType }}" @selected(request('event') === $eventType)>{{ str_replace('_', ' ', ucfirst($eventType)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="actor_type">{{ __('Actor type') }}</label>
                    <select id="actor_type" name="actor_type" class="input-field">
                        <option value="">{{ __('All actors') }}</option>
                        <option value="admin" @selected(request('actor_type') === 'admin')>{{ __('Admin') }}</option>
                        <option value="user" @selected(request('actor_type') === 'user')>{{ __('User') }}</option>
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
                    <a href="{{ route('admin.audit-log') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Audit Log Table Panel --}}
        <div class="panel p-6" x-data="{
            view: localStorage.getItem('admin-audit-view') || 'list'
        }" x-init="$watch('view', v => localStorage.setItem('admin-audit-view', v))">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Log Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }} {{ __('of') }} {{ $logs->total() }} {{ __('log entries.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="view = 'list'" :class="view === 'list' ? 'btn-primary' : 'btn-secondary'">{{ __('List') }}</button>
                    <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'btn-primary' : 'btn-secondary'">{{ __('Grid') }}</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <a href="{{ route('admin.audit-log.export', request()->query()) }}" class="btn-secondary">{{ __('Export') }}</a>
                </div>
            </div>

            {{-- List View --}}
            <div x-show="view === 'list'" class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">{{ __('Date & Time') }}</th>
                            <th class="pb-3">{{ __('Actor') }}</th>
                            <th class="pb-3">{{ __('Event') }}</th>
                            <th class="pb-3">{{ __('Target') }}</th>
                            <th class="pb-3">{{ __('IP Address') }}</th>
                            <th class="pb-3">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($logs as $log)
                        <tr>
                            <td class="py-4">
                                <span class="text-sm text-ink font-mono">{{ \Carbon\Carbon::parse($log->created_at)->format('M j, Y H:i:s') }}</span>
                            </td>
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold">
                                        {{ strtoupper(substr($log->actor_name ?? 'SY', 0, 2)) }}
                                    </span>
                                    <div>
                                        <span class="font-semibold text-ink">{{ $log->actor_name ?? __('System') }}</span>
                                        <p class="text-xs text-muted">{{ ucfirst($log->actor_type ?? 'system') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">{{ str_replace('_', ' ', ucfirst($log->event)) }}</span>
                            </td>
                            <td class="py-4 text-sm text-ink">
                                @if($log->auditable_type)
                                    {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                @else
                                    --
                                @endif
                            </td>
                            <td class="py-4">
                                <code class="text-xs font-mono text-muted">{{ $log->ip_address ?? '--' }}</code>
                            </td>
                            <td class="py-4 text-sm text-ink max-w-[250px] truncate">
                                @if($log->new_values)
                                    @php $vals = json_decode($log->new_values, true); @endphp
                                    @if(is_array($vals))
                                        {{ \Illuminate\Support\Str::limit(implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($vals), array_values($vals))), 80) }}
                                    @endif
                                @elseif($log->old_values)
                                    @php $vals = json_decode($log->old_values, true); @endphp
                                    @if(is_array($vals))
                                        {{ \Illuminate\Support\Str::limit(implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($vals), array_values($vals))), 80) }}
                                    @endif
                                @else
                                    --
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-sm text-muted">{{ __('No audit logs found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Grid View --}}
            <div x-show="view === 'grid'" x-cloak class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($logs as $log)
                <div class="panel p-4 space-y-2">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold">
                            {{ strtoupper(substr($log->actor_name ?? 'SY', 0, 2)) }}
                        </span>
                        <div class="min-w-0">
                            <span class="font-semibold text-ink text-sm">{{ $log->actor_name ?? __('System') }}</span>
                            <p class="text-xs text-muted">{{ ucfirst($log->actor_type ?? 'system') }}</p>
                        </div>
                    </div>
                    <div class="text-sm font-semibold text-ink">{{ str_replace('_', ' ', ucfirst($log->event)) }}</div>
                    <div class="flex items-center justify-between text-xs text-muted">
                        <span>
                            @if($log->auditable_type)
                                {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                            @else
                                --
                            @endif
                        </span>
                        <code class="font-mono">{{ $log->ip_address ?? '--' }}</code>
                    </div>
                    <div class="text-xs text-muted font-mono">{{ \Carbon\Carbon::parse($log->created_at)->format('M j, Y H:i:s') }}</div>
                </div>
                @empty
                <div class="col-span-full py-6 text-center text-sm text-muted">{{ __('No audit logs found.') }}</div>
                @endforelse
            </div>

            @if($logs->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $logs->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
