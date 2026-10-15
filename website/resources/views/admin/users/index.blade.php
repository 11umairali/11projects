<x-layouts.admin :title="__('Users')" :subtitle="__('Manage all registered users on the platform.')">
    <div class="space-y-6">

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Users') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage user accounts, roles, and access control.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'import-users')">{{ __('Import CSV') }}</button>
                    <a href="{{ route('admin.users.export', ['template' => 1]) }}" class="btn-secondary">{{ __('Download Template') }}</a>
                    <a href="{{ route('admin.users.create') }}" class="btn-primary">{{ __('Add User') }}</a>
                </div>
            </div>

            <form method="get" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by name or email') }}">
                </div>
                <div>
                    <label class="sr-only" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                        <option value="suspended" @selected(request('status') === 'suspended')>{{ __('Suspended') }}</option>
                        <option value="banned" @selected(request('status') === 'banned')>{{ __('Banned') }}</option>
                        <option value="pending_deletion" @selected(request('status') === 'pending_deletion')>{{ __('Pending Deletion') }}</option>
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
                    <a href="{{ route('admin.users.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Users Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            view: localStorage.getItem('admin-users-view') || 'list',
            allIds: [{{ $users->pluck('id')->join(',') }}],
            impersonateUrl: '',
            impersonateUserName: '',
            openImpersonate(url, name) {
                this.impersonateUrl = url;
                this.impersonateUserName = name;
                this.$dispatch('open-modal', 'impersonate-user');
            }
        }" x-init="$watch('view', v => localStorage.setItem('admin-users-view', v)); $watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('User Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $users->count() }} {{ __('of') }} {{ $users->total() }} {{ __('users.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="view = 'list'" :class="view === 'list' ? 'btn-primary' : 'btn-secondary'">{{ __('List') }}</button>
                    <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'btn-primary' : 'btn-secondary'">{{ __('Grid') }}</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <a href="{{ route('admin.users.export', request()->query()) }}" class="btn-secondary">{{ __('Export') }}</a>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-delete-users')" x-text="'{{ __('Bulk Delete') }} (' + selected.length + ')'"></button>
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
                            <th class="pb-3">{{ __('User') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3">{{ __('Workspaces') }}</th>
                            <th class="pb-3">{{ __('Joined') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($users as $user)
                        <tr>
                            <td class="py-4">
                                <input type="checkbox" value="{{ $user->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </td>
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold">{{ $user->initials }}</span>
                                    <div>
                                        <a href="{{ route('admin.users.show', $user->id) }}" class="font-semibold text-ink hover:text-brand transition-colors">{{ $user->name }}</a>
                                        <p class="text-xs text-muted">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">{{ $user->suspended_at ? 'Suspended' : ucfirst($user->status) }}</span>
                            </td>
                            <td class="py-4 text-sm text-ink">{{ $user->workspaces_count ?? $user->workspaces()->count() }}</td>
                            <td class="py-4 text-sm text-ink">{{ $user->created_at->format('M j, Y') }}</td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.users.show', $user->id) }}" class="btn-secondary">{{ __('View') }}</a>
                                    <button type="button" class="btn-secondary" @click="openImpersonate('{{ route('admin.users.impersonate', $user->id) }}', '{{ addslashes($user->name) }}')">{{ __('Impersonate') }}</button>
                                    <button type="button" class="btn-danger" @click="$dispatch('open-modal', 'confirm-delete-user-{{ $user->id }}')">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>

                        {{-- Delete confirmation modal --}}
                        <x-admin-modal name="confirm-delete-user-{{ $user->id }}">
                            <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Delete') }} "{{ $user->name }}"?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('This user and') }} <strong class="text-danger">{{ __('all their data') }}</strong> {{ __('will be') }} <strong class="text-danger">{{ __('permanently deleted') }}</strong> {{ __('from the system. This action cannot be undone.') }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-sm text-muted">{{ __('No users found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Grid View --}}
            <div x-show="view === 'grid'" x-cloak class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse($users as $user)
                <div class="rounded-2xl border border-border bg-surface-2 p-5 space-y-4 overflow-hidden min-w-0">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" value="{{ $user->id }}" x-model.number="selected" class="mt-1 h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand font-bold">{{ $user->initials }}</span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('admin.users.show', $user->id) }}" class="font-semibold text-ink hover:text-brand transition-colors truncate block">{{ $user->name }}</a>
                            <p class="text-xs text-muted truncate">{{ $user->email }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Status') }}</p>
                            <p class="mt-0.5 font-semibold text-ink">{{ $user->suspended_at ? 'Suspended' : ucfirst($user->status) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Joined') }}</p>
                            <p class="mt-0.5 text-ink">{{ $user->created_at->format('M j, Y') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 pt-3 border-t border-border/60">
                        <a href="{{ route('admin.users.show', $user->id) }}" class="btn-secondary btn-sm flex-1 justify-center text-[10px]">{{ __('View') }}</a>
                        <button type="button" class="btn-secondary btn-sm flex-1 justify-center text-[10px]" @click="openImpersonate('{{ route('admin.users.impersonate', $user->id) }}', '{{ addslashes($user->name) }}')">{{ __('Impersonate') }}</button>
                        <button type="button" class="btn-danger btn-sm flex-1 justify-center text-[10px]" @click="$dispatch('open-modal', 'confirm-delete-user-{{ $user->id }}')">{{ __('Delete') }}</button>
                    </div>
                </div>
                @empty
                <div class="col-span-full py-6 text-center text-sm text-muted">{{ __('No users found.') }}</div>
                @endforelse
            </div>

            @if($users->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $users->withQueryString()->links() }}
            </div>
            @endif

            {{-- Impersonate Confirmation Modal (inside x-data scope so :action binding works) --}}
            <x-admin-modal name="impersonate-user">
                <form method="POST" :action="impersonateUrl" class="p-6 space-y-4">
                    @csrf
                    <div>
                        <p class="text-lg font-semibold text-ink">{{ __('Impersonate User') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('You are about to log in as') }} <strong class="text-ink" x-text="impersonateUserName"></strong>. {{ __('Enter your admin password to confirm.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Your Password') }}</label>
                        <input type="password" name="confirm_password" required autofocus
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 bg-surface placeholder-muted"
                               placeholder="{{ __('Enter your admin password') }}">
                        @error('confirm_password')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn-primary">{{ __('Impersonate') }}</button>
                    </div>
                </form>
            </x-admin-modal>
        </div>
    </div>

    {{-- Import Modal --}}
    <x-admin-modal name="import-users">
        <form method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Import Users') }}</h3>
                <p class="mt-1 text-sm text-muted">{{ __('Upload a CSV file that matches the provided template.') }}</p>
            </div>
            <x-file-uploader name="file" accept=".csv,.txt" :label="__('CSV File')" />
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Upload') }}</button>
            </div>
        </form>
    </x-admin-modal>

    {{-- Bulk Delete Modal --}}
    <x-admin-modal name="bulk-delete-users">
        <form method="POST" action="{{ route('admin.users.bulk-destroy') }}" class="p-6 space-y-4"
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
                <p class="text-lg font-semibold text-ink">{{ __('Delete selected users?') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('You are about to') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('the selected user(s). This action cannot be undone.') }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
