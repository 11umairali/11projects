<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Offline') }} — {{ config('app.name') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', system-ui, sans-serif; background: #0f1117; color: #e5e7eb; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; text-align: center; }
        .container { max-width: 400px; }
        .icon { width: 80px; height: 80px; margin: 0 auto 1.5rem; background: rgba(99,102,241,0.15); border-radius: 1.25rem; display: flex; align-items: center; justify-content: center; }
        .icon svg { width: 40px; height: 40px; color: #6366f1; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
        p { color: #9ca3af; line-height: 1.6; margin-bottom: 1.5rem; }
        button { background: #6366f1; color: white; border: none; padding: 0.75rem 2rem; border-radius: 0.75rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m-2.829-2.829a5 5 0 000-7.07m-4.243 9.9a9 9 0 01-4.243-2.83M9.88 9.88a3 3 0 014.24 0"/></svg>
        </div>
        <h1>{{ __("You're offline") }}</h1>
        <p>{{ __("It looks like you've lost your internet connection.") }} {{ config('app.name') }} {{ __('needs an active connection to work. Please check your network and try again.') }}</p>
        <button onclick="location.reload()">{{ __('Try Again') }}</button>
    </div>
</body>
</html>
