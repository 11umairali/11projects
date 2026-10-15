<div
    x-data="{ shown: !localStorage.getItem('cookie_consent_accepted') }"
    x-show="shown"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-y-full opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-full opacity-0"
    x-cloak
    class="fixed bottom-0 inset-x-0 z-50 p-4"
    role="dialog"
    aria-label="Cookie consent"
>
    <div class="max-w-4xl mx-auto bg-surface-2 border border-border rounded-2xl shadow-lg p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div class="flex-1">
            <p class="text-sm text-ink font-medium">We use cookies</p>
            <p class="text-xs text-muted mt-1">We use cookies to improve your experience and analyze site traffic. By continuing, you agree to our
                <a href="<?php echo e(url('/privacy')); ?>" class="text-brand hover:text-brand-strong underline">Privacy Policy</a>.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button
                @click="localStorage.setItem('cookie_consent_accepted', 'true'); shown = false"
                class="btn-secondary text-xs px-4 py-2"
            >Decline</button>
            <button
                @click="localStorage.setItem('cookie_consent_accepted', 'true'); shown = false"
                class="btn-primary text-xs px-4 py-2"
            >Accept All</button>
        </div>
    </div>
</div>
<?php /**PATH /home/dahimail.com/public_html/resources/views/components/cookie-consent.blade.php ENDPATH**/ ?>