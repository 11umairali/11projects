<x-layouts.app :title="__('Complete Payment')">
<div class="max-w-lg mx-auto py-12 px-4">
    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-4">
        <h1 class="text-xl font-bold text-ink">{{ __('Complete Your Payment') }}</h1>
        <p class="text-sm text-muted">{{ __('Follow the instructions below to complete your payment for the') }} {{ $plan->name }} {{ __('plan') }}.</p>

        <div class="bg-surface rounded-xl border border-border p-4">
            {!! $html !!}
        </div>

        <a href="{{ route('settings.billing') }}" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('Back to Billing') }}
        </a>
    </div>
</div>
</x-layouts.app>
