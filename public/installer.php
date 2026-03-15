<?php
/**
 * InvoFlow Total Nuclear Installer for Hostinger
 * Version: 1.2.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_path = dirname(__DIR__);

// SMART DETECTION: Find ALL Laravel project roots
function find_all_laravel_roots($base) {
    $roots = [];
    if (@file_exists($base . '/artisan') && @file_exists($base . '/bootstrap/app.php')) {
        $roots[] = $base;
    }
    $it = new DirectoryIterator($base);
    foreach ($it as $f) {
        if ($f->isDir() && !$f->isDot()) {
            $path = $f->getPathname();
            if (@file_exists($path . '/artisan') && @file_exists($path . '/bootstrap/app.php')) {
                $roots[] = $path;
            }
        }
    }
    return array_unique($roots);
}

$all_roots = find_all_laravel_roots($root_path);
$action = $_GET['action'] ?? 'welcome';
$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. NUCLEAR SETUP: Overwrite ALL .env files found
    if (isset($_POST['setup_env'])) {
        $db_host = str_replace('127.0.0.1', 'localhost', $_POST['db_host']);
        $db_name = trim($_POST['db_name']);
        $db_user = trim($_POST['db_user']);
        $db_pass = trim($_POST['db_pass']);
        $app_url = trim($_POST['app_url'], '/ ');

        try {
            // First, test if the credentials actually work
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            
            $replacements = [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $db_host,
                'DB_DATABASE' => $db_name,
                'DB_USERNAME' => $db_user,
                'DB_PASSWORD' => $db_pass,
                'APP_URL' => $app_url,
                'APP_ENV' => 'local',
                'APP_DEBUG' => 'true',
                'SESSION_DRIVER' => 'file',
                'CACHE_STORE' => 'file'
            ];

            $synced = [];
            foreach ($all_roots as $root) {
                $env_path = $root . '/.env';
                $content = (file_exists($env_path)) ? file_get_contents($env_path) : (file_exists($root . '/.env.example') ? file_get_contents($root . '/.env.example') : "APP_NAME=InvoFlow\nAPP_ENV=local\nAPP_DEBUG=true\nDB_CONNECTION=mysql");

                foreach ($replacements as $key => $val) {
                    $q = '"' . str_replace('"', '\"', $val) . '"';
                    $content = preg_match("/^$key\s*=.*/m", $content) ? preg_replace("/^$key\s*=.*/m", "$key=$q", $content) : $content . "\n$key=$q";
                }

                if (@file_put_contents($env_path, $content)) {
                    @chmod($env_path, 0644);
                    // Force clear cache for this specific root
                    if (@is_dir($root . '/bootstrap/cache')) {
                        foreach (glob($root . "/bootstrap/cache/*.php") as $c_f) @unlink($c_f);
                    }
                    $synced[] = basename($root);
                }
            }

            if (!empty($synced)) {
                $message = "<strong>Nuclear Sync Success!</strong> Updated: " . implode(", ", $synced) . ". Purane config saaf ho gaye.";
                $action = 'database';
            } else { $error = "Kuch gadbad hai, koi bhi .env file nahi likhi gayi."; }
            
        } catch (Exception $e) { $error = "[Connection Failed] " . $e->getMessage() . "<br><small>Bhai, Hostinger panel se user/pass dubara check karo.</small>"; }
    }
    // 2. RUN MIGRATIONS (On primary detected root)
    elseif (isset($_POST['run_migrations'])) {
        try {
            $primary = $all_roots[0] ?? $root_path;
            require $primary . '/vendor/autoload.php';
            $app = require_once $primary . '/bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $output = new \Symfony\Component\Console\Output\BufferedOutput();
            $kernel->call('migrate', ['--force' => true, '--seed' => true], $output);
            
            $message = "Migration Result:<br><pre class='bg-gray-800 p-3 rounded mt-2'>" . $output->fetch() . "</pre>";
            $action = 'finish';
        } catch (Exception $e) { $error = "[Migration Error] " . $e->getMessage(); }
    }
}

