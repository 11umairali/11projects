<x-layouts.admin :title="__('System Hub')" :subtitle="__('Pages, extensions, security, and monitoring.')">
    <div class="space-y-8">

        {{-- Configuration --}}
        <div>
            <p class="panel-heading mb-4">{{ __('Configuration') }}</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ url('/admin/settings') }}" class="hub-card group">
                    <span class="scope-badge scope-badge-system">{{ __('System') }}</span>
                    <div class="hub-card-icon">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ __('System Settings') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('General, branding, SMTP, auth, security, AI, integrations.') }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted/50 transition group-hover:text-brand" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>

                <a href="{{ url('/admin/roles') }}" class="hub-card group">
                    <span class="scope-badge scope-badge-system">{{ __('System') }}</span>
                    <div class="hub-card-icon">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ __('Roles & Permissions') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('Manage admin access levels and module permissions.') }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted/50 transition group-hover:text-brand" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>

                <a href="{{ url('/admin/frontend-settings') }}" class="hub-card group">
                    <span class="scope-badge scope-badge-frontend">{{ __('Frontend') }}</span>
                    <div class="hub-card-icon">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l3 3v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M9 9h6"/><path d="M9 13h6"/><path d="M9 17h3"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ __('Frontend Settings') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('Landing page templates, CMS pages, custom code.') }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted/50 transition group-hover:text-brand" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            </div>
        </div>

        {{-- AI & Communication --}}
        <div>
            <p class="panel-heading mb-4">{{ __('AI & Communication') }}</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ url('/admin/ai-providers') }}" class="hub-card group">
                    <span class="scope-badge scope-badge-system">{{ __('System') }}</span>
                    <div class="hub-card-icon">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 0 1 7 7c0 2.4-1.2 4.5-3 5.7V17a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2.3C6.2 13.5 5 11.4 5 9a7 7 0 0 1 7-7z"/><path d="M9 22h6"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ __('AI Providers') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('Configure OpenAI, Anthropic, Gemini, Mistral.') }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted/50 transition group-hover:text-brand" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>

                <a href="{{ url('/admin/ai-usage') }}" class="hub-card group">
                    <span class="scope-badge scope-badge-monitoring">{{ __('Monitoring') }}</span>
                    <div class="hub-card-icon">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ __('AI Usage') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('Monitor token usage, costs, and performance.') }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted/50 transition group-hover:text-brand" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>

                <a href="{{ url('/admin/email-deliverability') }}" class="hub-card group">
                    <span class="scope-badge scope-badge-monitoring">{{ __('Monitoring') }}</span>
                    <div class="hub-card-icon">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ __('Email Deliverability') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('Bounce rates, sender reputation, domain health.') }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted/50 transition group-hover:text-brand" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            </div>
        </div>

        {{-- Security & Access --}}
        <div>
            <p class="panel-heading mb-4">{{ __('Security & Access') }}</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ url('/admin/blocked-ips') }}" class="hub-card group">
                    <span class="scope-badge scope-badge-security">{{ __('Security') }}</span>
                    <div class="hub-card-icon">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.7 5.7l12.6 12.6"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ __('Blocked IPs') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('Manage IP address blocking rules.') }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted/50 transition group-hover:text-brand" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>

                <a href="{{ url('/admin/audit-log') }}" class="hub-card group">
                    <span class="scope-badge scope-badge-security">{{ __('Security') }}</span>
                    <div class="hub-card-icon">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ __('Audit Log') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('Track admin actions and login history.') }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted/50 transition group-hover:text-brand" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>

                <a href="{{ url('/admin/system') }}" class="hub-card group">
                    <span class="scope-badge scope-badge-system">{{ __('System') }}</span>
                    <div class="hub-card-icon">
                        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ __('System Info') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('PHP, Laravel, database, and server details.') }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-muted/50 transition group-hover:text-brand" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            </div>
        </div>
    </div>
</x-layouts.admin>
