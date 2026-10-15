<x-layouts.guest :title="__('Reset Password')">
    <div>
        <h2 class="text-2xl font-bold text-ink">{{ __('Reset your password') }}</h2>
        <p class="mt-2 text-sm text-muted">{{ __('Choose an available method to reset your password.') }}</p>
        @if(!\App\Support\AccountFeatures::emailRecovery() && !\App\Support\AccountFeatures::phraseRecovery())
            <p class="mt-2 text-sm text-muted">{{ __('Password recovery is currently disabled. Contact your administrator for help.') }}</p>
        @endif
    </div>

    @if(\App\Support\AccountFeatures::phraseRecovery())
    <a href="{{ route('password.phrase') }}" class="mt-6 block rounded-xl border border-border bg-surface p-4 hover:border-brand transition-colors">
        <span class="block font-semibold text-brand">{{ __('Use recovery phrase') }}</span>
        <span class="mt-1 block text-sm text-muted">{{ __('Have your saved passphrase? Reset your password without accessing your mailbox.') }}</span>
    </a>
    @endif

    @if(session('status'))
    <div class="mt-4 p-4 bg-success/10 border border-success/20 text-success rounded-xl text-sm">
        {{ session('status') }}
    </div>
    @endif

    @if(\App\Support\AccountFeatures::emailRecovery())
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <h3 class="text-sm font-semibold text-ink">{{ __('Send an email reset link') }}</h3>
        <div>
            <label for="email" class="block text-sm font-medium text-muted mb-1">{{ __('Email address') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="input @error('email') !border-danger @enderror"
                   placeholder="{{ __('you@company.com') }}">
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <button type="submit" :disabled="submitting"
                class="btn-primary w-full py-3"
                :class="submitting && 'opacity-75 cursor-not-allowed'">
            <span x-show="!submitting">{{ __('Send Reset Link') }}</span>
            <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Sending reset link...') }}
            </span>
        </button>

        <p class="text-center text-sm text-muted">
            <a href="{{ route('login') }}" class="text-brand font-semibold hover:text-brand-strong">{{ __('Back to login') }}</a>
        </p>
    </form>
    @else
        <p class="mt-5 text-center text-sm"><a href="{{ route('login') }}" class="text-brand font-semibold">{{ __('Back to login') }}</a></p>
    @endif
</x-layouts.guest>
