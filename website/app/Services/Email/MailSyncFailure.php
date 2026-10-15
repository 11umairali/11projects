<?php

namespace App\Services\Email;

use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;

/** Public sync errors never contain provider responses, credentials or SQL. */
final class MailSyncFailure
{
    public static function describe(\Throwable $error): array
    {
        for ($cause = $error; $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof AuthFailedException ||
                ($cause instanceof ImapServerErrorException && preg_match('/\[AUTHENTICATIONFAILED\]/i', $cause->getMessage())) ||
                ($cause instanceof \Google\Service\Exception && $cause->getCode() === 401) ||
                ($cause instanceof \GuzzleHttp\Exception\ClientException && $cause->getResponse()->getStatusCode() === 401)) {
                return ['code' => 'authentication_failed', 'message' => 'Mail server sign-in failed. Reconnect this account in Connected accounts.'];
            }
            if ($cause instanceof ConnectionFailedException || $cause instanceof \GuzzleHttp\Exception\ConnectException) {
                return ['code' => 'connection_failed', 'message' => 'Could not connect to the mail server. Check its host, port and encryption in Connected accounts, then retry.'];
            }
        }
        return ['code' => 'sync_failed', 'message' => 'Mail sync failed. Try again; if it continues, ask support to check the server log for this account.'];
    }
}
