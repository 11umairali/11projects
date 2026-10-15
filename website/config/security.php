<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted IPs
    |--------------------------------------------------------------------------
    |
    | IPs listed here bypass DDoS protection entirely. Useful for your own
    | monitoring / health-check services and CI runners. Comma-separated
    | in the env var.  CIDR notation is NOT supported — list individual IPs.
    |
    */
    'trusted_ips' => array_filter(
        array_map('trim', explode(',', env('SECURITY_TRUSTED_IPS', '')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Trust Proxy
    |--------------------------------------------------------------------------
    |
    | When true the middleware will use $request->ip() (which respects
    | Laravel's trusted-proxy configuration) instead of REMOTE_ADDR.
    | Only enable this if the app is behind a reverse proxy whose IP
    | is listed in config/trustedproxy.php (or Laravel's TrustProxies
    | middleware).  Blindly trusting X-Forwarded-For allows IP spoofing.
    |
    */
    'trust_proxy' => (bool) env('SECURITY_TRUST_PROXY', false),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits — DDoS Protection
    |--------------------------------------------------------------------------
    */
    'max_requests_per_minute'     => (int) env('SECURITY_MAX_RPM', 200),
    'api_max_requests_per_minute' => (int) env('SECURITY_API_MAX_RPM', 50),

    // Number of unique endpoints an IP may hit inside scanner_window_seconds
    // before being flagged as a vulnerability scanner and banned.
    'scanner_threshold'        => (int) env('SECURITY_SCANNER_THRESHOLD', 10),
    'scanner_window_seconds'   => 5,
    'scanner_ban_seconds'      => 300,
    'rate_limit_ban_seconds'   => 60,

    /*
    |--------------------------------------------------------------------------
    | Honeypot
    |--------------------------------------------------------------------------
    */
    'honeypot_enabled'           => (bool) env('SECURITY_HONEYPOT', true),
    'honeypot_min_time_seconds'  => 2,

    /*
    |--------------------------------------------------------------------------
    | Blocked User-Agent Substrings
    |--------------------------------------------------------------------------
    |
    | Requests whose User-Agent header contains any of these substrings
    | (case-insensitive) are rejected by ApiSecurityMiddleware.
    | These are well-known automated scanner tool signatures.
    |
    */
    'blocked_user_agents' => [
        'sqlmap',
        'nikto',
        'nessus',
        'openvas',
        'w3af',
        'acunetix',
        'netsparker',
        'burpsuite',
        'zap',
        'dirbuster',
        'gobuster',
        'ffuf',
        'wfuzz',
        'nuclei',
        'masscan',
        'httprobe',
        'subjack',
        'amass',
        'subfinder',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Request Body Size (API)
    |--------------------------------------------------------------------------
    |
    | API requests with Content-Length exceeding this value (bytes) are
    | rejected before the framework parses the body.  10 MB default.
    |
    */
    'api_max_body_bytes' => (int) env('SECURITY_API_MAX_BODY', 10 * 1024 * 1024),

];
