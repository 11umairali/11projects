<x-layouts.admin :title="__('Permissions')" :subtitle="__('Manage all permissions used across roles.')">
    <div class="space-y-6">

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Permissions') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage fine-grained permissions grouped by module.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'import-permissions')">{{ __('Import CSV') }}</button>
                    <a href="{{ route('admin.permissions.export', request()->query()) }}" class="btn-secondary">{{ __('Export CSV') }}</a>
                    <a href="{{ route('admin.permissions.create') }}" class="btn-primary">{{ __('Create Permission') }}</a>
                </div>
            </div>

            <form method="get" action="{{ route('admin.permissions.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by permission name') }}">
                </div>
                <div>
                    <label class="sr-only" for="guard">{{ __('Guard') }}</label>
                    <select id="guard" name="guard" class="input-field">
                        <option value="">{{ __('All guards') }}</option>
                        <option value="web" @selected(request('guard') === 'web')>{{ __('Web') }}</option>
                        <option value="api" @selected(request('guard') === 'api')>{{ __('API') }}</option>
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="module">{{ __('Module') }}</label>
                    <select id="module" name="module" class="input-field">
                        <option value="">{{ __('All modules') }}</option>
                        @foreach($modules ?? [] as $mod)
                            <option value="{{ $mod }}" @selected(request('module') === $mod)>{{ ucfirst($mod) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.permissions.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Permissions Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            allIds: [{{ $permissions->pluck('id')->join(',') }}]
        }" x-init="$watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Permission Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $permissions->count() }} {{ __('of') }} {{ $permissions->total() }} {{ __('permissions.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-delete-permissions')" x-text="'Bulk Delete (' + selected.length + ')'"></button>
                </div>
            </div>

            @php
                $groupedPerms = $permissions->groupBy(function ($permission) {
                    $parts = explode('.', $permission->name);
                    return count($parts) > 1 ? $parts[0] : 'general';
                });
            @endphp

            <div class="mt-6 space-y-4">
                @forelse($groupedPerms as $module => $modulePermissions)
                <div class="border border-border rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-surface border-b border-border flex items-center justify-between">
                        <span class="text-sm font-bold text-ink uppercase tracking-wider">{{ ucfirst($module) }}</span>
                        <span class="text-xs text-muted">{{ $modulePermissions->count() }} {{ __('permissions') }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                                <tr>
                                    <th class="px-4 pb-3 pt-3">
                                        <input type="checkbox" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40"
                                               @click="
                                                   const ids = [{{ $modulePermissions->pluck('id')->join(',') }}];
                                                   const allChecked = ids.every(id => selected.includes(id));
                                                   if (allChecked) { selected = selected.filter(id => !ids.includes(id)); }
                                                   else { ids.forEach(id => { if (!selected.includes(id)) selected.push(id); }); }
                                               ">
                                    </th>
                                    <th class="px-4 pb-3 pt-3">{{ __('Name') }}</th>
                                    <th class="px-4 pb-3 pt-3">{{ __('Guard') }}</th>
                                    <th class="px-4 pb-3 pt-3">{{ __('Roles') }}</th>
                                    <th class="px-4 pb-3 pt-3 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                @foreach($modulePermissions as $permission)
                                <tr>
                                    <td class="px-4 py-3">
                                        <input type="checkbox" value="{{ $permission->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-ink">{{ $permission->name }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $permission->guard_name === 'web' ? 'bg-brand/15 text-brand' : 'bg-warning/15 text-warning' }}">{{ $permission->guard_name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-ink">{{ $permission->roles_count ?? $permission->roles->count() }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('admin.permissions.edit', $permission->id) }}" class="btn-secondary">{{ __('Edit') }}</a>
                                            <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'confirm-delete-perm-{{ $permission->id }}')">{{ __('Delete') }}</button>
                                        </div>
                                    </td>
                                </tr>

                                <x-admin-modal name="confirm-delete-perm-{{ $permission->id }}">
                                    <form method="POST" action="{{ route('admin.permissions.destroy', $permission->id) }}" class="p-6 space-y-4">
                                        @csrf
                                        @method('DELETE')
                                        <div>
                                            <p class="text-lg font-semibold text-ink">{{ __('Delete') }} "{{ $permission->name }}"?</p>
                                            <p class="mt-2 text-sm text-muted">{{ __('This permission will be') }} <strong class="text-danger">{{ __('permanently deleted') }}</strong> {{ __('and removed from all roles.') }}</p>
                                        </div>
                                        <div class="flex justify-end gap-3">
                                            <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                            <button type="submit" class="btn-danger">{{ __('Delete Permission') }}</button>
                                        </div>
                                    </form>
                                </x-admin-modal>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @empty
                <div class="py-6 text-center text-sm text-muted">{{ __('No permissions found.') }}</div>
                @endforelse
            </div>

            @if($permissions->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $permissions->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Import Modal --}}
    <x-admin-modal name="import-permissions">
        <form method="POST" action="{{ route('admin.permissions.import') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Import Permissions') }}</h3>
                <p class="mt-1 text-sm text-muted">{{ __('Upload a CSV file with permission definitions (name, guard_name).') }}</p>
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
    <x-admin-modal name="bulk-delete-permissions">
        <form method="POST" action="{{ route('admin.permissions.bulk-destroy') }}" class="p-6 space-y-4"
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
                <p class="text-lg font-semibold text-ink">{{ __('Delete selected permissions?') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('You are about to') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('the selected permission(s). They will be removed from all roles.') }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
