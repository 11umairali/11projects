<?php

namespace App\Services;

use App\Mailbox\MailboxCredentials;
use App\Models\EmailAccount;
use App\Models\User;
use App\Models\Workspace;

/**
 * Every @dahimail.com user already has a real mailbox (see AccountCreator).
 * The SaaS side's mail features (inbox, campaigns, AI auto-reply, ...) all
 * read/write through the `email_accounts` table, which normally holds
 * IMAP/SMTP credentials a user typed in for an external inbox (Gmail,
 * Outlook, a company mail server).
 *
 * For dahimail.com users there is nothing to type in: their built-in
 * mailbox lives on this server's own Postfix/Dovecot. So instead of
 * showing an "add email account" screen, we insert that EmailAccount row
 * ourselves, pointed at the internal mail server, the moment the user
 * has a workspace to attach it to (an EmailAccount always belongs to a
 * workspace — see the email_accounts migration).
 *
 * Call this:
 *  - right after a new workspace is created during onboarding (the user
 *    becomes its owner), and
 *  - right after a user accepts an invite into an existing workspace.
 *
 * It's safe to call more than once — it no-ops if the account already
 * exists for that user+workspace pair.
 */
class EmailAccountProvisioner
{
    public function __construct(private readonly MailboxCredentials $credentials) {}

    /**
     * Reads the user's mailbox password from the same encrypted, per-session
     * store the webmail engine itself uses (set on every successful login
     * and right after registration — see FortifyServiceProvider and
     * CreateNewUser). Returns null (and does nothing) if it isn't available
     * — e.g. this method is called from a queued job with no session. In
     * that case, call it again from the next request in that user's own
     * session (the login flow already does this — see LoginResponse).
     */
    public function ensure(User $user, Workspace $workspace): ?EmailAccount
    {
        // Not a dahimail.com identity (e.g. the pre-existing admin account
        // that still uses a real Gmail address) — nothing to provision.
        if (! $user->username) {
            return null;
        }

        $existing = EmailAccount::withTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('email', $user->email)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        $password = $this->credentials->password();
        if ($password === null) {
            return null;
        }

        $imap = config('dahify.imap');
        $smtp = config('dahify.smtp');

        $hasOtherDefault = EmailAccount::where('workspace_id', $workspace->id)->where('is_default', true)->exists();

        return EmailAccount::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'display_name' => $user->name,
            'provider' => 'imap',
            'imap_host' => $imap['host'],
            'imap_port' => $imap['port'],
            'imap_username' => $user->email,
            'imap_password' => $password,
            'imap_encryption' => $imap['encryption'],
            'smtp_host' => $smtp['host'],
            'smtp_port' => $smtp['port'],
            'smtp_username' => $user->email,
            'smtp_password' => $password,
            'smtp_encryption' => $smtp['implicit_tls'] ? 'ssl' : 'tls',
            'status' => 'connected',
            'is_default' => ! $hasOtherDefault,
            'sync_folders' => ['INBOX', 'Sent'],
        ]);
    }
}
