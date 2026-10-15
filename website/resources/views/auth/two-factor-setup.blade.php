<x-layouts.guest :title="__('Set Up Two-Factor Authentication')">
    <div>
        <h2 class="text-2xl font-bold text-ink">{{ __('Set up two-factor authentication') }}</h2>
        <p class="mt-2 text-sm text-muted">{{ __('Scan the QR code below with your authenticator app (Google Authenticator, Authy, 1Password, etc.) and enter the 6-digit code to verify.') }}</p>
    </div>

    {{-- Error messages --}}
    @if ($errors->any())
        <div class="mt-4 p-4 bg-danger/10 border border-danger/20 rounded-xl">
            @foreach ($errors->all() as $error)
                <p class="text-sm text-danger">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="mt-8 space-y-6">
        {{-- QR Code --}}
        <div class="flex flex-col items-center">
            <div class="bg-surface-2 p-4 rounded-2xl border border-border shadow-sm">
                <img src="{{ $qrImageUrl }}" alt="{{ __('QR Code for authenticator app') }}" class="w-[200px] h-[200px]" loading="eager">
            </div>
        </div>

        {{-- Manual secret --}}
        <div class="bg-surface rounded-xl border border-border p-4">
            <p class="text-xs font-medium text-muted mb-2">{{ __("Can't scan the QR code? Enter this key manually:") }}</p>
            <div class="flex items-center gap-3" x-data="{ copied: false }">
                <code class="flex-1 block text-sm font-mono text-ink bg-surface-2 px-4 py-2.5 rounded-lg border border-border select-all break-all tracking-wider">{{ $secret }}</code>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $secret }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="flex-shrink-0 px-3 py-2.5 text-sm font-medium text-brand border border-brand/30 rounded-lg hover:bg-brand/5 transition-colors">
                    <span x-show="!copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </button>
            </div>
        </div>

        {{-- Verification form --}}
        <form method="POST" action="{{ route('two-factor.activate') }}" class="space-y-5"
              x-data="{ submitting: false, code: '' }" @submit="submitting = true">
            @csrf

            <div>
                <label for="code" class="block text-sm font-medium text-muted mb-1">{{ __('Verification code') }}</label>
                <input id="code"
                       type="text"
                       name="code"
                       x-model="code"
                       required
                       autofocus
                       autocomplete="one-time-code"
                       inputmode="numeric"
                       pattern="[0-9]{6}"
                       maxlength="6"
                       placeholder="{{ __('Enter 6-digit code') }}"
                       class="input text-center text-2xl tracking-[0.5em] font-mono @error('code') !border-danger !ring-danger/20 @enderror">
                @error('code')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    :disabled="submitting || code.length !== 6"
                    class="btn-primary w-full py-3"
                    :class="(submitting || code.length !== 6) && 'opacity-75 cursor-not-allowed'">
                <span x-show="!submitting">{{ __('Enable Two-Factor Authentication') }}</span>
                <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Verifying...') }}
                </span>
            </button>
        </form>

        {{-- Back link --}}
        <p class="text-center text-sm text-muted">
            <a href="{{ route('settings.security') }}" class="text-brand font-semibold hover:text-brand-strong">{{ __('Back to security settings') }}</a>
        </p>
    </div>
</x-layouts.guest>
