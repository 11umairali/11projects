<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Admin Login') }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface antialiased">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            {{-- Logo & branding --}}
            <div class="text-center mb-8">
                <div class="w-14 h-14 bg-primary-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-primary-500/30">
                    <span class="text-2xl font-bold text-white">M</span>
                </div>
                <h1 class="text-2xl font-bold text-white">{{ __('Admin Panel') }}</h1>
                <p class="text-sm text-muted mt-1">{{ config('app.name') }} {{ __('Administration Console') }}</p>
            </div>

            {{-- Login form --}}
            <div class="bg-surface rounded-2xl border border-gray-700 p-8">
                <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
                    @csrf

                    @if($errors->any())
                    <div class="p-3 bg-red-900/50 border border-red-700 text-red-300 text-sm rounded-xl">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-muted/50 mb-1.5">{{ __('Email Address') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                               placeholder="{{ __('admin@mailtrixy.com') }}"
                               class="w-full px-4 py-2.5 text-sm bg-gray-700 border border-gray-600 text-white placeholder-gray-400 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-muted/50 mb-1.5">{{ __('Password') }}</label>
                        <input type="password" name="password" required
                               placeholder="{{ __('Enter your password') }}"
                               class="w-full px-4 py-2.5 text-sm bg-gray-700 border border-gray-600 text-white placeholder-gray-400 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-muted/50 mb-1.5">{{ __('2FA Code') }} <span class="text-muted">{{ __('(if enabled)') }}</span></label>
                        <input type="text" name="two_factor_code" maxlength="6"
                               placeholder="000000"
                               class="w-full px-4 py-2.5 text-sm bg-gray-700 border border-gray-600 text-white placeholder-gray-400 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent font-mono tracking-widest text-center">
                    </div>

                    @if(session('error'))
                    <div class="p-3 bg-red-900/50 border border-red-700 text-red-300 text-sm rounded-xl">
                        {{ session('error') }}
                    </div>
                    @endif

                    <button type="submit"
                            class="w-full px-4 py-3 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 transition-colors shadow-lg shadow-primary-500/30">
                        {{ __('Sign in to Admin Panel') }}
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-muted mt-6">
                <a href="{{ url('/') }}" class="hover:text-muted/50 transition-colors">&larr; {{ __('Back to') }} {{ config('app.name') }}</a>
            </p>
        </div>
    </div>
</body>
</html>
