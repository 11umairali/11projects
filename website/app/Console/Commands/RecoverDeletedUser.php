<?php

namespace App\Console\Commands;

use App\Services\DeletedUserRecovery;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecoverDeletedUser extends Command
{
    protected $signature = 'dahimail:recover-user {username : Existing mailbox username, without @domain}
                            {--name= : Display name for the recovered application account}
                            {--check : Check recovery eligibility without creating an account}';

    protected $description = 'Recover access to an existing CyberPanel mailbox whose application user was deleted';

    public function handle(DeletedUserRecovery $recovery): int
    {
        try {
            $identity = $recovery->inspect((string) $this->argument('username'));
            $this->line('Existing mailbox: '.$identity['email']);
            if ($this->option('check')) {
                $this->info('Recovery is available. Public registration remains blocked for this mailbox.');
                return self::SUCCESS;
            }
            $name = $this->option('name') ?: $this->ask('Account display name');
            $this->line('This creates a regular application account with a new ID. Deleted application history and permissions require a database backup.');
            if (!$this->confirm('Recover access to this existing mailbox?')) return self::SUCCESS;
            [$user, $words] = $recovery->recover($identity['username'], (string) $name);
            $this->info('Recovered '.$identity['email'].' (application user ID '.$user->id.').');
            $this->line('Sign in with the current CyberPanel mailbox password.');
            $this->line('Save this new recovery phrase privately:');
            $this->line(implode(' ', $words));
            return self::SUCCESS;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) foreach ($messages as $message) $this->error($message);
            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Recovery failed. Check application and CyberPanel database access and user-table uniqueness. The existing mailbox was not changed.');
            return self::FAILURE;
        }
    }
}
