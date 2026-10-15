<?php
/*
 | TEMPORARY DIAGNOSTIC 2 – finds the REAL error behind "unexpected error" when saving a phone number.
 | Open  https://your-site/check_errors.php?k=dahi2026      (optional: &id=5 to test as that user id; default = the first user)
 | Test saves are ROLLED BACK (nothing is kept). DELETE THIS FILE AFTER USE.
 */
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

if (($_GET['k'] ?? '') !== 'dahi2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
if (!is_file("$root/artisan")) { exit("Laravel root not found at $root\n"); }

// ── 1. newest real errors in the log (any topic) ──
echo "== 1. Newest errors in storage/logs (UTC times) ==\n";
$logs = glob("$root/storage/logs/*.log") ?: [];
usort($logs, fn ($a, $b) => filemtime($b) <=> filemtime($a));
$shown = 0;
foreach (array_slice($logs, 0, 2) as $log) {
    $size = filesize($log);
    $fh = fopen($log, 'r'); fseek($fh, max(0, $size - 900000)); $tail = stream_get_contents($fh); fclose($fh);
    $entries = preg_split('/(?=^\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\] )/m', $tail, -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_reverse($entries) as $e) {
        if (!preg_match('/^\[[^\]]+\] \w+\.(ERROR|CRITICAL|EMERGENCY)/', $e)) continue;
        $lines = preg_split('/\R/', $e);
        $first = preg_replace('/(password|token|secret|authorization)[^,)}\s]*/i', '$1=[hidden]', $lines[0]);
        echo mb_substr($first, 0, 700) . "\n";
        $frames = 0;
        foreach ($lines as $l) { if (preg_match('/^#\d+ .*\/(app|routes)\//', $l) && $frames < 3) { echo '    ' . str_replace($root, '', mb_substr($l, 0, 220)) . "\n"; $frames++; } }
        echo "\n";
        if (++$shown >= 6) break 2;
    }
}
if (!$shown) echo "no errors found in the newest log files.\n";

try {
    require "$root/vendor/autoload.php";
    $app = require "$root/bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable $e) { exit("\nLaravel could not start: " . $e->getMessage() . "\n"); }

$user = isset($_GET['id']) ? \App\Models\User::find((int) $_GET['id']) : \App\Models\User::orderBy('id')->first();
if (!$user) exit("\nno user found\n");
echo "== Tests run as user id {$user->id} ==\n";
function show(string $label, callable $fn): void {
    try { $r = $fn(); echo str_pad($label, 58) . 'OK' . ($r !== null ? '  → ' . $r : '') . "\n"; }
    catch (Throwable $e) { echo str_pad($label, 58) . "FAILED\n    " . get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 300) . "\n    at " . str_replace(dirname(__DIR__), '', $e->getFile()) . ':' . $e->getLine() . "\n"; }
}

// ── 2. the website's Phone & Discovery component, step by step ──
echo "\n== 2. Website page: Settings → Phone & Discovery ==\n";
Auth::login($user);
show('open the page (mount)', function () { $c = new \App\Livewire\Settings\PhoneDiscovery(); $c->mount(); return 'status ok'; });
show('draw the page (render the view)', function () {
    $c = new \App\Livewire\Settings\PhoneDiscovery(); $c->mount();
    $html = view('livewire.settings.phone-discovery', get_object_vars($c))->render();
    return strlen($html) . ' characters of HTML';
});
show('press "Save number" (saved, then rolled back)', function () {
    DB::beginTransaction();
    try {
        $c = new \App\Livewire\Settings\PhoneDiscovery(); $c->mount();
        $c->country = 'US'; $c->national = '5551234567'; $c->saveNumber();
        $html = view('livewire.settings.phone-discovery', get_object_vars($c))->render();
        return '"' . $c->message . '" and the page redraws (' . strlen($html) . ' chars)';
    } finally { DB::rollBack(); }
});

// ── 3. the same page as a real web request (middleware, layout, menu …) ──
show('GET /settings/phone through the whole web stack', function () use ($app, $user) {
    config(['app.debug' => true]);
    Auth::login($user);
    $req = Illuminate\Http\Request::create('/settings/phone', 'GET');
    $res = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($req);
    $code = $res->getStatusCode();
    if ($code >= 500) {
        $body = trim(preg_replace('/\s+/', ' ', strip_tags((string) $res->getContent())));
        throw new RuntimeException("HTTP $code – " . mb_substr($body, 0, 400));
    }
    return "HTTP $code";
});

// ── 4. what the APP calls ──
echo "\n== 4. App: POST me/phone ==\n";
show('me/phone with country + number (saved, then rolled back)', function () use ($user) {
    DB::beginTransaction();
    try {
        $req = Illuminate\Http\Request::create('/', 'POST', ['phone_country' => 'US', 'phone_national' => '5551234567']);
        $req->setUserResolver(fn () => $user);
        $res = app(\App\Http\Controllers\Api\Mobile\FriendsController::class)->phoneStart($req);
        return 'HTTP ' . $res->getStatusCode() . ' ' . $res->getContent();
    } finally { DB::rollBack(); }
});

// ── 5. sign-up validation ──
echo "\n== 5. Sign-up phone validation ==\n";
show('RegistrationPhone::validated (country + number)', function () {
    $p = app(\App\Services\Friends\RegistrationPhone::class)->validated(['phone_country' => 'US', 'phone_national' => '5551234567']);
    return $p ?? 'null (mode is off)';
});
echo "\nDELETE THIS FILE NOW.\n";
