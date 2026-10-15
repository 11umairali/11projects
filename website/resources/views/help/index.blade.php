<x-layouts.app :title="__('Help Center')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-ink">{{ __('Help Center') }}</h1>
        <p class="text-sm text-muted mt-1">{{ __('Find answers to common questions and learn how to use :app.', ['app' => config('app.name')]) }}</p>
    </div>
    <livewire:help-center />
</x-layouts.app>
