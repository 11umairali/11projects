<x-layouts.admin :title="__('Plans')" :subtitle="__('Manage subscription plans, feature limits, and pricing.')">
    <form method="POST" action="{{ route('admin.plans.configure') }}" class="mb-6 rounded-xl border border-border bg-surface-2 p-5 space-y-3">
        @csrf
        <label for="default-plan" class="block font-semibold">{{ __('Default plan') }}</label>
        <select id="default-plan" name="default_plan" class="input-field">
            <option value="__free_unlimited__" @selected(\App\Support\BillingPolicy::isFree())>{{ __('Free — unlimited for everyone') }}</option>
            @foreach($plans->where('is_active', true) as $defaultPlan)
                <option value="{{ $defaultPlan->slug }}" @selected(\App\Models\SystemSetting::get('default_plan', 'free') === $defaultPlan->slug)>{{ $defaultPlan->name }}@if($defaultPlan->isFree()) — {{ __('plan limits apply') }}@endif</option>
            @endforeach
        </select>
        <p class="text-sm text-muted">{{ __('A plan applies to new workspaces. Free — unlimited applies to everyone, enables every plan feature, removes quotas, and hides pricing, billing and checkout from the website and app. Existing recurring subscriptions must be canceled with their provider first.') }}</p>
        @error('default_plan')<p class="text-danger">{{ $message }}</p>@enderror
        <button type="submit" class="btn-primary">{{ __('Save default plan') }}</button>
    </form>

    <div class="space-y-6">

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Plans') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Manage subscription plans, pricing, and feature access.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'import-plans')">{{ __('Import CSV') }}</button>
                    <a href="{{ route('admin.plans.export', ['template' => 1]) }}" class="btn-secondary">{{ __('Download Template') }}</a>
                    <a href="{{ route('admin.plans.create') }}" class="btn-primary">{{ __('Add Plan') }}</a>
                </div>
            </div>

            <form method="get" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by plan name') }}">
                </div>
                <div>
                    <label class="sr-only" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
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
                    <a href="{{ route('admin.plans.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Plans Table Panel --}}
        <div class="panel p-6" x-data="{
            selected: [],
            selectAll: false,
            view: localStorage.getItem('admin-plans-view') || 'list',
            allIds: [{{ $plans->pluck('id')->join(',') }}]
        }" x-init="$watch('view', v => localStorage.setItem('admin-plans-view', v)); $watch('selectAll', v => { selected = v ? [...allIds] : [] })">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Plan Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $plans->count() }} {{ __('plans.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="view = 'list'" :class="view === 'list' ? 'btn-primary' : 'btn-secondary'">{{ __('List') }}</button>
                    <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'btn-primary' : 'btn-secondary'">{{ __('Grid') }}</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <a href="{{ route('admin.plans.export', request()->query()) }}" class="btn-secondary">{{ __('Export') }}</a>
                    <button x-show="selected.length > 0" x-cloak type="button" class="btn-danger" @click="$dispatch('open-modal', 'bulk-delete-plans')" x-text="'{{ __('Bulk Delete') }} (' + selected.length + ')'"></button>
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
                            <th class="pb-3">{{ __('Plan') }}</th>
                            <th class="pb-3">{{ __('Price') }}</th>
                            <th class="pb-3">{{ __('Subscribers') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($plans as $plan)
                        <tr>
                            <td class="py-4">
                                <input type="checkbox" value="{{ $plan->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                            </td>
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold">
                                        {{ strtoupper(substr($plan->name, 0, 2)) }}
                                    </span>
                                    <div>
                                        <span class="font-semibold text-ink">{{ $plan->name }}</span>
                                        <p class="text-xs text-muted font-mono">{{ $plan->slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4">
                                @if($plan->isFree())
                                    <span class="text-sm font-semibold text-ink">{{ __('Free') }}</span>
                                @else
                                    <div>
                                        <span class="text-sm font-semibold text-ink">@currency($plan->monthly_price){{ __('/mo') }}</span>
                                    </div>
                                    <p class="text-xs text-muted">@currency($plan->yearly_price){{ __('/yr') }}</p>
                                @endif
                            </td>
                            <td class="py-4 text-sm text-ink">{{ number_format($plan->active_subscriptions_count) }}</td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">{{ $plan->is_active ? __('Active') : __('Inactive') }}</span>
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('admin.plans.show', $plan->id) }}" class="btn-secondary">{{ __('View') }}</a>
                                    <a href="{{ route('admin.plans.edit', $plan->id) }}" class="btn-secondary">{{ __('Edit') }}</a>
                                    <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'confirm-delete-{{ $plan->id }}')">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>

                        {{-- Delete confirmation modal --}}
                        <x-admin-modal name="confirm-delete-{{ $plan->id }}">
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan->id) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Delete') }} "{{ $plan->name }}"?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('This will') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('this plan. Existing subscribers may be affected.') }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Delete Plan') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-sm text-muted">{{ __('No plans found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Grid View --}}
            <div x-show="view === 'grid'" x-cloak class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($plans as $plan)
                <div class="panel p-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" value="{{ $plan->id }}" x-model.number="selected" class="h-4 w-4 rounded border-border bg-surface-2/80 text-brand focus:ring-brand/40">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-sm font-bold">
                            {{ strtoupper(substr($plan->name, 0, 2)) }}
                        </span>
                        <div class="min-w-0">
                            <span class="font-semibold text-ink truncate block">{{ $plan->name }}</span>
                            <p class="text-xs text-muted font-mono">{{ $plan->slug }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        @if($plan->isFree())
                            <span class="font-semibold text-ink">{{ __('Free') }}</span>
                        @else
                            <span class="font-semibold text-ink">@currency($plan->monthly_price){{ __('/mo') }}</span>
                        @endif
                        <span class="text-muted">{{ number_format($plan->active_subscriptions_count) }} {{ __('subs') }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-ink">{{ $plan->is_active ? __('Active') : __('Inactive') }}</span>
                    </div>
                    <div class="flex items-center gap-2 pt-2 border-t border-border/60">
                        <a href="{{ route('admin.plans.show', $plan->id) }}" class="btn-secondary text-xs">{{ __('View') }}</a>
                        <a href="{{ route('admin.plans.edit', $plan->id) }}" class="btn-secondary text-xs">{{ __('Edit') }}</a>
                    </div>
                </div>
                @empty
                <div class="col-span-full py-6 text-center text-sm text-muted">{{ __('No plans found.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Import Modal --}}
    <x-admin-modal name="import-plans">
        <form method="POST" action="{{ route('admin.plans.import') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Import Plans') }}</h3>
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
    <x-admin-modal name="bulk-delete-plans">
        <form method="POST" action="{{ route('admin.plans.bulk-destroy') }}" class="p-6 space-y-4"
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
                <p class="text-lg font-semibold text-ink">{{ __('Delete selected plans?') }}</p>
                <p class="mt-2 text-sm text-muted">{{ __('You are about to') }} <strong class="text-danger">{{ __('permanently delete') }}</strong> {{ __('the selected plan(s). Plans with active subscriptions will be skipped. This action cannot be undone.') }}</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-danger">{{ __('Delete Permanently') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
