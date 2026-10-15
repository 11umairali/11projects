<?php
/*
 | ONE-TIME INSTALLER "update 4": call error hardening, audio+video calls in the room (add people), message reactions, forward,
 | delivered / read ticks, privacy page (last seen, read receipts), clear call history, new Meetings icon.
 | Open:  https://your-site/install_update4.php?k=dahi2026      (run install_update3.php first)
 */
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if (($_GET['k'] ?? '') !== 'dahi2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
if (!is_file("$root/artisan")) { exit("Laravel root not found at $root (this file must be inside the website's public folder).\n"); }
echo "Laravel root: $root\n\n";
$problems = 0;

echo "== 1. Files ==\n";
foreach ([
    'app/Services/Friends/FriendChatService.php', 'app/Services/Friends/FriendCallService.php', 'app/Services/Friends/PresenceService.php',
    'app/Http/Controllers/Friends/FriendChatController.php', 'app/Http/Controllers/Friends/FriendCallController.php', 'app/Http/Controllers/Friends/PresenceController.php',
    'resources/views/friends/chat.blade.php', 'resources/views/friends/index.blade.php', 'resources/views/friends/calls.blade.php', 'resources/views/settings/privacy.blade.php',
    'public/js/friend-chat.js', 'public/js/friend-call.js',
] as $f) {
    $has = is_file("$root/$f");
    if (!$has) $problems++;
    echo str_pad($f, 74) . ($has ? 'ok' : 'MISSING  <-- upload it') . "\n";
}

echo "\n== 2. Database ==\n";
try {
    require "$root/vendor/autoload.php";
    $app = require "$root/bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $step = function (string $label, callable $fn) use (&$problems) {
        try { $r = $fn(); echo str_pad($label, 74) . ($r ?? 'done') . "\n"; }
        catch (Throwable $e) { $problems++; echo str_pad($label, 74) . 'FAILED: ' . $e->getMessage() . "\n"; }
    };
    $col = function (string $table, string $name, callable $add) use ($step) {
        $step("$table.$name", function () use ($table, $name, $add) { if (!Schema::hasTable($table)) throw new Exception("table $table is missing"); if (Schema::hasColumn($table, $name)) return 'already there'; Schema::table($table, $add); });
    };
    $col('friend_messages', 'delivered_at', fn (Blueprint $t) => $t->timestamp('delivered_at')->nullable());
    $col('friend_messages', 'reacted_at', fn (Blueprint $t) => $t->timestamp('reacted_at')->nullable());
    $col('friend_messages', 'forwarded', fn (Blueprint $t) => $t->boolean('forwarded')->default(false));
    $col('friend_calls', 'audio_only', fn (Blueprint $t) => $t->boolean('audio_only')->default(false));
    $col('user_presence', 'read_receipts', fn (Blueprint $t) => $t->boolean('read_receipts')->default(true));
    $step('table friend_message_reactions', function () {
        if (Schema::hasTable('friend_message_reactions')) return 'already there';
        Schema::create('friend_message_reactions', function (Blueprint $t) {
            $t->unsignedBigInteger('message_id'); $t->unsignedBigInteger('user_id'); $t->string('emoji', 32); $t->timestamp('created_at')->nullable();
            $t->primary(['message_id', 'user_id']); $t->index('message_id');
        });
    });
    $step('table friend_call_clears', function () {
        if (Schema::hasTable('friend_call_clears')) return 'already there';
        Schema::create('friend_call_clears', function (Blueprint $t) { $t->unsignedBigInteger('user_id')->primary(); $t->unsignedBigInteger('before_id'); });
    });
    $step('old messages: mark as delivered', function () { \Illuminate\Support\Facades\DB::table('friend_messages')->whereNull('delivered_at')->whereNotNull('read_at')->update(['delivered_at' => \Illuminate\Support\Facades\DB::raw('read_at')]); return 'done'; });
} catch (Throwable $e) {
    $problems++;
    echo 'Laravel could not start: ' . $e->getMessage() . "\n";
}

function backup(string $root, string $rel, string $content): bool
{
    $dir = "$root/storage/app/backups_update4";
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    return @file_put_contents($dir . '/' . str_replace('/', '_', $rel) . '.' . date('Ymd_His') . '.bak', $content) !== false;
}
function save(string $root, string $rel, string $old, string $new): string
{
    global $problems;
    if (!backup($root, $rel, $old)) { $problems++; return 'could not create a backup – nothing changed'; }
    if (@file_put_contents("$root/$rel", $new) === false) { $problems++; return 'COULD NOT WRITE (permissions) – nothing changed'; }
    return 'patched (backup in storage/app/backups_update4)';
}
function patch(string $root, string $rel, string $marker, string $anchor, array $lines): string
{
    global $problems;
    $path = "$root/$rel";
    if (!is_file($path)) { $problems++; return 'FILE NOT FOUND'; }
    $s = file_get_contents($path);
    if (strpos($s, $marker) !== false) return 'already done';
    $nl = strpos($s, "\r\n") !== false ? "\r\n" : "\n";
    $done = false;
    $new = preg_replace_callback($anchor, function ($m) use ($lines, $nl, &$done) { $done = true; return $m[0] . $nl . $m[1] . implode($nl . $m[1], $lines); }, $s, 1);
    if (!$done || $new === null) { $problems++; return 'ANCHOR LINE NOT FOUND – nothing changed (see README)'; }
    return save($root, $rel, $s, $new);
}
function replaceAll(string $root, string $rel, array $pairs, string $marker): string
{
    global $problems;
    $path = "$root/$rel";
    if (!is_file($path)) { $problems++; return 'FILE NOT FOUND'; }
    $s = file_get_contents($path);
    if (strpos($s, $marker) !== false) return 'already done';
    $new = $s; $changed = false;
    foreach ($pairs as [$a, $b]) { if (strpos($new, $a) !== false) { $new = str_replace($a, $b, $new); $changed = true; } }
    if (!$changed) return 'nothing to change';
    return save($root, $rel, $s, $new);
}

