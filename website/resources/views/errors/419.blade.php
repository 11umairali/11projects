<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Session Expired') }} — {{ config('app.name') }}</title>
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
<body class="bg-gradient-to-br from-gray-50 via-white to-primary-50/30 dark:from-[#0f1117] dark:via-[#1a1d27] dark:to-[#1a1530] antialiased min-h-screen flex items-center justify-center px-4" style="font-family: 'Outfit', sans-serif">

    <div class="max-w-lg w-full text-center">
        {{-- Illustration --}}
        <div class="relative mx-auto w-40 h-40 mb-8">
            <div class="absolute inset-0 bg-primary-100/60 dark:bg-primary-900/20 rounded-full blur-2xl"></div>
            <div class="relative w-40 h-40 bg-gradient-to-br from-primary-50 to-primary-100 dark:from-primary-900/30 dark:to-primary-800/20 rounded-full flex items-center justify-center border-2 border-primary-200/50 dark:border-primary-700/30">
                <svg class="w-16 h-16 text-primary-400 dark:text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        {{-- Content --}}
        <h1 class="text-2xl sm:text-3xl font-bold text-ink dark:text-gray-100 mb-3">{{ __('Session expired') }}</h1>
        <p class="text-muted  mb-2 max-w-sm mx-auto leading-relaxed">
            {{ __('Your session has expired for security. Please refresh the page or log in again to continue.') }}
        </p>
        <p class="text-sm text-muted mt-2 mb-8 max-w-sm mx-auto leading-relaxed">{{ __("If you were writing an email, don't worry — your draft was automatically saved. You can find it after logging back in.") }}</p>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <button onclick="location.reload()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white text-sm font-semibold rounded-xl hover:from-primary-700 hover:to-primary-800 shadow-lg shadow-primary-600/20 transition-all hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                {{ __('Refresh Page') }}
            </button>
            <a href="{{ url('/login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-surface-2 dark:bg-[#1a1d27] text-ink/80 /50 text-sm font-semibold rounded-xl border border-border dark:border-gray-700 hover:bg-surface dark:hover:bg-surface/10 hover:border-border dark:hover:border-gray-600 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                {{ __('Log In Again') }}
            </a>
            <a href="{{ url('/inbox?filter=drafts') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 text-muted text-sm font-semibold rounded-xl hover:text-ink transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                {{ __('Go to Drafts') }}
            </a>
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
