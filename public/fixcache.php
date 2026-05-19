<?php
/**
 * InvoFlow - Cache Cleaner + Log Viewer
 * Upload to public_html/fixcache.php
 * DELETE AFTER USE!
 */
define('TOKEN', 'fix123');
if (($_GET['t'] ?? '') !== TOKEN) {
    die('<form><input name="t" placeholder="token"><button>Go</button></form>');
}

$appRoot = dirname(__DIR__) . '/invoflow';
$t = $_GET['t'];

// ACTION: Clear cache files
if (isset($_GET['clear'])) {
    $cacheDir = $appRoot . '/bootstrap/cache';
    $cleared = [];
    foreach (glob($cacheDir . '/*.php') as $f) {
        $name = basename($f);
        if ($name === '.gitignore') continue;
        unlink($f);
        $cleared[] = $name;
    }
    // Clear framework cache
    $fwCache = $appRoot . '/storage/framework/cache/data';
    $views   = $appRoot . '/storage/framework/views';
    array_map('unlink', glob($fwCache . '/*'));
    array_map('unlink', glob($views . '/*.php'));
    echo "<pre style='background:#064e3b;color:#34d399;padding:16px;border-radius:8px'>";
    echo "✅ Cleared bootstrap/cache/:\n";
    foreach ($cleared as $f) echo "   - $f\n";
    echo "✅ Cleared framework/cache/data/\n";
    echo "✅ Cleared compiled views\n";
    echo "\n<a href='?t=$t' style='color:#86efac'>← Back</a></pre>";
    exit;
}

// ACTION: Show last log errors
if (isset($_GET['log'])) {
    $logFile = $appRoot . '/storage/logs/laravel.log';
    if (!file_exists($logFile)) {
        echo "<pre style='color:#f87171'>Log file nahi mila: $logFile</pre>";
        exit;
    }
    // Get last 8000 chars
    $size = filesize($logFile);
    $fp = fopen($logFile, 'r');
    fseek($fp, max(0, $size - 8000));
    $content = fread($fp, 8000);
    fclose($fp);
    // Extract last ERROR block
    preg_match_all('/\[\d{4}-\d{2}-\d{2}[^\]]+\] \w+\.ERROR:.+?(?=\[\d{4}|\z)/s', $content, $m);
    $errors = $m[0] ?? [];
    $last = end($errors);
    // Trim stacktrace - show first 30 lines only
    if ($last) {
        $lines = explode("\n", $last);
        $trimmed = array_slice($lines, 0, 20);
        $last = implode("\n", $trimmed) . "\n... (trimmed)";
    }
    echo "<pre style='background:#0a0a14;color:#f87171;padding:16px;border-radius:8px;font-size:12px;white-space:pre-wrap;overflow-wrap:break-word'>";
    echo htmlspecialchars($last ?: "No ERROR entries found in last 8000 chars of log");
    echo "</pre>";
    echo "<a href='?t=$t' style='color:#818cf8'>← Back</a>";
    exit;
}

// ACTION: Delete self
if (isset($_GET['del'])) {
    unlink(__FILE__);
    echo "<div style='color:#34d399;font-family:monospace;padding:20px'>✅ fixcache.php deleted!</div>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>InvoFlow Fix Cache</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',monospace;background:#0f0f1a;color:#e5e7eb;padding:24px}
  .wrap{max-width:560px;margin:0 auto}
  h1{color:#a5b4fc;font-size:20px;margin-bottom:4px}
  p{color:#6b7280;font-size:13px;margin-bottom:24px}
  .btn{display:block;padding:14px 18px;border-radius:10px;font-size:14px;font-weight:600;margin-bottom:12px;text-decoration:none;transition:.15s}
  .blue{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff}
  .green{background:linear-gradient(135deg,#059669,#047857);color:#fff}
  .red{background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff}
  .btn:hover{opacity:.85}
  .info{font-size:12px;color:#6b7280;margin-top:4px;margin-bottom:16px;padding-left:4px}
</style>
</head>
<body>
<div class="wrap">
  <h1>🔧 InvoFlow Fix Cache</h1>
  <p>Cache clear karo ya latest error dekho</p>

  <a href="?t=<?= TOKEN ?>&clear=1" class="btn green">🧹 Clear All Cache (bootstrap + views + data)</a>
  <div class="info">bootstrap/cache/*.php aur compiled views delete honge</div>

  <a href="?t=<?= TOKEN ?>&log=1" class="btn blue">📋 Show Latest Error from laravel.log</a>
  <div class="info">Last error entry dikhayega — 500 ka exact reason</div>

  <a href="?t=<?= TOKEN ?>&del=1" class="btn red" onclick="return confirm('fixcache.php delete karna chahte ho?')">🗑️ Delete This File (Security!)</a>
</div>
</body>
</html>
