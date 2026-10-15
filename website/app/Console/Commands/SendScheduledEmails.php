<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledEmailJob;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledEmails extends Command
{
    protected $signature = 'emails:send-scheduled';
    protected $description = 'Send emails that are scheduled and due';

    public function handle(): int
    {
        $messages = Message::readyToSend()
            ->with(['conversation.workspace', 'conversation.contact', 'conversation.emailAccount'])
            ->limit(50)
            ->get();

        if ($messages->isEmpty()) {
            Log::debug('[send-scheduled] No scheduled emails due');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($messages as $message) {
            SendScheduledEmailJob::dispatch($message);
            $count++;
            Log::info("[send-scheduled] Dispatched message #{$message->id} (conv #{$message->conversation_id})");
        }

        Log::info("[send-scheduled] Done. {$count} email(s) dispatched");
        $this->info("Dispatched {$count} scheduled email(s).");

        return self::SUCCESS;
    }
}
