<x-layouts.admin :title="__('Frontend Settings')" :subtitle="__('Manage account features and website content')">
    <div class="space-y-8">

        @if(auth()->user()->isSuperAdmin())
        <form id="account-features" method="POST" action="{{ route('admin.frontend-settings.account-features') }}" class="panel p-5">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-bold text-ink">{{ __('Account access & guidance') }}</h2>
            <p class="mt-2 text-sm text-muted">{{ __('Choose the recovery and welcome features available to your users. Only superadmins can change these settings.') }}</p>
            @if(session('success'))
                <p role="status" class="mt-3 text-sm text-success">{{ session('success') }}</p>
            @endif
            @if($errors->any())
                <p role="alert" class="mt-3 text-sm text-danger">{{ $errors->first() }}</p>
            @endif
            <div class="mt-5 space-y-4">
                @foreach([
                    'phrase_recovery_enabled' => [__('Passphrase password recovery'), __('Allow users to reset their password using their saved recovery phrase on the website and app.')],
                    'email_recovery_enabled' => [__('Email password recovery'), __('Allow reset links on the website and email links or codes in the app. Turning this off also blocks existing links and codes while disabled.')],
                    'onboarding_enabled' => [__('Website onboarding wizard'), __('Show workspace setup after new website registrations. When disabled, create the default workspace automatically and open the dashboard. Existing workspaces are kept.')],
                    'product_tour_enabled' => [__('Website product tour'), __('Show the introductory walkthrough and the Restart Product Tour action.')],
                    'getting_started_enabled' => [__('Getting Started checklist'), __('Show the dashboard setup progress cards and the floating checklist, including completed steps.')],
                ] as $key => [$label, $description])
                    <input type="hidden" name="{{ $key }}" value="0">
                    <label class="flex items-start gap-3 rounded-xl border border-border p-4">
                        <input type="checkbox" name="{{ $key }}" value="1" class="mt-1 h-4 w-4 rounded border-border text-brand focus:ring-brand"
                               @checked(old($key, \App\Support\AccountFeatures::all()[$key]))>
                        <span><span class="block font-semibold text-ink">{{ $label }}</span><span class="mt-1 block text-sm text-muted">{{ $description }}</span></span>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="btn-primary mt-5">{{ __('Save account features') }}</button>
        </form>
        @endif

        {{-- Homepage Theme --}}
        <div class="panel p-5">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-2xl">
                    <h3 class="text-sm font-bold text-ink uppercase tracking-wider">{{ __('Homepage Design') }}</h3>
                    <p class="mt-2 text-sm text-muted">{{ __('Choose which homepage UI visitors see. Both designs use the same editable content below.') }}</p>
                </div>
                <a href="{{ url('/') }}" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-xl border border-border px-4 py-2 text-sm font-semibold text-muted transition hover:border-brand/40 hover:text-ink">
                    {{ __('Preview Homepage') }}
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 17L17 7M8 7h9v9"/></svg>
                </a>
            </div>

            @if(auth()->user()->isSuperAdmin())
            <form method="POST" action="{{ route('admin.frontend-settings.homepage-access') }}" class="mt-5 rounded-xl border border-border p-5">
                @csrf
                @method('PUT')
                <h3 class="font-semibold">{{ __('Default website page') }}</h3>
                <p class="mt-2 text-sm text-muted">{{ __('Login is the default page. Enable the public homepage to show your landing page instead. Signed-in users go to their dashboard. Meeting links and public information pages remain available.') }}</p>
                <input type="hidden" name="frontend_homepage_enabled" value="0">
                <label class="flex items-center gap-3 my-4">
                    <input type="checkbox" name="frontend_homepage_enabled" value="1" @checked($homepageEnabled)>
                    <span>{{ __('Show public homepage') }}</span>
                </label>
                <button type="submit" class="btn-primary">{{ __('Save default page') }}</button>
            </form>
            @endif

            <form method="POST" action="{{ route('admin.frontend-settings.homepage-theme') }}" class="mt-5" x-data="{ selected: @js($homepageTheme ?? 'classic') }">
                @csrf
                @method('PUT')
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach([
                        [
                            'value' => 'classic',
                            'title' => __('Classic Homepage'),
                            'desc' => __('The current landing page with the existing section layout and animations.'),
                            'preview' => ['bg-brand', 'bg-success', 'bg-warning'],
                        ],
                        [
                            'value' => 'modern',
                            'title' => __('Modern Detailed Homepage'),
                            'desc' => __('A cleaner second homepage with full product sections, animated previews, feature catalog, pricing, testimonials, FAQ, and CTA.'),
                            'preview' => ['bg-ink', 'bg-brand', 'bg-success'],
                        ],
                    ] as $option)
                        <label
                            class="group relative cursor-pointer rounded-2xl border p-4 transition hover:border-brand/40 hover:bg-surface-2"
                            :class="selected === '{{ $option['value'] }}' ? 'border-brand bg-brand/5 ring-2 ring-brand/15' : 'border-border bg-surface/60'"
                        >
                            <input type="radio" name="frontend_homepage_theme" value="{{ $option['value'] }}" class="sr-only" x-model="selected" @checked(($homepageTheme ?? 'classic') === $option['value'])>
                            <div class="mb-4 overflow-hidden rounded-xl border border-border bg-surface-2 shadow-soft">
                                <div class="flex h-7 items-center gap-1.5 border-b border-border px-3">
                                    <span class="h-2 w-2 rounded-full bg-danger/70"></span>
                                    <span class="h-2 w-2 rounded-full bg-warning/70"></span>
                                    <span class="h-2 w-2 rounded-full bg-success/70"></span>
                                </div>
                                <div class="grid h-32 grid-cols-[0.7fr_1fr] gap-3 p-3">
                                    <div class="space-y-2">
                                        <span class="block h-3 w-16 rounded-full {{ $option['preview'][1] }}"></span>
                                        <span class="block h-5 w-full rounded-lg {{ $option['preview'][0] }}"></span>
                                        <span class="block h-5 w-3/4 rounded-lg {{ $option['preview'][0] }}/80"></span>
                                        <span class="mt-3 block h-8 w-24 rounded-xl {{ $option['preview'][1] }}"></span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <span class="rounded-lg {{ $option['preview'][0] }}/10 border border-border"></span>
                                        <span class="rounded-lg {{ $option['preview'][1] }}/10 border border-border"></span>
                                        <span class="rounded-lg {{ $option['preview'][2] }}/10 border border-border"></span>
                                        <span class="rounded-lg bg-surface border border-border"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span
                                    class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full border"
                                    :class="selected === '{{ $option['value'] }}' ? 'border-brand bg-brand text-white' : 'border-border text-transparent'"
                                >
                                    <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 13l4 4L19 7"/></svg>
                                </span>
                                <span>
                                    <span class="block text-sm font-bold text-ink">{{ $option['title'] }}</span>
                                    <span class="mt-1 block text-xs leading-5 text-muted">{{ $option['desc'] }}</span>
                                </span>
                            </div>
                        </label>
                    @endforeach
                </div>
                <div class="mt-5 flex items-center justify-end">
                    <button type="submit" class="btn-primary">{{ __('Save Homepage Design') }}</button>
                </div>
            </form>
        </div>

        {{-- Landing Page Sections --}}
        <div>
            <h3 class="text-sm font-bold text-ink uppercase tracking-wider mb-2">{{ __('Landing Page') }}</h3>
            <p class="text-sm text-muted mb-2">{{ __('These content sections are shared by both homepage designs.') }}</p>
            {{--
            <p class="text-sm text-muted mb-5">{{ __('Edit the content shown on your homepage. The design stays the same — only the text changes.') }}</p>
            --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['type' => 'hero',         'title' => __('Hero Section'),     'desc' => __('Badge, headline, subtitle, and call-to-action buttons.'),   'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                    ['type' => 'features',     'title' => __('Features'),         'desc' => __('Feature cards with titles and descriptions.'),              'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
                    ['type' => 'testimonials', 'title' => __('Testimonials'),     'desc' => __('Customer quotes, names, and companies.'),                  'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
                    ['type' => 'faq',          'title' => __('FAQ'),              'desc' => __('Questions and answers displayed in accordion.'),             'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['type' => 'cta',          'title' => __('CTA Section'),      'desc' => __('Final call-to-action badge, title, and button.'),           'icon' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
                    ['type' => 'footer',       'title' => __('Footer'),           'desc' => __('Brand text, social links, newsletter, and copyright.'),    'icon' => 'M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2zM3 10h18'],
                ] as $card)
                <a href="{{ route('admin.frontend-settings.edit', $card['type']) }}"
                   class="group panel p-5 flex items-start gap-4 hover:border-brand/30 hover:shadow-lg hover:shadow-brand/5 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-brand/10 text-brand flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $card['icon'] }}"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ $card['title'] }}</p>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-brand bg-brand/10 px-2 py-0.5 rounded-full">{{ __('Landing') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-muted">{{ $card['desc'] }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-muted/40 group-hover:text-brand transition-colors mt-0.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Page Settings --}}
        <div>
            <h3 class="text-sm font-bold text-ink uppercase tracking-wider mb-2">{{ __('Page Settings') }}</h3>
            <p class="text-sm text-muted mb-5">{{ __("Edit the content displayed on your website's built-in pages.") }}</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['type' => 'terms',   'title' => __('Terms of Service'),  'desc' => __('Header and numbered legal sections.'),        'icon' => 'M14.5 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V7.5L14.5 2z'],
                    ['type' => 'privacy', 'title' => __('Privacy Policy'),    'desc' => __('Header, privacy sections, and bottom text.'), 'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10'],
                    ['type' => 'refund',  'title' => __('Refund Policy'),     'desc' => __('Refund eligibility and cancellation rules.'), 'icon' => 'M3 10h18M3 14h18M12 6v12'],
                    ['type' => 'contact', 'title' => __('Contact Us'),        'desc' => __('Subtitle, support channels, response time.'), 'icon' => 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z'],
                    ['type' => 'about',   'title' => __('About Us'),          'desc' => __('Story, mission, and company stats.'),         'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['type' => 'why_us',  'title' => __('Why Us'),            'desc' => __('Reasons to choose your platform.'),           'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                ] as $card)
                <a href="{{ route('admin.frontend-settings.edit', $card['type']) }}"
                   class="group panel p-5 flex items-start gap-4 hover:border-brand/30 hover:shadow-lg hover:shadow-brand/5 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-brand/10 text-brand flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $card['icon'] }}"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-ink group-hover:text-brand transition-colors">{{ $card['title'] }}</p>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-success bg-success/10 px-2 py-0.5 rounded-full">{{ __('Frontend') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-muted">{{ $card['desc'] }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-muted/40 group-hover:text-brand transition-colors mt-0.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 18l6-6-6-6"/></svg>
                </a>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.admin>
