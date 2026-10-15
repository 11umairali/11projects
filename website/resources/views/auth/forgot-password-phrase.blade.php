<x-layouts.guest :title="__('Reset password with recovery phrase')">
    <div>
        <h2 class="text-2xl font-bold text-ink">{{ __('Use your recovery phrase') }}</h2>
        <p class="mt-2 text-sm text-muted">{{ __('Enter your username and the recovery words you saved when you created your account. You can set a new password without opening your email.') }}</p>
    </div>

    @if(session('status'))
        <p role="status" class="mt-4 text-sm text-muted">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.phrase.update') }}" class="mt-6 space-y-4"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <div>
            <label for="username" class="block text-sm font-medium text-muted mb-1">{{ __('Username or email') }}</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus maxlength="255"
                   autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false"
                   class="input @error('username') !border-danger @enderror" placeholder="{{ __('Your username') }}">
            @error('username') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="phrase" class="block text-sm font-medium text-muted mb-1">{{ __('Recovery phrase (passphrase)') }}</label>
            <textarea id="phrase" name="phrase" rows="3" required maxlength="400" autocomplete="off"
                      autocapitalize="off" autocorrect="off" spellcheck="false"
                      class="input @error('phrase') !border-danger @enderror"
                      placeholder="{{ __('Enter your recovery words in order') }}"></textarea>
            <p class="mt-1 text-xs text-muted">{{ __('Keep your recovery phrase private. Enter all words in their original order.') }}</p>
            @error('phrase') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-muted mb-1">{{ __('New password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="input @error('password') !border-danger @enderror" placeholder="{{ __('Choose a new password') }}">
            <p class="mt-1 text-xs text-muted">{{ \App\Support\CredentialPolicy::publicRules()['password_help'] }}</p>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-muted mb-1">{{ __('Confirm new password') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="input" placeholder="{{ __('Confirm your new password') }}">
        </div>
        <button type="submit" :disabled="submitting" class="btn-primary w-full py-3"
                :class="submitting && 'opacity-75 cursor-not-allowed'">
            <span x-show="!submitting">{{ __('Reset password') }}</span>
            <span x-show="submitting" x-cloak>{{ __('Resetting password...') }}</span>
        </button>
    </form>

    <div class="mt-5 flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm">
        @if(\App\Support\AccountFeatures::emailRecovery())
        <a href="{{ route('password.request') }}" class="text-brand font-semibold hover:text-brand-strong">{{ __('Use an email reset link') }}</a>
        @endif
        <a href="{{ route('login') }}" class="text-muted hover:text-ink">{{ __('Back to login') }}</a>
    </div>
</x-layouts.guest>
