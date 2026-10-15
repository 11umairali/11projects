<x-layouts.admin :title="__('Currencies')" :subtitle="__('Manage available currencies and exchange rates.')">
    <div class="space-y-6">

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $totalCount }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Total Currencies') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $activeCount }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Active Currencies') }}</p>
            </div>
            <div class="panel p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"/><path d="M4 20L21 3"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/></svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-ink tracking-tight">{{ $totalCount - $activeCount }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Inactive Currencies') }}</p>
            </div>
        </div>

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Currencies') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage the currencies and exchange rates for your application.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-primary" x-data @click="$dispatch('open-modal', 'create-currency')">{{ __('Add Currency') }}</button>
                </div>
            </div>

            <form method="get" action="{{ route('admin.currencies.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_auto] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by name, code, or symbol') }}">
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
                    <a href="{{ route('admin.currencies.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Currencies Table Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Currency Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $currencies->count() }} {{ __('of') }} {{ $currencies->total() }} {{ __('currencies.') }}</p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">{{ __('Symbol') }}</th>
                            <th class="pb-3">{{ __('Code') }}</th>
                            <th class="pb-3">{{ __('Name') }}</th>
                            <th class="pb-3">{{ __('Exchange Rate') }}</th>
                            <th class="pb-3">{{ __('Position') }}</th>
                            <th class="pb-3">{{ __('Decimal') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3">{{ __('Default') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($currencies as $currency)
                        <tr>
                            <td class="py-4">
                                <span class="text-lg font-semibold text-ink">{{ $currency->symbol }}</span>
                            </td>
                            <td class="py-4">
                                <span class="text-xs font-mono text-muted bg-surface px-2 py-0.5 rounded">{{ $currency->code }}</span>
                            </td>
                            <td class="py-4">
                                <span class="font-semibold text-ink">{{ $currency->name }}</span>
                            </td>
                            <td class="py-4 text-sm text-ink font-mono">{{ number_format((float) $currency->exchange_rate, 6) }}</td>
                            <td class="py-4">
                                @if($currency->symbol_position === 'before')
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 rounded-full">{{ __('Before') }}</span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-amber-100 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 rounded-full">{{ __('After') }}</span>
                                @endif
                            </td>
                            <td class="py-4 text-sm text-ink">
                                <span class="text-xs text-muted">{{ $currency->decimal_digits }} {{ __('digits') }}</span>
                            </td>
                            <td class="py-4">
                                <form method="POST" action="{{ route('admin.currencies.toggle', $currency) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5">
                                        @if($currency->is_active)
                                            <span class="px-2 py-0.5 text-[10px] font-bold bg-success/15 text-success rounded-full">{{ __('Active') }}</span>
                                        @else
                                            <span class="px-2 py-0.5 text-[10px] font-bold bg-surface text-ink/80 rounded-full">{{ __('Inactive') }}</span>
                                        @endif
                                    </button>
                                </form>
                            </td>
                            <td class="py-4">
                                @if($currency->is_default)
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-brand/15 text-brand rounded-full">{{ __('Default') }}</span>
                                @else
                                    <form method="POST" action="{{ route('admin.currencies.default', $currency) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-muted hover:text-brand transition">{{ __('Set Default') }}</button>
                                    </form>
                                @endif
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'edit-currency-{{ $currency->id }}')">{{ __('Edit') }}</button>
                                    @unless($currency->is_default)
                                    <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'delete-currency-{{ $currency->id }}')">{{ __('Delete') }}</button>
                                    @endunless
                                </div>
                            </td>
                        </tr>

                        {{-- Edit Modal --}}
                        <x-admin-modal name="edit-currency-{{ $currency->id }}" maxWidth="lg">
                            <form method="POST" action="{{ route('admin.currencies.update', $currency) }}" class="p-6 space-y-5">
                                @csrf
                                @method('PUT')
                                <div>
                                    <h3 class="text-lg font-semibold text-ink">{{ __('Edit Currency') }}</h3>
                                    <p class="mt-1 text-sm text-muted">Update the details for "{{ $currency->name }}".</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="edit_name_{{ $currency->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Name') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="edit_name_{{ $currency->id }}" value="{{ $currency->name }}" required class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_code_{{ $currency->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Code') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="code" id="edit_code_{{ $currency->id }}" value="{{ $currency->code }}" required maxlength="5" class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_symbol_{{ $currency->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Symbol') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="symbol" id="edit_symbol_{{ $currency->id }}" value="{{ $currency->symbol }}" required maxlength="10" class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_position_{{ $currency->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Symbol Position') }} <span class="text-danger">*</span></label>
                                        <select name="symbol_position" id="edit_position_{{ $currency->id }}" class="input-field">
                                            <option value="before" @selected($currency->symbol_position === 'before')>{{ __('Before ($100)') }}</option>
                                            <option value="after" @selected($currency->symbol_position === 'after')>{{ __('After (100$)') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="edit_decimal_sep_{{ $currency->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Decimal Separator') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="decimal_separator" id="edit_decimal_sep_{{ $currency->id }}" value="{{ $currency->decimal_separator }}" required maxlength="5" class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_thousand_sep_{{ $currency->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Thousand Separator') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="thousand_separator" id="edit_thousand_sep_{{ $currency->id }}" value="{{ $currency->thousand_separator }}" required maxlength="5" class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_decimal_digits_{{ $currency->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Decimal Digits') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="decimal_digits" id="edit_decimal_digits_{{ $currency->id }}" value="{{ $currency->decimal_digits }}" required min="0" max="4" class="input-field">
                                    </div>
                                    <div>
                                        <label for="edit_exchange_rate_{{ $currency->id }}" class="block text-sm font-medium text-ink mb-1.5">{{ __('Exchange Rate') }} <span class="text-danger">*</span></label>
                                        <input type="number" name="exchange_rate" id="edit_exchange_rate_{{ $currency->id }}" value="{{ $currency->exchange_rate }}" required step="0.000001" min="0" class="input-field">
                                    </div>
                                </div>
                                <div class="flex items-center gap-6">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($currency->is_active) class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                                        <span class="text-sm text-ink">{{ __('Active') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="hidden" name="is_default" value="0">
                                        <input type="checkbox" name="is_default" value="1" @checked($currency->is_default) class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                                        <span class="text-sm text-ink">{{ __('Default') }}</span>
                                    </label>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-primary">{{ __('Update Currency') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>

                        {{-- Delete Modal --}}
                        @unless($currency->is_default)
                        <x-admin-modal name="delete-currency-{{ $currency->id }}">
                            <form method="POST" action="{{ route('admin.currencies.destroy', $currency) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">Delete "{{ $currency->name }}" ({{ $currency->code }})?</p>
                                    <p class="mt-2 text-sm text-muted">{!! __('This currency will be <strong class="text-danger">permanently removed</strong>. This action cannot be undone.') !!}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Delete Currency') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @endunless
                        @empty
                        <tr>
                            <td colspan="9" class="py-6 text-center text-sm text-muted">{{ __('No currencies found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($currencies->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $currencies->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Create Currency Modal --}}
    <x-admin-modal name="create-currency" maxWidth="lg">
        <form method="POST" action="{{ route('admin.currencies.store') }}" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Add Currency') }}</h3>
                <p class="mt-1 text-sm text-muted">{{ __('Add a new currency to your application.') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="create_name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="create_name" required class="input-field" placeholder="{{ __('US Dollar') }}">
                </div>
                <div>
                    <label for="create_code" class="block text-sm font-medium text-ink mb-1.5">{{ __('Code') }} <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="create_code" required maxlength="5" class="input-field" placeholder="USD">
                </div>
                <div>
                    <label for="create_symbol" class="block text-sm font-medium text-ink mb-1.5">{{ __('Symbol') }} <span class="text-danger">*</span></label>
                    <input type="text" name="symbol" id="create_symbol" required maxlength="10" class="input-field" placeholder="$">
                </div>
                <div>
                    <label for="create_position" class="block text-sm font-medium text-ink mb-1.5">{{ __('Symbol Position') }} <span class="text-danger">*</span></label>
                    <select name="symbol_position" id="create_position" class="input-field">
                        <option value="before">{{ __('Before ($100)') }}</option>
                        <option value="after">{{ __('After (100$)') }}</option>
                    </select>
                </div>
                <div>
                    <label for="create_decimal_sep" class="block text-sm font-medium text-ink mb-1.5">{{ __('Decimal Separator') }} <span class="text-danger">*</span></label>
                    <input type="text" name="decimal_separator" id="create_decimal_sep" required maxlength="5" class="input-field" placeholder="." value=".">
                </div>
                <div>
                    <label for="create_thousand_sep" class="block text-sm font-medium text-ink mb-1.5">{{ __('Thousand Separator') }} <span class="text-danger">*</span></label>
                    <input type="text" name="thousand_separator" id="create_thousand_sep" required maxlength="5" class="input-field" placeholder="," value=",">
                </div>
                <div>
                    <label for="create_decimal_digits" class="block text-sm font-medium text-ink mb-1.5">{{ __('Decimal Digits') }} <span class="text-danger">*</span></label>
                    <input type="number" name="decimal_digits" id="create_decimal_digits" required min="0" max="4" class="input-field" value="2">
                </div>
                <div>
                    <label for="create_exchange_rate" class="block text-sm font-medium text-ink mb-1.5">{{ __('Exchange Rate') }} <span class="text-danger">*</span></label>
                    <input type="number" name="exchange_rate" id="create_exchange_rate" required step="0.000001" min="0" class="input-field" value="1.000000">
                </div>
            </div>
            <div class="flex items-center gap-6">
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
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Create Currency') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
