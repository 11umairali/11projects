<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WelcomeBackNotification;
use Illuminate\Console\Command;

class SendWelcomeBackEmails extends Command
{
    protected $signature = 'emails:welcome-back';
    protected $description = 'Send day-2 welcome back emails to users who signed up ~24 hours ago';

    public function handle(): int
    {
        // Users who signed up approximately 1 day ago (within a 1-hour window)
        // This command should be scheduled hourly for consistent delivery.
        $users = User::whereBetween('created_at', [
                now()->subHours(25),
                now()->subHours(23),
            ])
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $user->notify(new WelcomeBackNotification());
            $count++;
        }

        if ($count > 0) {
            $this->info("Sent {$count} welcome-back email(s).");
        }

        return self::SUCCESS;
    }
}
