<?php
/*
 | ONE-TIME INSTALLER for Meetings, Video calls and message edit / delete.
 |   1. adds the database columns + tables (safe to repeat),
 |   2. adds the website routes + the app routes (backups first),
 |   3. adds "Meetings" to the website menu, makes browsers load the new scripts, clears caches, deletes itself.
 | Open:  https://your-site/install_meetings.php?k=dahi2026      (run install_people.php first)
 */
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (($_GET['k'] ?? '') !== 'dahi2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
if (!is_file("$root/artisan")) { exit("Laravel root not found at $root (this file must be inside the website's public folder).\n"); }
echo "Laravel root: $root\n\n";
$problems = 0;

echo "== 1. Files ==\n";
foreach ([
    'app/Services/Friends/FriendChatService.php', 'app/Services/Friends/FriendCallService.php', 'app/Services/Meetings/MeetingService.php',
    'app/Http/Controllers/Friends/FriendChatController.php', 'app/Http/Controllers/Friends/FriendCallController.php', 'app/Http/Controllers/Meetings/MeetingController.php',
    'app/Notifications/MeetingNotification.php', 'resources/views/meetings/room.blade.php', 'resources/views/meetings/index.blade.php',
    'resources/views/friends/chat.blade.php', 'resources/views/friends/person.blade.php',
    'public/js/meeting.js', 'public/js/friend-call.js', 'public/js/friend-chat.js',
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
        $step("$table.$name", function () use ($table, $name, $add) { if (Schema::hasColumn($table, $name)) return 'already there'; Schema::table($table, $add); });
    };
    if (!Schema::hasTable('friend_messages') || !Schema::hasTable('friend_calls')) { $problems++; echo "friend_messages / friend_calls MISSING <-- run install_chat.php and install_people.php first\n"; }
    else {
        $col('friend_messages', 'edited_at', fn (Blueprint $t) => $t->timestamp('edited_at')->nullable());
        $col('friend_messages', 'deleted_at', fn (Blueprint $t) => $t->timestamp('deleted_at')->nullable());
        $col('friend_calls', 'video', fn (Blueprint $t) => $t->boolean('video')->default(false));
        $col('friend_calls', 'meeting_code', fn (Blueprint $t) => $t->string('meeting_code', 24)->nullable());
    }
    $step('table meetings', function () {
        if (Schema::hasTable('meetings')) return 'already there';
        Schema::create('meetings', function (Blueprint $t) {
            $t->id(); $t->string('code', 24)->unique();
            $t->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $t->string('title', 160)->nullable(); $t->text('description')->nullable();
            $t->timestamp('scheduled_at')->nullable(); $t->unsignedSmallInteger('duration_min')->default(60);
            $t->string('status', 12)->default('scheduled'); $t->string('kind', 8)->default('meeting');
            $t->boolean('waiting_room')->default(true); $t->boolean('allow_guests')->default(true);
            $t->boolean('mute_on_entry')->default(false); $t->boolean('locked')->default(false);
            $t->timestamp('started_at')->nullable(); $t->timestamp('ended_at')->nullable();
            $t->timestamps();
        });
    });
    $step('table meeting_invitees', function () {
        if (Schema::hasTable('meeting_invitees')) return 'already there';
        Schema::create('meeting_invitees', function (Blueprint $t) {
            $t->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->primary(['meeting_id', 'user_id']);
        });
    });
    $step('table meeting_participants', function () {
        if (Schema::hasTable('meeting_participants')) return 'already there';
        Schema::create('meeting_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('guest_name', 80)->nullable(); $t->string('guest_token', 64)->nullable();
            $t->string('name', 120);
            $t->string('role', 10)->default('participant'); $t->string('status', 10)->default('waiting');
            $t->boolean('audio_on')->default(true); $t->boolean('video_on')->default(true);
            $t->boolean('hand')->default(false); $t->boolean('sharing')->default(false); $t->boolean('force_mute')->default(false);
            $t->timestamp('joined_at')->nullable(); $t->timestamp('left_at')->nullable(); $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->index(['meeting_id', 'status']);
        });
    });
    $step('table meeting_signals', function () {
        if (Schema::hasTable('meeting_signals')) return 'already there';
        Schema::create('meeting_signals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $t->unsignedBigInteger('from_pid'); $t->unsignedBigInteger('to_pid');
            $t->string('type', 8); $t->mediumText('payload');
            $t->timestamp('created_at')->nullable();
            $t->index(['meeting_id', 'to_pid', 'id']);
        });
    });
    $step('table meeting_messages', function () {
        if (Schema::hasTable('meeting_messages')) return 'already there';
        Schema::create('meeting_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $t->unsignedBigInteger('pid'); $t->string('name', 120); $t->text('body');
            $t->timestamp('created_at')->nullable();
            $t->index(['meeting_id', 'id']);
        });
    });
    $step("friend_calls kinds / notification type", function () { return 'nothing to do'; });
} catch (Throwable $e) {
    $problems++;
    echo 'Laravel could not start: ' . $e->getMessage() . "\n";
}