function checkExtension($ext) { return extension_loaded($ext) ? '<span class="text-green-500 font-bold">✔</span>' : '<span class="text-red-500 font-bold">✘</span>'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InvoFlow Nuclear Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #f1f5f9; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
        .btn-gradient { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); transition: all 0.3s ease; }
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.5); }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-6 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-blue-900/20 via-slate-950 to-slate-950">

    <div class="mb-8 text-center text-blue-400">
        <h1 class="text-5xl font-extrabold tracking-tight mb-2">InvoFlow</h1>
        <p class="text-slate-500 font-medium">Nuclear Deployment Fixer v1.2</p>
    </div>

    <div class="glass w-full max-w-xl rounded-[2rem] p-10 shadow-2xl">
        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/50 p-5 rounded-2xl mb-8 text-red-100 text-sm">
                <strong class="text-red-400 block mb-1">DUSHMAN MIL GAYA:</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="bg-green-500/10 border border-green-500/50 p-5 rounded-2xl mb-8 text-green-100 text-sm">
                <strong class="text-green-400 block mb-1">SUCCESS!</strong> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($action == 'welcome'): ?>
            <div class="space-y-6">
                <h2 class="text-2xl font-bold">Nuclear Checkup</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-slate-800/50 rounded-2xl flex justify-between"><span>PHP 8.2+</span><span><?php echo (PHP_VERSION_ID >= 80200) ? '✔' : '✘'; ?></span></div>
                    <div class="p-4 bg-slate-800/50 rounded-2xl flex justify-between"><span>PDO MySQL</span><span><?php echo checkExtension('pdo_mysql'); ?></span></div>
                </div>
                <div class="p-4 bg-blue-500/10 border border-blue-500/20 rounded-2xl text-xs text-blue-300">
                    Detected Roots: <code><?php echo count($all_roots); ?></code> folders found.
                </div>
                <a href="?action=config" class="btn-gradient block text-center py-5 rounded-2xl font-black uppercase tracking-widest mt-6">Fire Installer</a>
            </div>

        <?php elseif ($action == 'config'): ?>
            <h2 class="text-2xl font-bold mb-6">Database Config</h2>
            <form method="POST" class="space-y-4">
                <div><label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 mb-2 block">DB Host</label><input type="text" name="db_host" value="localhost" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-5 py-4 outline-none focus:ring-2 focus:ring-blue-500 transition"></div>
                <div><label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 mb-2 block">DB Name</label><input type="text" name="db_name" placeholder="u293228258_invoflow" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-5 py-4 outline-none focus:ring-2 focus:ring-blue-500 transition"></div>
                <div><label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 mb-2 block">DB User</label><input type="text" name="db_user" placeholder="u293228258_invoflow" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-5 py-4 outline-none focus:ring-2 focus:ring-blue-500 transition"></div>
                <div><label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 mb-2 block">DB Password</label><input type="password" name="db_pass" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-5 py-4 outline-none focus:ring-2 focus:ring-blue-500 transition"></div>
                <div><label class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1 mb-2 block">App URL</label><input type="text" name="app_url" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-5 py-4 outline-none focus:ring-2 focus:ring-blue-500 transition"></div>
                <button type="submit" name="setup_env" class="btn-gradient w-full py-5 rounded-[1.5rem] font-black uppercase tracking-widest mt-6">SAVE & BLAST CONFIG</button>
            </form>

        <?php elseif ($action == 'database'): ?>
            <h2 class="text-2xl font-bold mb-4">Wipe & Re-Run</h2>
            <p class="text-slate-400 mb-8 text-sm">Config synced! Ab hum seedha tables banayenge aur seed karenge.</p>
            <form method="POST">
                <button type="submit" name="run_migrations" class="btn-gradient w-full py-5 rounded-[1.5rem] font-black uppercase tracking-widest">Run Migration & Seeds</button>
                <a href="?action=config" class="block text-center text-slate-500 mt-6 text-xs uppercase font-bold hover:text-white transition">Go Back</a>
            </form>

        <?php elseif ($action == 'finish'): ?>
            <div class="text-center">
                <div class="w-24 h-24 bg-green-500 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-2xl rotate-12"><span class="text-5xl -rotate-12">🦾</span></div>
                <h2 class="text-3xl font-black mb-4 tracking-tighter">MISSION ACCOMPLISHED</h2>
                <p class="text-slate-400 mb-10 text-sm">Bhai, project Live ho chuka hai. Installer cleanup karo aur dashboard enjoy karo!</p>
                <form method="POST">
                    <button type="submit" name="cleanup" onclick="return confirm('Installer delete ho jayega. Continue?')" class="w-full bg-white text-slate-900 py-5 rounded-[1.5rem] font-black uppercase tracking-widest hover:bg-white/90">VISIT WEBSITE</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <div class="mt-8 text-slate-600 font-bold text-xs uppercase tracking-widest">Designed for Ultimate Speed • InvoFlow</div>
</body>
</html>
