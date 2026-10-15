<?php
/*
 | ONE-TIME FIX for the website menu: renames "Network"/"People" to "Friends" and puts Friends, Meetings, Inbox first.
 | It also prints what is installed (so I can see exactly which update reached the website). Safe to repeat.
 | Open:  https://dahimail.com/fix_menu.php?k=dahi2026
 */
if (($_GET['k'] ?? '') !== 'dahi2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
if (!is_file("$root/artisan")) { exit("Laravel root not found at $root\n"); }
$problems = 0;

function bak(string $root, string $rel, string $old): void { $d = "$root/storage/app/backups_menu"; @mkdir($d, 0775, true); @file_put_contents($d . '/' . str_replace('/', '_', $rel) . '.' . date('Ymd_His') . '.bak', $old); }

echo "== 1. What is installed on the website ==\n";
$marks = [
    ['app/Services/Friends/FriendFinder.php', null, 'update 5: friend search'],
    ['resources/views/friends/index.blade.php', 'friendSearch', 'update 5: search box on the Friends page'],
    ['public/js/friend-chat.js', 'openViewer', 'update 5: photo viewer in chat'],
    ['public/js/friend-chat.js', "'Download'", 'update 7: Download in the message menu'],
    ['app/Services/Friends/FriendChatService.php', 'function recent', 'update 6: chat list'],
    ['app/Services/Friends/FriendChatService.php', 'realMime', 'update 5: file types from file names'],
    ['resources/views/settings/privacy.blade.php', null, 'update 4: privacy page'],
    ['app/Services/Friends/PresenceService.php', null, 'update 2: online / last seen'],
];
foreach ($marks as [$rel, $needle, $label]) {
    $p = "$root/$rel";
    $ok = is_file($p) && ($needle === null || strpos((string) file_get_contents($p), $needle) !== false);
    echo str_pad($label, 52) . ($ok ? 'yes' : 'NO  <-- not uploaded / not installed') . "\n";
}

echo "\n== 2. Website menu ==\n";
$rel = 'resources/views/components/layouts/app.blade.php';
$path = "$root/$rel";
$s = is_file($path) ? file_get_contents($path) : '';
if ($s === '') { $problems++; echo "layout file not found\n"; }
else {
    $lines = preg_split('/(?<=\n)/', $s);
    $start = null; $end = null;
    foreach ($lines as $i => $l) { if ($start === null && strpos($l, '$navItems = [') !== false) $start = $i; elseif ($start !== null && preg_match('/^\s*\];/', $l)) { $end = $i; break; } }
    if ($start === null || $end === null) { $problems++; echo "menu list not found in the layout file – nothing changed\n"; }
    else {
        echo "menu entries now: ";
        $labels = [];
        for ($i = $start + 1; $i < $end; $i++) if (preg_match("/\\['label' => (?:__\\()?'([^']+)'/", $lines[$i], $m)) $labels[] = $m[1];
        echo implode(' | ', $labels) . "\n";
        $pick = ['friends' => null, 'meetings' => null, 'inbox' => null];
        for ($i = $start + 1; $i < $end; $i++) {
            if (preg_match("/\\['label' => __\\('(People|Network|Friends)'\\)/", $lines[$i])) $pick['friends'] = $i;
            elseif (preg_match("/\\['label' => __\\('Meetings'\\)/", $lines[$i])) $pick['meetings'] = $i;
            elseif (preg_match("/\\['label' => __\\('Inbox'\\)/", $lines[$i])) $pick['inbox'] = $i;
        }
        $missing = array_keys(array_filter($pick, fn ($v) => $v === null));
        if ($missing) { echo 'not found in the menu: ' . implode(', ', $missing) . " (the earlier installers did not add them) – only the ones found are moved\n"; }
        $found = array_filter($pick, fn ($v) => $v !== null);
        if ($found) {
            $moved = [];
            foreach (['friends', 'meetings', 'inbox'] as $k) { if (!isset($found[$k])) continue; $l = $lines[$found[$k]]; if ($k === 'friends') $l = preg_replace("/__\\('(People|Network|Friends)'\\)/", "__('Friends')", $l, 1); $moved[] = $l; }
            $rm = array_values($found); rsort($rm);
            foreach ($rm as $idx) array_splice($lines, $idx, 1);
            array_splice($lines, $start + 1, 0, $moved);
            $new = implode('', $lines);
            if ($new === $s) echo "menu order: already Friends, Meetings, Inbox first\n";
            else { bak($root, $rel, $s); echo @file_put_contents($path, $new) !== false ? "menu order + name: changed (backup in storage/app/backups_menu)\n" : "COULD NOT WRITE the layout file\n"; }
        }
    }
}

echo "\n== 3. Page titles: Network / People -> Friends ==\n";
foreach (['resources/views/friends/index.blade.php', 'resources/views/friends/person.blade.php', 'resources/views/friends/calls.blade.php', 'resources/views/friends/chat.blade.php',
          'resources/views/livewire/friends/friends-hub.blade.php', 'resources/views/livewire/friends/person-row.blade.php', 'resources/views/meetings/index.blade.php'] as $rel) {
    $p = "$root/$rel";
    if (!is_file($p)) { echo str_pad($rel, 66) . "not there\n"; continue; }
    $o = file_get_contents($p);
    $n = str_replace(["__('Network')", "__('People')", 'Back to Network', 'Back to People'], ["__('Friends')", "__('Friends')", 'Back to Friends', 'Back to Friends'], $o);
    if ($n === $o) { echo str_pad($rel, 66) . "ok\n"; continue; }
    bak($root, $rel, $o);
    echo str_pad($rel, 66) . (@file_put_contents($p, $n) !== false ? 'renamed' : 'COULD NOT WRITE') . "\n";
}

foreach (glob("$root/storage/framework/views/*.php") ?: [] as $v) { @unlink($v); }
if (function_exists('opcache_reset')) opcache_reset();
echo "\nview cache cleared. Reload the site with Ctrl+F5.\n";
echo $problems === 0 ? "Done. (delete this file when you are happy)\n" : "$problems problem(s) – send me this text.\n";
