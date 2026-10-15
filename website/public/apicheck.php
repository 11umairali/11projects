<?php
// TEMPORARY diagnostic v3 – upload to the website's public folder, open:
//   https://dahimail.com/apicheck.php?k=dahi2026            -> shows the real reason sign-up fails
//   https://dahimail.com/apicheck.php?k=dahi2026&reset=1    -> also clears PHP opcache first
// DELETE THE FILE when done.
if (($_GET['k'] ?? '') !== 'dahi2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
if (isset($_GET['reset']) && function_exists('opcache_reset')) { echo 'opcache_reset: ' . (opcache_reset() ? 'done' : 'failed') . "\n\n"; }

$root = dirname(__DIR__);
if (!is_file("$root/artisan")) { exit("Laravel root not found at $root\n"); }
echo "Laravel root: $root\n\n=== LAST SIGN-UP / HEALTH ERRORS FROM storage/logs ===\n";

$logs = glob("$root/storage/logs/*.log") ?: [];
usort($logs, fn ($a, $b) => filemtime($b) <=> filemtime($a));
if (!$logs) { echo "No log files found in storage/logs.\n"; }
else {
  $file = $logs[0];
  echo 'File: ' . basename($file) . '  (modified ' . date('Y-m-d H:i:s', filemtime($file)) . ")\n\n";
  $size = filesize($file);
  $fh = fopen($file, 'r'); fseek($fh, max(0, $size - 600000)); $tail = stream_get_contents($fh); fclose($fh);
  $hits = [];
  foreach (preg_split('/\R/', $tail) as $line) {
    if (preg_match('/Mobile registration failed|Health check failed|Mobile account provisioning failed|Username check failed|Auto workspace\/mailbox provisioning failed|CyberPanel|SQLSTATE/i', $line)) { $hits[] = $line; }
  }
  if (!$hits) { echo "No matching lines in the last part of the log. Try the sign-up in the app once, then reload this page.\n"; }
  foreach (array_slice($hits, -8) as $h) {
    // hide anything that looks like a secret/hash before printing
    $h = preg_replace('/\$2[aby]\$[0-9]{2}\$[.\/A-Za-z0-9]{40,}/', '[hash]', $h);
    $h = preg_replace('/(password|token|secret)[^,)]*/i', '$1=[hidden]', $h);
    echo mb_substr($h, 0, 600) . "\n\n";
  }
}
echo "DELETE THIS FILE NOW.\n";