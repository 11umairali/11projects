<x-layouts.admin :title="__('Edit Role')" :subtitle="__('Update role details and permission assignments for') . ' ' . $role->name . '.'">
    <div class="space-y-6" x-data="roleEditForm()">

        <form method="POST" action="{{ route('admin.roles.update', $role->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Role Information --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Role Information') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Update the role name and guard type.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Name --}}
                        <div>
                            <label for="name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Role Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name', $role->name) }}" required
                                   placeholder="{{ __('e.g. Editor, Moderator') }}"
                                   {{ $role->name === 'Super Admin' ? 'disabled' : '' }}
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('name') !border-danger !ring-danger/20 @enderror {{ $role->name === 'Super Admin' ? 'opacity-60 cursor-not-allowed' : '' }}">
                            @if($role->name === 'Super Admin')
                                <p class="text-xs text-warning mt-1">{{ __('The Super Admin role name cannot be changed.') }}</p>
                            @endif
                            @error('name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Guard --}}
                        <div>
                            <label for="guard_name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Guard') }} <span class="text-danger">*</span></label>
                            <select name="guard_name" id="guard_name" required
                                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('guard_name') !border-danger !ring-danger/20 @enderror">
                                <option value="web" {{ old('guard_name', $role->guard_name) === 'web' ? 'selected' : '' }}>{{ __('Web') }}</option>
                                <option value="api" {{ old('guard_name', $role->guard_name) === 'api' ? 'selected' : '' }}>{{ __('API') }}</option>
                            </select>
                            @error('guard_name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Current Role Details --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Role Details') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Current role assignment overview.') }}</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Created') }}</p>
                            <p class="mt-0.5 font-semibold text-ink">{{ $role->created_at->format('M j, Y') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Guard') }}</p>
                            <p class="mt-0.5 font-semibold text-ink">{{ $role->guard_name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Permissions') }}</p>
                            <p class="mt-0.5 font-semibold text-ink">{{ $role->permissions->count() }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Users') }}</p>
                            <p class="mt-0.5 font-semibold text-ink">{{ $role->users->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Permissions --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Permissions') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Update the permissions assigned to this role. Changes apply immediately.') }}</p>
                </div>
                <div class="p-6 space-y-6">
                    @error('permissions')
                        <div class="p-3 bg-danger/10 border border-danger/20 rounded-xl text-sm text-danger">{{ $message }}</div>
                    @enderror

                    @forelse($groupedPermissions ?? [] as $module => $permissions)
                    <div class="border border-border rounded-xl overflow-hidden">
                        <div class="px-4 py-3 bg-surface border-b border-border flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" @click="toggleModule('{{ $module }}')" :checked="isModuleSelected('{{ $module }}')"
                                           class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                                    <span class="text-sm font-bold text-ink uppercase tracking-wider">{{ ucfirst($module) }}</span>
                                </label>
                            </div>
                            <span class="text-xs text-muted">{{ count($permissions) }} {{ __('permissions') }}</span>
                        </div>
                        <div class="p-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                            @foreach($permissions as $permission)
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                       x-model.number="selectedPermissions"
                                       class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                                <span class="text-sm text-ink group-hover:text-brand transition-colors">{{ $permission->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-6">
                        <p class="text-sm text-muted">{{ __('No permissions available.') }} <a href="{{ route('admin.permissions.create') }}" class="text-brand hover:underline">{{ __('Create one') }}</a>.</p>
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-muted border border-border rounded-xl hover:bg-surface transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    {{ __('Back to Roles') }}
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all"
                        x-data @click.prevent="if(confirm('{{ __('Are you sure you want to update the permissions for this role? Changes will apply immediately to all users with this role.') }}')) $el.closest('form').submit()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Update Role') }}
                </button>
            </div>
        </form>
    </div>

    <script>
    function roleEditForm() {
        return {
            selectedPermissions: @json(old('permissions', $role->permissions->pluck('id')->toArray())),
            modulePermissions: @json(collect($groupedPermissions ?? [])->map(fn($perms) => collect($perms)->pluck('id')->toArray())->toArray()),
            toggleModule(module) {
                const ids = this.modulePermissions[module] || [];
                const allSelected = ids.every(id => this.selectedPermissions.includes(id));
                if (allSelected) {
                    this.selectedPermissions = this.selectedPermissions.filter(id => !ids.includes(id));
                } else {
                    ids.forEach(id => { if (!this.selectedPermissions.includes(id)) this.selectedPermissions.push(id); });
                }
            },
            isModuleSelected(module) {
                const ids = this.modulePermissions[module] || [];
                return ids.length > 0 && ids.every(id => this.selectedPermissions.includes(id));
            }
        };
    }
    </script>
</x-layouts.admin>
