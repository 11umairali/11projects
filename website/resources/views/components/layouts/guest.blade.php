<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $__sn = \App\Models\SystemSetting::get('site_name', config('app.name', 'MailTrixy'));
        $__fav = \App\Models\SystemSetting::get('favicon');
        $__logoL = \App\Models\SystemSetting::get('logo_light');
        $__logoD = \App\Models\SystemSetting::get('logo_dark');
    @endphp
    <title>{{ $title ?? $__sn }} — {{ $__sn }}</title>

    {{-- Favicon --}}
    @if($__fav)
    <link rel="icon" href="{{ asset('storage/' . $__fav) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('storage/' . $__fav) }}">
    @else
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/dahimail-logo.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @endif

    {{-- PWA (conditional) --}}
    @php $__pwa = \App\Models\SystemSetting::get('pwa_enabled', 'false') === 'true'; @endphp
    @if($__pwa)
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <meta name="theme-color" content="{{ \App\Models\SystemSetting::get('pwa_theme_color', '#6366f1') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Models\SystemSetting::get('pwa_app_name', $__sn) }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <x-brand-theme />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    {!! \App\Models\SystemSetting::get('head_code', '') !!}
</head>
<body class="h-full bg-surface text-ink antialiased font-sans">
<noscript>
    <div style="padding: 2rem; text-align: center; font-family: 'Inter', system-ui, sans-serif; background: #F8F9FC; min-height: 100vh; display: flex; align-items: center; justify-content: center;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1E293B; margin-bottom: 0.5rem;">{{ __('JavaScript Required') }}</h1>
            <p style="color: #9CA3AF; font-size: 0.875rem;">{{ __('This application requires JavaScript to function. Please enable JavaScript in your browser settings.') }}</p>
        </div>
    </div>
</noscript>
    @if($wide ?? false)
    {{-- Wide layout (onboarding wizard) --}}
    <div class="min-h-screen flex flex-col">
        {{-- Top bar --}}
        <div class="border-b border-border/50 bg-surface-2/80 backdrop-blur-md">
            <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center">
                    <x-brand-logo :name="$__sn" />
                </a>
                <div class="flex items-center gap-3">
                    <x-language-switcher />
                    @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-muted hover:text-ink font-medium transition-colors">
                            {{ __('Sign Out') }}
                        </button>
                    </form>
                    @else
                    <a href="{{ url('/login') }}" class="text-sm text-muted hover:text-ink font-medium transition-colors">
                        {{ __('Log in') }}
                    </a>
                    @endauth
                </div>
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 flex items-start justify-center p-6 sm:p-10">
            <div class="w-full max-w-5xl">
                {{ $slot }}
            </div>
        </div>

        {{-- Legal footer --}}
        <div class="text-center text-xs text-muted py-4 border-t border-border/40">
            <a href="{{ route('legal.terms') }}" class="hover:text-ink transition-colors">{{ __('Terms') }}</a>
            <span class="mx-1">&middot;</span>
            <a href="{{ route('legal.privacy') }}" class="hover:text-ink transition-colors">{{ __('Privacy') }}</a>
            <span class="mx-1">&middot;</span>
            <a href="{{ route('legal.refund') }}" class="hover:text-ink transition-colors">{{ __('Refund Policy') }}</a>
        </div>
    </div>
    @else
    {{-- Standard split layout (auth pages) --}}
    <div class="min-h-screen flex">
        {{-- Left: Branding Panel --}}
        <div class="hidden lg:flex lg:w-1/2 bg-slate-900 relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
            </div>
            <div class="relative z-10 flex flex-col justify-between p-12 text-white w-full">
                <div>
                    <a href="{{ url('/') }}" class="flex items-center">
                        <x-brand-logo :name="$__sn" />
                    </a>
                </div>
                <div class="space-y-6">
                    <h1 class="text-4xl font-bold leading-tight">{{ __('Your AI Communication Brain') }}</h1>
                    <p class="text-lg text-white/80 leading-relaxed">
                        {{ __('Automate email replies, manage multi-channel conversations, and scale your business communication with AI that actually knows your business.') }}
                    </p>
                </div>
                <div class="mt-8">
                    <p class="text-lg text-white/90 font-medium leading-relaxed">"{{ __('AI-powered communication automation for modern teams.') }}"</p>
                    <p class="text-white/60 text-sm mt-3">{{ __('Trusted by growing businesses worldwide') }}</p>
                </div>
            </div>
        </div>

        {{-- Right: Form Panel --}}
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:px-12 sm:py-8 bg-surface-2 overflow-y-auto">
            <div class="w-full max-w-md">
                {{-- Mobile logo + language --}}
                <div class="lg:hidden mb-8 flex items-center justify-between">
                    <a href="{{ url('/') }}" class="flex items-center">
                        <x-brand-logo :name="$__sn" />
                    </a>
                    <x-language-switcher />
                </div>

                {{ $slot }}

                {{-- Legal links below form --}}
                <div class="mt-8 text-center text-xs text-muted">
                    <a href="{{ route('legal.terms') }}" class="hover:text-ink transition-colors">{{ __('Terms') }}</a>
                    <span class="mx-1">&middot;</span>
                    <a href="{{ route('legal.privacy') }}" class="hover:text-ink transition-colors">{{ __('Privacy') }}</a>
                    <span class="mx-1">&middot;</span>
                    <a href="{{ route('legal.refund') }}" class="hover:text-ink transition-colors">{{ __('Refund Policy') }}</a>
                </div>
            </div>
        </div>
    </div>
    @endif

    @livewireScripts

    {{-- Cookie Consent --}}
    <x-cookie-consent />
    {!! \App\Models\SystemSetting::get('footer_code', '') !!}
    @include('partials.timezone-sync')
</body>
</html>