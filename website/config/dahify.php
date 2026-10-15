<?php

return [

    /*
    | The one domain every account lives on. Users choose only the part
    | before the "@". Kept as "dahify.domain" (not renamed) so the ported
    | Dahify classes (MailDomains, CyberPanelMailbox, ImapMailbox, ...)
    | work unmodified.
    */
    'domain' => env('DAHIFY_MAIL_DOMAIN', 'dahimail.com'),

    /*
    | When true (default), a workspace + the built-in mailbox are created
    | automatically at registration, and new users go straight to
    | /dashboard after the recovery-phrase screen -- skipping the
    | onboarding wizard's "create workspace" step (step 1).
    |
    | Set DAHIFY_AUTO_PROVISION_WORKSPACE=false in .env to turn this back
    | off: new users will be sent to /onboarding/step-1 as before, with no
    | other code changes needed.
    */
    'auto_provision_workspace' => (bool) env('DAHIFY_AUTO_PROVISION_WORKSPACE', true),

    /*
    | Domains this service used before (comma separated). People can still
    | sign in with an old address.
    */
    'old_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('DAHIFY_OLD_DOMAINS', ''))))),

    /*
    | CyberPanel stores mailboxes in cyberpanel.e_users. Postfix and Dovecot
    | read that table directly, so inserting a row there is what actually
    | creates the mailbox. See config/database.php for the "cyberpanel"
    | connection this points at.
    */
    'cyberpanel' => [
        'connection' => env('CYBERPANEL_DB_CONNECTION', 'cyberpanel'),
        'vmail_root' => env('CYBERPANEL_VMAIL_ROOT', '/home/vmail'),
        'bcrypt_cost' => (int) env('CYBERPANEL_BCRYPT_COST', 12),
    ],

    'username' => [
        'min' => 3,
        'max' => 30,
        // Starts and ends with a letter or digit; ".", "_" and "-" allowed
        // in between, never two in a row.
        'pattern' => '/^[a-z0-9](?:[a-z0-9]|[._-](?=[a-z0-9]))*$/',
        'reserved' => [
            'abuse', 'admin', 'administrator', 'billing', 'contact', 'daemon',
            'dmarc', 'help', 'helpdesk', 'hostmaster', 'info', 'mailer-daemon',
            'marketing', 'no-reply', 'noreply', 'noc', 'postmaster', 'privacy',
            'root', 'sales', 'security', 'staff', 'support', 'sysadmin',
            'system', 'team', 'webmaster', 'www', 'dahify', 'dahimail',
            'official', 'moderator', 'legal', 'ssl-admin', 'mail', 'ftp', 'test',
        ],
    ],

    'signup' => [
        'per_ip_per_day' => (int) env('DAHIFY_SIGNUPS_PER_IP_PER_DAY', 3),
        'per_hour' => (int) env('DAHIFY_SIGNUPS_PER_HOUR', 30),
        'min_seconds' => (int) env('DAHIFY_SIGNUP_MIN_SECONDS', 3),
    ],

    'recovery' => [
        'words' => 12,
    ],

    /*
    | How the app reaches the user's mailbox on the same server. The
    | certificate is issued for the public hostname, not 127.0.0.1, so
    | verification is off for these loopback connections.
    */
    'imap' => [
        'host' => env('MAIL_IMAP_HOST', '127.0.0.1'),
        'port' => (int) env('MAIL_IMAP_PORT', 993),
        'encryption' => env('MAIL_IMAP_ENCRYPTION', 'ssl'), // ssl, starttls or none
        'validate_cert' => (bool) env('MAIL_IMAP_VALIDATE_CERT', false),
        'timeout' => 15,
    ],

    'smtp' => [
        'host' => env('MAIL_SUBMISSION_HOST', '127.0.0.1'),
        'port' => (int) env('MAIL_SUBMISSION_PORT', 587),
        'implicit_tls' => (bool) env('MAIL_SUBMISSION_IMPLICIT_TLS', false),
        'verify_peer' => (bool) env('MAIL_SUBMISSION_VERIFY_PEER', false),
    ],

    'mail' => [
        'per_page' => 25,
        'max_recipients' => 50,
        'max_attachment_mb' => 10,
    ],

    'sending' => [
        'new_account_daily' => (int) env('DAHIFY_SEND_NEW_DAILY', 20),
        'established_daily' => (int) env('DAHIFY_SEND_ESTABLISHED_DAILY', 100),
        'trusted_daily' => (int) env('DAHIFY_SEND_TRUSTED_DAILY', 200),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    /*
    | Teach the server's spam filter when users click Report spam /
    | Not spam: "none", "rspamd" or "spamassassin". Defaults to "none",
    | which SpamLearner treats as disabled — safe to leave as-is unless
    | you're running rspamd/spamassassin on the mail server.
    */
    'spam_learning' => [
        'driver' => env('DAHIFY_SPAM_LEARNER', 'none'),
        'rspamd_url' => env('RSPAMD_CONTROLLER_URL', 'http://127.0.0.1:11334'),
        'rspamd_password' => env('RSPAMD_CONTROLLER_PASSWORD'),
        'sa_learn' => env('SA_LEARN_PATH', '/usr/bin/sa-learn'),
        'sa_learn_args' => env('SA_LEARN_ARGS', ''),
    ],

    'quota_mb' => (int) env('DAHIFY_QUOTA_MB', 1024),
];