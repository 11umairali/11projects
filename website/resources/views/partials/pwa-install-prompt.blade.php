{{-- PWA Install Prompt --}}
<div id="pwa-install-prompt" style="display:none;" class="fixed bottom-6 right-6 z-[9999] max-w-sm w-full">
    <div class="bg-surface-2 border border-border rounded-2xl shadow-2xl p-5 animate-slide-up">
        <div class="flex items-start gap-4">
            {{-- App Icon --}}
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shrink-0 overflow-hidden">
                @php $pwaIconPath = \App\Models\SystemSetting::get('pwa_icon'); @endphp
                @if($pwaIconPath)
                    <img src="{{ asset('storage/' . $pwaIconPath) }}" alt="App Icon" class="w-full h-full object-cover">
                @else
                    <span class="text-2xl font-extrabold text-white">M</span>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-ink">Install {{ \App\Models\SystemSetting::get('pwa_app_name', \App\Models\SystemSetting::get('site_name', 'MailTrixy')) }}</h3>
                <p class="text-xs text-muted mt-1">Add to your home screen for a faster, app-like experience.</p>
            </div>
            <button onclick="dismissPwaPrompt()" class="p-1 text-muted hover:text-ink shrink-0" aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Features --}}
        <div class="mt-3 space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-muted">
                <svg class="w-3.5 h-3.5 text-success shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Works offline with cached content
            </div>
            <div class="flex items-center gap-2 text-xs text-muted">
                <svg class="w-3.5 h-3.5 text-success shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Faster loading, no browser bars
            </div>
            <div class="flex items-center gap-2 text-xs text-muted">
                <svg class="w-3.5 h-3.5 text-success shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Launch from your home screen
            </div>
        </div>

        {{-- Buttons --}}
        <div class="mt-4 flex items-center gap-3">
            <button onclick="installPwa()" id="pwa-install-btn"
                    class="flex-1 px-4 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong transition-colors shadow-sm">
                Install App
            </button>
            <button onclick="dismissPwaPrompt()"
                    class="px-4 py-2.5 text-sm text-muted hover:text-ink transition-colors">
                Not now
            </button>
        </div>

        {{-- iOS instructions (shown only on iOS Safari) --}}
        <div id="pwa-ios-hint" style="display:none;" class="mt-3 p-3 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/30 rounded-xl">
            <p class="text-xs text-blue-700 dark:text-blue-400">
                Tap the <strong>Share</strong> button
                <svg class="inline w-4 h-4 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                then <strong>"Add to Home Screen"</strong>.
            </p>
        </div>
    </div>
</div>

<style>
@keyframes slide-up {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-slide-up { animation: slide-up 0.3s ease-out; }
</style>

<script>
(function() {
    var deferredPrompt = null;
    var promptEl = document.getElementById('pwa-install-prompt');
    var installBtn = document.getElementById('pwa-install-btn');
    var iosHint = document.getElementById('pwa-ios-hint');

    // Don't show if already dismissed recently or already installed
    if (localStorage.getItem('pwa_prompt_dismissed')) return;
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if (window.navigator.standalone === true) return;

    // Detect iOS Safari
    var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var isSafari = /safari/i.test(navigator.userAgent) && !/chrome|crios|fxios/i.test(navigator.userAgent);

    if (isIos && isSafari) {
        // Show iOS-specific instructions after delay
        setTimeout(function() {
            if (promptEl) {
                promptEl.style.display = 'block';
                if (installBtn) installBtn.style.display = 'none';
                if (iosHint) iosHint.style.display = 'block';
            }
        }, 3500);
        return;
    }

    // Chrome/Edge/Samsung — capture beforeinstallprompt
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        setTimeout(function() {
            if (promptEl) promptEl.style.display = 'block';
        }, 3500);
    });

    // Install button click
    window.installPwa = function() {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function(result) {
            if (result.outcome === 'accepted') {
                if (promptEl) promptEl.style.display = 'none';
            }
            deferredPrompt = null;
        });
    };

    // Dismiss prompt — don't show again for 7 days
    window.dismissPwaPrompt = function() {
        if (promptEl) promptEl.style.display = 'none';
        var expires = Date.now() + (7 * 24 * 60 * 60 * 1000);
        localStorage.setItem('pwa_prompt_dismissed', expires);
    };

    // If app gets installed
    window.addEventListener('appinstalled', function() {
        if (promptEl) promptEl.style.display = 'none';
        deferredPrompt = null;
    });

    // Check if dismissal has expired
    var dismissed = localStorage.getItem('pwa_prompt_dismissed');
    if (dismissed && Date.now() > parseInt(dismissed, 10)) {
        localStorage.removeItem('pwa_prompt_dismissed');
    }
})();
</script>
