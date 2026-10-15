<?php

namespace App\Services\Mail;

use App\Models\SystemSetting;

/**
 * Applies the values saved in Admin > Settings > Email to Laravel's runtime
 * mail configuration.
 *
 * Why this exists: those settings were saved to the database but only the
 * "Send test email" button ever read them. Every real system email (welcome,
 * password reset, invites, notifications) used .env instead, so changing the
 * SMTP settings in the admin panel had no effect on real mail — and with
 * MAIL_MAILER=log the test button "succeeded" while nothing was delivered.
 *
 * Rules:
 *  - Nothing saved (no driver, no host)  -> .env stays in charge, untouched.
 *  - Driver "log" / "sendmail"           -> that mailer becomes the default.
 *  - Driver "smtp" (or unset) + a host   -> the smtp mailer is configured from
 *    the saved values and becomes the default.
 *
 * Encryption note: this Laravel version ignores an "encryption" key; the
 * connection type comes from `scheme`. So we translate the dropdown:
 *    ssl  (or port 465) -> smtps  (implicit TLS)
 *    tls                -> smtp   (STARTTLS whenever the server offers it)
 *    none               -> smtp   with auto_tls disabled
 */
class AdminMailConfig
{
    private const KEYS = [
        'mail_driver', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
        'smtp_encryption', 'smtp_from_address', 'smtp_from_name',
        'mail_from_email', 'mail_from_name',
    ];

    /**
     * Pure mapping (no I/O) so it can be tested in isolation.
     *
     * @param  array<string,mixed>  $s  saved settings
     * @return array{default: ?string, config: array<string,mixed>}
     */
    public static function resolve(array $s): array
    {
        $val = static fn (string $k): string => trim((string) ($s[$k] ?? ''));

        $driver = strtolower($val('mail_driver'));
        $host = $val('smtp_host');
        $config = [];

        $fromAddress = $val('smtp_from_address') ?: $val('mail_from_email');
        $fromName = $val('smtp_from_name') ?: $val('mail_from_name');

        if ($fromAddress !== '') {
            $config['mail.from.address'] = $fromAddress;
        }
        if ($fromName !== '') {
            $config['mail.from.name'] = $fromName;
        }

        $default = null;

        if ($driver === 'log' || $driver === 'sendmail') {
            $default = $driver;
        } elseif (in_array($driver, ['', 'smtp'], true) && $host !== '') {
            $port = (int) $val('smtp_port') ?: 587;
            $encryption = strtolower($val('smtp_encryption') ?: 'tls');
            $loopback = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);

            // Individual keys, so the rest of config('mail.mailers.smtp')
            // (e.g. local_domain) is preserved.
            $config['mail.mailers.smtp.scheme'] = ($encryption === 'ssl' || $port === 465) ? 'smtps' : 'smtp';
            $config['mail.mailers.smtp.url'] = null;            // a stray MAIL_URL must not override the panel
            $config['mail.mailers.smtp.host'] = $host;
            $config['mail.mailers.smtp.port'] = $port;
            $config['mail.mailers.smtp.username'] = $val('smtp_username') ?: null;
            $config['mail.mailers.smtp.password'] = $val('smtp_password') ?: null;
            $config['mail.mailers.smtp.timeout'] = 30;
            $config['mail.mailers.smtp.auto_tls'] = $encryption !== 'none';
            // The server's certificate is issued for its public hostname, so
            // verifying it when connecting to 127.0.0.1 always fails.
            $config['mail.mailers.smtp.verify_peer'] = ! $loopback;

            $default = 'smtp';
        }

        return ['default' => $default, 'config' => $config];
    }

    /**
     * Read the saved settings and apply them. Returns the mailer that is now
     * the default when the panel took control, or null when .env is still in charge.
     */
    public static function apply(): ?string
    {
        $settings = [];
        foreach (self::KEYS as $key) {
            $settings[$key] = SystemSetting::get($key, '');
        }

        $resolved = self::resolve($settings);

        if ($resolved['config']) {
            config($resolved['config']);
        }
        if ($resolved['default']) {
            config(['mail.default' => $resolved['default']]);
        }

        return $resolved['default'];
    }
}
