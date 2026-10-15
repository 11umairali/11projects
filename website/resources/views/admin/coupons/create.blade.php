<x-layouts.admin :title="__('Create Coupon')" :subtitle="__('Add a new discount coupon synced to Stripe.')">
    <div class="space-y-6" x-data="couponForm()">

        <form method="POST" action="{{ route('admin.coupons.store') }}" class="space-y-6">
            @csrf

            {{-- Basic Information --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Coupon Details') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('This coupon will be synced to Stripe upon creation.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Name --}}
                        <div>
                            <label for="name" class="block text-sm font-medium text-ink mb-1.5">{{ __('Name') }} <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required aria-required="true"
                                   placeholder="{{ __('e.g. Summer Sale 20% Off') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('name') !border-danger !ring-danger/20 @enderror">
                            @error('name') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Code --}}
                        <div>
                            <label for="code" class="block text-sm font-medium text-ink mb-1.5">{{ __('Code') }}</label>
                            <input type="text" name="code" id="code" value="{{ old('code') }}"
                                   placeholder="{{ __('Auto-generated if empty') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink font-mono uppercase @error('code') !border-danger !ring-danger/20 @enderror">
                            <p class="text-xs text-muted mt-1">{{ __('Letters, numbers, dashes, underscores. Leave blank to auto-generate.') }}</p>
                            @error('code') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Discount Configuration --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Discount') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Choose the discount type and value.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    {{-- Type --}}
                    <div>
                        <label for="type" class="block text-sm font-medium text-ink mb-1.5">
                            {{ __('Discount Type') }} <span class="text-danger" aria-hidden="true">*</span>
                            <span class="relative inline-block ml-1" x-data="{ show: false }">
                                <svg @mouseenter="show = true" @mouseleave="show = false" class="w-4 h-4 inline text-muted/60 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div x-show="show" x-transition class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-ink rounded-lg shadow-lg whitespace-nowrap z-50">
                                    {{ __('Percentage applies a % discount. Fixed Amount deducts a specific dollar value.') }}
                                    <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 w-2 h-2 bg-ink rotate-45"></div>
                                </div>
                            </span>
                        </label>
                        <select name="type" id="type" x-model="type" required aria-required="true"
                                class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('type') !border-danger !ring-danger/20 @enderror">
                            <option value="percent_off">{{ __('Percentage Off') }}</option>
                            <option value="amount_off">{{ __('Fixed Amount Off') }}</option>
                        </select>
                        @error('type') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Percent Off --}}
                        <div x-show="type === 'percent_off'" x-transition>
                            <label for="percent_off" class="block text-sm font-medium text-ink mb-1.5">
                                {{ __('Percentage') }} <span class="text-danger" aria-hidden="true">*</span>
                                <span class="relative inline-block ml-1" x-data="{ show: false }">
                                    <svg @mouseenter="show = true" @mouseleave="show = false" class="w-4 h-4 inline text-muted/60 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div x-show="show" x-transition class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-ink rounded-lg shadow-lg whitespace-nowrap z-50">
                                        {{ __('Enter a value between 1 and 100. For example, 20 means 20% off.') }}
                                        <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 w-2 h-2 bg-ink rotate-45"></div>
                                    </div>
                                </span>
                            </label>
                            <div class="relative">
                                <input type="number" name="percent_off" id="percent_off" value="{{ old('percent_off') }}"
                                       min="1" max="100" step="0.01"
                                       placeholder="{{ __('e.g. 20') }}"
                                       :required="type === 'percent_off'"
                                       :aria-required="type === 'percent_off' ? 'true' : 'false'"
                                       class="w-full px-4 pr-10 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('percent_off') !border-danger !ring-danger/20 @enderror">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-muted font-medium">%</span>
                            </div>
                            @error('percent_off') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Amount Off --}}
                        <div x-show="type === 'amount_off'" x-transition>
                            <label for="amount_off" class="block text-sm font-medium text-ink mb-1.5">{{ __('Amount') }} <span class="text-danger" aria-hidden="true">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-muted font-medium">$</span>
                                <input type="number" name="amount_off" id="amount_off" value="{{ old('amount_off') }}"
                                       min="0.01" step="0.01"
                                       placeholder="{{ __('e.g. 10.00') }}"
                                       :required="type === 'amount_off'"
                                       :aria-required="type === 'amount_off' ? 'true' : 'false'"
                                       class="w-full pl-8 pr-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('amount_off') !border-danger !ring-danger/20 @enderror">
                            </div>
                            @error('amount_off') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Currency --}}
                        <div x-show="type === 'amount_off'" x-transition>
                            <label for="currency" class="block text-sm font-medium text-ink mb-1.5">
                                {{ __('Currency') }}
                                <span class="relative inline-block ml-1" x-data="{ show: false }">
                                    <svg @mouseenter="show = true" @mouseleave="show = false" class="w-4 h-4 inline text-muted/60 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div x-show="show" x-transition class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-ink rounded-lg shadow-lg whitespace-nowrap z-50">
                                        {{ __('The currency used for the fixed discount amount.') }}
                                        <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 w-2 h-2 bg-ink rotate-45"></div>
                                    </div>
                                </span>
                            </label>
                            <select name="currency" id="currency"
                                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('currency') !border-danger !ring-danger/20 @enderror">
                                <option value="USD" {{ old('currency', 'USD') === 'USD' ? 'selected' : '' }}>{{ __('USD - US Dollar') }}</option>
                                <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>{{ __('EUR - Euro') }}</option>
                                <option value="GBP" {{ old('currency') === 'GBP' ? 'selected' : '' }}>{{ __('GBP - British Pound') }}</option>
                                <option value="CAD" {{ old('currency') === 'CAD' ? 'selected' : '' }}>{{ __('CAD - Canadian Dollar') }}</option>
                                <option value="AUD" {{ old('currency') === 'AUD' ? 'selected' : '' }}>{{ __('AUD - Australian Dollar') }}</option>
                                <option value="INR" {{ old('currency') === 'INR' ? 'selected' : '' }}>{{ __('INR - Indian Rupee') }}</option>
                                <option value="JPY" {{ old('currency') === 'JPY' ? 'selected' : '' }}>{{ __('JPY - Japanese Yen') }}</option>
                                <option value="BRL" {{ old('currency') === 'BRL' ? 'selected' : '' }}>{{ __('BRL - Brazilian Real') }}</option>
                                <option value="SGD" {{ old('currency') === 'SGD' ? 'selected' : '' }}>{{ __('SGD - Singapore Dollar') }}</option>
                                <option value="CHF" {{ old('currency') === 'CHF' ? 'selected' : '' }}>{{ __('CHF - Swiss Franc') }}</option>
                            </select>
                            @error('currency') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Duration & Limits --}}
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Duration & Limits') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('Control how long the discount applies and how many times it can be used.') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Duration --}}
                        <div>
                            <label for="duration" class="block text-sm font-medium text-ink mb-1.5">
                                {{ __('Duration') }} <span class="text-danger" aria-hidden="true">*</span>
                                <span class="relative inline-block ml-1" x-data="{ show: false }">
                                    <svg @mouseenter="show = true" @mouseleave="show = false" class="w-4 h-4 inline text-muted/60 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div x-show="show" x-transition class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-ink rounded-lg shadow-lg whitespace-nowrap z-50">
                                        {{ __("How long the discount applies to a customer's subscription billing.") }}
                                        <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 w-2 h-2 bg-ink rotate-45"></div>
                                    </div>
                                </span>
                            </label>
                            <select name="duration" id="duration" x-model="duration" required aria-required="true"
                                    class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('duration') !border-danger !ring-danger/20 @enderror">
                                <option value="once">{{ __('Once (first invoice only)') }}</option>
                                <option value="repeating">{{ __('Repeating (multiple months)') }}</option>
                                <option value="forever">{{ __('Forever (every invoice)') }}</option>
                            </select>
                            @error('duration') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Duration in Months --}}
                        <div x-show="duration === 'repeating'" x-transition>
                            <label for="duration_in_months" class="block text-sm font-medium text-ink mb-1.5">{{ __('Number of Months') }} <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="number" name="duration_in_months" id="duration_in_months" value="{{ old('duration_in_months') }}"
                                   min="1" max="36"
                                   placeholder="{{ __('e.g. 3') }}"
                                   :required="duration === 'repeating'"
                                   :aria-required="duration === 'repeating' ? 'true' : 'false'"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('duration_in_months') !border-danger !ring-danger/20 @enderror">
                            @error('duration_in_months') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
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
                            <input type="number" name="max_redemptions" id="max_redemptions" value="{{ old('max_redemptions') }}"
                                   min="1"
                                   placeholder="{{ __('Unlimited') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('max_redemptions') !border-danger !ring-danger/20 @enderror">
                            <p class="text-xs text-muted mt-1">{{ __('Leave blank for unlimited uses.') }}</p>
                            @error('max_redemptions') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Expires At --}}
                        <div>
                            <label for="expires_at" class="block text-sm font-medium text-ink mb-1.5">{{ __('Expiration Date') }}</label>
                            <input type="date" name="expires_at" id="expires_at" value="{{ old('expires_at') }}"
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   class="w-full px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-transparent bg-surface text-ink @error('expires_at') !border-danger !ring-danger/20 @enderror">
                            <p class="text-xs text-muted mt-1">{{ __('Leave blank for no expiration.') }}</p>
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
                    {{ __('Create Coupon') }}
                </button>
            </div>
        </form>
    </div>

    <script>
    function couponForm() {
        return {
            type: '{{ old('type', 'percent_off') }}',
            duration: '{{ old('duration', 'once') }}',
        };
    }
    </script>
</x-layouts.admin>
