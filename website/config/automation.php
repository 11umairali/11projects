<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automation Mode
    |--------------------------------------------------------------------------
    |
    | Controls how background automation tasks are processed.
    |
    | 'middleware' - Process jobs inline via HTTP middleware (shared hosting,
    |                no cron/supervisor needed — plug and play)
    | 'queue'      - Use Laravel's queue worker (requires supervisor or cron)
    | 'scheduler'  - Use Laravel's scheduler (requires cron entry)
    |
    */
    'mode' => env('AUTOMATION_MODE', 'middleware'),

    /*
    |--------------------------------------------------------------------------
    | Middleware Settings
    |--------------------------------------------------------------------------
    |
    | When mode is 'middleware', these settings control inline processing.
    |
    */
    'middleware' => [
        // Enable/disable the automation middleware entirely
        'enabled' => env('AUTOMATION_MIDDLEWARE_ENABLED', true),

        // Maximum number of queue jobs to process per request
        'max_jobs_per_request' => env('AUTOMATION_MAX_JOBS', 3),

        // Maximum seconds spent processing jobs per request
        'max_seconds_per_request' => env('AUTOMATION_MAX_SECONDS', 5),

        // Minimum seconds between automation runs (throttle)
        'throttle_seconds' => env('AUTOMATION_THROTTLE', 60),

        // Process jobs only on specific HTTP methods (to avoid slowing GET requests)
        // Set to ['*'] to run on all methods
        'allowed_methods' => ['GET'],

        // Skip automation on these URL patterns
        'skip_paths' => [
            'api/*',
            'livewire/*',
            'admin/*',
            '_debugbar/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automation Features
    |--------------------------------------------------------------------------
    |
    | Toggle individual automation features on/off.
    |
    */
    'features' => [
        // Sync emails from connected accounts
        'email_sync' => env('AUTOMATION_EMAIL_SYNC', true),
        'email_sync_interval_minutes' => env('AUTOMATION_EMAIL_SYNC_INTERVAL', 3),

        // Refresh OAuth tokens before they expire
        'oauth_refresh' => env('AUTOMATION_OAUTH_REFRESH', true),
        'oauth_refresh_minutes_before' => env('AUTOMATION_OAUTH_REFRESH_BEFORE', 15),

        // Process pending queue jobs inline
        'queue_processing' => env('AUTOMATION_QUEUE_PROCESSING', true),

        // Process drip campaign steps
        'drip_processing' => env('AUTOMATION_DRIP_PROCESSING', true),

        // Send scheduled emails/campaigns
        'scheduled_emails' => env('AUTOMATION_SCHEDULED_EMAILS', true),
    ],

];
