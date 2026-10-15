<x-layouts.admin :title="__('Edit Permission')" :subtitle="__('Update permission details for') . ' ' . $permission->name . '.'">
    <div class="space-y-6">

        <form method="POST" action="{{ route('admin.permissions.update', $permission->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Permission Information --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Permission Details') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Use the format') }} <code class="text-xs font-mono bg-surface px-1.5 py-0.5 rounded">module.action</code> {{ __('for consistent naming.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Name --}}
                        <div>
                            <label for="name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Permission Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name', $permission->name) }}" required
                                   placeholder="{{ __('e.g. users.create, posts.delete') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink font-mono @error('name') !border-danger !ring-danger/20 @enderror">
                            <p class="text-xs text-muted mt-1">{{ __('Use dot notation: module.action') }}</p>
                            @error('name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Guard --}}
                        <div>
                            <label for="guard_name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Guard') }} <span class="text-danger">*</span></label>
                            <select name="guard_name" id="guard_name" required
                                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('guard_name') !border-danger !ring-danger/20 @enderror">
                                <option value="web" {{ old('guard_name', $permission->guard_name) === 'web' ? 'selected' : '' }}>{{ __('Web') }}</option>
                                <option value="api" {{ old('guard_name', $permission->guard_name) === 'api' ? 'selected' : '' }}>{{ __('API') }}</option>
                            </select>
                            @error('guard_name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Current Assignment Info --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Assignment Info') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Roles currently using this permission.') }}</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Created') }}</p>
                            <p class="mt-0.5 font-semibold text-ink">{{ $permission->created_at->format('M j, Y') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Guard') }}</p>
                            <p class="mt-0.5 font-semibold text-ink">{{ $permission->guard_name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Used by Roles') }}</p>
                            <p class="mt-0.5 font-semibold text-ink">{{ $permission->roles->count() }}</p>
                        </div>
                    </div>
                    @if($permission->roles->count() > 0)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($permission->roles as $role)
                        <span class="px-2.5 py-1 text-xs font-medium bg-brand/10 text-brand rounded-full">{{ $role->name }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between">
                <a href="{{ route('admin.permissions.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-muted border border-border rounded-xl hover:bg-surface transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    {{ __('Back to Permissions') }}
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Update Permission') }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
