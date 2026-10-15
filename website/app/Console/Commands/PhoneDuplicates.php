<?php

namespace App\Console\Commands;

use App\Services\Friends\PhoneVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PhoneDuplicates extends Command
{
    protected $signature = 'phones:duplicates {--clear=* : Account IDs whose duplicate number should be removed}';

    protected $description = 'List duplicate account phone numbers and optionally remove selected duplicate assignments';

    private function groups(bool $lock = false): array
    {
        $query = DB::table('users')->whereNotNull('phone')->orderBy('id');
        if ($lock) $query->lockForUpdate();
        $groups = [];
        foreach ($query->get(['id', 'name', 'email', 'phone', 'phone_verified_at']) as $user) {
            $digits = preg_replace('/\D+/', '', $user->phone);
            if ($digits !== '') $groups[$digits][] = $user;
        }
        return array_filter($groups, fn ($users) => count($users) > 1);
    }

    public function handle(): int
    {
        $groups = $this->groups();
        $requested = $this->option('clear');
        if (!$groups && !$requested) {
            $this->info('No duplicate mobile numbers found. Run php artisan migrate.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($groups as $digits => $users) {
            foreach ($users as $user) {
                $rows[] = [PhoneVerifier::mask('+'.$digits), $user->id, $user->name, $user->email, $user->phone_verified_at ? 'Yes' : 'No'];
            }
        }
        if ($rows) $this->table(['Mobile number', 'Account ID', 'Name', 'Email', 'Verified'], $rows);
        if (!$requested) {
            $this->line('Choose the account that should keep each number. Remove it from the other account(s) with --clear=ACCOUNT_ID (repeat for multiple IDs).');
            return self::SUCCESS;
        }

        foreach ($requested as $id) {
            if (!preg_match('/^[1-9][0-9]*$/', $id)) {
                $this->error('Each --clear value must be a positive account ID.');
                return self::FAILURE;
            }
        }
        $ids = array_values(array_unique(array_map('intval', $requested)));
        if (!$this->confirm('Remove the phone number and phone verification from account IDs '.implode(', ', $ids).'?', false)) {
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($ids) {
                $groups = $this->groups(true);
                $duplicateIds = [];
                foreach ($groups as $users) {
                    $groupIds = array_map(fn ($user) => (int) $user->id, $users);
                    $duplicateIds = array_merge($duplicateIds, $groupIds);
                    if (!array_diff($groupIds, $ids)) {
                        throw new \RuntimeException('Keep the number on at least one account in each duplicate group. No changes made.');
                    }
                }
                if (array_diff($ids, $duplicateIds)) {
                    throw new \RuntimeException('One or more selected IDs no longer have a duplicate number. No changes made.');
                }
                DB::table('users')->whereIn('id', $ids)->update([
                    'phone' => null, 'phone_key' => null, 'phone_verified_at' => null, 'discoverable' => false,
                    'updated_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        foreach ($ids as $id) Cache::forget("phone_code:{$id}");
        $this->info('Duplicate numbers removed from the selected accounts. Run php artisan phones:duplicates again, then php artisan migrate.');
        return self::SUCCESS;
    }
}
