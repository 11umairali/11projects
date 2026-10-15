<?php
/*
 | ONE-TIME INSTALLER for the "Mail Server" settings page.
 | It adds ONE route line to routes/web.php and ONE menu entry to settings.blade.php (originals are backed up first),
 | clears the cached config, and then DELETES ITSELF.
 |
 | Open:  https://your-site/install_mailserver.php?k=dahi2026
 */
if (($_GET['k'] ?? '') !== 'dahi2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
if (!is_file("$root/artisan")) { exit("Laravel root not found at $root (this file must be inside the website's public folder).\n"); }
echo "Laravel root: $root\n\n";
$problems = 0;

// 1. the new files must be there
echo "== 1. New files ==\n";
foreach ([
    'config/mailserver.php',
    'app/Support/MailServerInfo.php',
    'app/Livewire/Settings/MailServerSettings.php',
    'resources/views/settings/mail-server.blade.php',
    'resources/views/livewire/settings/mail-server-settings.blade.php',
] as $f) {
    $has = is_file("$root/$f");
    if (!$has) $problems++;
    echo str_pad($f, 70) . ($has ? 'ok' : 'MISSING  <-- upload it') . "\n";
}

function backup(string $root, string $rel, string $content): bool
{
    $dir = "$root/storage/app/backups_mailserver";
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
    return @file_put_contents($dir . '/' . str_replace('/', '_', $rel) . '.' . date('Ymd_His') . '.bak', $content) !== false;
}

// 2. patch one existing file: insert a line after a matching line (idempotent)
function patch(string $root, string $rel, string $marker, string $anchorRegex, string $newLine): string
{
    global $problems;
    $path = "$root/$rel";
    if (!is_file($path)) { $problems++; return "FILE NOT FOUND"; }
    $s = file_get_contents($path);
    if (strpos($s, $marker) !== false) return 'already done (nothing to change)';
    $nl = strpos($s, "\r\n") !== false ? "\r\n" : "\n";
    $done = false;
    $new = preg_replace_callback($anchorRegex, function ($m) use ($newLine, $nl, &$done) {
        $done = true;
        return $m[0] . $nl . $m[1] . $newLine;      // $m[1] = indentation of the anchor line
    }, $s, 1);
    if (!$done || $new === null) { $problems++; return 'ANCHOR LINE NOT FOUND – nothing changed (use MANUAL_EDITS.txt)'; }
    if (!backup($root, $rel, $s)) { $problems++; return 'could not create a backup – nothing changed'; }
    if (@file_put_contents($path, $new) === false) { $problems++; return 'COULD NOT WRITE (file permissions) – nothing changed'; }
    return 'patched (backup in storage/app/backups_mailserver)';
}

echo "\n== 2. Existing files (small edits) ==\n";
echo str_pad('routes/web.php', 70) . patch(
    $root, 'routes/web.php', "'/mail-server'",
    '~^([ \t]*)Route::get\(\'/email\',\s*function\s*\(\)\s*\{\s*return view\(\'settings\.email\'\);\s*\}\)->name\(\'email\'\);[ \t]*~m',
    "Route::get('/mail-server', function () { return view('settings.mail-server'); })->name('mail-server');"
) . "\n";
echo str_pad('settings.blade.php (menu entry)', 70) . patch(
    $root, 'resources/views/components/layouts/settings.blade.php', "/settings/mail-server",
    '~^([ \t]*)\[\'label\' => __\(\'Email Accounts\'\), \'href\' => url\(\'/settings/email\'\).*\],[ \t]*~m',
    "['label' => __('Mail Server'), 'href' => url('/settings/mail-server'), 'icon' => '<rect x=\"2\" y=\"3\" width=\"20\" height=\"8\" rx=\"2\"></rect><rect x=\"2\" y=\"13\" width=\"20\" height=\"8\" rx=\"2\"></rect><path d=\"M6 7h.01M6 17h.01\"></path>'],"
) . "\n";

// 3. cached config/routes would hide the new file / route → remove them (Laravel rebuilds them on its own)
echo "\n== 3. Caches ==\n";
foreach (['bootstrap/cache/config.php', 'bootstrap/cache/routes-v7.php'] as $c) {
    if (is_file("$root/$c")) echo str_pad($c, 70) . (@unlink("$root/$c") ? 'cleared' : 'COULD NOT DELETE – delete it by hand') . "\n";
}
foreach (glob("$root/bootstrap/cache/routes*.php") ?: [] as $c) { @unlink($c); }
if (function_exists('opcache_reset')) { opcache_reset(); echo "PHP opcache cleared\n"; }

echo "\n";
if ($problems === 0) {
    echo "ALL DONE. Open  /settings/mail-server  while signed in.\n";
    if (@unlink(__FILE__)) echo "This installer deleted itself.\n"; else echo "Please DELETE this file (install_mailserver.php) now.\n";
} else {
    echo "$problems problem(s) above – fix them and run this page again (it is safe to run repeatedly).\n";
}
