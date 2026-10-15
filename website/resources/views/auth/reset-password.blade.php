<x-layouts.guest :title="__('New Password')">
    <div>
        <h2 class="text-2xl font-bold text-ink">{{ __('Create a new password') }}</h2>
        <p class="mt-2 text-sm text-muted">{{ __('Your new password must be different from previous passwords.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block text-sm font-medium text-muted mb-1">{{ __('Email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required readonly
                   class="input bg-surface text-muted">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-muted mb-1">{{ __('New password') }}</label>
            <input id="password" type="password" name="password" required
                   class="input @error('password') !border-danger @enderror"
                   placeholder="{{ __('Choose a password') }}">
            <p class="text-xs text-muted">{{ \App\Support\CredentialPolicy::publicRules()['password_help'] }}</p>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-muted mb-1">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                   class="input"
                   placeholder="{{ __('Confirm your new password') }}">
        </div>

        <button type="submit" :disabled="submitting"
                class="btn-primary w-full py-3"
                :class="submitting && 'opacity-75 cursor-not-allowed'">
            <span x-show="!submitting">{{ __('Reset Password') }}</span>
            <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Resetting password...') }}
            </span>
        </button>
    </form>
</x-layouts.guest>
