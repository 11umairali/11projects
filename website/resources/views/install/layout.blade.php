<!DOCTYPE html>
<html lang="en" class="scroll-smooth dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Install')) - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        primary: { DEFAULT:'#6366f1', foreground:'#ffffff', 50:'#eef2ff', 100:'#e0e7ff', 200:'#c7d2fe', 500:'#6366f1', 600:'#4f46e5', 700:'#4338ca', 900:'#312e81' },
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', system-ui, sans-serif; }
        /* Dark mode overrides for installer — matches app theme */
        /* Inputs */
        input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="url"], select, textarea {
            background-color: #1a1d27 !important; color: #d1d5db !important; border-color: #2d3039 !important;
        }
        input::placeholder, textarea::placeholder { color: #4b5563 !important; }
        input:focus, select:focus, textarea:focus { border-color: #6366f1 !important; box-shadow: 0 0 0 2px rgba(99,102,241,0.2) !important; }
        label { color: #9ca3af !important; }
        /* Backgrounds */
        .bg-white { background-color: #0f1117 !important; }
        .bg-gray-50, .bg-gray-100, [class*="bg-gray-50/"], [class*="bg-gray-100/"] { background-color: #15171e !important; }
        .bg-gray-200, [class*="bg-gray-200/"] { background-color: #1a1d27 !important; }
        .bg-green-50, .bg-emerald-50 { background-color: rgba(16,185,129,0.1) !important; }
        .bg-red-50 { background-color: rgba(239,68,68,0.1) !important; }
        .bg-indigo-50 { background-color: rgba(99,102,241,0.1) !important; }
        /* Text */
        .text-gray-900, .text-gray-800 { color: #e5e7eb !important; }
        .text-gray-700 { color: #d1d5db !important; }
        .text-gray-600 { color: #9ca3af !important; }
        .text-gray-500 { color: #6b7280 !important; }
        .text-green-700, .text-green-600 { color: #10b981 !important; }
        .text-red-700, .text-red-600 { color: #ef4444 !important; }
        /* Borders */
        .border-gray-200, .border-gray-300, [class*="border-gray-200/"], [class*="border-gray-300/"] { border-color: #2d3039 !important; }
        .border-green-200, .border-green-300, .border-emerald-300 { border-color: rgba(16,185,129,0.3) !important; }
        .border-red-200, .border-red-300 { border-color: rgba(239,68,68,0.3) !important; }
        .border-indigo-200, .border-indigo-300 { border-color: rgba(99,102,241,0.3) !important; }
        /* Rings */
        .ring-gray-300 { --tw-ring-color: #2d3039 !important; }
        /* Elements */
        details[style] { background: #15171e !important; border-color: #2d3039 !important; }
        details summary { color: #d1d5db !important; }
        pre { background: #1a1d27 !important; color: #d1d5db !important; border: 1px solid #2d3039; border-radius: 8px; padding: 12px; }
        code { background: #1a1d27 !important; color: #a78bfa !important; }
        blockquote { background: #15171e !important; border-left: 3px solid #6366f1 !important; color: #d1d5db !important; padding: 12px 16px; border-radius: 8px; }
        /* Cards & panels */
        [class*="rounded"] { border-color: #2d3039; }
        .shadow-sm, .shadow-md, .shadow-lg { --tw-shadow-color: rgba(0,0,0,0.3) !important; }
        /* Tables */
        table { border-color: #2d3039 !important; }
        th { background-color: #15171e !important; color: #9ca3af !important; border-color: #2d3039 !important; }
        td { border-color: #2d3039 !important; color: #d1d5db !important; }
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0f1117; }
        ::-webkit-scrollbar-thumb { background: #2d3039; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #3d4049; }
    </style>
</head>
<body class="min-h-screen bg-[#0f1117] font-sans antialiased text-gray-200">
    <div class="h-screen flex overflow-hidden relative">

        {{-- Left Side: Branding + Step Indicator --}}
        <div class="hidden lg:flex lg:w-[420px] relative flex-col justify-between p-16 overflow-hidden bg-[#15171e]/50 shrink-0">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_50%,rgba(99,102,241,0.06),transparent)] pointer-events-none"></div>
            <div class="absolute top-0 right-0 w-px h-full bg-gradient-to-b from-transparent via-[#2d3039] to-transparent"></div>

            <div class="relative z-10 space-y-12">
                <div class="space-y-6">
                    <h2 class="text-4xl font-bold leading-tight tracking-tight text-gray-100">
                        {{ config('app.name', 'MailTrixy') }}
                    </h2>
                    <p class="text-gray-500 text-sm max-w-sm font-medium">
                        {{ __('Setup wizard will guide you through the installation process in a few simple steps.') }}
                    </p>
                </div>

                @include('install.partials.steps', ['currentStep' => $currentStep ?? session('install_step', 1)])
        </div>

            <div class="relative z-10">
                <p class="text-gray-500/40 text-[10px] font-bold tracking-widest uppercase flex items-center gap-3">
                    <span class="h-px w-8 bg-[#2d3039]"></span>
                    {{ config('app.name') }} {{ __('Installation Wizard') }}
                </p>
            </div>
        </div>

        {{-- Right Side: Form Content --}}
        <div class="flex-1 flex flex-col justify-center items-center p-6 md:p-10 bg-[#0f1117] relative overflow-hidden">
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute top-[20%] right-[-10%] w-96 h-96 bg-primary/5 blur-[120px] rounded-full"></div>
            </div>

            <div class="w-full max-w-2xl space-y-8 relative z-10">
                {{-- Mobile step indicator --}}
                <div class="lg:hidden mb-6">
                    <div class="flex items-center justify-between text-xs text-gray-500 font-bold uppercase tracking-widest mb-3">
                        <span>{{ __('Step') }} {{ $currentStep ?? session('install_step', 1) }} {{ __('of') }} 6</span>
                        <span class="text-primary">@yield('step-name', __('Welcome'))</span>
                    </div>
                    <div class="w-full bg-[#2d3039] rounded-full h-1.5">
                        <div class="bg-primary h-1.5 rounded-full transition-all duration-500" style="width: {{ (($currentStep ?? session('install_step', 1)) / 6) * 100 }}%"></div>
                    </div>
                </div>

                <div class="bg-[#15171e] border border-[#2d3039] rounded-2xl p-6 sm:p-10 shadow-lg">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
