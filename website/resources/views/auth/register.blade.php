<x-layouts.guest :title="__('Create Account')">
    @php($policy = \App\Support\CredentialPolicy::publicRules())
    <div>
        <h2 class="text-2xl font-bold text-ink">{{ __('Create your account') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('Your :domain address is your login for everything.', ['domain' => '@'.config('dahify.domain')]) }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        {{-- Honeypot + timing token (SignupGuard) — hidden from real people --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <input type="hidden" name="form_token" value="{{ $formToken }}">

        {{-- Name --}}
        <div>
            <label for="name" class="block text-sm font-medium text-muted mb-1">{{ __('Full name') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                   autocomplete="name"
                   class="input @error('name') !border-danger @enderror"
                   placeholder="{{ __('John Doe') }}">
            @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Username --}}
        <div>
            <label for="username" class="block text-sm font-medium text-muted mb-1">{{ __('Username') }}</label>
            <div class="relative">
                <input id="username" type="text" name="username" value="{{ old('username') }}" required
                       autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false"
                       minlength="{{ $policy['username_min_length'] }}" maxlength="{{ $policy['username_max_length'] }}"
                       class="input pr-40 @error('username') !border-danger @enderror"
                       placeholder="{{ __('yourname') }}">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted pointer-events-none">
                    {{ '@' . config('dahify.domain') }}
                </span>
            </div>
            <p class="mt-1 text-xs text-muted">{{ $policy['username_help'] }}</p>
            @error('username') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        @include('auth.partials.phone-field')
        
        {{-- Password with strength indicator --}}
        <div x-data="{ show: false, password: '' }">
            <label for="password" class="block text-sm font-medium text-muted mb-1">{{ __('Password') }}</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required
                       autocomplete="new-password"
                       x-model="password"
                       class="input pr-10 @error('password') !border-danger @enderror"
                       placeholder="{{ __('Choose a password') }}" minlength="{{ $policy['password_min_length'] }}" maxlength="{{ $policy['password_max_length'] }}">
                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
            <p class="mt-1 text-xs text-muted">{{ $policy['password_help'] }}</p>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Confirm password --}}
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-muted mb-1">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                   autocomplete="new-password"
                   class="input"
                   placeholder="{{ __('Confirm your password') }}">
        </div>

        {{-- Terms --}}
        <div class="flex items-start">
            <input id="terms" type="checkbox" name="terms" required class="w-4 h-4 mt-0.5 rounded border-border text-brand focus:ring-brand">
            <label for="terms" class="ml-2 text-sm text-muted">{{ __('I agree to the') }} <a href="{{ url('/terms') }}" target="_blank" class="text-brand hover:underline">{{ __('Terms') }}</a> {{ __('and') }} <a href="{{ url('/privacy') }}" target="_blank" class="text-brand hover:underline">{{ __('Privacy Policy') }}</a></label>
        </div>
        @error('terms') <p class="text-xs text-danger">{{ $message }}</p> @enderror

        @error('captcha') <p class="text-xs text-danger">{{ $message }}</p> @enderror

        {{-- Submit --}}
        <button type="submit" :disabled="submitting"
                class="btn-primary w-full py-2.5"
                :class="submitting && 'opacity-75 cursor-not-allowed'">
            <span x-show="!submitting">{{ __('Create Account') }}</span>
            <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Creating account...') }}
            </span>
        </button>

        {{--
            Social sign-up (Google/Microsoft/GitHub) intentionally removed:
            it would let someone register with an outside address, bypassing
            the @dahimail.com-only rule. Re-introduce only as "sign in with
            Google" for an *existing* dahimail.com account (linking), never
            as a way to create one.
        --}}

        {{-- Login link --}}
        <p class="text-center text-sm text-muted mt-4">
            {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="text-brand font-semibold hover:text-brand-strong">{{ __('Log in') }}</a>
        </p>
    </form>
</x-layouts.guest>
