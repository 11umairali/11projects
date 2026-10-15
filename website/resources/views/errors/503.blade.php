<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Maintenance Mode') }} — {{ config('app.name') }}</title>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
        </div>

        {{-- Content --}}
        <h1 class="text-2xl sm:text-3xl font-bold text-ink dark:text-gray-100 mb-3">{{ __("We'll be right back") }}</h1>
        <p class="text-muted  mb-8 max-w-sm mx-auto leading-relaxed">
            {{ config('app.name') }} {{ __("is undergoing scheduled maintenance to improve your experience. We'll be back shortly.") }}
        </p>

        {{-- Status indicator --}}
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-warning/10 dark:bg-amber-900/20 border border-warning/20 dark:border-amber-700/40 rounded-xl text-sm text-warning dark:text-amber-400 font-medium mb-8">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-warning/100"></span>
            </span>
            {{ __('Maintenance in progress') }}
        </div>

        {{-- Auto-refresh hint --}}
        <p class="text-xs text-muted ">{{ __('This page will auto-refresh in') }} <span id="countdown">60</span> {{ __('seconds') }}.</p>

        {{-- Admin Access --}}
        <div class="mt-8">
            <button type="button" onclick="openAdminModal()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-[#1a1d27] border border-border dark:border-gray-700 hover:border-primary-400 dark:hover:border-primary-500 text-ink dark:text-gray-200 hover:text-primary-700 dark:hover:text-primary-400 rounded-lg text-sm font-medium transition-all shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                {{ __('Admin Login') }}
            </button>
            <p class="text-[11px] text-muted mt-2 opacity-70">{{ __('Site administrators can sign in to bypass maintenance.') }}</p>
        </div>

        {{-- Brand --}}
        <div class="mt-10 flex items-center justify-center gap-2 text-muted ">
            <div class="w-6 h-6 bg-gradient-to-br from-primary-600 to-secondary-600 rounded-lg flex items-center justify-center">
                <span class="text-xs font-black text-white">M</span>
            </div>
            <span class="text-sm font-semibold">{{ config('app.name') }}</span>
        </div>
    </div>

    {{-- Admin Login Modal --}}
    <div id="adminModal" class="fixed inset-0 z-50 hidden items-center justify-center px-4" aria-modal="true" role="dialog">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAdminModal()"></div>

        {{-- Card --}}
        <div class="relative w-full max-w-md bg-white dark:bg-[#1a1d27] border border-border dark:border-gray-700 rounded-2xl shadow-2xl overflow-hidden">
            {{-- Header --}}
            <div class="px-6 pt-6 pb-4 border-b border-border dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-50 dark:bg-primary-900/30 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <div class="text-left">
                            <h2 class="text-base font-semibold text-ink dark:text-gray-100">{{ __('Admin Login') }}</h2>
                            <p class="text-xs text-muted">{{ __('Bypass maintenance mode') }}</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeAdminModal()" class="text-muted hover:text-ink dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <form id="adminForm" class="px-6 py-5 space-y-4 text-left" onsubmit="return submitAdminLogin(event)">
                <div id="adminError" class="hidden px-3 py-2 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/40 rounded-lg text-xs text-rose-700 dark:text-rose-400"></div>

                <div>
                    <label class="block text-xs font-medium text-ink dark:text-gray-200 mb-1.5">{{ __('Email') }}</label>
                    <input id="adminEmail" type="email" required autocomplete="email"
                           class="w-full px-3 py-2 bg-white dark:bg-[#0f1117] border border-border dark:border-gray-700 rounded-lg text-sm text-ink dark:text-gray-100 placeholder-muted focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"
                           placeholder="admin@example.com">
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink dark:text-gray-200 mb-1.5">{{ __('Password') }}</label>
                    <input id="adminPassword" type="password" required autocomplete="current-password"
                           class="w-full px-3 py-2 bg-white dark:bg-[#0f1117] border border-border dark:border-gray-700 rounded-lg text-sm text-ink dark:text-gray-100 placeholder-muted focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"
                           placeholder="••••••••">
                </div>

                <button id="adminSubmit" type="submit"
                        class="w-full px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-all flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                    <span id="adminSubmitLabel">{{ __('Sign In & Bypass') }}</span>
                    <svg id="adminSubmitSpinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </button>

                <p class="text-[11px] text-muted text-center pt-1">{{ __('Only active admin accounts can bypass.') }}</p>
            </form>
        </div>
    </div>

    <script>
        let seconds = 60;
        const el = document.getElementById('countdown');
        const timer = setInterval(() => {
            seconds--;
            if (el) el.textContent = seconds;
            if (seconds <= 0) { clearInterval(timer); location.reload(); }
        }, 1000);

        const modal = document.getElementById('adminModal');
        const errBox = document.getElementById('adminError');
        const btn = document.getElementById('adminSubmit');
        const btnLabel = document.getElementById('adminSubmitLabel');
        const btnSpin = document.getElementById('adminSubmitSpinner');

        function openAdminModal() {
            errBox.classList.add('hidden');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            clearInterval(timer);
            setTimeout(() => document.getElementById('adminEmail').focus(), 60);
        }
        function closeAdminModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeAdminModal();
        });

        async function submitAdminLogin(e) {
            e.preventDefault();
            errBox.classList.add('hidden');
            btn.disabled = true;
            btnLabel.textContent = "{{ __('Verifying...') }}";
            btnSpin.classList.remove('hidden');

            try {
                const res = await fetch('/admin-maintenance-bypass', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        email: document.getElementById('adminEmail').value.trim(),
                        password: document.getElementById('adminPassword').value,
                    }),
                });
                const data = await res.json().catch(() => ({}));

                if (res.ok && data.ok && data.redirect) {
                    btnLabel.textContent = "{{ __('Success — redirecting...') }}";
                    window.location.href = data.redirect;
                    return false;
                }

                errBox.textContent = data.message || "{{ __('Login failed. Please check your credentials.') }}";
                errBox.classList.remove('hidden');
            } catch (err) {
                errBox.textContent = "{{ __('Network error. Please try again.') }}";
                errBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btnLabel.textContent = "{{ __('Sign In & Bypass') }}";
                btnSpin.classList.add('hidden');
            }
            return false;
        }
    </script>

</body>
</html>
