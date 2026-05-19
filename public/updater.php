<?php
/**
 * InvoFlow — Version Updater
 * Upload to: public_html/updater.php
 * Open:      https://yourdomain.com/updater.php
 * DELETE after use!
 */

define('UPDATER_TOKEN', 'invoflow_update_2024');

$appRoot = dirname(__DIR__) . '/invoflow';
$pubHtml = __DIR__;
$token   = $_GET['token'] ?? $_POST['token'] ?? '';

// ── Auth ─────────────────────────────────────────────────────────────────────
if ($token !== UPDATER_TOKEN) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>InvoFlow Updater</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:#0f0f1a;display:flex;align-items:center;justify-content:center;min-height:100vh}
  .card{background:#1a1a2e;border:1px solid #4f46e5;border-radius:16px;padding:40px;width:380px;text-align:center}
  h1{color:#a5b4fc;font-size:22px;margin-bottom:6px}
  p{color:#6b7280;font-size:13px;margin-bottom:24px}
  input{width:100%;padding:12px 16px;background:#0f0f1a;border:1px solid #374151;border-radius:8px;color:#fff;font-size:14px;margin-bottom:16px;outline:none}
  input:focus{border-color:#4f46e5}
  button{width:100%;padding:12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
</style>
</head>
<body>
<div class="card">
  <h1>🔄 InvoFlow Updater</h1>
  <p>Version update karne ke liye token enter karo</p>
  <form method="GET">
    <input type="password" name="token" placeholder="Updater Token" required autofocus>
    <button type="submit">Unlock →</button>
  </form>
</div>
</body></html>
<?php exit; }

// ── Helpers ───────────────────────────────────────────────────────────────────
function findPhp(): string {
    foreach (['/usr/local/bin/php8.2','/usr/local/php82/bin/php','/usr/bin/php8.2','/usr/bin/php'] as $p) {
        if (file_exists($p)) return $p;
    }
    return 'php';
}
function runArtisan(string $appRoot, string $cmd): array {
    $php = findPhp();
    exec("$php $appRoot/artisan $cmd 2>&1", $out, $code);
    return ['output' => implode("\n", $out), 'code' => $code];
}
function getVersion(string $appRoot): array {
    $f = $appRoot . '/version.json';
    if (!file_exists($f)) return ['version'=>'unknown','release_date'=>'—','codename'=>'—','changelog'=>[],'requires_migration'=>false];
    return json_decode(file_get_contents($f), true) ?? [];
}
function humanSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes/1048576,2).' MB';
    return round($bytes/1024,1).' KB';
}
function row(string $icon, string $cls, string $title, string $detail, string $pre=''): void {
    echo "<div class='step'><div class='badge $cls'>$icon</div>";
    echo "<div class='desc'><strong>$title</strong><span>".htmlspecialchars($detail)."</span>";
    if ($pre) echo "<pre>".htmlspecialchars($pre)."</pre>";
    echo "</div></div>";
}

// ── ACTIONS ───────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
$msg    = '';

// --- Upload & Extract Update ZIP ---
if ($action === 'upload_update') {
    $upDir = $appRoot . '/_update_tmp';
    if (!is_dir($upDir)) mkdir($upDir, 0755, true);

    if (!isset($_FILES['update_zip']) || $_FILES['update_zip']['error'] !== UPLOAD_ERR_OK) {
        $msg = "<div class='alert alert-fail'>❌ File upload failed. Check PHP upload_max_filesize & post_max_size.</div>";
    } else {
        $zipPath = $upDir . '/update_' . time() . '.zip';
        move_uploaded_file($_FILES['update_zip']['tmp_name'], $zipPath);

        if (!extension_loaded('zip')) {
            $msg = "<div class='alert alert-fail'>❌ PHP zip extension nahi hai! Hostinger hPanel → PHP Extensions mein enable karo.</div>";
        } else {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) === true) {
                $extractTo = $upDir . '/extracted';
                if (is_dir($extractTo)) { // cleanup old
                    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractTo, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                    foreach ($it as $f) $f->isDir() ? rmdir($f) : unlink($f);
                    rmdir($extractTo);
                }
                mkdir($extractTo, 0755, true);
                $zip->extractTo($extractTo);
                $zip->close();

                // Smart copy: extracted → $appRoot (skip .env, storage/)
                $skip = ['.env', 'storage'];
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($extractTo, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                $copied = 0; $skipped = 0;
                foreach ($iterator as $item) {
                    $rel = ltrim(str_replace($extractTo, '', $item->getPathname()), '/\\');
                    $topLevel = explode('/', str_replace('\\','/',$rel))[0];
                    if (in_array($topLevel, $skip)) { $skipped++; continue; }
                    $dest = $appRoot . DIRECTORY_SEPARATOR . $rel;
                    if ($item->isDir()) { if (!is_dir($dest)) mkdir($dest, 0755, true); }
                    else { copy($item->getPathname(), $dest); $copied++; }
                }
                $msg = "<div class='alert alert-ok'>✅ Update extracted! <strong>$copied files</strong> copied, <strong>$skipped</strong> skipped (.env & storage protected).</div>";
                // Cleanup temp
                unlink($zipPath);
            } else {
                $msg = "<div class='alert alert-fail'>❌ ZIP open nahi hua. Corrupt file ya invalid format.</div>";
            }
        }
    }
}

// --- Run Migrations ---
if ($action === 'run_migrations') {
    $r = runArtisan($appRoot, 'migrate --force');
    $cls = $r['code'] === 0 ? 'alert-ok' : 'alert-fail';
    $ico = $r['code'] === 0 ? '✅' : '❌';
    $msg = "<div class='alert $cls'>$ico Migrations: <pre style='margin-top:8px;background:transparent;border:none;padding:0'>"
         . htmlspecialchars($r['output']) . "</pre></div>";
}

// --- Clear & Rebuild Cache ---
if ($action === 'optimize') {
    $cmds = ['config:clear','route:clear','view:clear','cache:clear','config:cache','route:cache','view:cache'];
    $out = '';
    foreach ($cmds as $cmd) {
        $r = runArtisan($appRoot, $cmd);
        $ico = $r['code'] === 0 ? '✅' : '❌';
        $out .= "$ico php artisan $cmd\n";
        if (trim($r['output'])) $out .= "   ".$r['output']."\n";
    }
    $msg = "<div class='alert alert-ok'><pre style='background:transparent;border:none;padding:0'>".htmlspecialchars($out)."</pre></div>";
}

// --- Save New version.json ---
if ($action === 'save_version') {
    $newVer = trim($_POST['new_version'] ?? '');
    $newDate = date('Y-m-d');
    $newCodename = trim($_POST['codename'] ?? '');
    $changelogRaw = trim($_POST['changelog'] ?? '');
    $changelogArr = array_filter(array_map('trim', explode("\n", $changelogRaw)));
    $requiresMigration = isset($_POST['requires_migration']);

    if (!preg_match('/^\d+\.\d+\.\d+$/', $newVer)) {
        $msg = "<div class='alert alert-fail'>❌ Version format galat hai. Use: 1.2.3</div>";
    } else {
        $versionData = [
            'version'           => $newVer,
            'release_date'      => $newDate,
            'codename'          => $newCodename,
            'changelog'         => array_values($changelogArr),
            'requires_migration'=> $requiresMigration,
        ];
        file_put_contents($appRoot . '/version.json', json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $msg = "<div class='alert alert-ok'>✅ version.json updated to v$newVer!</div>";
    }
}

// --- Fix Permissions ---
if ($action === 'fix_perms') {
    $dirs = [$appRoot.'/storage', $appRoot.'/bootstrap/cache'];
    $out = '';
    foreach ($dirs as $d) {
        if (is_dir($d)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) @chmod($f->getPathname(), $f->isDir() ? 0755 : 0644);
            @chmod($d, 0755);
            $out .= "✅ $d → 755\n";
        } else { $out .= "❌ Not found: $d\n"; }
    }
    $msg = "<div class='alert alert-ok'><pre style='background:transparent;border:none;padding:0'>".htmlspecialchars($out)."</pre></div>";
}

$currentVer = getVersion($appRoot);
$tokenEnc   = urlencode($token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>InvoFlow Updater v<?= htmlspecialchars($currentVer['version'] ?? '?') ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:#0f0f1a;color:#e5e7eb;min-height:100vh;padding:24px}
  .wrap{max-width:860px;margin:0 auto}
  h1{font-size:26px;color:#a5b4fc;margin-bottom:4px}
  .sub{color:#6b7280;font-size:13px;margin-bottom:28px}
  .card{background:#1a1a2e;border:1px solid #2d2d4e;border-radius:14px;padding:28px;margin-bottom:20px}
  .card h2{font-size:15px;color:#c4b5fd;margin-bottom:18px}
  .step{display:flex;align-items:flex-start;gap:14px;padding:12px 0;border-bottom:1px solid #1f1f35}
  .step:last-child{border-bottom:none}
  .badge{min-width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
  .ok{background:#064e3b;color:#34d399} .fail{background:#450a0a;color:#f87171}
  .info{background:#1e1b4b;color:#818cf8} .warn{background:#451a03;color:#fb923c}
  .desc{flex:1} .desc strong{display:block;font-size:14px;margin-bottom:3px} .desc span{font-size:12px;color:#9ca3af}
  pre{background:#0a0a14;border:1px solid #2d2d4e;border-radius:8px;padding:14px;font-size:12px;color:#86efac;overflow-x:auto;margin-top:10px;white-space:pre-wrap;word-break:break-all}
  button,a.btn{padding:11px 22px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
  button:hover{opacity:.9}
  .danger-btn{background:linear-gradient(135deg,#dc2626,#991b1b)}
  .success-btn{background:linear-gradient(135deg,#059669,#047857)}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media(max-width:600px){.grid{grid-template-columns:1fr}}
  label{display:block;font-size:12px;color:#9ca3af;margin-bottom:5px;margin-top:12px}
  input[type=text],textarea,select{width:100%;padding:10px 14px;background:#0f0f1a;border:1px solid #374151;border-radius:8px;color:#fff;font-size:13px}
  input[type=text]:focus,textarea:focus{border-color:#4f46e5;outline:none}
  textarea{resize:vertical;min-height:100px;font-family:monospace}
  .alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px}
  .alert-ok{background:#064e3b33;border:1px solid #34d399;color:#34d399}
  .alert-fail{background:#450a0a33;border:1px solid #f87171;color:#f87171}
  .ver-badge{display:inline-block;padding:4px 14px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:20px;font-size:13px;font-weight:700;color:#fff;margin-left:10px}
  .changelog-item{font-size:12px;color:#86efac;padding:3px 0;display:flex;gap:8px}
  .changelog-item::before{content:"→";color:#4f46e5;flex-shrink:0}
  .upload-area{border:2px dashed #374151;border-radius:12px;padding:28px;text-align:center;cursor:pointer;transition:border-color .2s}
  .upload-area:hover{border-color:#4f46e5}
  .upload-area input{display:none}
  .upload-area p{font-size:13px;color:#6b7280;margin-top:8px}
  .step-number{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:#4f46e5;border-radius:50%;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
  .process-step{display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid #1f1f35}
  .process-step:last-child{border-bottom:none}
</style>
</head>
<body>
<div class="wrap">

  <h1>🔄 InvoFlow Updater
    <span class="ver-badge">v<?= htmlspecialchars($currentVer['version'] ?? '?') ?></span>
  </h1>
  <p class="sub">
    <?= htmlspecialchars($currentVer['codename'] ?? '') ?>
    &nbsp;|&nbsp; Released: <?= htmlspecialchars($currentVer['release_date'] ?? '—') ?>
    &nbsp;|&nbsp; <a href="?token=<?= $tokenEnc ?>" style="color:#818cf8">Refresh</a>
    &nbsp;|&nbsp; <strong style="color:#f87171">DELETE THIS FILE AFTER USE</strong>
  </p>

<?= $msg ?>

<!-- ── CURRENT VERSION INFO ─────────────────────────────────────────────── -->
<div class="card">
  <h2>📋 Current Version Info</h2>
  <?php
  $checks = [
    ['PHP '.PHP_VERSION,       version_compare(PHP_VERSION,'8.2.0','>='), version_compare(PHP_VERSION,'8.2.0','>=') ? 'Requirement met':'Upgrade PHP to 8.2+'],
    ['App Root exists',        is_dir($appRoot),    $appRoot],
    ['vendor/ exists',         is_dir($appRoot.'/vendor'), $appRoot.'/vendor'],
    ['.env exists',            file_exists($appRoot.'/.env'), $appRoot.'/.env'],
    ['version.json exists',    file_exists($appRoot.'/version.json'), $appRoot.'/version.json'],
    ['storage/ writable',      is_writable($appRoot.'/storage'), ''],
    ['bootstrap/cache/ writable', is_writable($appRoot.'/bootstrap/cache'), ''],
    ['PHP zip extension',      extension_loaded('zip'), extension_loaded('zip') ? 'Required for ZIP upload':'Install php-zip extension'],
  ];
  foreach ($checks as [$label,$ok,$detail]) {
      row($ok?'✓':'✗', $ok?'ok':'fail', $label, $detail);
  }
  ?>
  <?php if (!empty($currentVer['changelog'])): ?>
  <div style="margin-top:16px;padding-top:16px;border-top:1px solid #1f1f35">
    <div style="font-size:11px;color:#6b7280;margin-bottom:8px;text-transform:uppercase;letter-spacing:.1em">Changelog v<?= htmlspecialchars($currentVer['version']) ?></div>
    <?php foreach ($currentVer['changelog'] as $item): ?>
    <div class="changelog-item"><?= htmlspecialchars($item) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── UPDATE PROCESS GUIDE ─────────────────────────────────────────────── -->
<div class="card">
  <h2>📖 Update Karne Ka Process (In Order)</h2>
  <?php
  $steps = [
    ['Local PC par nayi ZIP banao', 'make_deploy_zips.ps1 run karo — invoflow-app_TIMESTAMP.zip milegi'],
    ['ZIP yahan upload karo (Step 1)', 'Niche "Upload Update ZIP" section mein invoflow-app ZIP upload karo'],
    ['Migrations run karo (Step 2)', 'Agar naye database changes hain to "Run Migrations" click karo'],
    ['Cache rebuild karo (Step 3)', '"Optimize Cache" click karo — config/route/view cache refresh hogi'],
    ['Version update karo (Step 4)', '"Update version.json" mein naya version number set karo'],
    ['Test karo', 'yourdomain.com open karo, sab kuch check karo'],
    ['Ye file delete karo', 'Security ke liye updater.php ZAROOR delete karo!'],
  ];
  foreach ($steps as $i => [$title, $desc]) {
      echo "<div class='process-step'>";
      echo "<div class='step-number'>".($i+1)."</div>";
      echo "<div><strong style='font-size:14px;color:#e5e7eb'>$title</strong><br><span style='font-size:12px;color:#9ca3af'>$desc</span></div>";
      echo "</div>";
  }
  ?>
</div>

<!-- ── STEP 1: UPLOAD ZIP ───────────────────────────────────────────────── -->
<div class="card">
  <h2>📦 Step 1 — Update ZIP Upload Karo</h2>
  <p style="font-size:13px;color:#9ca3af;margin-bottom:16px">
    <strong style="color:#fb923c">invoflow-app_*.zip</strong> file upload karo (invoflow-public.zip <strong>nahi</strong>).
    .env aur storage/ automatically protected hain — overwrite nahi honge.
  </p>
  <form method="POST" enctype="multipart/form-data" onsubmit="this.querySelector('button').textContent='Uploading & Extracting...';this.querySelector('button').disabled=true">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="action" value="upload_update">
    <div class="upload-area" onclick="document.getElementById('zipFile').click()">
      <div style="font-size:36px">📁</div>
      <div style="font-size:15px;font-weight:600;color:#a5b4fc;margin-top:8px">Click to select invoflow-app ZIP</div>
      <p>Maximum size: <?= ini_get('upload_max_filesize') ?> (PHP limit)</p>
      <input type="file" id="zipFile" name="update_zip" accept=".zip" onchange="document.getElementById('fileName').textContent=this.files[0]?.name||'No file selected'">
    </div>
    <p id="fileName" style="font-size:12px;color:#818cf8;margin:8px 0 16px;text-align:center">No file selected</p>
    <button type="submit" style="width:100%">🚀 Upload & Extract Update</button>
  </form>

  <div style="margin-top:16px;padding:12px;background:#0a0a14;border-radius:8px;font-size:12px;color:#6b7280">
    <strong style="color:#fb923c">⚠️ Protected Files (will NOT be overwritten):</strong><br>
    <code style="color:#86efac">.env</code> (database credentials safe hain) &nbsp;|&nbsp;
    <code style="color:#86efac">storage/</code> (uploaded files, logs safe hain)
  </div>
</div>

<!-- ── STEP 2: MIGRATIONS ───────────────────────────────────────────────── -->
<div class="card">
  <h2>🗄️ Step 2 — Run Migrations (Agar Required Ho)</h2>
  <p style="font-size:13px;color:#9ca3af;margin-bottom:16px">
    Naye version mein database changes hain to ye run karo. version.json mein
    <code style="color:#fb923c">requires_migration: true</code> dikhe to zaroor run karo.
    <strong style="color:<?= ($currentVer['requires_migration'] ?? false) ? '#f87171' : '#34d399' ?>">
      Current version requires migration: <?= ($currentVer['requires_migration'] ?? false) ? 'YES ⚠️' : 'No ✅' ?>
    </strong>
  </p>
  <div class="grid">
    <form method="POST">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="action" value="run_migrations">
      <button type="submit" style="width:100%">🗄️ Run Migrations (Safe)</button>
    </form>
    <form method="POST">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="action" value="fix_perms">
      <button type="submit" class="success-btn" style="width:100%">🔐 Fix Storage Permissions</button>
    </form>
  </div>
</div>

<!-- ── STEP 3: CACHE ────────────────────────────────────────────────────── -->
<div class="card">
  <h2>⚡ Step 3 — Cache Optimize Karo</h2>
  <p style="font-size:13px;color:#9ca3af;margin-bottom:16px">
    Update ke baad ye zaroor run karo — nayi files ka cache build hoga.
  </p>
  <form method="POST">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="action" value="optimize">
    <button type="submit" style="width:100%">🔄 Clear & Rebuild All Cache</button>
  </form>
</div>

<!-- ── STEP 4: VERSION UPDATE ───────────────────────────────────────────── -->
<div class="card">
  <h2>🏷️ Step 4 — Version.json Update Karo</h2>
  <p style="font-size:13px;color:#9ca3af;margin-bottom:16px">
    Update successfully apply hone ke baad naya version set karo. Ye invoflow/version.json mein save hoga.
  </p>
  <form method="POST">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="action" value="save_version">
    <div class="grid">
      <div>
        <label>New Version Number (format: 1.2.3) *</label>
        <input type="text" name="new_version" placeholder="e.g. 1.1.0" value="<?= htmlspecialchars($currentVer['version'] ?? '1.0.0') ?>">
      </div>
      <div>
        <label>Codename / Release Name</label>
        <input type="text" name="codename" placeholder="e.g. Bug Fix Release" value="<?= htmlspecialchars($currentVer['codename'] ?? '') ?>">
      </div>
    </div>
    <label>Changelog (ek line = ek item)</label>
    <textarea name="changelog" placeholder="New feature added&#10;Bug fix: product sync&#10;Performance improvements"><?= htmlspecialchars(implode("\n", $currentVer['changelog'] ?? [])) ?></textarea>
    <label style="display:flex;align-items:center;gap:8px;margin-top:12px;cursor:pointer">
      <input type="checkbox" name="requires_migration" style="width:auto;margin:0" <?= ($currentVer['requires_migration'] ?? false) ? 'checked' : '' ?>>
      <span style="color:#fb923c">Is migration required for this version?</span>
    </label>
    <button type="submit" class="success-btn" style="width:100%;margin-top:16px">💾 Save Version Info</button>
  </form>
</div>

<!-- ── DANGER ZONE ──────────────────────────────────────────────────────── -->
<div class="card" style="border-color:#7f1d1d">
  <h2 style="color:#fca5a5">⚠️ Danger Zone</h2>
  <div class="step">
    <div class="badge fail">!</div>
    <div class="desc">
      <strong>Update ke baad ye file DELETE karo!</strong>
      <span>updater.php publicly accessible hai — koi bhi update trigger kar sakta hai. Use ke baad turant delete karo.</span>
    </div>
  </div>
  <div style="margin-top:16px">
    <a class="btn danger-btn" href="?token=<?= $tokenEnc ?>&delete=1" onclick="return confirm('updater.php delete karna chahte ho? Ye file permanently remove ho jayegi.')">
      🗑️ Delete updater.php Now
    </a>
  </div>
</div>

<?php
if (isset($_GET['delete']) && $_GET['delete'] === '1' && $token === UPDATER_TOKEN) {
    unlink(__FILE__);
    echo "<div class='alert alert-ok' style='margin-top:16px'>✅ updater.php delete ho gaya! Site ready hai 🎉</div>";
}
?>

</div><!-- .wrap -->
</body>
</html>
