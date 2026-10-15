<x-layouts.admin :title="__('Languages')" :subtitle="__('Manage available languages and localization settings.')">
    <div class="space-y-6">

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $totalCount }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Total Languages') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $activeCount }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Active Languages') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $totalCount - $activeCount }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Inactive Languages') }}</p>
            </div>
        </div>

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Languages') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage the languages available for your application.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-primary" x-data @click="$dispatch('open-modal', 'create-language')">{{ __('Add Language') }}</button>
                </div>
            </div>

            <form method="get" action="{{ route('admin.languages.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_auto] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by name, code, or native name') }}">
                </div>
                <div>
                    <label class="sr-only" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.languages.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Languages Table Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Language Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $languages->count() }} {{ __('of') }} {{ $languages->total() }} {{ __('languages.') }}</p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">{{ __('Flag') }}</th>
                            <th class="pb-3">{{ __('Name') }}</th>
                            <th class="pb-3">{{ __('Native Name') }}</th>
                            <th class="pb-3">{{ __('Code') }}</th>
                            <th class="pb-3">{{ __('Direction') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3">{{ __('Default') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($languages as $language)
                        <tr>
                            <td class="py-4 text-lg">{{ $language->flag ?? '--' }}</td>
                            <td class="py-4">
                                <span class="font-semibold text-ink">{{ $language->name }}</span>
                            </td>
                            <td class="py-4 text-sm text-ink">{{ $language->native_name ?? '--' }}</td>
                            <td class="py-4">
                                <span class="text-xs font-mono text-muted bg-surface px-2 py-0.5 rounded">{{ $language->code }}</span>
                            </td>
                            <td class="py-4">
                                @if($language->direction === 'rtl')
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-amber-100 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 rounded-full">RTL</span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 rounded-full">LTR</span>
                                @endif
                            </td>
                            <td class="py-4">
                                <form method="POST" action="{{ route('admin.languages.toggle', $language) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5">
                                        @if($language->is_active)
                                            <span class="px-2 py-0.5 text-[10px] font-bold bg-success/15 text-success rounded-full">{{ __('Active') }}</span>
                                        @else
                                            <span class="px-2 py-0.5 text-[10px] font-bold bg-surface text-ink/80 rounded-full">{{ __('Inactive') }}</span>
                                        @endif
                                    </button>
                                </form>
                            </td>
                            <td class="py-4">
                                @if($language->is_default)
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-brand/15 text-brand rounded-full">{{ __('Default') }}</span>
                                @else
                                    <form method="POST" action="{{ route('admin.languages.default', $language) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-muted hover:text-brand transition">{{ __('Set Default') }}</button>
                                    </form>
                                @endif
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'edit-language-{{ $language->id }}')">{{ __('Edit') }}</button>
                                    @unless($language->is_default)
                                    <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'delete-language-{{ $language->id }}')">{{ __('Delete') }}</button>
                                    @endunless
                                </div>
                            </td>
                        </tr>

                        {{-- Edit Modal --}}
                        <x-admin-modal name="edit-language-{{ $language->id }}" maxWidth="lg">
                            <form method="POST" action="{{ route('admin.languages.update', $language) }}" class="p-6 space-y-5">
                                @csrf
                                @method('PUT')
                                <div>
                                    <h3 class="text-lg font-semibold text-ink">{{ __('Edit Language') }}</h3>
                                    <p class="mt-1 text-sm text-muted">Update the details for "{{ $language->name }}".</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="edit_name_{{ $language->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Name') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="edit_name_{{ $language->id }}" value="{{ $language->name }}" required class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_code_{{ $language->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Code') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="code" id="edit_code_{{ $language->id }}" value="{{ $language->code }}" required maxlength="10" class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_native_{{ $language->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Native Name') }}</label>
                                        <input type="text" name="native_name" id="edit_native_{{ $language->id }}" value="{{ $language->native_name }}" class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_flag_{{ $language->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Flag Emoji') }}</label>
                                        <input type="text" name="flag" id="edit_flag_{{ $language->id }}" value="{{ $language->flag }}" maxlength="10" class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_direction_{{ $language->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Direction') }} <span class="text-danger">*</span></label>
                                        <select name="direction" id="edit_direction_{{ $language->id }}" class="input-field">
                                            <option value="ltr" @selected($language->direction === 'ltr')>{{ __('LTR (Left to Right)') }}</option>
                                            <option value="rtl" @selected($language->direction === 'rtl')>{{ __('RTL (Right to Left)') }}</option>
                                        </select>
                                    </div>
                                    <div class="flex items-end gap-6">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" @checked($language->is_active) class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                                            <span class="text-sm text-ink">{{ __('Active') }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="hidden" name="is_default" value="0">
                                            <input type="checkbox" name="is_default" value="1" @checked($language->is_default) class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                                            <span class="text-sm text-ink">{{ __('Default') }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-primary">{{ __('Update Language') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>

                        {{-- Delete Modal --}}
                        @unless($language->is_default)
                        <x-admin-modal name="delete-language-{{ $language->id }}">
                            <form method="POST" action="{{ route('admin.languages.destroy', $language) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">Delete "{{ $language->name }}"?</p>
                                    <p class="mt-2 text-sm text-muted">{!! __('This language will be <strong class="text-danger">permanently removed</strong>. This action cannot be undone.') !!}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Delete Language') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @endunless
                        @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-sm text-muted">{{ __('No languages found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($languages->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $languages->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Create Language Modal --}}
    <x-admin-modal name="create-language" maxWidth="lg">
        <form method="POST" action="{{ route('admin.languages.store') }}" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Add Language') }}</h3>
                <p class="mt-1 text-sm text-muted">{{ __('Add a new language to your application.') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="create_name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="create_name" required class="input-field" placeholder="{{ __('English') }}">
                </div>
                <div>
                    <label for="create_code" class="block text-sm font-medium text-ink mb-1.5">{{ __('Code') }} <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="create_code" required maxlength="10" class="input-field" placeholder="en">
                </div>
                <div>
                    <label for="create_native" class="block text-sm font-medium text-ink mb-1.5">{{ __('Native Name') }}</label>
                    <input type="text" name="native_name" id="create_native" class="input-field" placeholder="{{ __('English') }}">
                </div>
                <div>
                    <label for="create_flag" class="block text-sm font-medium text-ink mb-1.5">{{ __('Flag Emoji') }}</label>
                    <input type="text" name="flag" id="create_flag" maxlength="10" class="input-field" placeholder="🇺🇸">
                </div>
                <div>
                    <label for="create_direction" class="block text-sm font-medium text-ink mb-1.5">{{ __('Direction') }} <span class="text-danger">*</span></label>
                    <select name="direction" id="create_direction" class="input-field">
                        <option value="ltr">{{ __('LTR (Left to Right)') }}</option>
                        <option value="rtl">{{ __('RTL (Right to Left)') }}</option>
                    </select>
                </div>
                <div class="flex items-end gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                        <span class="text-sm text-ink">{{ __('Active') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                        <span class="text-sm text-ink">{{ __('Default') }}</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Create Language') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
