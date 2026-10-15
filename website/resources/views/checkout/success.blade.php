<x-layouts.app :title="__('Payment Successful')">
<div class="max-w-lg mx-auto py-16 px-4 text-center">
    <div class="bg-surface-2 rounded-2xl border border-border p-8 space-y-5">
        <div class="w-16 h-16 bg-success/10 rounded-full flex items-center justify-center mx-auto">
            <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>

        <h1 class="text-2xl font-bold text-ink">{{ __('Payment Successful!') }}</h1>
        <p class="text-sm text-muted">{{ __('Your subscription has been activated. You now have access to all plan features.') }}</p>

        @if($payment)
        <div class="bg-surface rounded-xl p-4 text-sm text-left space-y-1">
            @if($payment->coupon_code)
            <div class="flex justify-between"><span class="text-muted">{{ __('Original Amount') }}</span><span class="font-medium text-ink">{{ strtoupper($payment->currency) }} {{ number_format($payment->original_amount, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-muted">{{ __('Coupon') }}</span><span class="font-medium text-success">{{ $payment->coupon_code }}</span></div>
            <div class="flex justify-between"><span class="text-muted">{{ __('Discount') }}</span><span class="font-medium text-success">-{{ strtoupper($payment->currency) }} {{ number_format($payment->discount_amount, 2) }}</span></div>
            @endif
            <div class="flex justify-between"><span class="text-muted">{{ __('Amount Paid') }}</span><span class="font-medium text-ink">{{ strtoupper($payment->currency) }} {{ number_format($payment->amount, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-muted">{{ __('Status') }}</span><span class="font-medium text-success">{{ __('Completed') }}</span></div>
            @if($payment->gateway_transaction_id)
            <div class="flex justify-between"><span class="text-muted">{{ __('Transaction ID') }}</span><span class="font-mono text-xs text-ink">{{ $payment->gateway_transaction_id }}</span></div>
            @endif
        </div>
        @if($payment->coupon_code && $payment->discount_amount > 0)
        <div class="bg-success/10 border border-success/20 rounded-xl p-3 text-sm text-success font-medium">
            {{ __('You saved') }} {{ strtoupper($payment->currency) }} {{ number_format($payment->discount_amount, 2) }} {{ __('with coupon') }} {{ $payment->coupon_code }}!
        </div>
        @endif
        @endif

        <a href="{{ route('dashboard') }}" class="inline-block px-6 py-2.5 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 transition-colors">
            {{ __('Go to Dashboard') }}
        </a>
    </div>
</div>
</x-layouts.app>
