<?php
/**
 * InvoFlow — Hostinger Browser Installer
 * Upload to: public_html/installer.php
 * Open:      https://yourdomain.com/installer.php
 * DELETE after use!
 */

// ── Security token ────────────────────────────────────────────────────────────
// Change this before uploading!
define('INSTALL_TOKEN', 'invoflow2024');

// ── App root (invoflow/ folder — one level above public_html) ─────────────────
$appRoot  = dirname(__DIR__) . '/invoflow';
$pubHtml  = __DIR__;
$token    = $_GET['token'] ?? $_POST['token'] ?? '';

// ── Auth check ────────────────────────────────────────────────────────────────
if ($token !== INSTALL_TOKEN) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvoFlow Installer</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:#0f0f1a;display:flex;align-items:center;justify-content:center;min-height:100vh}
  .card{background:#1a1a2e;border:1px solid #4f46e5;border-radius:16px;padding:40px;width:360px;text-align:center}
  h1{color:#a5b4fc;font-size:22px;margin-bottom:8px}
  p{color:#6b7280;font-size:13px;margin-bottom:24px}
  input{width:100%;padding:12px 16px;background:#0f0f1a;border:1px solid #374151;border-radius:8px;color:#fff;font-size:14px;margin-bottom:16px;outline:none}
  input:focus{border-color:#4f46e5}
  button{width:100%;padding:12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
</style>
</head>
<body>
<div class="card">
  <h1>🚀 InvoFlow Installer</h1>
  <p>Enter your installer token to continue</p>
  <form method="GET">
    <input type="password" name="token" placeholder="Installer Token" required autofocus>
    <button type="submit">Unlock →</button>
  </form>
</div>
</body></html>
<?php exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvoFlow Installer</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:#0f0f1a;color:#e5e7eb;min-height:100vh;padding:24px}
  .wrap{max-width:820px;margin:0 auto}
  h1{font-size:26px;color:#a5b4fc;margin-bottom:4px}
  .sub{color:#6b7280;font-size:13px;margin-bottom:28px}
  .card{background:#1a1a2e;border:1px solid #2d2d4e;border-radius:14px;padding:28px;margin-bottom:20px}
  .card h2{font-size:15px;color:#c4b5fd;margin-bottom:18px;display:flex;align-items:center;gap:8px}
  .step{display:flex;align-items:flex-start;gap:14px;padding:14px 0;border-bottom:1px solid #1f1f35}
  .step:last-child{border-bottom:none}
  .badge{min-width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
  .ok{background:#064e3b;color:#34d399}
  .fail{background:#450a0a;color:#f87171}
  .warn{background:#451a03;color:#fb923c}
  .info{background:#1e1b4b;color:#818cf8}
  .desc{flex:1}
  .desc strong{display:block;font-size:14px;margin-bottom:3px}
  .desc span{font-size:12px;color:#9ca3af}
  pre{background:#0a0a14;border:1px solid #2d2d4e;border-radius:8px;padding:14px;font-size:12px;color:#86efac;overflow-x:auto;margin-top:10px;white-space:pre-wrap;word-break:break-all}
  .run-btn{margin-top:20px;display:inline-block}
  button,a.btn{padding:11px 24px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
  button:hover,a.btn:hover{opacity:.9}
  .danger{background:linear-gradient(135deg,#dc2626,#991b1b)}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:600px){.grid{grid-template-columns:1fr}}
  label{display:block;font-size:12px;color:#9ca3af;margin-bottom:5px}
  input[type=text],input[type=password],select{width:100%;padding:10px 14px;background:#0f0f1a;border:1px solid #374151;border-radius:8px;color:#fff;font-size:13px;margin-bottom:14px}
  .alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px}
  .alert-ok{background:#064e3b33;border:1px solid #34d399;color:#34d399}
  .alert-fail{background:#450a0a33;border:1px solid #f87171;color:#f87171}
  .sep{height:1px;background:#2d2d4e;margin:20px 0}
</style>
</head>
<body>
<div class="wrap">
  <h1>🚀 InvoFlow Installer</h1>
  <p class="sub">Hostinger Shared Hosting Setup | Token: <?= htmlspecialchars($token) ?> | <a href="?token=<?= urlencode($token) ?>" style="color:#818cf8">Refresh</a></p>

<?php

// ── Helper functions ──────────────────────────────────────────────────────────
function runArtisan(string $appRoot, string $cmd): array {
    $phpBin = findPhp();
    $artisan = $appRoot . '/artisan';
    $full    = "$phpBin $artisan $cmd 2>&1";
    exec($full, $out, $code);
    return ['output' => implode("\n", $out), 'code' => $code];
}

function findPhp(): string {
    foreach (['/usr/local/bin/php8.2','/usr/local/php82/bin/php','/usr/bin/php8.2','/usr/bin/php'] as $p) {
        if (file_exists($p)) return $p;
    }
    return 'php';
}

function stepRow(string $icon, string $cls, string $title, string $detail, string $pre = ''): void {
    echo "<div class='step'>";
    echo "<div class='badge $cls'>$icon</div>";
    echo "<div class='desc'><strong>$title</strong><span>$detail</span>";
    if ($pre) echo "<pre>" . htmlspecialchars($pre) . "</pre>";
    echo "</div></div>";
}

// ── ACTION HANDLER ────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
$actionResult = '';

if ($action === 'fix_index') {
    $indexFile = $pubHtml . '/index.php';
    if (file_exists($indexFile)) {
        $content = file_get_contents($indexFile);
        // Replace local path with Hostinger path
        $new = preg_replace(
            '/\$appRoot\s*=\s*dirname\(__DIR__\)\s*;/',
            "\$appRoot = dirname(__DIR__) . '/invoflow';",
            $content
        );
        if ($new !== $content) {
            file_put_contents($indexFile, $new);
            $actionResult = '<div class="alert alert-ok">✅ index.php patched! $appRoot ab invoflow/ ko point karta hai.</div>';
        } else {
            $actionResult = '<div class="alert alert-ok">ℹ️ index.php already correct hai (ya manually patched hai).</div>';
        }
    } else {
        $actionResult = '<div class="alert alert-fail">❌ index.php nahi mila at ' . htmlspecialchars($indexFile) . '</div>';
    }
}

if ($action === 'storage_link') {
    $target = $appRoot . '/storage/app/public';
    $link   = $pubHtml . '/storage';
    if (is_link($link)) {
        unlink($link);
    }
    if (!file_exists($target)) {
        @mkdir($target, 0755, true);
    }
    if (@symlink($target, $link)) {
        $actionResult = '<div class="alert alert-ok">✅ Storage symlink banaya: public_html/storage → invoflow/storage/app/public</div>';
    } else {
        $actionResult = '<div class="alert alert-fail">❌ Symlink nahi bana. SSH se run karo: php artisan storage:link</div>';
    }
}

if ($action === 'artisan_cmd') {
    $cmd    = trim($_POST['artisan_cmd'] ?? '');
    $allowed = ['migrate --force','migrate:fresh --force','config:cache','route:cache','view:cache',
                 'cache:clear','config:clear','route:clear','view:clear','key:generate --force',
                 'optimize','optimize:clear','queue:table','session:table'];
    if (in_array($cmd, $allowed)) {
        $r = runArtisan($appRoot, $cmd);
        $cls = $r['code'] === 0 ? 'alert-ok' : 'alert-fail';
        $ico = $r['code'] === 0 ? '✅' : '❌';
        $actionResult = "<div class='alert $cls'>$ico php artisan $cmd<pre style='margin-top:8px;background:transparent;border:none;padding:0'>" . htmlspecialchars($r['output']) . "</pre></div>";
    } else {
        $actionResult = '<div class="alert alert-fail">❌ Command not allowed: ' . htmlspecialchars($cmd) . '</div>';
    }
}

if ($action === 'optimize_all') {
    $cmds   = ['config:clear','route:clear','view:clear','cache:clear','config:cache','route:cache','view:cache'];
    $output = '';
    foreach ($cmds as $cmd) {
        $r = runArtisan($appRoot, $cmd);
        $ico = $r['code'] === 0 ? '✅' : '❌';
        $output .= "$ico php artisan $cmd\n";
        if (trim($r['output'])) $output .= "   " . $r['output'] . "\n";
    }
    $actionResult = "<div class='alert alert-ok'><pre style='background:transparent;border:none;padding:0'>" . htmlspecialchars($output) . "</pre></div>";
}

if ($action === 'set_permissions') {
    $dirs = [$appRoot . '/storage', $appRoot . '/bootstrap/cache'];
    $out  = '';
    foreach ($dirs as $d) {
        if (is_dir($d)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) { @chmod($f->getPathname(), $f->isDir() ? 0755 : 0644); }
            @chmod($d, 0755);
            $out .= "✅ $d → 755\n";
        } else {
            $out .= "❌ Not found: $d\n";
        }
    }
    $actionResult = "<div class='alert alert-ok'><pre style='background:transparent;border:none;padding:0'>" . htmlspecialchars($out) . "</pre></div>";
}

echo $actionResult;

// ── SECTION 1: System Requirements ───────────────────────────────────────────
echo "<div class='card'><h2>🔍 System Requirements</h2>";

$checks = [
    ['PHP >= 8.2', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION],
    ['PDO MySQL',  extension_loaded('pdo_mysql'),  ''],
    ['mbstring',   extension_loaded('mbstring'),   ''],
    ['openssl',    extension_loaded('openssl'),     ''],
    ['fileinfo',   extension_loaded('fileinfo'),    ''],
    ['zip',        extension_loaded('zip'),         ''],
    ['xml',        extension_loaded('xml'),         ''],
    ['invoflow/ exists', is_dir($appRoot),          $appRoot],
    ['vendor/ exists',   is_dir($appRoot.'/vendor'),$appRoot.'/vendor'],
    ['.env exists',      file_exists($appRoot.'/.env'), $appRoot.'/.env'],
    ['storage/ writable',is_writable($appRoot.'/storage'), ''],
    ['bootstrap/cache/ writable', is_writable($appRoot.'/bootstrap/cache'), ''],
];

foreach ($checks as [$label, $pass, $detail]) {
    $icon = $pass ? '✓' : '✗';
    $cls  = $pass ? 'ok' : 'fail';
    stepRow($icon, $cls, $label, $detail ?: ($pass ? 'OK' : 'MISSING / FAILED'));
}
echo "</div>";

// ── SECTION 2: Quick Actions ──────────────────────────────────────────────────
echo "<div class='card'><h2>⚡ Quick Actions</h2>";

$actions = [
    ['fix_index',       '🔧 Fix index.php',      'public_html/index.php mein $appRoot path fix karo (Hostinger version)'],
    ['storage_link',    '🔗 Storage Link',        'public_html/storage → invoflow/storage/app/public symlink banao'],
    ['set_permissions', '🔐 Fix Permissions',     'storage/ aur bootstrap/cache/ ko 755 karo'],
    ['optimize_all',    '🚀 Optimize Cache',      'Config + Route + View cache clear aur rebuild karo'],
];

echo "<div class='grid'>";
foreach ($actions as [$act, $label, $desc]) {
    echo "<form method='POST' style='margin:0'>
            <input type='hidden' name='token' value='" . htmlspecialchars($token) . "'>
            <input type='hidden' name='action' value='$act'>
            <button type='submit' style='width:100%;text-align:left;padding:14px 18px'>
              <div style='font-size:15px;margin-bottom:4px'>$label</div>
              <div style='font-size:11px;opacity:.7;font-weight:400'>$desc</div>
            </button>
          </form>";
}
echo "</div></div>";

// ── SECTION 3: Artisan Commands ───────────────────────────────────────────────
$artisanCmds = [
    'migrate --force'       => '🗄️ Run Migrations',
    'migrate:fresh --force' => '⚠️ Fresh Migrate (DATA DELETE)',
    'key:generate --force'  => '🔑 Generate APP_KEY',
    'config:cache'          => '📦 Config Cache',
    'route:cache'           => '🛣️ Route Cache',
    'view:cache'            => '🖼️ View Cache',
    'optimize'              => '⚡ Optimize All',
    'optimize:clear'        => '🧹 Clear All Cache',
    'queue:table'           => '📋 Queue Table',
    'session:table'         => '🔒 Session Table',
];

echo "<div class='card'><h2>🎮 Artisan Commands</h2>";
echo "<form method='POST'>
        <input type='hidden' name='token' value='" . htmlspecialchars($token) . "'>
        <input type='hidden' name='action' value='artisan_cmd'>
        <label>Command select karo:</label>
        <select name='artisan_cmd' style='margin-bottom:14px'>";
foreach ($artisanCmds as $cmd => $label) {
    echo "<option value='$cmd'>$label — php artisan $cmd</option>";
}
echo "</select><button type='submit'>▶ Run Command</button>
      </form></div>";

// ── SECTION 4: .env Viewer ───────────────────────────────────────────────────
echo "<div class='card'><h2>📄 .env Status</h2>";
$envFile = $appRoot . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $safe = [];
    foreach ($envLines as $line) {
        if (str_starts_with($line, '#')) { $safe[] = $line; continue; }
        if (str_contains($line, 'PASSWORD') || str_contains($line, 'KEY') || str_contains($line, 'SECRET')) {
            $parts = explode('=', $line, 2);
            $safe[] = $parts[0] . '=***hidden***';
        } else {
            $safe[] = $line;
        }
    }
    echo "<pre>" . htmlspecialchars(implode("\n", $safe)) . "</pre>";
} else {
    echo "<div class='alert alert-fail'>❌ .env nahi mila at $envFile<br>HOSTINGER_DEPLOY_GUIDE.md ka STEP 6 dekho.</div>";
}
echo "</div>";

// ── SECTION 5: PHP Info ───────────────────────────────────────────────────────
echo "<div class='card'><h2>ℹ️ Server Info</h2>";
$info = [
    'PHP Version'    => PHP_VERSION,
    'PHP Binary'     => findPhp(),
    'Server'         => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Document Root'  => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Script Dir'     => __DIR__,
    'App Root'       => $appRoot,
    'App Root Exists'=> is_dir($appRoot) ? '✅ YES' : '❌ NO',
    'Disk Free'      => function_exists('disk_free_space') ? round(disk_free_space('/') / 1024 / 1024, 0) . ' MB free' : 'N/A',
    'Memory Limit'   => ini_get('memory_limit'),
    'Max Exec Time'  => ini_get('max_execution_time') . 's',
    'Upload Max'     => ini_get('upload_max_filesize'),
];
echo "<div class='grid'>";
foreach ($info as $k => $v) {
    echo "<div class='step' style='padding:10px 0'><div class='desc'><strong style='color:#c4b5fd'>$k</strong><span>$v</span></div></div>";
}
echo "</div></div>";

// ── DANGER ZONE ───────────────────────────────────────────────────────────────
echo "<div class='card' style='border-color:#7f1d1d'>
  <h2 style='color:#fca5a5'>⚠️ DANGER ZONE</h2>
  <div class='step'>
    <div class='badge fail'>!</div>
    <div class='desc'>
      <strong>Delete This File After Use</strong>
      <span>installer.php publicly accessible hai — use ke baad ZAROOR delete karo!</span>
    </div>
  </div>
  <div style='margin-top:16px'>
    <a class='btn danger' href='?token=" . urlencode($token) . "&delete=1' onclick=\"return confirm('installer.php delete karna chahte ho?')\">🗑️ Delete installer.php Now</a>
  </div>
</div>";

// Delete self
if (isset($_GET['delete']) && $_GET['delete'] === '1' && $token === INSTALL_TOKEN) {
    unlink(__FILE__);
    echo "<div class='alert alert-ok' style='margin-top:16px'>✅ installer.php delete ho gaya! Sab kuch ready hai 🎉</div>";
}
?>

</div><!-- .wrap -->
</body>
</html>
