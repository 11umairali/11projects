<div x-data="{ dismissed: localStorage.getItem('deliverability_guide_dismissed') === 'true' }" x-show="!dismissed" x-transition x-cloak
     class="bg-warning/5 border border-warning/20 rounded-xl p-4 mb-6">
    <div class="flex items-start gap-3">
        <svg viewBox="0 0 24 24" class="w-5 h-5 text-warning shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>
        </svg>
        <div class="flex-1">
            <h4 class="text-sm font-semibold text-ink mb-1">Improve your email deliverability</h4>
            <p class="text-xs text-muted mb-3">To prevent your campaigns from landing in spam, set up these DNS records for your sending domain:</p>
            <ul class="text-xs text-muted space-y-1.5">
                <li class="flex items-start gap-2">
                    <span class="font-semibold text-ink shrink-0">SPF</span>
                    <span>Add a TXT record: <code class="bg-surface px-1 py-0.5 rounded text-[11px]">v=spf1 include:_spf.yourmailserver.com ~all</code></span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-semibold text-ink shrink-0">DKIM</span>
                    <span>Generate and add a DKIM TXT record from your email provider's settings.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="font-semibold text-ink shrink-0">DMARC</span>
                    <span>Add a TXT record: <code class="bg-surface px-1 py-0.5 rounded text-[11px]">v=DMARC1; p=none; rua=mailto:dmarc@yourdomain.com</code></span>
                </li>
            </ul>
            <p class="text-xs text-muted mt-2">Ask your hosting provider for help if you're unsure how to add DNS records.</p>
        </div>
        <button @click="localStorage.setItem('deliverability_guide_dismissed', 'true'); dismissed = true"
                class="text-muted hover:text-ink transition-colors shrink-0 p-1" title="Dismiss">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>
