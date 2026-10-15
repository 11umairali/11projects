<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — AI-Powered Email Automation</title>
    <meta name="description" content="All-in-one AI email platform. Unified inbox, smart replies, campaigns, CRM, pipeline, 5-channel messaging. Self-hosted. One-time $199. Replace $170/month in tools.">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Open Graph -->
    <meta property="og:title" content="{{ config('app.name') }} — AI Email Automation & CRM">
    <meta property="og:description" content="Replace HelpScout + Mailchimp + HubSpot + Intercom. Self-hosted. $199 one-time.">
    <meta property="og:image" content="{{ asset('images/og-image.png') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">

    <!-- Favicon -->
    @php
        $__landFav = \App\Models\SystemSetting::get('favicon');
        $__pwaOn = \App\Models\SystemSetting::get('pwa_enabled', 'false') === 'true';
    @endphp
    @if($__landFav)
    <link rel="icon" href="{{ asset('storage/' . $__landFav) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('storage/' . $__landFav) }}">
    @else
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/dahimail-logo.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @endif
    @if($__pwaOn)
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <meta name="theme-color" content="{{ \App\Models\SystemSetting::get('pwa_theme_color', '#6366f1') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    @else
    <meta name="theme-color" content="#6C3CE7">
    @endif

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <x-brand-theme />
    @vite(['resources/css/landing.css', 'resources/js/landing-app.js'])

    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="landing-body">
    @yield('content')

    @if($__pwaOn ?? false)
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ asset("sw.js") }}').catch(function() {});
    }
    </script>
    @endif
</body>
</html>
