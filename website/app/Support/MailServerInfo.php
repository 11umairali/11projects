<?php

namespace App\Support;

/**
 * The details a customer needs to use their mailbox in another app or website.
 * Shared by the website page (Settings → Mail Server) and the mobile API (email-accounts/mail-settings).
 * NEVER contains a password.
 */
class MailServerInfo
{
    public static function for($user): array
    {
        $name = (string) config('app.name', 'DahiMail');
        $out = ['available' => false, 'app_name' => $name, 'email' => (string) $user->email, 'username' => (string) $user->email];

        // Only accounts that own a mailbox on this platform have server settings to show.
        if (empty($user->username)) {
            $out['message'] = 'Your sign-in address (' . $user->email . ') is an outside email address, not a ' . $name . ' mailbox. Use the settings of that address’s own provider.';
            return $out;
        }

        $imap = config('mailserver.imap');
        $smtp = config('mailserver.smtp');
        $pop = config('mailserver.pop3');

        $out['available'] = true;
        $out['imap'] = ['host' => $imap['host'], 'port' => $imap['port'], 'security' => $imap['security'], 'alt_port' => $imap['alt_port'], 'alt_security' => $imap['alt_security']];
        $out['smtp'] = ['host' => $smtp['host'], 'port' => $smtp['port'], 'security' => $smtp['security'], 'alt_port' => $smtp['alt_port'], 'alt_security' => $smtp['alt_security']];
        $out['pop3'] = $pop['enabled'] ? ['host' => $pop['host'], 'port' => $pop['port'], 'security' => $pop['security']] : null;
        $out['webmail_url'] = (string) config('mailserver.webmail_url', '');
        $out['password_hint'] = 'Your ' . $name . ' password (the one you sign in with).';
        return $out;
    }
}
