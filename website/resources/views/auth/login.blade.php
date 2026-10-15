<x-layouts.guest :title="__('Log In')">
    <div>
        <h2 class="text-2xl font-bold text-ink">{{ __('Welcome back') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('Log in to your :app account', ['app' => config('app.name')]) }}</p>
    </div>

    @if(session('status'))
        <div role="status" class="mt-4 p-4 bg-success/10 border border-success/20 text-success rounded-xl text-sm">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        {{-- Username only: "@domain" is added automatically (same as registration). The field is still posted as "email"
             (Fortify's credential field); a full address typed by the user is accepted too. --}}
        <div x-data="{ v: @js(old('email', '')) }">
            <label for="email" class="block text-sm font-medium text-muted mb-1">{{ __('Username') }}</label>
            <div class="relative">
                <input id="email" type="text" name="email" required autofocus x-model="v"
                       autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false"
                       x-on:input="v = v.toLowerCase()"
                       :class="v.indexOf('@') === -1 ? 'pr-40' : ''"
                       class="input @error('email') !border-danger !ring-danger/20 @enderror"
                       placeholder="{{ __('username') }}">
                <span x-show="v.indexOf('@') === -1" class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted pointer-events-none">{{ '@' . config('dahify.domain') }}</span>
            </div>
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Password --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="password" class="block text-sm font-medium text-muted">{{ __('Password') }}</label>
                <a href="{{ route('password.request') }}" class="text-sm text-brand hover:text-brand-strong font-medium">{{ __('Forgot password?') }}</a>
            </div>
            <div x-data="{ show: false }" class="relative">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required
                       autocomplete="current-password"
                       class="input pr-10"
                       placeholder="{{ __('Enter your password') }}">
                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                    <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg x-show="show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                </button>
            </div>
        </div>

        {{-- Remember me --}}
        <div class="flex items-center">
            <input id="remember" type="checkbox" name="remember" class="w-4 h-4 rounded border-border text-brand focus:ring-brand">
            <label for="remember" class="ml-2 text-sm text-muted">{{ __('Remember me') }}</label>
        </div>

        {{-- Submit --}}
        <button type="submit" :disabled="submitting"
                class="btn-primary w-full py-2.5"
                :class="submitting && 'opacity-75 cursor-not-allowed'">
            <span x-show="!submitting">{{ __('Log In') }}</span>
            <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                {{ __('Signing in...') }}
            </span>
        </button>

        {{--
            Social login buttons removed here too. They were previously
            paired with social *registration* (see register.blade.php);
            since registration no longer creates accounts from Google/
            Microsoft/GitHub, keeping only a "sign in with" path for
            accounts that don't have a password would need a real
            account-linking flow, which is out of scope for this pass.
        --}}

        {{-- Register link --}}
        <p class="text-center text-sm text-muted mt-4">
            {{ __("Don't have an account?") }} <a href="{{ route('register') }}" class="text-brand font-semibold hover:text-brand-strong">{{ __('Sign up') }}</a>
        </p>
    </form>
</x-layouts.guest>
