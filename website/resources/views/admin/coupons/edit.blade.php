<x-layouts.admin :title="__('Edit Coupon')" :subtitle="__('Update coupon') . ' ' . $coupon->code . ' ' . __('settings.')">
    <div class="space-y-6">

        <form method="POST" action="{{ route('admin.coupons.update', $coupon->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Editable Fields --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Edit Coupon') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Only name, status, maximum uses, and expiration can be modified. Stripe coupons are mostly immutable once created.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    {{-- Immutable fields (display only) --}}
                    <div class="p-4 bg-surface/50 rounded-xl border border-border dark:border-gray-700/50 space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted">{{ __('Immutable Fields (set at creation)') }}</p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <p class="text-xs text-muted">{{ __('Code') }}</p>
                                <code class="text-sm font-bold text-brand">{{ $coupon->code }}</code>
                            </div>
                            <div>
                                <p class="text-xs text-muted">{{ __('Type') }}</p>
                                <p class="text-sm font-medium text-ink">
                                    {{ $coupon->type === 'percent_off' ? __('Percentage') : __('Fixed Amount') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">{{ __('Value') }}</p>
                                <p class="text-sm font-bold text-ink">
                                    @if($coupon->type === 'percent_off')
                                        {{ $coupon->percent_off }}%
                                    @else
                                        @currency($coupon->amount_off)
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">{{ __('Duration') }}</p>
                                <p class="text-sm font-medium text-ink capitalize">
                                    {{ $coupon->duration }}
                                    @if($coupon->duration === 'repeating' && $coupon->duration_in_months)
                                        ({{ $coupon->duration_in_months }} {{ __('months') }})
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Name') }} <span class="text-danger" aria-hidden="true">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $coupon->name) }}" required aria-required="true"
                               placeholder="{{ __('e.g. Summer Sale 20% Off') }}"
                               class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('name') !border-danger !ring-danger/20 @enderror">
                        @error('name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        {{-- Active --}}
                        <div class="flex items-center gap-3 sm:pt-5">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $coupon->is_active) ? 'checked' : '' }}
                                       class="sr-only peer">
                                <div class="w-10 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-surface-2 after:border-border after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand"></div>
                            </label>
                            <span class="text-sm font-medium text-ink">{{ __('Active') }}</span>
                        </div>

                        {{-- Maximum Uses --}}
                        <div>
                            <label for="max_redemptions" class="block text-sm font-medium text-ink mb-1.5">
                                {{ __('Maximum Uses') }}
                                <span class="relative inline-block ml-1" x-data="{ show: false }">
                                    <svg @mouseenter="show = true" @mouseleave="show = false" class="w-4 h-4 inline text-muted/60 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div x-show="show" x-transition class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-ink rounded-lg shadow-lg whitespace-nowrap z-50">
                                        {{ __('Total number of times this coupon can be applied across all customers.') }}
                                        <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 w-2 h-2 bg-ink rotate-45"></div>
                                    </div>
                                </span>
                            </label>
                            <input type="number" name="max_redemptions" id="max_redemptions" value="{{ old('max_redemptions', $coupon->max_redemptions) }}"
                                   min="1"
                                   placeholder="{{ __('Unlimited') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('max_redemptions') !border-danger !ring-danger/20 @enderror">
                            @if($coupon->times_redeemed)
                                <p class="text-xs text-muted mt-1">{{ __('Already used') }} {{ $coupon->times_redeemed }} {{ __('time(s).') }}</p>
                            @endif
                            @error('max_redemptions') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Expires At --}}
                        <div>
                            <label for="expires_at" class="block text-sm font-medium text-ink mb-1.5">{{ __('Expiration Date') }}</label>
                            <input type="date" name="expires_at" id="expires_at"
                                   value="{{ old('expires_at', $coupon->expires_at ? \Carbon\Carbon::parse($coupon->expires_at)->format('Y-m-d') : '') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('expires_at') !border-danger !ring-danger/20 @enderror">
                            @error('expires_at') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
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
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong shadow-lg shadow-soft transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ __('Update Coupon') }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>
