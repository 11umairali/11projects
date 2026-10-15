<?php

/**
 * Third Party Service Credentials
 *
 * Priority: system_settings (admin panel) → .env fallback
 * Admin enters OAuth/API keys in Admin > Settings > Integrations.
 * These are stored encrypted in the system_settings table.
 * The s() helper reads from system_settings with env() as fallback.
 */

// Helper to read from system_settings with .env fallback
// Cannot use SystemSetting model here (config loads before app boots),
// so we read from env() only. The AppServiceProvider boots() method
// will override these with system_settings values at runtime.

return [
    'realtime' => ['ws_host' => env('PUSHER_WS_HOST'), 'ws_port' => env('PUSHER_WS_PORT', 443), 'ws_scheme' => env('PUSHER_WS_SCHEME', 'https')],

    /*
    |--------------------------------------------------------------------------
    | Channel Test Mode
    |--------------------------------------------------------------------------
    |
    | When enabled (CHANNEL_TEST_MODE=1), all non-email channels (SMS, WhatsApp,
    | Telegram, Slack) bypass actual API calls and mark messages as sent
    | immediately. Messages appear in inbox/conversations as if delivered.
    | Email channel is NOT affected — it always uses the real send path.
    |
    */

    'channel_test_mode' => env('CHANNEL_TEST_MODE', false),

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Social Login
    |--------------------------------------------------------------------------
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    */

    'slack' => [
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'signing_secret' => env('SLACK_SIGNING_SECRET'),
        'redirect' => env('SLACK_REDIRECT_URI'),
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Salesforce CRM
    |--------------------------------------------------------------------------
    */

    'salesforce' => [
        'client_id' => env('SALESFORCE_CLIENT_ID'),
        'client_secret' => env('SALESFORCE_CLIENT_SECRET'),
        'redirect' => env('SALESFORCE_REDIRECT_URI'),
        'instance_url' => env('SALESFORCE_INSTANCE_URL', 'https://login.salesforce.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Calendar
    |--------------------------------------------------------------------------
    */

    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Payments
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pusher (Live Chat Broadcasting)
    |--------------------------------------------------------------------------
    */

    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp / Twilio / Telegram — Per-workspace (user settings)
    |--------------------------------------------------------------------------
    */

    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HubSpot CRM — Per-workspace (user settings)
    |--------------------------------------------------------------------------
    */

    'hubspot' => [
        'api_key' => env('HUBSPOT_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider Services — Per-workspace (user settings)
    |--------------------------------------------------------------------------
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'fcm' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        // Web push (browsers) – Firebase console → Project settings → Cloud Messaging → Web configuration → "Web Push certificates" key pair
        'web_vapid_key' => env('FIREBASE_WEB_VAPID_KEY'),
        'web_config' => [
            'apiKey' => env('FIREBASE_WEB_API_KEY'), 'authDomain' => env('FIREBASE_WEB_AUTH_DOMAIN'), 'projectId' => env('FIREBASE_WEB_PROJECT_ID'),
            'messagingSenderId' => env('FIREBASE_WEB_SENDER_ID'), 'appId' => env('FIREBASE_WEB_APP_ID'),
        ],
    ],

    // iPhone "real phone call" ringing (PushKit VoIP) – see ApnsVoip.php
    'apns' => [
        'key_path'  => env('APNS_KEY_PATH'),
        'key_id'    => env('APNS_KEY_ID'),
        'team_id'   => env('APNS_TEAM_ID'),
        'bundle_id' => env('APNS_BUNDLE_ID', 'com.dahify.dahimail'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Database (Pinecone)
    |--------------------------------------------------------------------------
    */

    'pinecone' => [
        'api_key' => env('PINECONE_API_KEY'),
        'host' => env('PINECONE_HOST'),
    ],

];
