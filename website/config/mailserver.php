<?php

/*
|--------------------------------------------------------------------------
| PUBLIC mail-server settings (what a customer types into Gmail, Outlook, Thunderbird …)
|--------------------------------------------------------------------------
| NOT the internal 127.0.0.1 connection the website uses (config/dahify.php). Defaults follow the usual CyberPanel layout
| (mail.<your-domain>). Change them in the website .env only if your mail host is named differently:
|
|   MAIL_PUBLIC_HOST=mail.example.com        (used for IMAP, SMTP and POP3 unless overridden below)
|   MAIL_PUBLIC_IMAP_HOST=…   MAIL_PUBLIC_SMTP_HOST=…   MAIL_PUBLIC_POP3_HOST=…
|   MAIL_PUBLIC_POP3=true|false              (show POP3 as well)
|   MAIL_WEBMAIL_URL=https://mail.example.com/   (shows an "Open webmail" button; leave empty to hide)
*/
$domain = env('DAHIFY_MAIL_DOMAIN', 'dahimail.com');
$host = env('MAIL_PUBLIC_HOST', 'mail.' . $domain);

return [
    'imap' => [
        'host' => env('MAIL_PUBLIC_IMAP_HOST', $host),
        'port' => (int) env('MAIL_PUBLIC_IMAP_PORT', 993),
        'security' => 'SSL/TLS',
        'alt_port' => 143,
        'alt_security' => 'STARTTLS',
    ],
    'smtp' => [
        'host' => env('MAIL_PUBLIC_SMTP_HOST', $host),
        'port' => (int) env('MAIL_PUBLIC_SMTP_PORT', 465),
        'security' => 'SSL/TLS',
        'alt_port' => 587,
        'alt_security' => 'STARTTLS',
    ],
    'pop3' => [
        'enabled' => (bool) env('MAIL_PUBLIC_POP3', true),
        'host' => env('MAIL_PUBLIC_POP3_HOST', $host),
        'port' => (int) env('MAIL_PUBLIC_POP3_PORT', 995),
        'security' => 'SSL/TLS',
    ],
    'webmail_url' => env('MAIL_WEBMAIL_URL', ''),
];
