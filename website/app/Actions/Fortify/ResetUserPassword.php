<?php

namespace App\Actions\Fortify;

use App\Models\EmailAccount;
use App\Models\User;
use App\Services\CyberPanelMailbox;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(private readonly CyberPanelMailbox $mailbox) {}

    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => \App\Support\CredentialPolicy::passwordRules(),
        ])->validate();

        if (Hash::check($input['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['New password must be different from your current password.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($input['password'])])->save();

        if ($user->username) {
            $this->mailbox->updatePassword($user->username, $input['password']);

            EmailAccount::where('user_id', $user->id)
                ->where('email', $user->email)
                ->update(['imap_password' => $input['password'], 'smtp_password' => $input['password']]);
        }
    }
}
