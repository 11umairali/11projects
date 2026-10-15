<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Too Many Requests') }} — {{ config('app.name') }}</title>
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
<body class="bg-gradient-to-br from-gray-50 via-white to-orange-50/20 dark:from-[#0f1117] dark:via-[#1a1d27] dark:to-[#1a1530] antialiased min-h-screen flex items-center justify-center px-4" style="font-family: 'Outfit', sans-serif">

    <div class="max-w-lg w-full text-center">
        {{-- Illustration --}}
        <div class="relative mx-auto w-40 h-40 mb-8">
            <div class="absolute inset-0 bg-orange-100/60 dark:bg-orange-900/20 rounded-full blur-2xl"></div>
            <div class="relative w-40 h-40 bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/30 dark:to-orange-800/20 rounded-full flex items-center justify-center border-2 border-orange-200/50 dark:border-orange-700/30">
                <svg class="w-16 h-16 text-orange-400 dark:text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
        </div>

        {{-- Content --}}
        <h1 class="text-2xl sm:text-3xl font-bold text-ink dark:text-gray-100 mb-3">{{ __('Slow down') }}</h1>
        <p class="text-muted  mb-8 max-w-sm mx-auto leading-relaxed">
            {{ __("You've made too many requests. Please wait a moment and try again.") }}
        </p>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <button onclick="setTimeout(() => location.reload(), 100)" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white text-sm font-semibold rounded-xl hover:from-primary-700 hover:to-primary-800 shadow-lg shadow-primary-600/20 transition-all hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                {{ __('Try Again') }}
            </button>
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
