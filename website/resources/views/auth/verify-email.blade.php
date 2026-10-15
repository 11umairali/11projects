<x-layouts.guest :title="__('Verify Email')">
    <div class="text-center">
        <div class="mx-auto w-16 h-16 bg-brand/10 rounded-full flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
        <h2 class="text-2xl font-bold text-ink">{{ __('Check your email') }}</h2>
        <p class="mt-2 text-sm text-muted">{{ __("We've sent a verification link to") }} <strong>{{ auth()->user()->email }}</strong></p>
    </div>

    @if(session('status') == 'verification-link-sent')
    <div class="mt-4 p-4 bg-success/10 border border-success/20 text-success rounded-xl text-sm text-center">
        {{ __('A new verification link has been sent to your email.') }}
    </div>
    @endif

    <div class="mt-8 space-y-4">
        <form method="POST" action="{{ route('verification.send') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <button type="submit" :disabled="submitting"
                    class="btn-primary w-full py-3"
                    :class="submitting && 'opacity-75 cursor-not-allowed'">
                <span x-show="!submitting">{{ __('Resend Verification Email') }}</span>
                <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ __('Sending...') }}
                </span>
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full py-2.5 px-4 bg-surface border border-border text-ink text-sm font-medium rounded-xl hover:bg-surface transition-colors">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-layouts.guest>
