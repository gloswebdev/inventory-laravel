<?php
$appRoot = dirname(__DIR__) . '/invoflow';
$php = '/usr/local/bin/php8.2';
if (!file_exists($php)) $php = 'php';

$out = [];
$cmds = ['config:clear', 'cache:clear', 'view:clear', 'route:clear'];
foreach ($cmds as $cmd) {
    exec("$php $appRoot/artisan $cmd 2>&1", $lines, $code);
    $out[] = ($code === 0 ? '✅' : '❌') . " php artisan $cmd\n   " . implode("\n   ", $lines);
    $lines = [];
}

// Also manually delete bootstrap cache files
$cleared = [];
foreach (glob($appRoot . '/bootstrap/cache/*.php') as $f) {
    unlink($f);
    $cleared[] = basename($f);
}

echo '<pre style="background:#0a0a14;color:#86efac;padding:20px;font-family:monospace;font-size:13px">';
echo "=== Artisan Commands ===\n\n";
echo implode("\n\n", $out);
echo "\n\n=== Manually Deleted from bootstrap/cache/ ===\n";
echo $cleared ? implode(', ', $cleared) : 'Nothing to delete (already clean)';

// Show current DB config being used
echo "\n\n=== Current DB Config (from .env) ===\n";
$env = [];
foreach (file($appRoot . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with($line, 'DB_')) {
        $parts = explode('=', $line, 2);
        $key = $parts[0];
        $val = $parts[1] ?? '';
        if (str_contains($key, 'PASSWORD')) $val = str_repeat('*', strlen($val));
        echo "$key=$val\n";
    }
}
echo "\n\n⚠️  DELETE cc.php IMMEDIATELY AFTER USE!\n";
echo '<a href="?del=1" style="color:#f87171">→ Click here to delete cc.php</a>';
echo '</pre>';

if (isset($_GET['del'])) { unlink(__FILE__); echo '<script>document.body.innerHTML="✅ cc.php deleted!"</script>'; }
