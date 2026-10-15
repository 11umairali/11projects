<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // IMPORTANT: api/widget/* is intentionally excluded — those routes are
    // handled by the WidgetCors middleware (registered to run BEFORE this
    // one) which reflects the request's Origin back. If api/widget/* were
    // listed, Laravel's HandleCors would set Allow-Origin to APP_URL and
    // the chat widget would break on every external customer site with:
    //   "Access-Control-Allow-Origin has a value '<your-crm>' that is
    //    not equal to the supplied origin"
    // Instead we list only the inbox/auth/main API surface here.
    'paths' => [
        'api/v1/*',
        'api/inbox/*',
        'api/notifications/*',
        'api/temp-mail/*',
        'api/webhooks/*',
        'api/uploads/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // SECURITY: Never use ['*'] — explicitly list allowed origins only.
    // Set FRONTEND_URL in .env if the SPA/mobile app lives on a separate domain.
    // Specific origins for main app API routes
    'allowed_origins' => array_values(array_filter(
        array_map(
            fn ($origin) => trim((string) $origin),
            [
                env('APP_URL', 'http://localhost'),
                env('FRONTEND_URL'),
            ]
        ),
        fn ($origin) => $origin !== '' && $origin !== '*',
    )),

    // Widget routes (api/widget/*) use their own CORS middleware; no wildcard needed here.
    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'X-CSRF-TOKEN',
        'X-Request-Id',
    ],

    'exposed_headers' => [
        'X-Request-Id',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],

    'max_age' => 7200,

    'supports_credentials' => false,

];
