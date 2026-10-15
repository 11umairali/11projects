<?php
/*
 | DIAGNOSTIC for "call gives an error". Open:
 |   https://your-site/diag_calls.php?k=dahi2026&me=1&friend=2        (me = your user id, friend = a friend's user id)
 | It starts a test video call inside a transaction (rolled back, nothing is kept) and prints the REAL error, the columns it
 | needs, and the last errors of the Laravel log. Send me the whole text. DELETE this file afterwards.
 */
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (($_GET['k'] ?? '') !== 'dahi2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
require "$root/vendor/autoload.php";
$app = require "$root/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "== tables / columns ==\n";
$need = ['friend_calls' => ['video', 'meeting_code', 'group_invite', 'audio_only'], 'meetings' => ['code', 'kind', 'host_id'], 'meeting_participants' => ['meeting_id'], 'meeting_invitees' => ['meeting_id'],
         'meeting_signals' => ['meeting_id'], 'meeting_messages' => ['meeting_id'], 'user_presence' => ['user_id', 'read_receipts'], 'friend_messages' => ['edited_at', 'deleted_at', 'delivered_at', 'reacted_at', 'forwarded'],
         'friend_message_reactions' => ['message_id'], 'friend_call_clears' => ['user_id']];
foreach ($need as $t => $cols) {
    if (!Schema::hasTable($t)) { echo str_pad($t, 28) . "TABLE MISSING\n"; continue; }
    $miss = array_filter($cols, fn ($c) => !Schema::hasColumn($t, $c));
    echo str_pad($t, 28) . ($miss ? 'missing columns: ' . implode(', ', $miss) : 'ok') . "\n";
}
echo "\n== test call (rolled back) ==\n";
try {
    $me = \App\Models\User::findOrFail((int) ($_GET['me'] ?? 0));
    $fr = (int) ($_GET['friend'] ?? 0);
    auth()->setUser($me);
    DB::beginTransaction();
    foreach ([['video', true], ['audio', false]] as [$label, $video]) {
        try {
            [$ok, $msg, $data] = app(\App\Services\Friends\FriendCallService::class)->start($me, $fr, $video);
            echo "$label call: " . ($ok ? 'OK' : 'refused') . " – $msg\n";
        } catch (Throwable $e) {
            echo "$label call: EXCEPTION " . get_class($e) . ': ' . $e->getMessage() . "\n   at " . $e->getFile() . ':' . $e->getLine() . "\n";
            foreach (array_slice($e->getTrace(), 0, 6) as $t) echo '   ← ' . ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?') . ' ' . ($t['function'] ?? '') . "\n";
        }
        DB::table('friend_calls')->where('caller_id', $me->id)->whereIn('status', ['ringing'])->update(['status' => 'cancelled']);
    }
    DB::rollBack();
} catch (Throwable $e) { try { DB::rollBack(); } catch (Throwable $x) {} echo 'setup problem: ' . $e->getMessage() . "\n"; }
echo "\n== last errors in storage/logs/laravel.log ==\n";
$log = glob("$root/storage/logs/laravel*.log");
if ($log) { usort($log, fn ($a, $b) => filemtime($b) <=> filemtime($a)); $tail = substr((string) file_get_contents($log[0], false, null, max(0, filesize($log[0]) - 30000)), -30000);
    preg_match_all('/^\[\d{4}-[^\n]*(?:ERROR|CRITICAL)[^\n]*/m', $tail, $m); foreach (array_slice($m[0], -8) as $l) echo substr($l, 0, 400) . "\n"; }
else echo "(no log file)\n";
