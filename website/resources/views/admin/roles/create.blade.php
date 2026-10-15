<x-layouts.admin :title="__('Create Role')" :subtitle="__('Define a new role with specific permissions.')">
    <div class="space-y-6" x-data="roleForm()">

        <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-6">
            @csrf

            {{-- Role Information --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Role Information') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Define the role name and guard type.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Name --}}
                        <div>
                            <label for="name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Role Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                   placeholder="{{ __('e.g. Editor, Moderator') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('name') !border-danger !ring-danger/20 @enderror">
                            @error('name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Guard --}}
                        <div>
                            <label for="guard_name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Guard') }} <span class="text-danger">*</span></label>
                            <select name="guard_name" id="guard_name" required
                                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('guard_name') !border-danger !ring-danger/20 @enderror">
                                <option value="web" {{ old('guard_name', 'web') === 'web' ? 'selected' : '' }}>{{ __('Web') }}</option>
                                <option value="api" {{ old('guard_name') === 'api' ? 'selected' : '' }}>{{ __('API') }}</option>
                            </select>
                            @error('guard_name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Permissions --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Permissions') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Select the permissions to assign to this role. Permissions are grouped by module.') }}</p>
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
                                       {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}
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
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Create Role') }}
                </button>
            </div>
        </form>
    </div>

    <script>
    function roleForm() {
        return {
            selectedPermissions: @json(old('permissions', [])),
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
