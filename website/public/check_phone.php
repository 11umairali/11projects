<?php
/*
 | TEMPORARY DIAGNOSTIC for "phone number cannot be saved". Open  https://your-site/check_phone.php?k=dahi2026
 | It reads settings, tests a save inside a database transaction that is ROLLED BACK (nothing is kept), and shows the real
 | error. DELETE THIS FILE AFTER USE.
 */
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (($_GET['k'] ?? '') !== 'dahi2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
if (!is_file("$root/artisan")) { exit("Laravel root not found at $root\n"); }
echo "Laravel root: $root\n\n";

echo "== 1. Files ==\n";
foreach (['config/phone_countries.php', 'app/Support/PhoneSettings.php', 'app/Support/PhoneKey.php', 'app/Services/Friends/PhoneVerifier.php', 'app/Services/Friends/RegistrationPhone.php', 'app/Services/Friends/FriendService.php', 'app/Livewire/Settings/PhoneDiscovery.php', 'resources/views/livewire/settings/phone-discovery.blade.php'] as $f) {
    echo str_pad($f, 70) . (is_file("$root/$f") ? 'ok' : 'MISSING  <-- upload it') . "\n";
}

try {
    require "$root/vendor/autoload.php";
    $app = require "$root/bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable $e) { exit("\nLaravel could not start: " . $e->getMessage() . "\n"); }

echo "\n== 2. Database columns (users) ==\n";
foreach (['phone', 'phone_key', 'phone_verified_at', 'discoverable'] as $c) {
    $has = Schema::hasColumn('users', $c);
    echo str_pad("users.$c", 70) . ($has ? 'ok' : 'MISSING  <-- run install_friends.php (this is the usual cause)') . "\n";
}
try {
    $col = DB::select("SHOW COLUMNS FROM users LIKE 'phone'");
    if ($col) echo str_pad('users.phone type', 70) . $col[0]->Type . "\n";
} catch (Throwable $e) {}

echo "\n== 3. Settings ==\n";
try {
    echo str_pad('phone_countries entries', 70) . count(config('phone_countries', [])) . (count(config('phone_countries', [])) ? '' : '   <-- 0 = config cache: delete bootstrap/cache/config.php') . "\n";
    echo str_pad('sign-up phone mode', 70) . \App\Support\PhoneSettings::registrationMode() . "\n";
    echo str_pad('verification channels available', 70) . (json_encode(\App\Support\PhoneSettings::channels())) . "\n";
} catch (Throwable $e) { echo 'FAILED: ' . get_class($e) . ': ' . $e->getMessage() . "\n"; }

echo "\n== 4. Test save (rolled back) ==\n";
try {
    $user = \App\Models\User::orderBy('id')->first();
    if (!$user) { echo "no users found\n"; }
    else {
        [$e164, $err] = \App\Services\Friends\RegistrationPhone::build('US', '5551234567');
        echo "number built: " . ($e164 ?? 'null') . ($err ? "  error: $err" : '') . "\n";
        DB::beginTransaction();
        try {
            $res = app(\App\Services\Friends\PhoneVerifier::class)->saveUnverified($user, $e164 ?: '+15551234567');
            echo 'saveUnverified(): ' . json_encode($res) . "\n";
            echo 'status(): ' . json_encode(app(\App\Services\Friends\PhoneVerifier::class)->status($user)) . "\n";
        } finally { DB::rollBack(); }
        echo "(rolled back – nothing was kept)\n";
    }
} catch (Throwable $e) {
    echo "THE REAL ERROR:\n  " . get_class($e) . ': ' . $e->getMessage() . "\n  at " . str_replace($root, '', $e->getFile()) . ':' . $e->getLine() . "\n";
}

echo "\n== 5. Last related lines in storage/logs ==\n";
$logs = glob("$root/storage/logs/*.log") ?: [];
usort($logs, fn ($a, $b) => filemtime($b) <=> filemtime($a));
if ($logs) {
    $fh = fopen($logs[0], 'r'); fseek($fh, max(0, filesize($logs[0]) - 600000)); $tail = stream_get_contents($fh); fclose($fh);
    $hits = [];
    foreach (preg_split('/\R/', $tail) as $l) { if (preg_match('/phone|Unknown column|PhoneVerifier|RegistrationPhone|FriendService|PhoneDiscovery/i', $l) && preg_match('/ERROR|Exception/', $l)) $hits[] = $l; }
    if (!$hits) echo "none in " . basename($logs[0]) . " (log times are UTC)\n";
    foreach (array_slice($hits, -4) as $h) echo mb_substr(preg_replace('/(password|token|secret)[^,)]*/i', '$1=[hidden]', $h), 0, 380) . "\n\n";
}
echo "\nDELETE THIS FILE NOW.\n";
