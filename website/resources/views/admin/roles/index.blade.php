<x-layouts.admin :title="__('Roles & Permissions')" :subtitle="__('Manage roles, permissions, and access control.')">
    <div class="space-y-6">

        {{-- Stats Bar --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $roles->total() }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Total Roles') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $totalPermissions ?? 0 }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Total Permissions') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $totalGuards ?? 0 }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Guard Types') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $totalUsersWithRoles ?? 0 }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Users with Roles') }}</p>
            </div>
        </div>

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Roles') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage roles and their assigned permissions.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'import-roles')">{{ __('Import CSV') }}</button>
                    <a href="{{ route('admin.roles.export', request()->query()) }}" class="btn-secondary">{{ __('Export CSV') }}</a>
                    <a href="{{ route('admin.roles.create') }}" class="btn-primary">{{ __('Create Role') }}</a>
                </div>
            </div>

            <form method="get" action="{{ route('admin.roles.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by role name') }}">
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
                    <label class="sr-only" for="sort">{{ __('Sort') }}</label>
                    <select id="sort" name="sort" class="input-field">
                        <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('Created (Newest)') }}</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('Created (Oldest)') }}</option>
                        <option value="name_asc" @selected(request('sort') === 'name_asc')>{{ __('Name (A-Z)') }}</option>
                        <option value="name_desc" @selected(request('sort') === 'name_desc')>{{ __('Name (Z-A)') }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.roles.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Roles Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            allIds: [{{ $roles->pluck('id')->join(',') }}]
        }" x-init="$watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Role Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $roles->count() }} {{ __('of') }} {{ $roles->total() }} {{ __('roles.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-delete-roles')" x-text="'Bulk Delete (' + selected.length + ')'"></button>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">
                                <input type="checkbox" x-model="selectAll" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </th>
                            <th class="pb-3">{{ __('Name') }}</th>
                            <th class="pb-3">{{ __('Guard') }}</th>
                            <th class="pb-3">{{ __('Permissions') }}</th>
                            <th class="pb-3">{{ __('Users') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($roles as $role)
                        <tr>
                            <td class="py-4">
                                <input type="checkbox" value="{{ $role->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </td>
                            <td class="py-4">
                                <span class="font-semibold text-ink">{{ $role->name }}</span>
                            </td>
                            <td class="py-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $role->guard_name === 'web' ? 'bg-brand/15 text-brand' : 'bg-warning/15 text-warning' }}">{{ $role->guard_name }}</span>
                            </td>
                            <td class="py-4 text-sm text-ink">{{ $role->permissions_count ?? $role->permissions->count() }}</td>
                            <td class="py-4 text-sm text-ink">{{ $role->users_count ?? $role->users->count() }}</td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.roles.show', $role->id) }}" class="btn-secondary">{{ __('View') }}</a>
                                    <a href="{{ route('admin.roles.edit', $role->id) }}" class="btn-secondary">{{ __('Edit') }}</a>
                                    @if($role->name !== 'Super Admin')
                                    <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'confirm-delete-role-{{ $role->id }}')">{{ __('Delete') }}</button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Delete confirmation modal --}}
                        @if($role->name !== 'Super Admin')
                        <x-admin-modal name="confirm-delete-role-{{ $role->id }}">
                            <form method="POST" action="{{ route('admin.roles.destroy', $role->id) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Delete') }} "{{ $role->name }}"?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('This role will be') }} <strong class="text-danger">{{ __('permanently deleted') }}</strong>. {{ __('Users assigned to this role will lose their permissions.') }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Delete Role') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @endif
                        @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-sm text-muted">{{ __('No roles found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($roles->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $roles->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Import Modal --}}
    <x-admin-modal name="import-roles">
        <form method="POST" action="{{ route('admin.roles.import') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Import Roles') }}</h3>
                <p class="mt-1 text-sm text-muted">{{ __('Upload a CSV file with role definitions.') }}</p>
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
    <x-admin-modal name="bulk-delete-roles">
        <form method="POST" action="{{ route('admin.roles.bulk-destroy') }}" class="p-6 space-y-4"
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
                <p class="text-lg font-semibold text-ink">{{ __('Delete selected roles?') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('You are about to') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('the selected role(s). Users assigned to these roles will lose their permissions.') }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
