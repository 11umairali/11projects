<x-layouts.admin :title="__('Payments')" :subtitle="__('Track all subscription payments, refunds, and failed transactions.')">
    <div class="space-y-6">

        {{-- Filter & Actions Panel --}}
        <div class="panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Payments') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Track payments, refunds, and transaction history.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'import-payments')">{{ __('Import CSV') }}</button>
                    <a href="{{ route('admin.payments.export', ['template' => 1]) }}" class="btn-secondary">{{ __('Download Template') }}</a>
                </div>
            </div>

            <form method="get" action="{{ route('admin.payments.index') }}" class="filter-form mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr_auto] md:grid-cols-[1.4fr_1fr_1fr] sm:grid-cols-2">
                <div>
                    <label class="sr-only" for="search">{{ __('Search') }}</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="input-field" placeholder="{{ __('Search by invoice or user') }}">
                </div>
                <div>
                    <label class="sr-only" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="input-field">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="succeeded" @selected(request('status') === 'succeeded')>{{ __('Succeeded') }}</option>
                        <option value="failed" @selected(request('status') === 'failed')>{{ __('Failed') }}</option>
                        <option value="refunded" @selected(request('status') === 'refunded')>{{ __('Refunded') }}</option>
                        <option value="pending" @selected(request('status') === 'pending')>{{ __('Pending') }}</option>
                    </select>
                </div>
                <div>
                    <label class="sr-only" for="from">{{ __('From date') }}</label>
                    <input id="from" type="date" name="from" value="{{ request('from') }}" class="input-field">
                </div>
                <div>
                    <label class="sr-only" for="to">{{ __('To date') }}</label>
                    <input id="to" type="date" name="to" value="{{ request('to') }}" class="input-field">
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn-secondary" type="submit">{{ __('Filter') }}</button>
                    <a href="{{ route('admin.payments.index') }}" class="btn-secondary">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        {{-- Payments Table Panel --}}
        <div class="panel p-6" x-data="{
            view: localStorage.getItem('admin-payments-view') || 'list'
        }" x-init="$watch('view', v => localStorage.setItem('admin-payments-view', v))">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="panel-heading">{{ __('Payment Directory') }}</p>
                    <p class="mt-2 text-sm text-muted">{{ __('Showing') }} {{ $payments->firstItem() ?? 0 }}-{{ $payments->lastItem() ?? 0 }} {{ __('of') }} {{ $payments->total() }} {{ __('transactions.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="view = 'list'" :class="view === 'list' ? 'btn-primary' : 'btn-secondary'">{{ __('List') }}</button>
                    <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'btn-primary' : 'btn-secondary'">{{ __('Grid') }}</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">{{ __('Print') }}</button>
                    <a href="{{ route('admin.payments.export', request()->query()) }}" class="btn-secondary">{{ __('Export') }}</a>
                </div>
            </div>

            {{-- List View --}}
            <div x-show="view === 'list'" class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-[0.2em] text-muted">
                        <tr>
                            <th class="pb-3">{{ __('Invoice') }}</th>
                            <th class="pb-3">{{ __('User') }}</th>
                            <th class="pb-3">{{ __('Amount') }}</th>
                            <th class="pb-3">{{ __('Status') }}</th>
                            <th class="pb-3">{{ __('Date') }}</th>
                            <th class="pb-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($payments as $payment)
                        <tr>
                            <td class="py-4">
                                <code class="text-xs text-muted font-mono">{{ $payment->stripe_payment_id ?? '#' . $payment->id }}</code>
                            </td>
                            <td class="py-4">
                                <span class="font-semibold text-ink">{{ $payment->workspace?->name ?? __('N/A') }}</span>
                            </td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">@currency($payment->amount)</span>
                            </td>
                            <td class="py-4">
                                <span class="text-sm font-semibold text-ink">{{ ucfirst($payment->status) }}</span>
                                @if($payment->gateway_slug)
                                    <span class="block text-xs text-muted">{{ str_replace('_', ' ', ucfirst($payment->gateway_slug)) }}</span>
                                @endif
                                @if($payment->status === 'failed' && $payment->failure_reason)
                                    <span class="block text-xs text-danger">{{ \Illuminate\Support\Str::limit($payment->failure_reason, 60) }}</span>
                                @endif
                            </td>
                            <td class="py-4 text-sm text-ink">{{ $payment->created_at->format('M j, Y') }}</td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    @if($payment->status === 'succeeded' && !$payment->refund_amount)
                                        <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'confirm-refund-{{ $payment->id }}')">{{ __('Refund') }}</button>
                                    @endif
                                    @if($payment->status === 'pending' && in_array($payment->gateway_slug, ['bank_transfer', 'offline'], true))
                                        <button type="button" class="btn-primary" x-data @click="$dispatch('open-modal', 'approve-payment-{{ $payment->id }}')">{{ __('Approve') }}</button>
                                        <button type="button" class="btn-secondary" x-data @click="$dispatch('open-modal', 'reject-payment-{{ $payment->id }}')">{{ __('Reject') }}</button>
                                    @endif
                                    @if(in_array($payment->status, ['pending', 'failed'], true))
                                        <button type="button" class="btn-danger" x-data @click="$dispatch('open-modal', 'delete-payment-{{ $payment->id }}')">{{ __('Delete') }}</button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Refund confirmation modal --}}
                        @if($payment->status === 'succeeded' && !$payment->refund_amount)
                        <x-admin-modal name="confirm-refund-{{ $payment->id }}">
                            <form method="POST" action="{{ route('admin.payments.refund', $payment->id) }}" class="p-6 space-y-4">
                                @csrf
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Refund') }} @currency($payment->amount)?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('This will issue a') }} <strong class="text-danger">{{ __('full refund') }}</strong> {{ __('of') }} @currency($payment->amount) {{ __('to') }} {{ $payment->workspace?->name ?? __('this customer') }}. {{ __('This action cannot be undone.') }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Issue Refund') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @endif

                        {{-- Approve / Reject / Delete modals (bank transfer review) --}}
                        @if($payment->status === 'pending' && in_array($payment->gateway_slug, ['bank_transfer', 'offline'], true))
                        <x-admin-modal name="approve-payment-{{ $payment->id }}">
                            <form method="POST" action="{{ route('admin.payments.approve', $payment->id) }}" class="p-6 space-y-4">
                                @csrf
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Approve payment') }} @currency($payment->amount)?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('Confirm you have received this bank transfer from') }} <strong>{{ $payment->workspace?->name ?? __('this customer') }}</strong>. {{ __('Their plan will be activated immediately.') }}</p>
                                    <p class="mt-1 text-xs text-muted">{{ __('Reference') }}: #{{ $payment->id }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-primary">{{ __('Approve & activate plan') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>

                        <x-admin-modal name="reject-payment-{{ $payment->id }}">
                            <form method="POST" action="{{ route('admin.payments.reject', $payment->id) }}" class="p-6 space-y-4">
                                @csrf
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Reject payment') }} @currency($payment->amount)?</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('The customer\'s plan will not change. You can add a reason for your records.') }}</p>
                                </div>
                                <textarea name="reason" rows="3" maxlength="500" class="input w-full" placeholder="{{ __('Reason (optional) — e.g. transfer not received') }}"></textarea>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Reject payment') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @endif

                        @if(in_array($payment->status, ['pending', 'failed'], true))
                        <x-admin-modal name="delete-payment-{{ $payment->id }}">
                            <form method="POST" action="{{ route('admin.payments.destroy', $payment->id) }}" class="p-6 space-y-4">
                                @csrf
                                @method('DELETE')
                                <div>
                                    <p class="text-lg font-semibold text-ink">{{ __('Delete this payment record?') }}</p>
                                    <p class="mt-2 text-sm text-muted">{{ __('This removes the pending/failed record permanently. This action cannot be undone.') }}</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                                    <button type="submit" class="btn-danger">{{ __('Delete') }}</button>
                                </div>
                            </form>
                        </x-admin-modal>
                        @endif
                        @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-sm text-muted">{{ __('No payments found.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Grid View --}}
            <div x-show="view === 'grid'" x-cloak class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($payments as $payment)
                <div class="panel p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-ink">{{ $payment->workspace?->name ?? __('N/A') }}</span>
                        <span class="text-sm font-semibold text-ink">@currency($payment->amount)</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-ink">{{ ucfirst($payment->status) }}</span>
                        <span class="text-muted">{{ $payment->created_at->format('M j, Y') }}</span>
                    </div>
                    <code class="text-xs text-muted font-mono block truncate">{{ $payment->stripe_payment_id ?? '#' . $payment->id }}</code>
                    <div class="pt-2 border-t border-border/60 flex flex-wrap gap-2">
                        @if($payment->status === 'succeeded' && !$payment->refund_amount)
                            <button type="button" class="btn-danger text-xs" x-data @click="$dispatch('open-modal', 'confirm-refund-{{ $payment->id }}')">{{ __('Refund') }}</button>
                        @endif
                        @if($payment->status === 'pending' && in_array($payment->gateway_slug, ['bank_transfer', 'offline'], true))
                            <button type="button" class="btn-primary text-xs" x-data @click="$dispatch('open-modal', 'approve-payment-{{ $payment->id }}')">{{ __('Approve') }}</button>
                            <button type="button" class="btn-secondary text-xs" x-data @click="$dispatch('open-modal', 'reject-payment-{{ $payment->id }}')">{{ __('Reject') }}</button>
                        @endif
                        @if(in_array($payment->status, ['pending', 'failed'], true))
                            <button type="button" class="btn-danger text-xs" x-data @click="$dispatch('open-modal', 'delete-payment-{{ $payment->id }}')">{{ __('Delete') }}</button>
                        @endif
                    </div>
                </div>
                @empty
                <div class="col-span-full py-6 text-center text-sm text-muted">{{ __('No payments found.') }}</div>
                @endforelse
            </div>

            @if($payments->hasPages())
            <div class="mt-4 border-t border-border/60 pt-4">
                {{ $payments->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- Import Modal --}}
    <x-admin-modal name="import-payments">
        <form method="POST" action="{{ route('admin.payments.import') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            <div>
                <h3 class="text-lg font-semibold text-ink">{{ __('Import Payments') }}</h3>
                <p class="mt-1 text-sm text-muted">{{ __('Upload a CSV file that matches the provided template.') }}</p>
            </div>
            <x-file-uploader name="file" accept=".csv,.txt" :label="__('CSV File')" />
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Upload') }}</button>
            </div>
        </form>
    </x-admin-modal>
</x-layouts.admin>