function backup(string $root, string $rel, string $content): bool
{
    $dir = "$root/storage/app/backups_meetings";
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    return @file_put_contents($dir . '/' . str_replace('/', '_', $rel) . '.' . date('Ymd_His') . '.bak', $content) !== false;
}
function save(string $root, string $rel, string $old, string $new): string
{
    global $problems;
    if (!backup($root, $rel, $old)) { $problems++; return 'could not create a backup – nothing changed'; }
    if (@file_put_contents("$root/$rel", $new) === false) { $problems++; return 'COULD NOT WRITE (permissions) – nothing changed'; }
    return 'patched (backup in storage/app/backups_meetings)';
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

$C = '\\App\\Http\\Controllers\\Friends\\FriendChatController::class';
$K = '\\App\\Http\\Controllers\\Friends\\FriendCallController::class';
$M = '\\App\\Http\\Controllers\\Meetings\\MeetingController::class';

echo "\n== 3. Website routes ==\n";
echo str_pad('routes/web.php → Meetings pages (signed in)', 74) . patch($root, 'routes/web.php', "name('meetings.index')",
    '~^([ \t]*)Route::get\(\'/people/\{userId\}\'.*->name\(\'people\.show\'\);[ \t]*~m', [
        "Route::get('/meetings', [$M, 'index'])->name('meetings.index');",
        "Route::post('/meetings', [$M, 'store'])->name('meetings.store');",
        "Route::post('/meetings/{code}/cancel', [$M, 'cancelForm'])->name('meetings.cancel');",
    ]) . "\n";
echo str_pad('routes/web.php → edit / delete message, video call', 74) . patch($root, 'routes/web.php', "'messages/{userId}/{messageId}'",
    '~^([ \t]*)Route::post\(\'calls/start/\{userId\}\'.*;[ \t]*~m', [
        "Route::patch('messages/{userId}/{messageId}', [$C, 'edit'])->whereNumber('userId')->whereNumber('messageId');",
        "Route::delete('messages/{userId}/{messageId}', [$C, 'delete'])->whereNumber('userId')->whereNumber('messageId');",
        "Route::post('calls/video/{userId}', [$K, 'startVideo'])->whereNumber('userId');",
    ]) . "\n";
// public meeting room + its JSON endpoints (guests can use them with their guest token)
$pubPath = "$root/routes/web.php";
$web = is_file($pubPath) ? file_get_contents($pubPath) : '';
if ($web === '') { $problems++; echo str_pad('routes/web.php → public meeting room', 74) . "FILE NOT FOUND\n"; }
elseif (strpos($web, "meetings/api") !== false) echo str_pad('routes/web.php → public meeting room', 74) . "already done\n";
else {
    $add = "\n// ── Meetings (public room page + JSON API; guests use a guest token) ──\n"
        . "Route::get('/meet/{code}', [$M, 'room'])->name('meetings.room');\n"
        . "Route::prefix('meetings/api/{code}')->group(function () {\n"
        . "    Route::get('/', [$M, 'show'])->middleware('throttle:60,1');\n"
        . "    Route::post('join', [$M, 'join'])->middleware('throttle:30,1');\n"
        . "    Route::get('poll', [$M, 'poll'])->middleware('throttle:240,1');\n"
        . "    Route::post('signal', [$M, 'signal'])->middleware('throttle:600,1');\n"
        . "    Route::post('state', [$M, 'state'])->middleware('throttle:120,1');\n"
        . "    Route::post('chat', [$M, 'chat'])->middleware('throttle:60,1');\n"
        . "    Route::post('host', [$M, 'host'])->middleware('throttle:120,1');\n"
        . "    Route::post('leave', [$M, 'leave'])->middleware('throttle:60,1');\n"
        . "});\n";
    echo str_pad('routes/web.php → public meeting room', 74) . save($root, 'routes/web.php', $web, rtrim($web) . "\n" . $add) . "\n";
}

echo str_pad('menu: Meetings, newest scripts', 74) . patch($root, 'resources/views/components/layouts/app.blade.php', "url('/meetings')",
    '~^([ \t]*)\[\'label\' => __\(\'People\'\), \'href\' => url\(\'/people\'\).*\],[ \t]*~m', [
        "['label' => __('Meetings'), 'href' => url('/meetings'), 'icon' => '<polygon points=\"23 7 16 12 23 17 23 7\"></polygon><rect x=\"1\" y=\"5\" width=\"15\" height=\"14\" rx=\"2\" ry=\"2\"></rect>'],",
    ]) . "\n";
echo str_pad('page layout → call script v3', 74) . replaceAll($root, 'resources/views/components/layouts/app.blade.php', [
    ["js/friend-call.js') }}?v=2", "js/friend-call.js') }}?v=3"],
    ["js/friend-call.js') }}?v=1", "js/friend-call.js') }}?v=3"],
], "friend-call.js') }}?v=3") . "\n";

echo "\n== 4. App routes ==\n";
echo str_pad('routes/api_mobile.php → meetings, edit/delete, video call', 74) . patch($root, 'routes/api_mobile.php', "'meetings/{code}/join'",
    '~^([ \t]*)Route::post\(\'friends/\{userId\}/call\'.*;[ \t]*~m', [
        "Route::match(['put', 'patch'], 'friends/{userId}/messages/{messageId}', [$C, 'edit'])->whereNumber('userId')->whereNumber('messageId');",
        "Route::delete('friends/{userId}/messages/{messageId}', [$C, 'delete'])->whereNumber('userId')->whereNumber('messageId');",
        "Route::post('friends/{userId}/video-call', [$K, 'startVideo'])->whereNumber('userId');",
        "Route::get('meetings', [$M, 'list']);",
        "Route::post('meetings', [$M, 'create']);",
        "Route::get('meetings/{code}', [$M, 'show']);",
        "Route::post('meetings/{code}/join', [$M, 'join']);",
        "Route::get('meetings/{code}/poll', [$M, 'poll']);",
        "Route::post('meetings/{code}/signal', [$M, 'signal']);",
        "Route::post('meetings/{code}/state', [$M, 'state']);",
        "Route::post('meetings/{code}/chat', [$M, 'chat']);",
        "Route::post('meetings/{code}/host', [$M, 'host']);",
        "Route::post('meetings/{code}/leave', [$M, 'leave']);",
        "Route::delete('meetings/{code}', [$M, 'cancel']);",
    ]) . "\n";

echo "\n== 5. Caches ==\n";
foreach (array_merge(['bootstrap/cache/config.php'], array_map(fn ($p) => str_replace("$root/", '', $p), glob("$root/bootstrap/cache/routes*.php") ?: [])) as $c) {
    if (is_file("$root/$c")) echo str_pad($c, 74) . (@unlink("$root/$c") ? 'cleared' : 'COULD NOT DELETE – delete it by hand') . "\n";
}
foreach (glob("$root/storage/framework/views/*.php") ?: [] as $v) { @unlink($v); }
if (function_exists('opcache_reset')) { opcache_reset(); echo "PHP opcache cleared\n"; }
echo "\n";
if ($problems === 0) {
    echo "ALL DONE. Open  /meetings  while signed in.\n";
    echo @unlink(__FILE__) ? "This installer deleted itself.\n" : "Please DELETE this file (install_meetings.php) now.\n";
} else {
    echo "$problems problem(s) above – fix them and run this page again (safe to repeat).\n";
}
