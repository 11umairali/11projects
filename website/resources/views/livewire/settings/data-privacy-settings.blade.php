<div class="space-y-6">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    <div>
        <h1 class="text-2xl font-bold text-ink">{{ __('Data & Privacy') }}</h1>
        <p class="text-sm text-muted mt-1">{{ __('Control your data, privacy settings, and compliance preferences.') }}</p>
    </div>

    {{-- Data retention --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Data Retention') }}</h2>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Conversation retention period') }}</label>
                <select wire:model="conversationRetention" class="w-full sm:w-80 px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                    <option value="30">{{ __('30 days') }}</option>
                    <option value="90">{{ __('90 days') }}</option>
                    <option value="180">{{ __('180 days') }}</option>
                    <option value="365">{{ __('1 year') }}</option>
                    <option value="0">{{ __('Forever') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1.5">{{ __('Contact data retention') }}</label>
                <select wire:model="contactRetention" class="w-full sm:w-80 px-4 py-2.5 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-surface-2">
                    <option value="90">{{ __('90 days') }}</option>
                    <option value="365">{{ __('1 year') }}</option>
                    <option value="0">{{ __('Forever') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Privacy controls --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Privacy Controls') }}</h2>
        <div class="space-y-4">
            <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                <div>
                    <p class="text-sm font-medium text-ink">{{ __('Allow AI model training') }}</p>
                    <p class="text-xs text-muted mt-0.5">{{ __('Allow your conversation data to improve AI models') }}</p>
                </div>
                <button wire:click="$toggle('aiTraining')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $aiTraining ? 'bg-primary-600' : 'bg-gray-200' }}">
                    <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $aiTraining ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>
            <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                <div>
                    <p class="text-sm font-medium text-ink">{{ __('Usage analytics') }}</p>
                    <p class="text-xs text-muted mt-0.5">{{ __('Help us improve by sharing anonymous usage analytics') }}</p>
                </div>
                <button wire:click="$toggle('analytics')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $analytics ? 'bg-primary-600' : 'bg-gray-200' }}">
                    <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $analytics ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>
            <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                <div>
                    <p class="text-sm font-medium text-ink">{{ __('Third-party data sharing') }}</p>
                    <p class="text-xs text-muted mt-0.5">{{ __('Share data with connected integrations') }}</p>
                </div>
                <button wire:click="$toggle('thirdParty')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $thirdParty ? 'bg-primary-600' : 'bg-gray-200' }}">
                    <span class="inline-block h-4 w-4 rounded-full bg-surface-2 shadow transition-transform {{ $thirdParty ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Save --}}
    <div class="flex items-center justify-end">
        <button wire:click="save" wire:loading.attr="disabled"
                class="btn-primary px-6 py-2.5 text-sm disabled:opacity-60">
            <span wire:loading.remove wire:target="save">{{ __('Save Privacy Settings') }}</span>
            <span wire:loading wire:target="save" class="flex items-center gap-1.5">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Saving...
            </span>
        </button>
    </div>

    {{-- Export data --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-2">{{ __('Export Your Data') }}</h2>
        <p class="text-sm text-muted mb-4">{{ __('Download a complete export of all your data in JSON format.') }}</p>
        <a href="{{ url('/settings/export-data') }}"
            class="px-4 py-2.5 text-sm font-medium border border-border rounded-xl hover:bg-surface transition-colors inline-flex items-center gap-2">
            <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ __('Request Data Export') }}
        </a>
    </div>
</div>
