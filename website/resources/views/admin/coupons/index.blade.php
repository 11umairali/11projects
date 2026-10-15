<x-layouts.admin :title="__('Coupons')" :subtitle="__('Manage discount codes and promotional offers.')">
    <div class="space-y-6">

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Coupons') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage discount codes, promotional offers, and usage limits.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'import-coupons')">{{ __('Import CSV') }}</button>
                    <a href="{{ route('admin.coupons.export', ['template' => 1]) }}" class="btn-secondary">{{ __('Download Template') }}</a>
                    <a href="{{ route('admin.coupons.create') }}" class="btn-primary">{{ __('Add Coupon') }}</a>
                </div>
            </div>

            <form method="get" action="{{ route('admin.coupons.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by code or name') }}">
                </div>
                <div>
                    <label class="sr-only" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
                        <option value="expired" @selected(request('status') === 'expired')>{{ __('Expired') }}</option>
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="sort">{{ __('Sort') }}</label>
                    <select id="sort" name="sort" class="input-field">
                        <option value="latest" @selected(request('sort', 'latest') === 'latest')>{{ __('Created (Newest)') }}</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>{{ __('Created (Oldest)') }}</option>
                        <option value="most_used" @selected(request('sort') === 'most_used')>{{ __('Most Used') }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.coupons.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Coupons Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            view: localStorage.getItem('admin-coupons-view') || 'list',
            allIds: [{{ $coupons->pluck('id')->join(',') }}]
        }" x-init="$watch('view', v => localStorage.setItem('admin-coupons-view', v)); $watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Coupon Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $coupons->count() }} {{ __('of') }} {{ $coupons->total() }} {{ __('coupons.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="view = 'list'" :class="view === 'list' ? 'btn-primary' : 'btn-secondary'">{{ __('List') }}</button>
                    <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'btn-primary' : 'btn-secondary'">{{ __('Grid') }}</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <a href="{{ route('admin.coupons.export', request()->query()) }}" class="btn-secondary">{{ __('Export') }}</a>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-delete-coupons')" x-text="'Bulk Delete (' + selected.length + ')'"></button>
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
                            <th class="pb-3">{{ __('Code') }}</th>
                            <th class="pb-3">{{ __('Type') }}</th>
                            <th class="pb-3">{{ __('Discount') }}</th>
                            <th class="pb-3">{{ __('Usage') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($coupons as $coupon)
                        <tr>
                            <td class="py-4">
                                <input type="checkbox" value="{{ $coupon->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </td>
                            <td class="py-4">
                                <div>
                                    <code class="font-mono font-semibold text-ink">{{ $coupon->code }}</code>
                                    <p class="text-xs text-muted">{{ $coupon->name }}</p>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">{{ $coupon->type === 'percent_off' ? __('Percentage') : __('Fixed') }}</span>
                            </td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">
                                    @if($coupon->type === 'percent_off')
                                        {{ $coupon->percent_off }}%
                                    @else
                                        @currency($coupon->amount_off)
                                    @endif
                                </span>
                            </td>
                            <td class="py-4 text-sm text-ink">
                                {{ $coupon->times_redeemed ?? 0 }} / {{ $coupon->max_redemptions ?? '&#8734;' }}
                            </td>
                            <td class="py-4">
                                @php
                                    $isExpired = $coupon->expires_at && \Carbon\Carbon::parse($coupon->expires_at)->isPast();
                                @endphp
                                <span class="text-sm font-semibold text-ink">
                                    @if($isExpired)
                                        {{ __('Expired') }}
                                    @elseif($coupon->is_active)
                                        {{ __('Active') }}
                                    @else
                                        {{ __('Inactive') }}
                                    @endif
                                </span>
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.coupons.edit', $coupon->id) }}" class="btn-secondary">{{ __('Edit') }}</a>
                                    <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'confirm-delete-{{ $coupon->id }}')">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>

                        {{-- Delete confirmation modal --}}
                        <x-admin-modal name="confirm-delete-{{ $coupon->id }}">
                            <form method="POST" action="{{ route('admin.coupons.destroy', $coupon->id) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Delete coupon') }} "{{ $coupon->code }}"?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('This will') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('this coupon and deactivate it in Stripe. This action cannot be undone.') }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Delete Coupon') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-sm text-muted">{{ __('No coupons found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Grid View --}}
            <div x-show="view === 'grid'" x-cloak class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($coupons as $coupon)
                <div class="panel p-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" value="{{ $coupon->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                        <div class="min-w-0">
                            <code class="font-mono font-semibold text-ink">{{ $coupon->code }}</code>
                            <p class="text-xs text-muted truncate">{{ $coupon->name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-ink">
                            @if($coupon->type === 'percent_off')
                                {{ $coupon->percent_off }}% {{ __('off') }}
                            @else
                                @currency($coupon->amount_off) {{ __('off') }}
                            @endif
                        </span>
                        @php $isExpired = $coupon->expires_at && \Carbon\Carbon::parse($coupon->expires_at)->isPast(); @endphp
                        <span class="font-semibold text-ink">
                            @if($isExpired) {{ __('Expired') }} @elseif($coupon->is_active) {{ __('Active') }} @else {{ __('Inactive') }} @endif
                        </span>
                    </div>
                    <div class="text-xs text-muted">
                        {{ __('Used:') }} {{ $coupon->times_redeemed ?? 0 }} / {{ $coupon->max_redemptions ?? '&#8734;' }}
                    </div>
                    <div class="flex items-center gap-2 pt-2 border-t border-border/60">
                        <a href="{{ route('admin.coupons.edit', $coupon->id) }}" class="btn-secondary text-xs">{{ __('Edit') }}</a>
                        <button type="button" class="btn-danger text-xs" x-data @click="$dispatch('open-modal', 'confirm-delete-{{ $coupon->id }}')">{{ __('Delete') }}</button>
                    </div>
                </div>
                @empty
                <div class="col-span-full py-6 text-center text-sm text-muted">{{ __('No coupons found.') }}</div>
                @endforelse
            </div>

            @if($coupons->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $coupons->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Import Modal --}}
    <x-admin-modal name="import-coupons">
        <form method="POST" action="{{ route('admin.coupons.import') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Import Coupons') }}</h3>
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
    <x-admin-modal name="bulk-delete-coupons">
        <form method="POST" action="{{ route('admin.coupons.bulk-destroy') }}" class="p-6 space-y-4"
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
                <p class="text-lg font-semibold text-ink">{{ __('Delete selected coupons?') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('You are about to') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('the selected coupon(s). They will also be deactivated in Stripe. This action cannot be undone.') }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
