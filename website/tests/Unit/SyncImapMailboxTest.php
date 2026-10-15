<?php

use App\Models\EmailAccount;
use App\Services\Email\MailSyncFailure;
use App\Services\Email\SyncImapMailbox;

test('normal mailbox passwords preserve spaces and only grouped Gmail app passwords lose separators', function () {
    $account = new EmailAccount(['imap_host' => 'mail.example.test', 'imap_password' => 'abcd efgh ijkl mnop']);
    expect(SyncImapMailbox::password($account))->toBe('abcd efgh ijkl mnop');
    $account->imap_host = 'imap.gmail.com';
    expect(SyncImapMailbox::password($account))->toBe('abcdefghijklmnop');
    $account->imap_password = 'normal password with spaces';
    expect(SyncImapMailbox::password($account))->toBe('normal password with spaces');
});

test('connection errors are actionable and unexpected failures never expose raw server details', function () {
    $error = new RuntimeException('transport wrapper', 0, new Webklex\PHPIMAP\Exceptions\ConnectionFailedException('secret connection data'));
    expect(MailSyncFailure::describe($error)['code'])->toBe('connection_failed');
    expect(MailSyncFailure::describe(new Webklex\PHPIMAP\Exceptions\ImapServerErrorException('NO [AUTHENTICATIONFAILED] Authentication failed.'))['code'])->toBe('authentication_failed');
    $detail = MailSyncFailure::describe(new RuntimeException('SQL or access_token=secret'));
    expect($detail['code'])->toBe('sync_failed')->and(json_encode($detail))->not->toContain('access_token', 'secret', 'SQL');
});
