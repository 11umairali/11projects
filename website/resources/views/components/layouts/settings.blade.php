<x-layouts.app :title="$title ?? 'Settings'">
    <div class="flex flex-col lg:flex-row gap-6">
        {{-- Settings sidebar --}}
        <aside class="lg:w-52 flex-shrink-0">
            <nav class="panel p-3 lg:sticky lg:top-24 space-y-1"
                 x-data="{ mobileOpen: false }">
                {{-- Mobile toggle --}}
                <button @click="mobileOpen = !mobileOpen"
                        class="lg:hidden w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium text-ink">
                    <span>{{ __('Settings Menu') }}</span>
                    <svg class="w-4 h-4 text-muted transition-transform" :class="mobileOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div :class="mobileOpen ? 'block' : 'hidden lg:block'" class="space-y-1">
                    @php
                    $settingsNav = [
                        ['label' => __('Profile'), 'href' => url('/settings/profile'), 'icon' => '<circle cx="12" cy="8" r="4"></circle><path d="M5 21v-1a7 7 0 0 1 14 0v1"></path>'],
                        ['label' => __('Security'), 'href' => url('/settings/security'), 'icon' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>'],
                        ['label' => __('Email Accounts'), 'href' => url('/settings/email'), 'icon' => '<rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>'],
                        ['label' => __('Phone & Discovery'), 'href' => url('/settings/phone'), 'icon' => '<rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line>'],
                        ['label' => __('Privacy'), 'href' => url('/settings/privacy'), 'icon' => '<rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>'],

                        ['label' => __('Mail Server'), 'href' => url('/settings/mail-server'), 'icon' => '<rect x="2" y="3" width="20" height="8" rx="2"></rect><rect x="2" y="13" width="20" height="8" rx="2"></rect><path d="M6 7h.01M6 17h.01"></path>'],
                        ['label' => __('Channels'), 'href' => url('/settings/channels'), 'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>'],
                        ['label' => __('AI Configuration'), 'href' => url('/settings/ai'), 'icon' => '<path d="M12 2a7 7 0 0 1 7 7c0 2.4-1.2 4.5-3 5.7V17a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-2.3C6.2 13.5 5 11.4 5 9a7 7 0 0 1 7-7z"></path><path d="M9 22h6"></path>'],
                        ['label' => __('Auto-Reply Rules'), 'href' => url('/settings/auto-reply'), 'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><path d="M13 8H7"></path><path d="M17 12H7"></path>'],
                        ['label' => __('Contacts'), 'href' => url('/settings/contacts'), 'icon' => '<circle cx="9" cy="8" r="3"></circle><path d="M3 19c1.8-3 4.8-4.5 6-4.5s4.2 1.5 6 4.5"></path><path d="M15.5 11a3 3 0 1 0 0-6"></path>'],
                        ['label' => __('Team'), 'href' => url('/settings/team'), 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
                        ['label' => __('Billing'), 'href' => url('/settings/billing'), 'icon' => '<rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20"></path>'],
                        ['label' => __('Workspace'), 'href' => url('/settings/workspace'), 'icon' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>'],
                        ['label' => __('Notifications'), 'href' => url('/settings/notifications'), 'icon' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>'],
                        ['label' => __('Integrations'), 'href' => url('/settings/integrations'), 'icon' => '<path d="M11 4a2 2 0 1 1 4 0v1a1 1 0 0 0 1 1h3a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1a2 2 0 1 0 0 4h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1v-1a2 2 0 1 0-4 0v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-3a1 1 0 0 0-1-1H4a2 2 0 1 1 0-4h1a1 1 0 0 0 1-1V7a1 1 0 0 1 1-1h3a1 1 0 0 0 1-1V4z"></path>'],
                        ['label' => __('Webhooks'), 'href' => url('/settings/webhooks'), 'icon' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>'],
                        ['label' => __('Data & Privacy'), 'href' => url('/settings/data-privacy'), 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>'],
                        ['label' => __('Account'), 'href' => url('/settings/account'), 'icon' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09"></path>'],
                    ];
                    @endphp

                    @foreach($settingsNav as $item)
                    @continue(\App\Support\BillingPolicy::isFree() && $item['href'] === url('/settings/billing'))
                    @php
                        // $item['href'] is an absolute URL produced by url('/settings/...').
                        // request()->is() expects a *path pattern* (no scheme/host), so we
                        // strip both the scheme/host AND any base URI (e.g. "/mailtrixy/public")
                        // before comparing. Without this every entry was "not active"
                        // because we were matching the request path against a full URL.
                        $path     = parse_url($item['href'], PHP_URL_PATH) ?? $item['href'];
                        $basePath = parse_url(url('/'), PHP_URL_PATH) ?? '';
                        if ($basePath && $basePath !== '/' && str_starts_with($path, $basePath)) {
                            $path = substr($path, strlen($basePath));
                        }
                        $path   = ltrim($path, '/');
                        $active = $path !== '' && (request()->is($path) || request()->is($path . '/*'));
                    @endphp
                    <a href="{{ $item['href'] }}" wire:navigate
                       class="nav-item {{ $active ? 'nav-item-active' : '' }}">
                        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">@safeSvg($item['icon'])</svg>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                    @endforeach
                </div>
            </nav>
        </aside>

        {{-- Settings content --}}
        <div class="flex-1 min-w-0">
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
