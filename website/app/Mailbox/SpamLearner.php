<?php

namespace App\Mailbox;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Teaches the server's spam filter from users' "Report spam" and
 * "Not spam" clicks. A failure here never stops the user's action.
 *
 * - rspamd: sent straight to Rspamd's controller (learnspam / learnham).
 * - spamassassin: queued in storage/app/spam-learn, then fed to sa-learn
 *   by `php artisan dahify:learn-spam` from root's cron, because the
 *   SpamAssassin database usually belongs to another system user.
 */
class SpamLearner
{
    public function enabled(): bool
    {
        return in_array(config('dahify.spam_learning.driver'), ['rspamd', 'spamassassin'], true);
    }

    public function learn(string $rawMessage, bool $isSpam): void
    {
        if (! $this->enabled() || trim($rawMessage) === '') {
            return;
        }

        try {
            match (config('dahify.spam_learning.driver')) {
                'rspamd' => $this->rspamd($rawMessage, $isSpam),
                'spamassassin' => $this->queue($rawMessage, $isSpam),
            };
        } catch (Throwable $e) {
            Log::warning('Spam learning failed', ['error' => $e->getMessage()]);
        }
    }

    public static function spoolPath(string $kind = ''): string
    {
        return storage_path('app/spam-learn'.($kind !== '' ? '/'.$kind : ''));
    }

    private function rspamd(string $raw, bool $isSpam): void
    {
        $request = Http::timeout(10)->withBody($raw, 'message/rfc822');
        if ($password = config('dahify.spam_learning.rspamd_password')) {
            $request = $request->withHeaders(['Password' => $password]);
        }

        $url = rtrim((string) config('dahify.spam_learning.rspamd_url'), '/').($isSpam ? '/learnspam' : '/learnham');
        $response = $request->post($url);

        // 208 means Rspamd already learned this exact message.
        if (! $response->successful() && $response->status() !== 208) {
            Log::warning('Rspamd did not accept a learning request', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);
        }
    }

    private function queue(string $raw, bool $isSpam): void
    {
        $dir = self::spoolPath($isSpam ? 'spam' : 'ham');
        File::ensureDirectoryExists($dir, 0770);
        File::put($dir.'/'.now()->format('YmdHis').'-'.Str::random(12).'.eml', $raw);
    }
}
