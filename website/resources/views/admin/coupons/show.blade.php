<x-layouts.admin :title="__('Coupon') . ' ' . $coupon->code" :subtitle="$coupon->name">
    <div class="space-y-6">

        {{-- Coupon Summary Panel --}}
        <div class="panel p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <code class="text-sm font-bold text-brand">{{ $coupon->code }}</code>
                    @if($coupon->expires_at && \Carbon\Carbon::parse($coupon->expires_at)->isPast())
                        <span class="badge badge-error">{{ __('Expired') }}</span>
                    @elseif($coupon->is_active)
                        <span class="badge badge-success">{{ __('Active') }}</span>
                    @else
                        <span class="badge badge-ghost">{{ __('Inactive') }}</span>
                    @endif
                </div>
                <a href="{{ route('admin.coupons.edit', $coupon->id) }}" class="btn-secondary">{{ __('Edit Coupon') }}</a>
            </div>
        </div>

        {{-- Coupon Details --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Discount Information --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-bold text-ink">{{ __('Discount Information') }}</h2>
                </div>
                <div class="divide-y divide-border">
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Type') }}</span>
                        <span>
                            @if($coupon->type === 'percent_off')
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-brand/15 text-brand">{{ __('Percentage') }}</span>
                            @else
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-info/15 text-info">{{ __('Fixed Amount') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Value') }}</span>
                        <span class="text-lg font-extrabold text-ink">
                            @if($coupon->type === 'percent_off')
                                {{ $coupon->percent_off }}% {{ __('off') }}
                            @else
                                @currency($coupon->amount_off) {{ __('off') }}
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Duration') }}</span>
                        <span class="text-sm font-medium text-ink capitalize">
                            {{ $coupon->duration }}
                            @if($coupon->duration === 'repeating' && $coupon->duration_in_months)
                                <span class="text-muted font-normal">({{ $coupon->duration_in_months }} {{ __('months') }})</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Status') }}</span>
                        <span>
                            @if($coupon->is_active)
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-success/15 text-success">{{ __('Active') }}</span>
                            @else
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-surface text-ink/80">{{ __('Inactive') }}</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Usage & Dates --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-bold text-ink">{{ __('Usage & Dates') }}</h2>
                </div>
                <div class="divide-y divide-border">
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Redemptions') }}</span>
                        <div class="text-right">
                            <span class="text-sm font-bold text-ink">{{ $coupon->times_redeemed ?? 0 }}</span>
                            @if($coupon->max_redemptions)
                                <span class="text-muted"> / {{ $coupon->max_redemptions }}</span>
                                <div class="w-24 h-1.5 bg-gray-200 rounded-full mt-1 ml-auto">
                                    <div class="h-1.5 bg-brand/100 rounded-full" style="width: {{ min(100, (($coupon->times_redeemed ?? 0) / $coupon->max_redemptions) * 100) }}%"></div>
                                </div>
                            @else
                                <span class="text-muted"> / {{ __('unlimited') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Expires At') }}</span>
                        <span class="text-sm font-medium text-ink">
                            @if($coupon->expires_at)
                                {{ \Carbon\Carbon::parse($coupon->expires_at)->format('M j, Y g:i A') }}
                                @if(\Carbon\Carbon::parse($coupon->expires_at)->isPast())
                                    <span class="block text-xs text-danger font-medium text-right">{{ __('Already expired') }}</span>
                                @else
                                    <span class="block text-xs text-muted text-right" title="{{ \Carbon\Carbon::parse($coupon->expires_at)->format('M j, Y g:i A') }}">{{ \Carbon\Carbon::parse($coupon->expires_at)->diffForHumans() }}</span>
                                @endif
                            @else
                                <span class="text-muted">{{ __('No expiration') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Stripe Coupon ID') }}</span>
                        <span class="text-sm font-medium text-ink">
                            @if($coupon->stripe_coupon_id)
                                <code class="text-xs bg-surface text-ink/80 px-2 py-0.5 rounded-lg border border-border">{{ $coupon->stripe_coupon_id }}</code>
                            @else
                                <span class="text-muted">{{ __('Not linked') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <span class="text-sm text-muted">{{ __('Created At') }}</span>
                        <span class="text-sm font-medium text-ink">
                            {{ \Carbon\Carbon::parse($coupon->created_at)->format('M j, Y g:i A') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.coupons.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-muted border border-border rounded-xl hover:bg-surface transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Back to Coupons') }}
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.coupons.edit', $coupon->id) }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand border border-brand/20 rounded-xl hover:bg-brand/10 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    {{ __('Edit Coupon') }}
                </a>
                <form method="POST" action="{{ route('admin.coupons.destroy', $coupon->id) }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            onclick="if(!confirm('{{ __('Are you sure you want to delete this coupon? This will also deactivate it in Stripe.') }}')){event.preventDefault();return;} this.disabled=true; this.closest('form').submit();"
                            class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-danger border border-danger/20 rounded-xl hover:bg-danger/10 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        {{ __('Delete') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
