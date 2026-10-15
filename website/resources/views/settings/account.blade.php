<x-layouts.settings :title="__('Account Settings')">
    <div class="space-y-6">
        {{-- Page header --}}
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Account Settings') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('Manage your account preferences and configuration.') }}</p>
        </div>

        {{-- Account info --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6">
            <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Account Information') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 bg-surface rounded-xl">
                    <p class="text-xs text-muted">{{ __('Account ID') }}</p>
                    <p class="text-sm font-mono font-medium text-ink mt-0.5">{{ auth()->user()->id }}</p>
                </div>
                <div class="p-4 bg-surface rounded-xl">
                    <p class="text-xs text-muted">{{ __('Account Created') }}</p>
                    <p class="text-sm font-medium text-ink mt-0.5">{{ auth()->user()->created_at->format('F j, Y') }}</p>
                </div>
                <div class="p-4 bg-surface rounded-xl">
                    <p class="text-xs text-muted">{{ __('Email') }}</p>
                    <p class="text-sm font-medium text-ink mt-0.5">{{ auth()->user()->email ?? 'john@mailtrixy.com' }}</p>
                </div>
                <div class="p-4 bg-surface rounded-xl">
                    <p class="text-xs text-muted">{{ __('Account Status') }}</p>
                    <p class="text-sm font-medium text-ink mt-0.5">{{ ucfirst(auth()->user()->status ?? 'active') }}</p>
                </div>
                <div class="p-4 bg-surface rounded-xl">
                    <p class="text-xs text-muted">{{ __('Auth Provider') }}</p>
                    <p class="text-sm font-medium text-ink mt-0.5">{{ __('Email & Password') }}</p>
                </div>
            </div>
        </div>

        {{-- Appearance (dark mode only — theme switcher removed) --}}

        {{-- Connected accounts --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6">
            <h2 class="text-lg font-semibold text-ink mb-4">{{ __('Connected Accounts') }}</h2>
            <div class="space-y-3">
                @php
                $oauthProviders = [
                    ['name' => 'Google', 'connected' => false, 'bg' => 'bg-danger/10', 'text' => 'text-danger'],
                    ['name' => 'Microsoft', 'connected' => false, 'bg' => 'bg-info/10', 'text' => 'text-blue-600'],
                    ['name' => 'GitHub', 'connected' => false, 'bg' => 'bg-surface', 'text' => 'text-muted'],
                ];
                @endphp

                @foreach($oauthProviders as $provider)
                <div class="flex items-center justify-between p-4 bg-surface rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 {{ $provider['bg'] }} rounded-xl flex items-center justify-center">
                            <span class="text-sm font-bold {{ $provider['text'] }}">{{ substr($provider['name'], 0, 1) }}</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $provider['name'] }}</p>
                            <p class="text-xs text-muted">{{ __('Not connected') }}</p>
                        </div>
                    </div>
                    <a href="{{ url('/auth/' . strtolower($provider['name']) . '/redirect') }}" class="px-4 py-2 text-sm font-medium text-muted bg-surface border border-border rounded-xl transition-colors inline-block cursor-default opacity-60" :title="__('Social login connections are managed automatically when you sign in with') . ' ' . $provider['name']">
                        {{ __('Auto-connected on login') }}
                    </a>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Danger zone --}}
        <div class="bg-surface-2 rounded-2xl border-2 border-danger/20 p-6" x-data="{ showDeactivate: false, showDelete: false }">
            <h2 class="text-lg font-semibold text-danger mb-2">{{ __('Danger Zone') }}</h2>
            <p class="text-sm text-muted mb-4">{{ __('These actions are permanent and cannot be undone.') }}</p>

            <div class="space-y-3">
                <div class="flex items-center justify-between p-4 bg-danger/10 rounded-xl border border-danger/20">
                    <div>
                        <p class="text-sm font-medium text-red-900">{{ __('Deactivate Account') }}</p>
                        <p class="text-xs text-danger mt-0.5">{{ __('Temporarily disable your account. You can reactivate later.') }}</p>
                    </div>
                    <form method="POST" action="{{ url('/settings/deactivate-account') }}"
                          @submit.prevent="if(confirm('Are you sure you want to deactivate your account? You can reactivate by contacting support.')) $el.submit()">
                        @csrf
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-danger border border-red-300 rounded-xl hover:bg-danger/15 transition-colors disabled:opacity-50">
                            {{ __('Deactivate') }}
                        </button>
                    </form>
                </div>
                <div class="p-4 bg-danger/10 rounded-xl border border-danger/20" x-data="{ showDeleteForm: false }">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-red-900">{{ __('Delete Account') }}</p>
                            <p class="text-xs text-danger mt-0.5">{{ __('Permanently delete your account and all associated data.') }}</p>
                        </div>
                        <button @click="showDeleteForm = !showDeleteForm"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-xl hover:bg-red-700 transition-colors">
                            {{ __('Delete Account') }}
                        </button>
                    </div>
                    <form x-show="showDeleteForm" x-transition method="POST" action="{{ url('/settings/delete-account') }}" class="mt-4 pt-4 border-t border-danger/20 space-y-3" style="display: none;">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-red-900 mb-1">{{ __('Confirm your password') }}</label>
                            <input type="password" name="password" required class="w-full px-3 py-2 text-sm border border-danger/30 rounded-xl focus:outline-none focus:ring-2 focus:ring-danger/40 bg-surface" placeholder="{{ __('Enter your current password') }}">
                            @error('password') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-red-900 mb-1">{{ __('Type') }} <strong>DELETE</strong> {{ __('to confirm') }}</label>
                            <input type="text" name="confirmation" required pattern="DELETE" class="w-full px-3 py-2 text-sm border border-danger/30 rounded-xl focus:outline-none focus:ring-2 focus:ring-danger/40 bg-surface" placeholder="{{ __('Type DELETE') }}">
                            @error('confirmation') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-xl hover:bg-red-700 transition-colors">
                            {{ __('Permanently Delete My Account') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.settings>
