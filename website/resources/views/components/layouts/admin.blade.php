@props(['title' => 'Dashboard', 'subtitle' => null])
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ $textDirection ?? 'ltr' }}"
    x-data="adminLayout()"
    x-init="init()"
    :class="{ dark: theme === 'dark' }"
    :data-theme="theme"
    class="h-full overflow-hidden"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    @php $__siteName = \App\Models\SystemSetting::get('site_name', config('app.name', 'MailTrixy')); $__favicon = \App\Models\SystemSetting::get('favicon'); @endphp
    <title>{{ $title }} — {{ $__siteName }} Admin</title>

    {{-- Favicon --}}
    @if($__favicon)
    <link rel="icon" href="{{ asset('storage/' . $__favicon) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('storage/' . $__favicon) }}">
    @else
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/dahimail-logo.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @endif

    {{-- Dynamic brand colors from admin settings --}}


    {{-- Google Fonts: Outfit --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="{{ asset('vendor/chart.min.js') }}" defer></script>

    {{-- Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-brand-theme />
    @livewireStyles

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    {{-- Admin-defined custom CSS + head HTML (analytics, custom meta, etc.) --}}
    @php $__customCss = \App\Models\SystemSetting::get('custom_css', ''); @endphp
    @if(trim($__customCss) !== '')
        <style id="admin-custom-css">{!! $__customCss !!}</style>
    @endif
    {!! \App\Models\SystemSetting::get('head_code', '') !!}
</head>
<body class="h-full overflow-hidden font-sans text-ink bg-surface antialiased">
<noscript>
    <div style="padding: 2rem; text-align: center; font-family: 'Inter', system-ui, sans-serif; background: #F8F9FC; min-height: 100vh; display: flex; align-items: center; justify-content: center;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1E293B; margin-bottom: 0.5rem;">{{ __('JavaScript Required') }}</h1>
            <p style="color: #9CA3AF; font-size: 0.875rem;">{{ __('This application requires JavaScript to function. Please enable JavaScript in your browser settings.') }}</p>
        </div>
    </div>
</noscript>

{{-- Progress Bar --}}
<div id="mb-progress-bar"></div>

<div class="flex h-full min-h-0" x-data="{ sidebarOpen: false }">

    {{-- ═══════════════════════════════════════════════
         SIDEBAR
         ═══════════════════════════════════════════════ --}}
    <aside
        class="admin-sidebar fixed inset-y-0 left-0 z-40 flex w-62 flex-col border-r border-border bg-surface-2/95 backdrop-blur-sm transition-transform duration-300 -translate-x-full lg:static lg:translate-x-0"
        :class="{ '!translate-x-0': sidebarOpen }"
        role="navigation"
        aria-label="Admin navigation"
    >
        {{-- Brand --}}
        @php $__logoLight = \App\Models\SystemSetting::get('logo_light'); $__logoDark = \App\Models\SystemSetting::get('logo_dark'); @endphp
        <div class="px-4 pt-5 pb-4 flex items-center justify-between gap-2">
            <a href="{{ url('/admin/dashboard') }}" class="flex items-center min-w-0" wire:navigate>
                <x-brand-logo :name="$__siteName" />
            </a>
            {{-- Mobile close button (desktop hides via lg:hidden since sidebar is always visible there). --}}
            <button type="button"
                    class="lg:hidden shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-ink hover:bg-surface-2 transition-colors"
                    @click="sidebarOpen = false"
                    aria-label="Close sidebar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav id="admin-sidebar-nav" class="flex-1 overflow-y-auto overflow-x-hidden px-3 pb-3 scrollbar-hide" aria-label="Admin sidebar">
            <div class="space-y-1">

                {{-- ── MAIN ── --}}
                <a href="{{ url('/admin/dashboard') }}" wire:navigate class="nav-item {{ request()->is('admin/dashboard') ? 'nav-item-active' : '' }}" @if(request()->is('admin/dashboard')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                    </svg>
                    <span>{{ __('Dashboard') }}</span>
                </a>

                <a href="{{ url('/admin/users') }}" wire:navigate class="nav-item {{ request()->is('admin/users*') ? 'nav-item-active' : '' }}" @if(request()->is('admin/users*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="8" r="3"></circle>
                        <path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"></path>
                        <path d="M15.5 11a3 3 0 1 0 0-6"></path>
                        <path d="M18 19c.6-1.1 1.6-2.2 3-3"></path>
                    </svg>
                    <span>{{ __('Users') }}</span>
                </a>

                <a href="{{ url('/admin/tickets') }}" wire:navigate class="nav-item {{ request()->is('admin/tickets*') ? 'nav-item-active' : '' }}" @if(request()->is('admin/tickets*')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        <path d="M8 10h.01"></path>
                        <path d="M12 10h.01"></path>
                        <path d="M16 10h.01"></path>
                    </svg>
                    <span>{{ __('Tickets') }}</span>
                </a>

                {{-- ── BUSINESS ── --}}
                <p class="mt-2 px-3 text-[11px] font-semibold uppercase tracking-widest text-muted/60">{{ __('Business') }}</p>
                <div class="mt-2 space-y-1">
                    @if(auth()->user()->is_admin && (auth()->user()->admin_role === 'super_admin' || auth()->user()->hasRole('Super Admin') || auth()->user()->can('plans.view')))
                    <a href="{{ url('/admin/plans') }}" wire:navigate class="nav-item {{ request()->is('admin/plans*') ? 'nav-item-active' : '' }}" @if(request()->is('admin/plans*')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <path d="M2 10h20"></path>
                        </svg>
                        <span>{{ __('Plans') }}</span>
                    </a>
                    @endif

                    @can('coupons.view')
                    <a href="{{ url('/admin/coupons') }}" wire:navigate class="nav-item {{ request()->is('admin/coupons*') ? 'nav-item-active' : '' }}" @if(request()->is('admin/coupons*')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"></path>
                            <path d="M13 5v2"></path>
                            <path d="M13 17v2"></path>
                            <path d="M13 11v2"></path>
                        </svg>
                        <span>{{ __('Coupons') }}</span>
                    </a>
                    @endcan

                    @if(auth()->user()->is_admin && (auth()->user()->admin_role === 'super_admin' || auth()->user()->hasRole('Super Admin') || auth()->user()->can('payments.view')))
                    <a href="{{ url('/admin/payments') }}" wire:navigate class="nav-item {{ request()->is('admin/payments*') ? 'nav-item-active' : '' }}" @if(request()->is('admin/payments*')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                        <span>{{ __('Payments') }}</span>
                    </a>
                    @endif

                    @if(auth()->user()->is_admin && (auth()->user()->admin_role === 'super_admin' || auth()->user()->hasRole('Super Admin') || auth()->user()->can('payment-gateways.view')))
                    <a href="{{ url('/admin/payment-gateways') }}" wire:navigate class="nav-item {{ request()->is('admin/payment-gateways*') ? 'nav-item-active' : '' }}" @if(request()->is('admin/payment-gateways*')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            <path d="M6 8h.01"></path>
                            <path d="M10 8h.01"></path>
                            <path d="M14 8h.01"></path>
                        </svg>
                        <span>{{ __('Payment Gateways') }}</span>
                    </a>
                    @endif
                </div>

                {{-- ── CONTENT ── --}}
                <p class="mt-2 px-3 text-[11px] font-semibold uppercase tracking-widest text-muted/60">{{ __('Content') }}</p>
                <div class="mt-2 space-y-1">
                    @can('cms.view')
                    <a href="{{ url('/admin/cms') }}" wire:navigate class="nav-item {{ request()->is('admin/cms*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span>{{ __('CMS') }}</span>
                    </a>
                    @endcan
                    @can('frontend-settings.view')
                    <a href="{{ url('/admin/frontend-settings') }}" wire:navigate class="nav-item {{ request()->is('admin/frontend-settings*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                        <span>{{ __('Frontend') }}</span>
                    </a>
                    @endcan
                    @can('notifications.view')
                    <a href="{{ url('/admin/notifications') }}" wire:navigate class="nav-item {{ request()->is('admin/notifications*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        <span>{{ __('Notifications') }}</span>
                    </a>
                    @endcan
                </div>

                {{-- ── SYSTEM ── --}}
                <p class="mt-2 px-3 text-[11px] font-semibold uppercase tracking-widest text-muted/60">{{ __('System') }}</p>
                <div class="mt-2 space-y-1">
                    @can('settings.view')
                    <a href="{{ url('/admin/settings') }}" wire:navigate class="nav-item {{ request()->is('admin/settings') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span>{{ __('Settings') }}</span>
                    </a>
                    @endcan
                    @if(auth()->user()->admin_role === 'super_admin' || auth()->user()->hasRole('Super Admin'))
                    <a href="{{ url('/admin/phone-verification') }}" wire:navigate class="nav-item {{ request()->is('admin/phone-verification*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        <span>{{ __('Phone Verification') }}</span>
                    </a>
                    @endif
                    @if(auth()->user()->admin_role === 'super_admin' || auth()->user()->hasRole('Super Admin'))
                    @if(auth()->user()->admin_role === 'super_admin')
                    <a href="{{ route('admin.broadcasts.index') }}" class="nav-item">{{ __('Broadcasts · push & email') }}</a>
                    @endif
                    <a href="{{ url('/admin/friends-settings') }}" wire:navigate class="nav-item {{ request()->is('admin/friends-settings*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>{{ __('Friends Settings') }}</span>
                    </a>
                    @endif
                    @can('ai-providers.view')
                    <a href="{{ url('/admin/ai-providers') }}" wire:navigate class="nav-item {{ request()->is('admin/ai-providers*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 0 1 7 7c0 2.4-1.2 4.5-3 5.7V17a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2.3C6.2 13.5 5 11.4 5 9a7 7 0 0 1 7-7z"/><path d="M9 22h6"/></svg>
                        <span>{{ __('AI Providers') }}</span>
                    </a>
                    @endcan
                    @can('ai-usage.view')
                    <a href="{{ url('/admin/ai-usage') }}" wire:navigate class="nav-item {{ request()->is('admin/ai-usage*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/></svg>
                        <span>{{ __('AI Usage') }}</span>
                    </a>
                    @endcan
                    @can('temp-mail.view')
                    <a href="{{ url('/admin/temp-mail') }}" wire:navigate class="nav-item {{ request()->is('admin/temp-mail*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/><line x1="2" y1="20" x2="8" y2="14"/><line x1="22" y1="20" x2="16" y2="14"/></svg>
                        <span>{{ __('Temp Mail') }}</span>
                    </a>
                    @endcan
                    @can('roles.view')
                    <a href="{{ url('/admin/roles') }}" wire:navigate class="nav-item {{ request()->is('admin/roles*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>{{ __('Roles') }}</span>
                    </a>
                    @endcan
                    @can('permissions.view')
                    <a href="{{ url('/admin/permissions') }}" wire:navigate class="nav-item {{ request()->is('admin/permissions*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        <span>{{ __('Permissions') }}</span>
                    </a>
                    @endcan
                    @can('audit-logs.view')
                    <a href="{{ url('/admin/audit-log') }}" wire:navigate class="nav-item {{ request()->is('admin/audit-log*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                        <span>{{ __('Audit Log') }}</span>
                    </a>
                    @endcan
                    @can('security-audit-logs.view')
                    <a href="{{ url('/admin/security-audit-logs') }}" wire:navigate class="nav-item {{ request()->is('admin/security-audit-logs*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                        <span>{{ __('Security Logs') }}</span>
                    </a>
                    @endcan
                    @can('security-settings.view')
                    <a href="{{ url('/admin/security-settings') }}" wire:navigate class="nav-item {{ request()->is('admin/security-settings*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span>{{ __('Security Settings') }}</span>
                    </a>
                    @endcan
                    @can('blocked-ips.view')
                    <a href="{{ url('/admin/blocked-ips') }}" wire:navigate class="nav-item {{ request()->is('admin/blocked-ips*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M5.7 5.7l12.6 12.6"/></svg>
                        <span>{{ __('Blocked IPs') }}</span>
                    </a>
                    @endcan
                    @if(auth()->user()->admin_role === 'super_admin' || auth()->user()->hasRole('Super Admin'))
                    <a href="{{ url('/admin/sending-limits') }}" wire:navigate class="nav-item {{ request()->is('admin/sending-limits*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                        <span>{{ __('Sending limits') }}</span>
                    </a>
                    @endif
                    @can('blocked-locations.view')
                    <a href="{{ url('/admin/blocked-locations') }}" wire:navigate class="nav-item {{ request()->is('admin/blocked-locations*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span>{{ __('Blocked Locations') }}</span>
                    </a>
                    @endcan
                    @can('system-settings.view')
                    <a href="{{ url('/admin/languages') }}" wire:navigate class="nav-item {{ request()->is('admin/languages*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span>{{ __('Languages') }}</span>
                    </a>
                    <a href="{{ url('/admin/currencies') }}" wire:navigate class="nav-item {{ request()->is('admin/currencies*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span>{{ __('Currencies') }}</span>
                    </a>
                    @endcan
                    <a href="{{ url('/admin/email-deliverability') }}" wire:navigate class="nav-item {{ request()->is('admin/email-deliverability*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span>{{ __('Email Health') }}</span>
                    </a>
                    <a href="{{ url('/admin/system') }}" wire:navigate class="nav-item {{ request()->is('admin/system') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        <span>{{ __('System Info') }}</span>
                    </a>
                    {{-- System Update (Super Admin only — UpdateController guards with HasMiddleware) --}}
                    @php
                        $u = auth()->user();
                        $isSuperAdmin = $u && (
                            (method_exists($u, 'hasRole') && $u->hasRole('Super Admin'))
                            || ($u->is_admin && ($u->admin_role ?? '') === 'super_admin')
                        );
                    @endphp
                    @if($isSuperAdmin)
                    <a href="{{ url('/admin/update') }}" wire:navigate class="nav-item {{ request()->is('admin/update*') ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
                        <span class="flex-1">{{ __('System Update') }}</span>
                        <span class="text-[9px] font-semibold uppercase tracking-wider bg-brand/15 text-brand px-1.5 py-0.5 rounded">v{{ config('version.version', '1.0.0') }}</span>
                    </a>
                    @endif
                </div>
            </div>
        </nav>

        {{-- Sidebar Footer --}}
        <div class="border-t border-border/40 px-4 py-3 shrink-0">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand/15 text-brand text-xs font-bold">
                    {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold truncate leading-tight text-ink">{{ auth()->user()->name ?? 'Admin' }}</p>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-muted truncate leading-tight">{{ auth()->user()->admin_role ?? 'Super Admin' }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-muted hover:text-danger hover:bg-danger/10 transition" aria-label="Logout">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Mobile sidebar overlay --}}
    <div
        x-show="sidebarOpen"
        @click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm lg:hidden"
        x-transition.opacity
        style="display: none;"
    ></div>

    {{-- ═══════════════════════════════════════════════
         MAIN CONTENT
         ═══════════════════════════════════════════════ --}}
    <div class="flex flex-1 flex-col overflow-y-auto">

        {{-- Header --}}
        <header class="sticky top-0 z-30 border-b border-border/60 bg-surface-2/90 backdrop-blur">
            <div class="flex items-center justify-between px-4 py-4 lg:px-6">

                {{-- Left side --}}
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    {{-- Mobile hamburger --}}
                    <button type="button" class="icon-button shrink-0 lg:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle sidebar">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="panel-heading">{{ __('Admin Console') }}</p>
                        <h1 class="truncate text-lg font-semibold tracking-tight sm:text-2xl text-ink">{{ $title }}</h1>
                        @if($subtitle)
                            <p class="text-sm text-muted mt-0.5 truncate">{{ $subtitle }}</p>
                        @endif
                    </div>
                </div>

                {{-- Right side --}}
                <div class="flex shrink-0 items-center gap-3">
                    {{-- Live Search --}}
                    <div class="hidden md:block relative" x-data="{
                        query: '',
                        results: { users: [], plans: [], tickets: [] },
                        loading: false,
                        open: false,
                        debounceTimer: null,
                        async search() {
                            if (this.query.trim().length < 2) { this.results = { users: [], plans: [], tickets: [] }; this.open = false; return; }
                            this.loading = true;
                            this.open = true;
                            clearTimeout(this.debounceTimer);
                            this.debounceTimer = setTimeout(async () => {
                                try {
                                    const q = encodeURIComponent(this.query.trim());
                                    const [usersRes, plansRes, ticketsRes] = await Promise.all([
                                        fetch('/admin/users?search=' + q, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.text()),
                                        Promise.resolve(''),
                                        Promise.resolve('')
                                    ]);
                                    // Parse user names from HTML response
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(usersRes, 'text/html');
                                    const rows = doc.querySelectorAll('tbody tr td .font-semibold');
                                    let users = [];
                                    rows.forEach((el, i) => {
                                        if (i < 5) {
                                            const link = el.closest('a');
                                            const emailEl = el.parentElement?.querySelector('.text-muted');
                                            users.push({
                                                name: el.textContent.trim(),
                                                email: emailEl ? emailEl.textContent.trim() : '',
                                                url: link ? link.getAttribute('href') : '#'
                                            });
                                        }
                                    });
                                    this.results = { users, plans: [], tickets: [] };
                                } catch(e) { console.error(e); }
                                this.loading = false;
                            }, 300);
                        },
                        clear() { this.query = ''; this.open = false; this.results = { users: [], plans: [], tickets: [] }; }
                    }" @click.outside="open = false">
                        <div class="relative">
                            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" x-model="query" @input="search()" @focus="if(query.length >= 2) open = true"
                                   @keydown.enter.prevent="if(query.trim()) window.location.href='{{ url('/admin/users') }}?search=' + encodeURIComponent(query.trim())"
                                   @keydown.escape="clear()"
                                   placeholder="{{ __('Search people, events, uploads...') }}"
                                   class="h-10 w-72 rounded-xl border border-border bg-surface pl-10 pr-9 text-sm text-ink placeholder:text-muted/60 focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none transition-all">
                            <button x-show="query.length > 0" x-cloak @click="clear()" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Search Results Dropdown --}}
                        <div x-show="open" x-transition class="absolute left-0 right-0 mt-2 rounded-2xl border border-border bg-surface-2 shadow-xl z-50 overflow-hidden" style="display:none">
                            {{-- Loading --}}
                            <div x-show="loading" class="px-4 py-3 text-center">
                                <svg class="animate-spin h-4 w-4 text-brand mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </div>

                            {{-- Users --}}
                            <template x-if="results.users.length > 0">
                                <div>
                                    <p class="px-4 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-widest text-muted">{{ __('Users') }}</p>
                                    <template x-for="user in results.users" :key="user.url">
                                        <a :href="user.url" class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface transition-colors">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand text-xs font-bold" x-text="user.name.charAt(0).toUpperCase()"></span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-ink truncate" x-text="user.name"></p>
                                                <p class="text-xs text-muted truncate" x-text="user.email"></p>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            {{-- No results --}}
                            <div x-show="!loading && results.users.length === 0 && query.length >= 2" class="px-4 py-4 text-center">
                                <p class="text-sm text-muted">{{ __('No results for') }} "<span class="font-medium text-ink" x-text="query"></span>"</p>
                            </div>

                            {{-- View all link --}}
                            <a x-show="query.length >= 2" :href="'/admin/users?search=' + encodeURIComponent(query)" class="flex items-center justify-center gap-2 px-4 py-2.5 border-t border-border/60 text-xs font-semibold text-brand hover:bg-brand/5 transition-colors">
                                {{ __('View all results') }}
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>

                    {{-- Fullscreen toggle --}}
                    <button class="icon-button" title="Toggle fullscreen" @click="
                        if (!document.fullscreenElement) {
                            document.documentElement.requestFullscreen();
                        } else {
                            document.exitFullscreen();
                        }
                    ">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                        </svg>
                    </button>

                    {{-- Language switcher --}}
                    <x-language-switcher />

                    <x-theme-toggle />

                    {{-- Notification bell --}}
                    @php
                        $openTickets = \Illuminate\Support\Facades\DB::table('tickets')->where('status', 'open')->count();
                        $recentUsers = \App\Models\User::where('created_at', '>=', now()->subDay())->count();
                        $failedPayments = \Illuminate\Support\Facades\DB::table('payments')->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count();
                        $pendingJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
                        $newWorkspaces = \Illuminate\Support\Facades\DB::table('workspaces')->where('created_at', '>=', now()->subDay())->count();
                        $activeNow = \Illuminate\Support\Facades\DB::table('sessions')->where('last_activity', '>=', now()->subMinutes(5)->timestamp)->count();
                        $notifCount = $openTickets + $recentUsers + $failedPayments + $pendingJobs;
                    @endphp
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="icon-button relative" aria-label="Notifications">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                            @if($notifCount > 0)
                            <span class="absolute -top-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-danger text-[10px] font-bold text-white ring-2 ring-surface-2">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>
                            @endif
                        </button>
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-80 rounded-2xl border border-border bg-surface-2 shadow-xl z-50"
                            style="display: none;"
                        >
                            <div class="flex items-center justify-between px-4 py-3 border-b border-border/60">
                                <p class="text-sm font-semibold text-ink">{{ __('Notifications') }}</p>
                                @if($notifCount > 0)
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-danger/10 text-danger">{{ $notifCount }} {{ __('new') }}</span>
                                @endif
                            </div>
                            <div class="max-h-72 overflow-y-auto scrollbar-hide">
                                @if($openTickets > 0)
                                <a href="{{ url('/admin/tickets') }}" class="flex items-start gap-3 px-4 py-3 hover:bg-surface transition-colors border-b border-border/40">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-warning/10 text-warning mt-0.5">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-ink">{{ $openTickets }} {{ __('open ticket') }}{{ $openTickets > 1 ? 's' : '' }}</p>
                                        <p class="text-xs text-muted">{{ __('Awaiting response') }}</p>
                                    </div>
                                </a>
                                @endif
                                @if($recentUsers > 0)
                                <a href="{{ url('/admin/users') }}" class="flex items-start gap-3 px-4 py-3 hover:bg-surface transition-colors border-b border-border/40">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-success/10 text-success mt-0.5">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-ink">{{ $recentUsers }} {{ __('new user') }}{{ $recentUsers > 1 ? 's' : '' }} {{ __('today') }}</p>
                                        <p class="text-xs text-muted">{{ __('Registered in the last 24h') }}</p>
                                    </div>
                                </a>
                                @endif
                                @if($failedPayments > 0)
                                <a href="{{ url('/admin/payments?status=failed') }}" class="flex items-start gap-3 px-4 py-3 hover:bg-surface transition-colors border-b border-border/40">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger mt-0.5">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-ink">{{ $failedPayments }} {{ __('failed payment') }}{{ $failedPayments > 1 ? 's' : '' }}</p>
                                        <p class="text-xs text-muted">{{ __('In the last 7 days') }}</p>
                                    </div>
                                </a>
                                @endif
                                @if($pendingJobs > 0)
                                <a href="{{ url('/admin/system') }}" class="flex items-start gap-3 px-4 py-3 hover:bg-surface transition-colors border-b border-border/40">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger mt-0.5">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-ink">{{ $pendingJobs }} {{ __('failed job') }}{{ $pendingJobs > 1 ? 's' : '' }}</p>
                                        <p class="text-xs text-muted">{{ __('Requires attention') }}</p>
                                    </div>
                                </a>
                                @endif
                                @if($newWorkspaces > 0)
                                <a href="{{ url('/admin/users') }}" class="flex items-start gap-3 px-4 py-3 hover:bg-surface transition-colors border-b border-border/40">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand mt-0.5">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-ink">{{ $newWorkspaces }} {{ __('new workspace') }}{{ $newWorkspaces > 1 ? 's' : '' }}</p>
                                        <p class="text-xs text-muted">{{ __('Created in the last 24h') }}</p>
                                    </div>
                                </a>
                                @endif
                                {{-- Always show active users count --}}
                                <div class="flex items-start gap-3 px-4 py-3 border-b border-border/40">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-success/10 text-success mt-0.5">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-ink">{{ $activeNow }} {{ __('user') }}{{ $activeNow !== 1 ? 's' : '' }} {{ __('online now') }}</p>
                                        <p class="text-xs text-muted">{{ __('Active in the last 5 minutes') }}</p>
                                    </div>
                                </div>
                                @if($notifCount === 0)
                                <div class="px-4 py-4 text-center">
                                    <p class="text-xs text-muted">{{ __('No urgent notifications') }}</p>
                                </div>
                                @endif
                            </div>
                            <a href="{{ url('/admin/notifications') }}" class="flex items-center justify-center gap-2 px-4 py-2.5 border-t border-border/60 text-xs font-semibold uppercase tracking-wider text-brand hover:bg-brand/5 transition-colors rounded-b-2xl">
                                {{ __('View all notifications') }}
                            </a>
                        </div>
                    </div>

                    {{-- Admin user --}}
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="flex items-center gap-2.5 transition" aria-label="User menu" aria-haspopup="true" :aria-expanded="open">
                            <div class="text-right hidden sm:block">
                                <p class="text-sm font-semibold text-ink">{{ auth()->user()->name ?? 'Admin' }}</p>
                                <p class="text-[10px] text-muted">{{ __('Super Admin') }}</p>
                            </div>
                            <div class="h-9 w-9 rounded-full bg-brand/15 flex items-center justify-center text-sm font-bold text-brand">
                                {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                            </div>
                        </button>
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-56 rounded-2xl border border-border/70 bg-surface-2 p-2 shadow-soft z-50"
                            style="display: none;"
                        >
                            <div class="px-3 py-2 border-b border-border/60 mb-1">
                                <p class="text-sm font-semibold text-ink truncate">{{ auth()->user()->name ?? 'Admin' }}</p>
                                <p class="text-[11px] text-muted truncate">{{ auth()->user()->email ?? '' }}</p>
                            </div>
                            <a href="{{ url('/dashboard') }}" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-ink transition hover:bg-muted/10">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 text-ink/60" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect></svg>
                                {{ __('User Dashboard') }}
                            </a>
                            <a href="{{ url('/admin/settings') }}" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-ink transition hover:bg-muted/10">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 text-ink/60" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09"></path></svg>
                                {{ __('Settings') }}
                            </a>
                            <div class="my-1 border-t border-border/60"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-danger transition hover:bg-danger/10">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                    {{ __('Sign Out') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 px-4 py-6 lg:px-8 overflow-y-auto">
            <div class="max-w-7xl mx-auto">
            {{-- Flash messages --}}
            @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                 role="alert" aria-live="polite"
                 class="mb-4 alert alert-success">
                <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span class="flex-1 text-sm font-medium">{{ session('success') }}</span>
                <button @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Dismiss">&times;</button>
            </div>
            @endif

            @if(session('error'))
            <div x-data="{ show: true }" x-show="show"
                 role="alert" aria-live="assertive"
                 class="mb-4 alert alert-error">
                <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                <span class="flex-1 text-sm font-medium">{{ session('error') }}</span>
                <button @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Dismiss">&times;</button>
            </div>
            @endif

            {{ $slot }}

            </div>{{-- max-w-7xl --}}
        </main>
    </div>
</div>

@livewireScripts

<script>
function adminLayout() {
    return {
        theme: localStorage.getItem('theme') || 'light',

        init() {
            document.documentElement.setAttribute('data-theme', this.theme);
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
        },

        toggleTheme() {
            this.theme = this.theme === 'light' ? 'dark' : 'light';
            localStorage.setItem('theme', this.theme);
            document.documentElement.setAttribute('data-theme', this.theme);
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
        },
    };
}

// wire:navigate progress bar
(function () {
    const bar = document.getElementById('mb-progress-bar');
    let timer = null;

    function start() {
        if (!bar) return;
        clearTimeout(timer);
        bar.style.transition = 'none';
        bar.style.width = '0%';
        bar.classList.add('active');
        bar.offsetHeight;
        bar.style.transition = 'width 300ms ease';
        bar.style.width = '60%';
        timer = setTimeout(() => { bar.style.width = '80%'; }, 400);
    }

    function finish() {
        if (!bar) return;
        clearTimeout(timer);
        bar.style.transition = 'width 200ms ease';
        bar.style.width = '100%';
        setTimeout(() => {
            bar.style.transition = 'opacity 300ms ease';
            bar.classList.remove('active');
            setTimeout(() => { bar.style.width = '0%'; }, 350);
        }, 200);
    }

    document.addEventListener('livewire:navigating', start);
    document.addEventListener('livewire:navigated', finish);

    // Page enter animation
    document.addEventListener('livewire:navigated', () => {
        const main = document.querySelector('main');
        if (!main || !main.firstElementChild) return;
        const el = main.firstElementChild;
        el.style.animation = 'none';
        el.offsetHeight;
        el.style.animation = '';
    });

    // Re-initialize Lucide icons on SPA navigation
    document.addEventListener('livewire:navigated', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    // Fix dark mode persistence on wire:navigate SPA transitions
    document.addEventListener('livewire:navigated', function() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.classList.toggle('dark', savedTheme === 'dark');
        document.documentElement.setAttribute('data-theme', savedTheme);
    });
})();
</script>

<script>if(typeof lucide !== 'undefined') lucide.createIcons();</script>
<script>
(function() {
    var nav = document.getElementById('admin-sidebar-nav');
    if (!nav) return;
    var saved = sessionStorage.getItem('admin-sidebar-scroll');
    if (saved) nav.scrollTop = parseInt(saved, 10);
    nav.addEventListener('scroll', function() { sessionStorage.setItem('admin-sidebar-scroll', nav.scrollTop); });
    document.addEventListener('livewire:navigating', function() { sessionStorage.setItem('admin-sidebar-scroll', nav.scrollTop); });
})();
</script>

{{-- Admin-defined raw HTML rendered before </body> (analytics, chat widgets, etc.) --}}
{!! \App\Models\SystemSetting::get('footer_code', '') !!}
    @include('partials.timezone-sync')
</body>
</html>