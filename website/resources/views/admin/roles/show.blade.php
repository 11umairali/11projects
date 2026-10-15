<x-layouts.admin :title="$role->name" :subtitle="__('Role details, permissions, and assigned users.')">
    <div class="space-y-6">

        {{-- Role Info Panel --}}
        <div class="panel p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand/10 text-brand text-lg font-bold">
                        {{ strtoupper(substr($role->name, 0, 2)) }}
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold text-ink">{{ $role->name }}</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $role->guard_name === 'web' ? 'bg-brand/15 text-brand' : 'bg-warning/15 text-warning' }}">{{ $role->guard_name }}</span>
                        </div>
                        <p class="text-sm text-muted mt-1">
                            {{ __('Created') }} {{ $role->created_at->format('M j, Y') }}
                            &middot; {{ $role->permissions->count() }} {{ __('permissions') }}
                            &middot; {{ $role->users->count() }} {{ __('users') }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.roles.edit', $role->id) }}" class="btn-secondary">{{ __('Edit Role') }}</a>
                    @if($role->name !== 'Super Admin')
                    <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'confirm-delete-role')">{{ __('Delete') }}</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Permissions by Module --}}
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <h2 class="text-lg font-bold text-ink">{{ __('Assigned Permissions') }}</h2>
                    <p class="text-sm text-muted mt-0.5">{{ $role->permissions->count() }} {{ __('permissions grouped by module.') }}</p>
                </div>
                <div class="p-6 space-y-4">
                    @php
                        $grouped = $role->permissions->groupBy(function ($permission) {
                            $parts = explode('.', $permission->name);
                            return count($parts) > 1 ? $parts[0] : 'general';
                        });
                    @endphp

                    @forelse($grouped as $module => $permissions)
                    <div class="border border-border rounded-xl overflow-hidden">
                        <div class="px-4 py-2.5 bg-surface border-b border-border flex items-center justify-between">
                            <span class="text-sm font-bold text-ink uppercase tracking-wider">{{ ucfirst($module) }}</span>
                            <span class="text-xs text-muted">{{ $permissions->count() }} {{ __('permissions') }}</span>
                        </div>
                        <div class="p-3 flex flex-wrap gap-2">
                            @foreach($permissions as $permission)
                            <span class="px-2.5 py-1 text-xs font-medium bg-brand/10 text-brand rounded-full">{{ $permission->name }}</span>
                            @endforeach
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-6">
                        <p class="text-sm text-muted">{{ __('No permissions assigned to this role.') }}</p>
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Users with this Role --}}
            <div class="panel overflow-hidden">
                <div class="px-6 py-4 border-b border-border/50 bg-surface/50">
                    <h2 class="text-lg font-bold text-ink">{{ __('Users with this Role') }}</h2>
                    <p class="text-sm text-muted mt-0.5">{{ $role->users->count() }} {{ __('users assigned.') }}</p>
                </div>
                <div class="p-6 space-y-3">
                    @forelse($role->users as $user)
                    <div class="flex items-center justify-between p-4 bg-surface rounded-xl hover:bg-surface transition-colors">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-ink">{{ $user->name }}</p>
                                <p class="text-xs text-muted">{{ $user->email }}</p>
                            </div>
                        </div>
                        <a href="{{ route('admin.users.show', $user->id) }}" class="btn-secondary text-xs">{{ __('View') }}</a>
                    </div>
                    @empty
                    <div class="text-center py-6">
                        <p class="text-sm text-muted">{{ __('No users assigned to this role.') }}</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Back Button --}}
        <div class="flex items-center">
            <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-muted border border-border rounded-xl hover:bg-surface transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Back to Roles') }}
            </a>
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    @if($role->name !== 'Super Admin')
    <x-admin-modal name="confirm-delete-role">
        <form method="POST" action="{{ route('admin.roles.destroy', $role->id) }}" class="p-6 space-y-4">
            @csrf
            @method('DELETE')
            <div>
                <p class="text-lg font-semibold text-ink">{{ __('Delete') }} "{{ $role->name }}"?</p>
                <p class="mt-2 text-sm text-muted">{{ __('This role will be') }} <strong class="text-danger">{{ __('permanently deleted') }}</strong>. {{ __('All') }} {{ $role->users->count() }} {{ __('users assigned to this role will lose their permissions.') }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Role') }}</button>
            </div>
        </form>
    </x-admin-modal>
    @endif
</x-layouts.admin>
