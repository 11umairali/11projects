<?php

namespace App\Console\Commands;

use App\Services\ApnsVoip;
use App\Services\FcmPush;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PushStatus extends Command
{
    protected $signature = 'push:status {--user= : Show device counts for one user ID} {--probe : Validate that user’s device tokens with FCM without delivering alerts} {--test : Send a real push delivery test to that user’s devices}';
    protected $description = 'Check push configuration; optionally validate tokens or send a real delivery test';

    public function handle(FcmPush $fcm, ApnsVoip $apns): int
    {
        $user = $this->option('user');
        if ($user !== null && (!ctype_digit((string) $user) || (int) $user < 1)) {
            $this->error('--user must be a positive user ID.');
            return self::INVALID;
        }
        if (($this->option('probe') || $this->option('test')) && $user === null) {
            $this->error('--probe and --test require --user to select whose devices to check.');
            return self::INVALID;
        }
        if ($this->option('probe') && $this->option('test')) {
            $this->error('Choose --probe or --test, not both.');
            return self::INVALID;
        }
        $error = $fcm->configurationError();
        $this->line('FCM: '.($error ?? 'local credentials valid'));
        $this->line('APNs VoIP: '.($apns->enabled() ? 'configured locally' : 'not configured (check APNS_* and HTTP/2 cURL support)'));
        try {
            $columns = Schema::getColumnListing('device_tokens');
            $missing = array_diff(['app_version', 'voip_token', 'apns_sandbox'], $columns);
            if ($missing) $this->warn('Missing push columns: '.implode(', ', $missing).'. Run the device push registration migration.');
            $version = in_array('app_version', $columns, true) ? 'app_version' : 'NULL';
            $voip = in_array('voip_token', $columns, true) ? "SUM(CASE WHEN voip_token IS NOT NULL AND voip_token <> '' THEN 1 ELSE 0 END)" : '0';
            $rows = DB::table('device_tokens')->when($user !== null, fn ($q) => $q->where('user_id', (int) $user))
                ->selectRaw("platform, {$version} as version, COUNT(*) as devices, {$voip} as voip_devices")
                ->groupBy('platform')->when($version !== 'NULL', fn ($q) => $q->groupBy('app_version'))->get();
            $this->table(['Platform', 'App version', 'Devices', 'With VoIP token'], $rows->map(fn ($r) => [$r->platform, $r->version ?? 'unknown', $r->devices, $r->voip_devices])->all());
            if ($rows->isEmpty()) $this->warn('No registered devices. Sign in with the rebuilt app and check registration again.');
            if (($this->option('probe') || $this->option('test')) && $error === null) {
                $failed = false;
                foreach (DB::table('device_tokens')->where('user_id', (int) $user)->get() as $device) {
                    $result = $this->option('test') ? $fcm->testToken($device->token, $device->platform) : $fcm->probeToken($device->token, $device->platform);
                    $this->line("Device {$device->id} ({$device->platform}): {$result['code']}");
                    if (isset($result['hint'])) $this->warn(($result['stage'] ?? 'push').': '.$result['hint']);
                    $failed = $failed || !$result['accepted'];
                }
                $this->line($this->option('test')
                    ? 'Real test sent to Firebase. Accepted does not confirm phone receipt. Reopen Device notifications to check the last Android delivery result.'
                    : 'Provider validation only; no notification was sent. Device receipt still needs a real call/message.');
                return $failed || $rows->isEmpty() ? self::FAILURE : self::SUCCESS;
            }
        } catch (\Throwable) {
            $this->error('Device registrations could not be read. Check database access and device_tokens migrations.');
            return self::FAILURE;
        }
        $this->line('Local checks only. Verify provider acceptance and locked/background delivery on a physical device.');
        return $error === null ? self::SUCCESS : self::FAILURE;
    }
}
