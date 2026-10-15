<x-layouts.settings :title="__('Webhooks')">
    <div class="space-y-6">
        {{-- Page header --}}
        <div>
            <h2 class="text-xl font-semibold text-ink">{{ __('Webhook Logs') }}</h2>
            <p class="text-sm text-muted mt-1">{{ __('Monitor outbound webhook deliveries and retry failed requests.') }}</p>
        </div>

        <livewire:settings.webhook-logs />
    </div>
</x-layouts.settings>
