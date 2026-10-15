<x-layouts.admin :title="__('Blocked Locations')" :subtitle="__('Manage geographic location-based access restrictions.')">
    <div class="space-y-6">

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $blockedLocations->total() }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Total Blocked Locations') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $activeCount ?? 0 }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Active Rules') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $countriesCount ?? 0 }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Countries Blocked') }}</p>
            </div>
        </div>

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Blocked Locations') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage geographic access restrictions by country, state, or city.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'import-locations')">{{ __('Import CSV') }}</button>
                    <a href="{{ route('admin.blocked-locations.export', request()->query()) }}" class="btn-secondary">{{ __('Export CSV') }}</a>
                    <a href="{{ route('admin.blocked-locations.create') }}" class="btn-primary">{{ __('Block Location') }}</a>
                </div>
            </div>

            <form method="get" action="{{ route('admin.blocked-locations.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by country, state, city, or reason') }}">
                </div>
                <div>
                    <label class="sr-only" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="sort">{{ __('Sort') }}</label>
                    <select id="sort" name="sort" class="input-field">
                        <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('Created (Newest)') }}</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('Created (Oldest)') }}</option>
                        <option value="country_asc" @selected(request('sort') === 'country_asc')>{{ __('Country (A-Z)') }}</option>
                        <option value="country_desc" @selected(request('sort') === 'country_desc')>{{ __('Country (Z-A)') }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.blocked-locations.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Blocked Locations Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            allIds: [{{ $blockedLocations->pluck('id')->join(',') }}]
        }" x-init="$watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Location Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $blockedLocations->count() }} {{ __('of') }} {{ $blockedLocations->total() }} {{ __('blocked locations.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-delete-locations')" x-text="'Bulk Delete (' + selected.length + ')'"></button>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">
                                <input type="checkbox" x-model="selectAll" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </th>
                            <th class="pb-3">{{ __('Country') }}</th>
                            <th class="pb-3">{{ __('State') }}</th>
                            <th class="pb-3">{{ __('City') }}</th>
                            <th class="pb-3">{{ __('Reason') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3">{{ __('Created By') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($blockedLocations as $location)
                        <tr>
                            <td class="py-4">
                                <input type="checkbox" value="{{ $location->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </td>
                            <td class="py-4">
                                <div>
                                    <span class="font-semibold text-ink">{{ $location->country_name }}</span>
                                    <p class="text-xs text-muted font-mono">{{ $location->country_code }}</p>
                                </div>
                            </td>
                            <td class="py-4 text-sm text-ink">{{ $location->state ?? '--' }}</td>
                            <td class="py-4 text-sm text-ink">{{ $location->city ?? '--' }}</td>
                            <td class="py-4 text-sm text-ink max-w-[200px] truncate">{{ $location->reason ?? '--' }}</td>
                            <td class="py-4">
                                @if($location->is_active)
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-success/15 text-success rounded-full">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-surface text-ink/80 rounded-full">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="py-4 text-sm text-ink">{{ $location->created_by_name ?? $location->createdBy->name ?? __('System') }}</td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.blocked-locations.edit', $location->id) }}" class="btn-secondary">{{ __('Edit') }}</a>
                                    <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'confirm-delete-location-{{ $location->id }}')">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>

                        <x-admin-modal name="confirm-delete-location-{{ $location->id }}">
                            <form method="POST" action="{{ route('admin.blocked-locations.destroy', $location->id) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">Remove block for "{{ $location->country_name }}{{ $location->state ? ' / ' . $location->state : '' }}{{ $location->city ? ' / ' . $location->city : '' }}"?</p>
                                    <p class="mt-2 text-sm text-muted">{!! __('This location will be <strong class="text-danger">removed from the block list</strong> and users from this area will regain access.') !!}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Remove Block') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-sm text-muted">{{ __('No blocked locations found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($blockedLocations->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $blockedLocations->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Import Modal --}}
    <x-admin-modal name="import-locations">
        <form method="POST" action="{{ route('admin.blocked-locations.import') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Import Blocked Locations') }}</h3>
                <p class="mt-1 text-sm text-muted">{{ __('Upload a CSV file with location data (country_code, country_name, state, city, reason, is_active).') }}</p>
            </div>
            <div>
                <label for="import_file" class="block text-sm font-medium text-ink mb-1.5">{{ __('CSV File') }} <span class="text-danger">*</span></label>
                <input type="file" name="file" id="import_file" accept=".csv,.txt" required
                       class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Upload') }}</button>
            </div>
        </form>
    </x-admin-modal>

    {{-- Bulk Delete Modal --}}
    <x-admin-modal name="bulk-delete-locations">
        <form method="POST" action="{{ route('admin.blocked-locations.bulk-destroy') }}" class="p-6 space-y-4"
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
                <p class="text-lg font-semibold text-ink">{{ __('Delete selected blocked locations?') }}</p>
                <p class="mt-2 text-sm text-muted">{!! __('You are about to <strong class="text-danger">remove</strong> the selected location blocks. Users from these areas will regain access.') !!}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
