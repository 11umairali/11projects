<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Something Went Wrong') }} — {{ config('app.name') }}</title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gradient-to-br from-gray-50 via-white to-red-50/20 dark:from-[#0f1117] dark:via-[#1a1d27] dark:to-[#1a1530] antialiased min-h-screen flex items-center justify-center px-4" style="font-family: 'Outfit', sans-serif">

    <div class="max-w-lg w-full text-center">
        {{-- Illustration --}}
        <div class="relative mx-auto w-40 h-40 mb-8">
            <div class="absolute inset-0 bg-danger/15/60 dark:bg-red-900/20 rounded-full blur-2xl"></div>
            <div class="relative w-40 h-40 bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/30 dark:to-red-800/20 rounded-full flex items-center justify-center border-2 border-danger/20/50 dark:border-red-700/30">
                <svg class="w-16 h-16 text-red-400 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
        </div>

        {{-- Content --}}
        <h1 class="text-2xl sm:text-3xl font-bold text-ink dark:text-gray-100 mb-3">{{ __('Something went wrong') }}</h1>
        <p class="text-muted  mb-8 max-w-sm mx-auto leading-relaxed">
            {{ __('We hit an unexpected error. Please try again. If this continues, share the reference below with support.') }}
        </p>
        <p class="text-xs text-muted mb-6">{{ __('Reference') }}: {{ \App\Support\ErrorReference::current() }}</p>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <button onclick="location.reload()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white text-sm font-semibold rounded-xl hover:from-primary-700 hover:to-primary-800 shadow-lg shadow-primary-600/20 transition-all hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                {{ __('Try Again') }}
            </button>
            <a href="{{ url('/') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-surface-2 dark:bg-[#1a1d27] text-ink/80 /50 text-sm font-semibold rounded-xl border border-border dark:border-gray-700 hover:bg-surface dark:hover:bg-surface/10 hover:border-border dark:hover:border-gray-600 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                {{ __('Go Home') }}
            </a>
        </div>

        {{-- Support hint --}}
        <p class="mt-8 text-xs text-muted ">
            {{ __('If this keeps happening, contact') }}
            <a href="{{ route('contact') }}" class="text-primary-600 dark:text-primary-400 hover:underline">{{ __('support') }}</a>.
        </p>

        {{-- Brand --}}
        <div class="mt-8 flex items-center justify-center gap-2 text-muted ">
            <div class="w-6 h-6 bg-gradient-to-br from-primary-600 to-secondary-600 rounded-lg flex items-center justify-center">
                <span class="text-xs font-black text-white">M</span>
            </div>
            <span class="text-sm font-semibold">{{ config('app.name') }}</span>
        </div>
    </div>

</body>
</html>
