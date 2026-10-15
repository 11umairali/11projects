<x-layouts.guest :title="__('Two-Factor Authentication')">
    <div class="text-center">
        <div class="mx-auto w-16 h-16 bg-brand/10 rounded-full flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-ink">{{ __('Two-factor authentication') }}</h2>
        <p class="mt-2 text-sm text-muted">{{ __('Enter the 6-digit code from your authenticator app to continue.') }}</p>
    </div>

    {{-- Error messages --}}
    @if ($errors->any())
        <div class="mt-4 p-4 bg-danger/10 border border-danger/20 rounded-xl">
            @foreach ($errors->all() as $error)
                <p class="text-sm text-danger">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="mt-8" x-data="{ mode: 'totp' }">
        {{-- Tab switcher --}}
        <div class="flex border border-border rounded-xl overflow-hidden mb-6">
            <button type="button" @click="mode = 'totp'"
                    :class="mode === 'totp' ? 'bg-brand text-white' : 'bg-surface-2 text-muted hover:text-ink'"
                    class="flex-1 py-2.5 text-sm font-medium transition-colors">
                {{ __('Authenticator Code') }}
            </button>
            <button type="button" @click="mode = 'recovery'"
                    :class="mode === 'recovery' ? 'bg-brand text-white' : 'bg-surface-2 text-muted hover:text-ink'"
                    class="flex-1 py-2.5 text-sm font-medium transition-colors border-l border-border">
                {{ __('Recovery Code') }}
            </button>
        </div>

        {{-- TOTP form --}}
        <form method="POST" action="{{ route('two-factor.verify') }}"
              x-show="mode === 'totp'"
              class="space-y-5"
              x-data="{ submitting: false, code: '' }" @submit="submitting = true">
            @csrf

            <div>
                <label for="totp-code" class="block text-sm font-medium text-muted mb-1">{{ __('Authentication code') }}</label>
                <input id="totp-code"
                       type="text"
                       name="code"
                       x-model="code"
                       required
                       autofocus
                       autocomplete="one-time-code"
                       inputmode="numeric"
                       pattern="[0-9]{6}"
                       maxlength="6"
                       placeholder="000000"
                       class="input text-center text-2xl tracking-[0.5em] font-mono">
            </div>

            <button type="submit"
                    :disabled="submitting || code.length !== 6"
                    class="btn-primary w-full py-3"
                    :class="(submitting || code.length !== 6) && 'opacity-75 cursor-not-allowed'">
                <span x-show="!submitting">{{ __('Verify') }}</span>
                <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Verifying...') }}
                </span>
            </button>
        </form>

        {{-- Recovery code form --}}
        <form method="POST" action="{{ route('two-factor.verify') }}"
              x-show="mode === 'recovery'" x-cloak
              class="space-y-5"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <div>
                <label for="recovery-code" class="block text-sm font-medium text-muted mb-1">{{ __('Recovery code') }}</label>
                <input id="recovery-code"
                       type="text"
                       name="code"
                       required
                       autocomplete="off"
                       placeholder="XXXX-XXXX"
                       class="input text-center text-lg tracking-wider font-mono uppercase">
                <p class="mt-1.5 text-xs text-muted">{{ __('Enter one of the recovery codes you saved when setting up 2FA.') }}</p>
            </div>

            <button type="submit" :disabled="submitting"
                    class="btn-primary w-full py-3"
                    :class="submitting && 'opacity-75 cursor-not-allowed'">
                <span x-show="!submitting">{{ __('Verify Recovery Code') }}</span>
                <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Verifying...') }}
                </span>
            </button>
        </form>

        {{-- Back to login --}}
        <p class="text-center text-sm text-muted mt-6">
            <a href="{{ route('login') }}" class="text-brand font-semibold hover:text-brand-strong">{{ __('Back to login') }}</a>
        </p>
    </div>
</x-layouts.guest>
