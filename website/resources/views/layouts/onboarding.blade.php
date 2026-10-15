<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    @php
        $__sn      = \App\Models\SystemSetting::get('site_name', config('app.name', 'MailTrixy'));
        $__logoL   = \App\Models\SystemSetting::get('logo_light');
        $__logoD   = \App\Models\SystemSetting::get('logo_dark');
        $__hasLogo = $__logoL || $__logoD;
    @endphp
    <title>@yield('title', 'Setup') - {{ $__sn }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- Apply theme BEFORE @vite so the .dark class is set during first paint
         (otherwise Tailwind's dark: variants miss and the page flashes light). --}}
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            const isDark = savedTheme === 'dark';
            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak]{display:none!important}

        /* ============================================================
           LIGHT MODE (default) — clean white surfaces, dark text
           ============================================================ */
        :root {
            --ob-bg:        #f8fafc;          /* main background */
            --ob-bg-alt:    #ffffff;          /* sidebar / cards */
            --ob-border:    #e2e8f0;
            --ob-text:      #0f172a;          /* near-black headings */
            --ob-text-soft: #475569;          /* body copy */
            --ob-text-mute: #4b5563;          /* labels / placeholder */
            --ob-brand:     #6366f1;
            --ob-brand-soft:#a5b4fc;
            --ob-input-bg:  #ffffff;
            --ob-input-bd:  #e2e8f0;
            --ob-step-bg:   #f1f5f9;          /* upcoming step pill */
            --ob-divider:   #e2e8f0;
        }

        /* ============================================================
           DARK MODE — dark navy surfaces, light text
           ============================================================ */
        html.dark {
            --ob-bg:        #0f1117;
            --ob-bg-alt:    #15171e;
            --ob-border:    #2d3039;
            --ob-text:      #f3f4f6;
            --ob-text-soft: #cbd5e1;
            --ob-text-mute: #94a3b8;
            --ob-brand:     #6366f1;
            --ob-brand-soft:#a5b4fc;
            --ob-input-bg:  #1a1d27;
            --ob-input-bd:  #2d3039;
            --ob-step-bg:   #1a1d27;
            --ob-divider:   #2d3039;
        }

        /* ============================================================
           Apply tokens — same selectors, both modes via CSS vars
           ============================================================ */
        body {
            background: var(--ob-bg) !important;
            color: var(--ob-text) !important;
        }

        .bg-surface  { background-color: var(--ob-bg) !important; }
        .bg-surface-2{ background-color: var(--ob-bg-alt) !important; }
        .border-border, .border-border\/60 { border-color: var(--ob-border) !important; }

        /* Headings + text utilities reflect the active theme */
        h1, h2, h3, h4 { color: var(--ob-text) !important; }

        .text-ink           { color: var(--ob-text) !important; }
        .text-ink\/80       { color: color-mix(in srgb, var(--ob-text) 85%, transparent) !important; }
        .text-ink\/50       { color: color-mix(in srgb, var(--ob-text) 60%, transparent) !important; }
        .text-muted         { color: var(--ob-text-mute) !important; }
        .text-muted\/60     { color: color-mix(in srgb, var(--ob-text-mute) 65%, transparent) !important; }
        .text-muted\/40     { color: color-mix(in srgb, var(--ob-text-mute) 45%, transparent) !important; }

        /* Brand accents — same hue, theme-aware brightness */
        .text-brand,
        .text-indigo-400,
        .text-primary,
        .text-primary-600 { color: var(--ob-brand) !important; }
        html.dark .text-brand,
        html.dark .text-indigo-400,
        html.dark .text-primary,
        html.dark .text-primary-600 { color: var(--ob-brand-soft) !important; }

        .bg-brand           { background-color: var(--ob-brand) !important; }
        .bg-brand\/10       { background-color: color-mix(in srgb, var(--ob-brand) 12%, transparent) !important; }
        .bg-brand\/15       { background-color: color-mix(in srgb, var(--ob-brand) 18%, transparent) !important; }
        .bg-brand\/20       { background-color: color-mix(in srgb, var(--ob-brand) 24%, transparent) !important; }
        .bg-primary         { background-color: var(--ob-brand) !important; }
        .bg-primary-100     { background-color: color-mix(in srgb, var(--ob-brand) 15%, transparent) !important; }
        .bg-primary-900\/20 { background-color: color-mix(in srgb, var(--ob-brand) 12%, transparent) !important; }

        /* Inputs */
        input, select, textarea {
            background-color: var(--ob-input-bg) !important;
            border-color: var(--ob-input-bd) !important;
            color: var(--ob-text) !important;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--ob-brand) !important;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--ob-brand) 25%, transparent) !important;
        }
        input::placeholder, textarea::placeholder { color: var(--ob-text-mute) !important; }

        /* Status colors — same in both modes (saturation tweaked for dark) */
        .text-success { color: #10b981 !important; }
        .text-warning { color: #d97706 !important; }
        .text-danger  { color: #dc2626 !important; }
        .text-info    { color: #2563eb !important; }
        html.dark .text-success { color: #34d399 !important; }
        html.dark .text-warning { color: #fbbf24 !important; }
        html.dark .text-danger  { color: #f87171 !important; }
        html.dark .text-info    { color: #60a5fa !important; }

        /* Status panel backgrounds — light pastel in light mode, dark translucent in dark */
        .bg-amber-50              { background-color: #fffbeb !important; }
        .bg-success\/10, .bg-success\/15 { background-color: #ecfdf5 !important; }
        .bg-warning\/10           { background-color: #fffbeb !important; }
        .bg-danger\/10            { background-color: #fef2f2 !important; }
        .bg-info\/10, .bg-info\/15{ background-color: #eff6ff !important; }
        html.dark .bg-amber-50,
        html.dark .bg-amber-900\/20 { background-color: rgba(245,158,11,0.10) !important; }
        html.dark .bg-success\/10,
        html.dark .bg-success\/15 { background-color: rgba(34,197,94,0.12) !important; }
        html.dark .bg-warning\/10 { background-color: rgba(245,158,11,0.12) !important; }
        html.dark .bg-danger\/10  { background-color: rgba(239,68,68,0.12) !important; }
        html.dark .bg-info\/10,
        html.dark .bg-info\/15    { background-color: rgba(59,130,246,0.12) !important; }

        /* Status borders */
        .border-success\/20 { border-color: rgba(34,197,94,0.25) !important; }
        .border-warning\/20 { border-color: rgba(245,158,11,0.25) !important; }
        .border-danger\/20  { border-color: rgba(239,68,68,0.25) !important; }
        .border-info\/20    { border-color: rgba(59,130,246,0.25) !important; }
        .border-primary-400 { border-color: color-mix(in srgb, var(--ob-brand) 50%, transparent) !important; }

        /* Accent icon hues — auto-flip on dark */
        .text-blue-500, .text-blue-600 { color: #2563eb !important; }
        .text-blue-800, .text-blue-900 { color: #1e40af !important; }
        .text-purple-600 { color: #7c3aed !important; }
        .text-purple-900 { color: #5b21b6 !important; }
        .text-amber-500, .text-amber-600 { color: #d97706 !important; }
        .text-amber-700  { color: #b45309 !important; }
        .text-amber-300  { color: #fbbf24 !important; }
        .text-red-500    { color: #dc2626 !important; }
        .text-indigo-500 { color: var(--ob-brand) !important; }
        html.dark .text-blue-500, html.dark .text-blue-600 { color: #60a5fa !important; }
        html.dark .text-blue-800, html.dark .text-blue-900 { color: #93c5fd !important; }
        html.dark .text-purple-600 { color: #c4b5fd !important; }
        html.dark .text-purple-900 { color: #ddd6fe !important; }
        html.dark .text-amber-500, html.dark .text-amber-600 { color: #fbbf24 !important; }
        html.dark .text-amber-700  { color: #fde68a !important; }
        html.dark .text-red-500    { color: #f87171 !important; }
        html.dark .text-indigo-500 { color: var(--ob-brand-soft) !important; }

        /* Hover states */
        .hover\:text-primary-700:hover { color: #4338ca !important; }
        html.dark .hover\:text-primary-700:hover { color: #c7d2fe !important; }
        .hover\:bg-surface:hover { background-color: var(--ob-step-bg) !important; }

        /* Sidebar gradient + divider — theme-aware */
        .ob-sidebar-glow { background: radial-gradient(circle at 30% 50%, rgba(99,102,241,0.06), transparent); }
        .ob-sidebar-divider { background: linear-gradient(to bottom, transparent, var(--ob-divider), transparent); }

        /* Mobile progress bar bg */
        .ob-progress-track { background-color: var(--ob-step-bg); }

        /* Soft right-side glow on the content area */
        .ob-content-glow { background-color: color-mix(in srgb, var(--ob-brand) 5%, transparent); }

        .shadow-sm { box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important; }
        html.dark .shadow-sm { box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important; }
    </style>
</head>
<body class="min-h-screen font-sans antialiased">
    <div class="h-screen flex overflow-hidden relative">

        {{-- Left Side: Branding + Step Indicator --}}
        <div class="hidden lg:flex lg:w-[420px] relative flex-col justify-between p-16 overflow-hidden shrink-0"
             style="background-color: var(--ob-bg-alt)">
            <div class="absolute inset-0 ob-sidebar-glow pointer-events-none"></div>
            <div class="absolute top-0 right-0 w-px h-full ob-sidebar-divider"></div>

            <div class="relative z-10 space-y-12">
                <div class="space-y-6">
                    <a href="{{ url('/') }}" class="inline-flex items-center" aria-label="{{ $__sn }}">
                        <x-brand-logo :name="$__sn" class="text-2xl" />
                    </a>
                    <p class="text-sm max-w-sm font-medium" style="color: var(--ob-text-soft)">
                        Let's set up your workspace in a few simple steps.
                    </p>
                </div>

                {{-- Step indicator --}}
                @php
                    $onboardingSteps = [
                        ['num' => 1, 'label' => 'Workspace'],
                        ['num' => 2, 'label' => 'Email'],
                        ['num' => 3, 'label' => 'AI Training'],
                        ['num' => 4, 'label' => 'Auto-Reply'],
                        ['num' => 5, 'label' => 'Team'],
                    ];
                    $currentOnboardingStep = $currentStep ?? 1;
                @endphp
                <div class="space-y-1 mt-8">
                    @foreach ($onboardingSteps as $step)
                        @php
                            $isCompleted = $currentOnboardingStep > $step['num'];
                            $isActive = $currentOnboardingStep === $step['num'];
                        @endphp
                        <div class="flex items-center gap-4 py-2.5 px-3 rounded-xl transition-all"
                             style="{{ $isActive ? 'background-color: color-mix(in srgb, var(--ob-brand) 10%, transparent);' : '' }}">
                            @if ($isCompleted)
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
                                     style="background-color: color-mix(in srgb, var(--ob-brand) 20%, transparent);">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color: var(--ob-brand)">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                            @elseif ($isActive)
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-lg"
                                     style="background-color: var(--ob-brand); box-shadow: 0 8px 16px -4px color-mix(in srgb, var(--ob-brand) 40%, transparent);">
                                    <span class="text-xs font-bold text-white">{{ $step['num'] }}</span>
                                </div>
                            @else
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 border"
                                     style="background-color: var(--ob-step-bg); border-color: var(--ob-border);">
                                    <span class="text-xs font-medium" style="color: var(--ob-text-mute)">{{ $step['num'] }}</span>
                                </div>
                            @endif
                            <span class="text-[11px] font-bold uppercase tracking-widest"
                                  style="color: {{ $isActive ? 'var(--ob-brand)' : ($isCompleted ? 'var(--ob-text)' : 'var(--ob-text-mute)') }};">
                                {{ $step['label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="relative z-10">
                <p class="text-[10px] font-bold tracking-widest uppercase flex items-center gap-3" style="color: var(--ob-text-mute)">
                    <span class="h-px w-8" style="background-color: var(--ob-divider)"></span>
                    {{ config('app.name') }} Setup Wizard
                </p>
            </div>
        </div>

        {{-- Right Side: Content --}}
        <div class="flex-1 flex flex-col items-center p-4 md:p-6 pt-6 md:pt-8 relative overflow-y-auto"
             style="background-color: var(--ob-bg)">
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-[20%] right-[-10%] w-96 h-96 ob-content-glow blur-[120px] rounded-full"></div>
            </div>

            <div class="w-full max-w-2xl space-y-8 relative z-10">
                {{-- Mobile step indicator --}}
                <div class="lg:hidden mb-6">
                    <div class="flex items-center justify-between text-xs font-bold uppercase tracking-widest mb-3">
                        <span style="color: var(--ob-text-soft)">Step {{ $currentOnboardingStep }} of 5</span>
                        <span style="color: var(--ob-brand)">@yield('step-name', 'Setup')</span>
                    </div>
                    <div class="w-full ob-progress-track rounded-full h-1.5">
                        <div class="h-1.5 rounded-full transition-all duration-500"
                             style="width: {{ ($currentOnboardingStep / 5) * 100 }}%; background-color: var(--ob-brand);"></div>
                    </div>
                </div>

                @yield('content')
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
