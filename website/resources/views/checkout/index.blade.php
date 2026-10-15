<x-layouts.app title="Checkout — {{ $plan->name }}">
<div class="max-w-4xl mx-auto" x-data="{
    selectedGateway: '',
    selectedCurrency: '{{ $defaultCurrency }}',
    basePrice: {{ $price }},
    gatewayCurrencies: @js($gatewayCurrencies),
    currencySymbols: @js($currencySymbols),
    exchangeRates: @js($currencies->pluck('exchange_rate', 'code')),

    // Coupon state
    couponCode: '{{ $couponCode }}',
    couponValid: false,
    couponMessage: '',
    couponType: '',
    couponValue: 0,
    couponName: '',
    couponLoading: false,
    couponError: false,

    get filteredGateways() {
        const c = this.selectedCurrency;
        return Object.entries(this.gatewayCurrencies).filter(([slug, currencies]) => {
            return currencies.length === 0 || currencies.includes(c);
        }).map(([slug]) => slug);
    },
    get currencySymbol() {
        return this.currencySymbols[this.selectedCurrency] || this.selectedCurrency;
    },
    get convertedPrice() {
        const rate = this.exchangeRates[this.selectedCurrency] || 1;
        return (this.basePrice * rate).toFixed(2);
    },
    get discountAmount() {
        if (!this.couponValid) return 0;
        const price = parseFloat(this.convertedPrice);
        if (this.couponType === 'percent') {
            return (price * (this.couponValue / 100)).toFixed(2);
        }
        return Math.min(this.couponValue, price).toFixed(2);
    },
    get finalPrice() {
        if (!this.couponValid) return this.convertedPrice;
        return Math.max(0, parseFloat(this.convertedPrice) - parseFloat(this.discountAmount)).toFixed(2);
    },

    async applyCoupon() {
        if (!this.couponCode.trim()) return;
        this.couponLoading = true;
        this.couponError = false;
        this.couponValid = false;
        this.couponMessage = '';
        try {
            const response = await fetch('{{ route('checkout.validate-coupon') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    code: this.couponCode,
                    plan_id: {{ $plan->id }},
                }),
            });
            const data = await response.json();
            if (data.valid) {
                this.couponValid = true;
                this.couponType = data.type;
                this.couponValue = parseFloat(data.value);
                this.couponName = data.name;
                this.couponMessage = data.message;
                this.couponError = false;
            } else {
                this.couponValid = false;
                this.couponMessage = data.message;
                this.couponError = true;
            }
        } catch (e) {
            this.couponMessage = '{{ __("Failed to validate coupon. Please try again.") }}';
            this.couponError = true;
        }
        this.couponLoading = false;
    },

    removeCoupon() {
        this.couponCode = '';
        this.couponValid = false;
        this.couponMessage = '';
        this.couponType = '';
        this.couponValue = 0;
        this.couponName = '';
        this.couponError = false;
    }
}">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Gateway selection --}}
        <div class="lg:col-span-2 space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-ink">{{ __('Complete Your Purchase') }}</h1>
                <p class="text-sm text-muted mt-1">{{ __('Choose your preferred payment method to subscribe to') }} {{ $plan->name }}.</p>
            </div>

            {{-- Currency selector --}}
            <div class="bg-surface-2 rounded-2xl border border-border p-5">
                <label class="block text-sm font-semibold text-ink mb-2">{{ __('Currency') }}</label>
                <select x-model="selectedCurrency" class="w-full sm:w-64 px-4 py-2.5 text-sm bg-surface border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @foreach($availableCurrencies as $code)
                    <option value="{{ $code }}">{{ $code }} {{ $currencySymbols[$code] ?? '' }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-muted mt-2">{{ __('Payment will be processed in the selected currency. Available gateways depend on currency.') }}</p>
            </div>

            {{-- Payment gateways --}}
            <div class="bg-surface-2 rounded-2xl border border-border p-5">
                <label class="block text-sm font-semibold text-ink mb-3">{{ __('Payment Method') }}</label>

                <div class="space-y-2">
                    @foreach($gateways as $gw)
                    {{-- Selected state used to apply `bg-primary-50` — a near-
                         white background from the old light-theme palette —
                         which made the white text-ink label illegible on the
                         dark theme. Swapped for brand-tinted translucent
                         classes that contrast against both themes. --}}
                    <label x-show="filteredGateways.includes('{{ $gw->slug }}')"
                           x-transition
                           class="flex items-center gap-4 p-4 border-2 rounded-xl cursor-pointer transition-all"
                           :class="selectedGateway === '{{ $gw->slug }}' ? 'border-brand bg-brand/10 ring-1 ring-brand/40' : 'border-border hover:border-brand/40'">
                        <input type="radio" name="gateway_radio" value="{{ $gw->slug }}" x-model="selectedGateway" class="sr-only">

                        {{-- Gateway icon --}}
                        <div class="w-10 h-10 rounded-xl bg-surface flex items-center justify-center border border-border shrink-0">
                            @if($gw->logo)
                            <img src="{{ asset($gw->logo) }}" alt="{{ $gw->name }}" class="w-6 h-6 object-contain">
                            @else
                            <span class="text-xs font-bold text-brand">{{ strtoupper(substr($gw->slug, 0, 2)) }}</span>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-ink">{{ $gw->name }}</p>
                            @if($gw->description)
                            <p class="text-xs text-muted line-clamp-1">{{ $gw->description }}</p>
                            @endif
                        </div>

                        {{-- Checkmark --}}
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0"
                             :class="selectedGateway === '{{ $gw->slug }}' ? 'border-brand bg-brand' : 'border-border'">
                            <svg x-show="selectedGateway === '{{ $gw->slug }}'" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </label>
                    @endforeach
                </div>

                {{-- No gateways message --}}
                <div x-show="filteredGateways.length === 0" class="text-center py-8">
                    <p class="text-sm text-muted">{{ __('No payment methods available for this currency. Please select a different currency.') }}</p>
                </div>
            </div>
        </div>

        {{-- Right: Order summary --}}
        <div class="lg:col-span-1">
            <div class="bg-surface-2 rounded-2xl border border-border p-5 sticky top-24 space-y-4">
                <h2 class="text-sm font-semibold text-ink uppercase tracking-wide">{{ __('Order Summary') }}</h2>

                <div class="bg-gradient-to-br from-primary-600 to-secondary-600 rounded-xl p-4 text-white">
                    <p class="text-sm font-medium text-white/80">{{ $plan->name }} {{ __('Plan') }}</p>
                    <template x-if="!couponValid">
                        <p class="text-3xl font-extrabold mt-1"><span x-text="currencySymbol"></span><span x-text="convertedPrice"></span></p>
                    </template>
                    <template x-if="couponValid">
                        <div>
                            <p class="text-lg line-through text-white/50 mt-1"><span x-text="currencySymbol"></span><span x-text="convertedPrice"></span></p>
                            <p class="text-3xl font-extrabold"><span x-text="currencySymbol"></span><span x-text="finalPrice"></span></p>
                        </div>
                    </template>
                    <p class="text-xs text-white/60 mt-0.5">{{ $billingCycle === 'yearly' ? __('per year') : __('per month') }} <span x-show="selectedCurrency !== 'USD'" class="text-white/40">(<span x-text="selectedCurrency"></span>)</span></p>
                </div>

                {{-- Coupon code input --}}
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-ink uppercase tracking-wide">{{ __('Coupon Code') }}</label>
                    <template x-if="!couponValid">
                        <div class="flex gap-2">
                            <input type="text"
                                   x-model="couponCode"
                                   @keydown.enter.prevent="applyCoupon()"
                                   placeholder="{{ __('ENTER COUPON CODE') }}"
                                   class="flex-1 min-w-0 px-3 py-2 text-sm bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 uppercase">
                            <button type="button"
                                    @click="applyCoupon()"
                                    :disabled="couponLoading || !couponCode.trim()"
                                    class="px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed whitespace-nowrap">
                                <span x-show="!couponLoading">{{ __('Apply') }}</span>
                                <span x-show="couponLoading">...</span>
                            </button>
                        </div>
                    </template>
                    <template x-if="couponValid">
                        <div class="flex items-center justify-between bg-success/10 border border-success/20 rounded-lg px-3 py-2">
                            <div>
                                <p class="text-sm font-semibold text-success" x-text="couponCode.toUpperCase()"></p>
                                <p class="text-xs text-success/80" x-text="couponMessage"></p>
                            </div>
                            <button type="button" @click="removeCoupon()" class="text-muted hover:text-danger transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                    <p x-show="couponError" x-text="couponMessage" class="text-xs text-danger"></p>
                </div>

                {{-- Price breakdown --}}
                <template x-if="couponValid">
                    <div class="bg-surface rounded-xl p-3 space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('Original price') }}</span>
                            <span class="text-ink"><span x-text="currencySymbol"></span><span x-text="convertedPrice"></span></span>
                        </div>
                        <div class="flex justify-between text-success">
                            <span>{{ __('Discount') }}</span>
                            <span>-<span x-text="currencySymbol"></span><span x-text="discountAmount"></span></span>
                        </div>
                        <div class="border-t border-border pt-1.5 flex justify-between font-semibold">
                            <span class="text-ink">{{ __('Total') }}</span>
                            <span class="text-ink"><span x-text="currencySymbol"></span><span x-text="finalPrice"></span></span>
                        </div>
                    </div>
                </template>

                @if($plan->features)
                <ul class="space-y-2">
                    @foreach($plan->features as $key => $value)
                    @if($key !== 'trial_days')
                    @php
                        if (is_numeric($key)) {
                            // Plain array: value is the feature string itself
                            $display = is_string($value) ? $value : '';
                        } else {
                            // Associative array: key is feature name, value is count/boolean
                            $label = str_replace('_', ' ', $key);
                            $display = is_bool($value) || $value === true ? ucfirst($label) : (strtolower((string)$value) === 'unlimited' ? 'Unlimited ' . $label : $value . ' ' . $label);
                        }
                    @endphp
                    @if($display)
                    <li class="flex items-start gap-2 text-xs text-muted">
                        <svg class="w-3.5 h-3.5 text-success flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        {{ $display }}
                    </li>
                    @endif
                    @endif
                    @endforeach
                </ul>
                @endif

                {{-- Submit form --}}
                <form method="POST" action="{{ route('checkout.process', $plan->id) }}">
                    @csrf
                    <input type="hidden" name="billing_cycle" value="{{ $billingCycle }}">
                    <input type="hidden" name="gateway" :value="selectedGateway">
                    <input type="hidden" name="currency" :value="selectedCurrency">
                    <input type="hidden" name="coupon_code" :value="couponValid ? couponCode : ''">

                    <button type="submit"
                            :disabled="!selectedGateway"
                            class="w-full py-3 px-4 text-sm font-semibold rounded-xl transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                            :class="selectedGateway ? 'bg-primary-600 text-white hover:bg-primary-700 shadow-sm' : 'bg-gray-300 text-gray-500'">
                        {{ __('Pay & Subscribe') }}
                    </button>
                </form>

                @if(session('error'))
                <div class="p-3 bg-danger/10 border border-danger/20 text-danger rounded-xl text-xs">{{ session('error') }}</div>
                @endif

                <p class="text-[10px] text-muted text-center leading-relaxed">
                    {{ __('By proceeding, you agree to our') }} <a href="{{ route('legal.terms') }}" class="underline">{{ __('Terms of Service') }}</a>.
                    {{ __('Your subscription will auto-renew. Cancel anytime from billing settings.') }}
                </p>
            </div>
        </div>
    </div>
</div>
</x-layouts.app>
