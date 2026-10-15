@php
    $__sn = \App\Models\SystemSetting::get('site_name', config('app.name'));
    $__fav = \App\Models\SystemSetting::get('favicon');
    $__logoD = \App\Models\SystemSetting::get('logo_dark');
    $__logoL = \App\Models\SystemSetting::get('logo_light');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="inboxLayout()"
      x-init="init()"
      :class="{ dark: theme === 'dark' }"
      :data-theme="theme"
      data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Inbox' }} — {{ $__sn }}</title>
    @if($__fav)
        <link rel="icon" href="{{ asset('storage/' . $__fav) }}" type="image/png">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <x-brand-theme />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script>
        (function() {
            localStorage.removeItem('inbox-theme-user-set');
            localStorage.removeItem('inbox-theme');
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        body.inbox-page {
            font-family: 'Inter', sans-serif;
            background: #f8fafc !important;
            background-image: none !important;
            color: #1e293b;
        }
        .dark body.inbox-page,
        [data-theme="dark"] body.inbox-page {
            background-color: #0f1117 !important;
            background-image: none !important;
            background: #0f1117 !important;
            color: #d1d5db;
        }
        .hide-scroll::-webkit-scrollbar { display: none; }
        .hide-scroll { -ms-overflow-style: none; scrollbar-width: none; }

        /* Quill editor theme overrides */
        .ql-toolbar.ql-snow { border: none !important; border-bottom: 1px solid #e2e8f0 !important; background: #ffffff; padding: 6px 8px !important; }
        .ql-container.ql-snow { border: none !important; font-family: 'Inter', sans-serif; font-size: 14px; }
        .ql-editor { color: #1e293b; min-height: 80px; padding: 12px 16px !important; }
        .ql-editor.ql-blank::before { color: #94a3b8 !important; font-style: normal !important; }
        .ql-snow .ql-stroke { stroke: #64748b !important; }
        .ql-snow .ql-fill { fill: #64748b !important; }
        .ql-snow .ql-picker { color: #475569 !important; }
        .ql-snow .ql-picker-label { border-color: #e2e8f0 !important; }
        .ql-snow .ql-picker-label:hover, .ql-snow button:hover .ql-stroke { stroke: #0f172a !important; }
        .ql-snow button:hover .ql-fill { fill: #0f172a !important; }
        .ql-snow .ql-picker-options { background: #ffffff !important; border-color: #e2e8f0 !important; }
        .ql-snow .ql-picker-item:hover { color: #0f172a !important; }
        .ql-snow .ql-active .ql-stroke { stroke: #3b82f6 !important; }
        .ql-snow .ql-active .ql-fill { fill: #3b82f6 !important; }
        .ql-snow .ql-active { color: #3b82f6 !important; }
        .ql-snow .ql-tooltip { background: #ffffff !important; border-color: #e2e8f0 !important; color: #1e293b !important; box-shadow: 0 16px 40px rgba(15,23,42,0.14) !important; }
        .ql-snow .ql-tooltip input[type=text] { background: #ffffff !important; border-color: #cbd5e1 !important; color: #1e293b !important; }
        .ql-snow .ql-tooltip a { color: #3b82f6 !important; }
        .ql-snow .ql-picker-label { padding-left: 6px !important; }
        .ql-font-roboto { font-family: 'Roboto', sans-serif; }
        .ql-font-serif { font-family: Georgia, serif; }
        .ql-font-monospace { font-family: 'Fira Code', monospace; }

        .dark .ql-toolbar.ql-snow, [data-theme="dark"] .ql-toolbar.ql-snow { border-bottom-color: #2d3039 !important; background: #11131a; }
        .dark .ql-editor, [data-theme="dark"] .ql-editor { color: #d1d5db; }
        .dark .ql-editor.ql-blank::before, [data-theme="dark"] .ql-editor.ql-blank::before { color: #4b5563 !important; }
        .dark .ql-snow .ql-stroke, [data-theme="dark"] .ql-snow .ql-stroke { stroke: #6b7280 !important; }
        .dark .ql-snow .ql-fill, [data-theme="dark"] .ql-snow .ql-fill { fill: #6b7280 !important; }
        .dark .ql-snow .ql-picker, [data-theme="dark"] .ql-snow .ql-picker { color: #9ca3af !important; }
        .dark .ql-snow .ql-picker-label, [data-theme="dark"] .ql-snow .ql-picker-label { border-color: #2d3039 !important; }
        .dark .ql-snow .ql-picker-label:hover, .dark .ql-snow button:hover .ql-stroke,
        [data-theme="dark"] .ql-snow .ql-picker-label:hover, [data-theme="dark"] .ql-snow button:hover .ql-stroke { stroke: #e5e7eb !important; }
        .dark .ql-snow button:hover .ql-fill, [data-theme="dark"] .ql-snow button:hover .ql-fill { fill: #e5e7eb !important; }
        .dark .ql-snow .ql-picker-options, [data-theme="dark"] .ql-snow .ql-picker-options { background: #1a1d27 !important; border-color: #2d3039 !important; }
        .dark .ql-snow .ql-picker-item:hover, [data-theme="dark"] .ql-snow .ql-picker-item:hover { color: #fff !important; }
        .dark .ql-snow .ql-tooltip, [data-theme="dark"] .ql-snow .ql-tooltip { background: #1a1d27 !important; border-color: #2d3039 !important; color: #d1d5db !important; box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important; }
        .dark .ql-snow .ql-tooltip input[type=text], [data-theme="dark"] .ql-snow .ql-tooltip input[type=text] { background: #0f1117 !important; border-color: #2d3039 !important; color: #d1d5db !important; }

        html[data-theme="light"] .inbox-root { background: #f8fafc; color: #1e293b; }
        html[data-theme="light"] .inbox-root [class~="bg-[#0c0d12]"],
        html[data-theme="light"] .inbox-root [class~="bg-[#0c0d12]/80"],
        html[data-theme="light"] .inbox-root [class~="bg-[#07090e]"] { background-color: #f8fafc !important; }
        html[data-theme="light"] .inbox-root [class~="bg-[#0f1117]"],
        html[data-theme="light"] .inbox-root [class~="bg-[#11131a]"],
        html[data-theme="light"] .inbox-root [class~="bg-[#11131a]/95"],
        html[data-theme="light"] .inbox-root [class~="bg-[#15171e]"],
        html[data-theme="light"] .inbox-root [class~="bg-[#1a1d27]"],
        html[data-theme="light"] .inbox-root [class~="bg-[#1a1d27]/95"],
        html[data-theme="light"] .inbox-root .bg-white\/\[0\.02\],
        html[data-theme="light"] .inbox-root .bg-white\/\[0\.03\],
        html[data-theme="light"] .inbox-root .bg-white\/\[0\.05\],
        html[data-theme="light"] .inbox-root .bg-white\/5,
        html[data-theme="light"] .inbox-root .bg-white\/10 { background-color: #ffffff !important; }
        html[data-theme="light"] .inbox-root .bg-white\/20 { background-color: #e2e8f0 !important; }
        html[data-theme="light"] .inbox-root .bg-gray-700,
        html[data-theme="light"] .inbox-root .bg-gray-500\/10 { background-color: #e2e8f0 !important; }
        html[data-theme="light"] .inbox-root [class~="border-[#2d3039]"],
        html[data-theme="light"] .inbox-root [class~="border-[#2d3039]/60"],
        html[data-theme="light"] .inbox-root .border-white\/10,
        html[data-theme="light"] .inbox-root .border-white\/20,
        html[data-theme="light"] .inbox-root .border-white\/\[0\.05\] { border-color: #e2e8f0 !important; }
        html[data-theme="light"] .inbox-root .text-gray-100 { color: #0f172a !important; }
        html[data-theme="light"] .inbox-root .text-gray-200 { color: #1e293b !important; }
        html[data-theme="light"] .inbox-root .text-gray-300 { color: #334155 !important; }
        html[data-theme="light"] .inbox-root .text-gray-400 { color: #475569 !important; }
        html[data-theme="light"] .inbox-root .text-gray-500,
        html[data-theme="light"] .inbox-root .text-gray-500\/80 { color: #64748b !important; }
        html[data-theme="light"] .inbox-root .text-gray-600 { color: #94a3b8 !important; }
        html[data-theme="light"] .inbox-root .text-gray-700 { color: #cbd5e1 !important; }
        html[data-theme="light"] .inbox-root .text-white,
        html[data-theme="light"] .inbox-root .text-white\/90 { color: #0f172a !important; }
        html[data-theme="light"] .inbox-root .text-white\/80 { color: #334155 !important; }
        html[data-theme="light"] .inbox-root .text-white\/70 { color: #475569 !important; }
        html[data-theme="light"] .inbox-root [class~="bg-[#3b82f6]"],
        html[data-theme="light"] .inbox-root [class~="!bg-[#3b82f6]"] { background-color: #3b82f6 !important; color: #ffffff !important; }
        html[data-theme="light"] .inbox-root [class~="bg-[#3b82f6]"] [class*="text-white"],
        html[data-theme="light"] .inbox-root [class~="!bg-[#3b82f6]"] [class*="text-white"],
        html[data-theme="light"] .inbox-root [class~="bg-[#3b82f6]"][class*="text-white"],
        html[data-theme="light"] .inbox-root [class~="!bg-[#3b82f6]"][class*="text-white"],
        html[data-theme="light"] .inbox-root [class*="from-pink"][class*="text-white"] { color: #ffffff !important; }
        html[data-theme="light"] .inbox-root [class~="bg-[#3b82f6]"] .bg-white\/20,
        html[data-theme="light"] .inbox-root [class~="!bg-[#3b82f6]"] .bg-white\/20,
        html[data-theme="light"] .inbox-root [class~="bg-[#3b82f6]"] .bg-white\/10,
        html[data-theme="light"] .inbox-root [class~="!bg-[#3b82f6]"] .bg-white\/10 { background-color: rgba(255,255,255,0.2) !important; }
        html[data-theme="light"] .inbox-root .hover\:bg-white\/5:hover,
        html[data-theme="light"] .inbox-root .hover\:bg-white\/10:hover,
        html[data-theme="light"] .inbox-root .hover\:bg-white\/\[0\.02\]:hover,
        html[data-theme="light"] .inbox-root .hover\:bg-white\/\[0\.04\]:hover,
        html[data-theme="light"] .inbox-root .hover\:bg-white\/\[0\.05\]:hover { background-color: #f1f5f9 !important; }
        html[data-theme="light"] .inbox-root .hover\:text-gray-200:hover,
        html[data-theme="light"] .inbox-root .hover\:text-gray-300:hover,
        html[data-theme="light"] .inbox-root .hover\:text-white:hover { color: #0f172a !important; }
        html[data-theme="light"] .inbox-root [class*="shadow-[0_15px_40px"],
        html[data-theme="light"] .inbox-root [class*="shadow-[0_10px_30px"] { box-shadow: 0 18px 45px rgba(15,23,42,0.14) !important; }
        html[data-theme="light"] .inbox-root input,
        html[data-theme="light"] .inbox-root textarea,
        html[data-theme="light"] .inbox-root select { background-color: #ffffff; color: #1e293b; border-color: #cbd5e1; color-scheme: light; }
        html[data-theme="light"] .inbox-root input::placeholder,
        html[data-theme="light"] .inbox-root textarea::placeholder { color: #94a3b8; }
        html .inbox-root .mail-avatar-initial { background:#dbeafe!important; color:#1e3a8a!important; font-size:18px!important; font-weight:700!important; }
    </style>
</head>
<body class="inbox-page h-screen w-screen overflow-hidden flex flex-col bg-surface text-ink antialiased font-sans">

    {{-- Full-screen inbox container (no header) --}}
    <div class="h-screen flex inbox-root">
        {{ $slot }}
    </div>

    @livewireScripts
    {!! \App\Models\SystemSetting::get('footer_code', '') !!}
    <script>
        function inboxLayout() {
            return {
                theme: localStorage.getItem('theme') || 'light',

                init() {
                    this.applyTheme();
                },

                applyTheme() {
                    document.documentElement.classList.toggle('dark', this.theme === 'dark');
                    document.documentElement.setAttribute('data-theme', this.theme);
                },
            };
        }
    </script>
    @include('partials.timezone-sync')
</body>
</html>
