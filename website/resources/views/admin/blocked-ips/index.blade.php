<x-layouts.admin :title="__('Blocked IPs')" :subtitle="__('Manage IP addresses blocked from accessing the application.')">
    <div class="space-y-6">

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Blocked IPs') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage blocked IP addresses and access restrictions.') }}</p>
                </div>
            </div>

            {{-- Inline Block IP Form --}}
            <div class="mt-6 border-t border-border/60 pt-6">
                <p class="text-sm font-semibold text-ink mb-4">{{ __('Block New IP Address') }}</p>
                <form method="POST" action="{{ route('admin.blocked-ips.store') }}" class="filter-form grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                    @csrf
                    <div>
                        <label class="sr-only" for="ip_address">{{ __('IP Address') }}</label>
                        <input id="ip_address" type="text" name="ip_address" value="{{ old('ip_address') }}" class="input-field" placeholder="{{ __('e.g. 192.168.1.100') }}" required>
                        @error('ip_address')
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="sr-only" for="reason">{{ __('Reason') }}</label>
                        <input id="reason" type="text" name="reason" value="{{ old('reason') }}" class="input-field" placeholder="{{ __('e.g. Brute force attack, spam') }}">
                    </div>
                    <div>
                        <label class="sr-only" for="duration">{{ __('Duration') }}</label>
                        <select id="duration" name="duration" class="input-field">
                            <option value="permanent">{{ __('Permanent') }}</option>
                            <option value="1h">{{ __('1 Hour') }}</option>
                            <option value="24h">{{ __('24 Hours') }}</option>
                            <option value="7d">{{ __('7 Days') }}</option>
                            <option value="30d">{{ __('30 Days') }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" class="btn-danger">{{ __('Block IP') }}</button>
                    </div>
                </form>
            </div>

            {{-- Search Filter --}}
            <form method="get" action="{{ route('admin.blocked-ips.index') }}" class="filter-form mt-6 border-t border-border/60 pt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_auto] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by IP, reason, or blocked by') }}">
                </div>
                <div>
                    <label class="sr-only" for="filter_status">{{ __('Status') }}</label>
                    <select id="filter_status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="permanent" @selected(request('status') === 'permanent')>{{ __('Permanent') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('Active (Temporary)') }}</option>
                        <option value="expired" @selected(request('status') === 'expired')>{{ __('Expired') }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.blocked-ips.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Blocked IPs Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            view: localStorage.getItem('admin-blocked-ips-view') || 'list',
            allIds: [{{ $blockedIps->pluck('id')->join(',') }}]
        }" x-init="$watch('view', v => localStorage.setItem('admin-blocked-ips-view', v)); $watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Blocked IP Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $blockedIps->count() }} {{ __('of') }} {{ $blockedIps->total() }} {{ __('blocked IPs.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="view = 'list'" :class="view === 'list' ? 'btn-primary' : 'btn-secondary'">{{ __('List') }}</button>
                    <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'btn-primary' : 'btn-secondary'">{{ __('Grid') }}</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-unblock-ips')" x-text="'Bulk Unblock (' + selected.length + ')'"></button>
                </div>
            </div>

            {{-- List View --}}
            <div x-show="view === 'list'" class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">
                                <input type="checkbox" x-model="selectAll" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </th>
                            <th class="pb-3">{{ __('IP Address') }}</th>
                            <th class="pb-3">{{ __('Reason') }}</th>
                            <th class="pb-3">{{ __('Blocked By') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3">{{ __('Blocked Until') }}</th>
                            <th class="pb-3">{{ __('Created') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($blockedIps as $blocked)
                            @php
                                $isPermanent = is_null($blocked->blocked_until);
                                $isExpired   = !$isPermanent && \Carbon\Carbon::parse($blocked->blocked_until)->isPast();
                            @endphp
                        <tr>
                            <td class="py-4">
                                <input type="checkbox" value="{{ $blocked->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </td>
                            <td class="py-4">
                                <span class="font-mono text-sm font-semibold text-ink">{{ $blocked->ip_address }}</span>
                            </td>
                            <td class="py-4 text-sm text-ink">{{ $blocked->reason ?? '-' }}</td>
                            <td class="py-4 text-sm text-ink">{{ $blocked->blocked_by ?? __('System') }}</td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">
                                    @if($isPermanent)
                                        {{ __('Permanent') }}
                                    @elseif($isExpired)
                                        {{ __('Expired') }}
                                    @else
                                        {{ __('Active') }}
                                    @endif
                                </span>
                            </td>
                            <td class="py-4 text-sm text-ink">
                                @if($isPermanent)
                                    {{ __('Never') }}
                                @else
                                    {{ \Carbon\Carbon::parse($blocked->blocked_until)->format('M j, Y H:i') }}
                                @endif
                            </td>
                            <td class="py-4 text-sm text-ink">{{ \Carbon\Carbon::parse($blocked->created_at)->format('M j, Y') }}</td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'confirm-unblock-{{ $blocked->id }}')">{{ __('Unblock') }}</button>
                                </div>
                            </td>
                        </tr>

                        {{-- Unblock confirmation modal --}}
                        <x-admin-modal name="confirm-unblock-{{ $blocked->id }}">
                            <form method="POST" action="{{ route('admin.blocked-ips.destroy', $blocked->id) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Unblock') }} "{{ $blocked->ip_address }}"?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('This IP address will be able to access the application again. You can always re-block it later.') }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Unblock IP') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-sm text-muted">{{ __('No blocked IPs found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Grid View --}}
            <div x-show="view === 'grid'" x-cloak class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($blockedIps as $blocked)
                    @php
                        $isPermanent = is_null($blocked->blocked_until);
                        $isExpired   = !$isPermanent && \Carbon\Carbon::parse($blocked->blocked_until)->isPast();
                    @endphp
                <div class="panel p-4 space-y-2">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" value="{{ $blocked->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                        <span class="font-mono text-sm font-semibold text-ink">{{ $blocked->ip_address }}</span>
                    </div>
                    <div class="text-sm text-ink">{{ $blocked->reason ?? __('No reason') }}</div>
                    <div class="flex items-center justify-between text-xs text-muted">
                        <span>{{ $blocked->blocked_by ?? __('System') }}</span>
                        <span class="font-semibold text-ink">
                            @if($isPermanent) {{ __('Permanent') }} @elseif($isExpired) {{ __('Expired') }} @else {{ __('Active') }} @endif
                        </span>
                    </div>
                    <div class="pt-2 border-t border-border/60">
                        <button type="button" class="btn-secondary text-xs" x-data @click="$dispatch('open-modal', 'confirm-unblock-{{ $blocked->id }}')">{{ __('Unblock') }}</button>
                    </div>
                </div>
                @empty
                <div class="col-span-full py-6 text-center text-sm text-muted">{{ __('No blocked IPs found.') }}</div>
                @endforelse
            </div>

            @if($blockedIps->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $blockedIps->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Bulk Unblock Modal --}}
    <x-admin-modal name="bulk-unblock-ips">
        <form method="POST" action="{{ route('admin.blocked-ips.bulk-destroy') }}" class="p-6 space-y-4"
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
                <p class="text-lg font-semibold text-ink">{{ __('Unblock selected IPs?') }}</p>
                <p class="mt-2 text-sm text-muted">{!! __('The selected IP address(es) will be <strong class="text-danger">removed from the block list</strong> and will be able to access the application again.') !!}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Unblock All Selected') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
