<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Access Denied') }} — {{ config('app.name') }}</title>
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
<body class="bg-gradient-to-br from-gray-50 via-white to-amber-50/20 dark:from-[#0f1117] dark:via-[#1a1d27] dark:to-[#1a1530] antialiased min-h-screen flex items-center justify-center px-4" style="font-family: 'Outfit', sans-serif">

    <div class="max-w-lg w-full text-center">
        {{-- Illustration --}}
        <div class="relative mx-auto w-40 h-40 mb-8">
            <div class="absolute inset-0 bg-warning/15/60 dark:bg-amber-900/20 rounded-full blur-2xl"></div>
            <div class="relative w-40 h-40 bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/30 dark:to-amber-800/20 rounded-full flex items-center justify-center border-2 border-warning/20/50 dark:border-amber-700/30">
                <svg class="w-16 h-16 text-amber-400 dark:text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v.01M12 12a1.5 1.5 0 001.5-1.5c0-.829-.672-1.5-1.5-1.5s-1.5.671-1.5 1.5M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                </svg>
            </div>
        </div>

        {{-- Content --}}
        <h1 class="text-2xl sm:text-3xl font-bold text-ink dark:text-gray-100 mb-3">{{ __('Access denied') }}</h1>
        <p class="text-muted  mb-8 max-w-sm mx-auto leading-relaxed">
            {{ $exception->getMessage() ?: __("You don't have permission to access this page. Contact your workspace admin if you think this is a mistake.") }}
        </p>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ url('/dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white text-sm font-semibold rounded-xl hover:from-primary-700 hover:to-primary-800 shadow-lg shadow-primary-600/20 transition-all hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                {{ __('Go to Dashboard') }}
            </a>
            <button onclick="history.back()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-surface-2 dark:bg-[#1a1d27] text-ink/80 /50 text-sm font-semibold rounded-xl border border-border dark:border-gray-700 hover:bg-surface dark:hover:bg-surface/10 hover:border-border dark:hover:border-gray-600 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                {{ __('Go Back') }}
            </button>
        </div>

        {{-- Brand --}}
        <div class="mt-12 flex items-center justify-center gap-2 text-muted ">
            <div class="w-6 h-6 bg-gradient-to-br from-primary-600 to-secondary-600 rounded-lg flex items-center justify-center">
                <span class="text-xs font-black text-white">M</span>
            </div>
            <span class="text-sm font-semibold">{{ config('app.name') }}</span>
        </div>
    </div>

</body>
</html>