function regexReplace(string $root, string $rel, string $pattern, string $to, string $marker, bool $markerMeansDone = true): string
{
    global $problems;
    $path = "$root/$rel";
    if (!is_file($path)) { $problems++; return 'FILE NOT FOUND'; }
    $s = file_get_contents($path);
    if ($markerMeansDone ? strpos($s, $marker) !== false : strpos($s, $marker) === false) return 'already done';
    $new = preg_replace($pattern, $to, $s, 1, $count);
    if ($new === null || !$count) { $problems++; return 'PLACE NOT FOUND – nothing changed (see README)'; }
    return save($root, $rel, $s, $new);
}
$C = '\\App\\Http\\Controllers\\Friends\\FriendChatController::class';
$K = '\\App\\Http\\Controllers\\Friends\\FriendCallController::class';
echo "\n== 3. Website routes ==\n";
echo str_pad('react / forward / forward list', 74) . patch($root, 'routes/web.php', "'forward-targets'",
    '~^([ \t]*)Route::post\(\'calls/video/\{userId\}\'.*;[ \t]*~m', [
        "Route::post('messages/{userId}/{messageId}/react', [$C, 'react'])->whereNumber('userId')->whereNumber('messageId');",
        "Route::post('messages/{userId}/{messageId}/forward', [$C, 'forward'])->whereNumber('userId')->whereNumber('messageId');",
        "Route::get('forward-targets', [$C, 'targets']);",
    ]) . "\n";
echo str_pad('clear call history (website)', 74) . patch($root, 'routes/web.php', "Route::delete('calls/history'",
    '~^([ \t]*)Route::get\(\'calls/history\'.*;[ \t]*~m', ["Route::delete('calls/history', [$K, 'clearHistory']);"]) . "\n";
echo str_pad('privacy page', 74) . patch($root, 'routes/web.php', "name('privacy')",
    '~^([ \t]*)Route::get\(\'/phone\',.*->name\(\'phone\'\);[ \t]*~m', ["Route::get('/privacy', function () { return view('settings.privacy'); })->name('privacy');"]) . "\n";
echo str_pad('settings menu: Privacy', 74) . regexReplace($root, 'resources/views/components/layouts/settings.blade.php',
    '~^([ \t]*)(\[\'label\' => __\(\'Phone & Discovery\'\).*\],[ \t]*)$~m',
    "$1$2\n$1['label' => __('Privacy'), 'href' => url('/settings/privacy'), 'icon' => '<rect x=\"3\" y=\"11\" width=\"18\" height=\"11\" rx=\"2\"></rect><path d=\"M7 11V7a5 5 0 0 1 10 0v4\"></path>'],",
    "url('/settings/privacy')") . "\n";
echo str_pad('main menu: new Meetings icon (calendar)', 74) . regexReplace($root, 'resources/views/components/layouts/app.blade.php',
    '~<polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>~',
    '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18M8 14h3M8 17h6"></path>', 'M16 2v4M8 2v4M3 10h18M8 14h3') . "\n";
echo str_pad('page layout: call script v5', 74) . regexReplace($root, 'resources/views/components/layouts/app.blade.php',
    '~js/friend-call\.js\'\) \}\}\?v=\d+~', "js/friend-call.js') }}?v=5", "friend-call.js') }}?v=5") . "\n";

echo "\n== 4. App routes ==\n";
echo str_pad('app: react / forward / clear call history', 74) . patch($root, 'routes/api_mobile.php', "'friends/forward-targets'",
    '~^([ \t]*)Route::post\(\'friends/\{userId\}/video-call\'.*;[ \t]*~m', [
        "Route::post('friends/{userId}/messages/{messageId}/react', [$C, 'react'])->whereNumber('userId')->whereNumber('messageId');",
        "Route::post('friends/{userId}/messages/{messageId}/forward', [$C, 'forward'])->whereNumber('userId')->whereNumber('messageId');",
        "Route::get('friends/forward-targets', [$C, 'targets']);",
        "Route::delete('calls/history', [$K, 'clearHistory']);",
    ]) . "\n";

echo "\n== 5. Caches ==\n";
foreach (array_merge(['bootstrap/cache/config.php'], array_map(fn ($p) => str_replace("$root/", '', $p), glob("$root/bootstrap/cache/routes*.php") ?: [])) as $c) {
    if (is_file("$root/$c")) echo str_pad($c, 74) . (@unlink("$root/$c") ? 'cleared' : 'COULD NOT DELETE – delete it by hand') . "\n";
}
foreach (glob("$root/storage/framework/views/*.php") ?: [] as $v) { @unlink($v); }
if (function_exists('opcache_reset')) { opcache_reset(); echo "PHP opcache cleared\n"; }
echo "\n";
if ($problems === 0) {
    echo "ALL DONE.\n";
    echo @unlink(__FILE__) ? "This installer deleted itself.\n" : "Please DELETE this file (install_update4.php) now.\n";
} else {
    echo "$problems problem(s) above – fix them and run this page again (safe to repeat).\n";
}
