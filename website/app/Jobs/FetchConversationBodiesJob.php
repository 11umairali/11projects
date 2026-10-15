<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\Email\EmailSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchConversationBodiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public Conversation $conversation
    ) {
        $this->onQueue('email-sync');
    }

    public function handle(EmailSyncService $syncService): void
    {
        $account = $this->conversation->emailAccount;

        if (! $account || ! $account->imap_host) return;

        $syncService->batchFetchBodiesForConversation($this->conversation, $account);
    }
}
