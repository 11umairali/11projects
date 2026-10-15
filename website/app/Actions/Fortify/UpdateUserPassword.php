<?php

namespace App\Actions\Fortify;

use App\Mailbox\MailboxCredentials;
use App\Models\EmailAccount;
use App\Models\User;
use App\Services\CyberPanelMailbox;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(
        private readonly CyberPanelMailbox $mailbox,
        private readonly MailboxCredentials $credentials,
    ) {}

    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => \App\Support\CredentialPolicy::passwordRules(),
        ])->validate();

        $newPassword = $input['password'];

        $user->forceFill(['password' => Hash::make($newPassword)])->save();

        // Dahimail.com users: the mail server (Dovecot/Postfix) has its
        // own copy of the password, separate from this app's `users`
        // table, and must be changed too or IMAP/SMTP logins break.
        if ($user->username) {
            $this->mailbox->updatePassword($user->username, $newPassword);
            $this->credentials->remember($newPassword);

            // Keep the auto-provisioned EmailAccount(s) usable.
            EmailAccount::where('user_id', $user->id)
                ->where('email', $user->email)
                ->update(['imap_password' => $newPassword, 'smtp_password' => $newPassword]);
        }
    }
}
