<?php

namespace App\Mailbox;

use RuntimeException;

class MailboxException extends RuntimeException
{
    public static function authFailed(): self
    {
        return new self('The mailbox rejected the saved password.', 401);
    }

    public function isAuthFailure(): bool
    {
        return $this->getCode() === 401;
    }
}
