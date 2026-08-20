<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SystemController extends Controller
{
    protected $backupService;

    public function __construct(DatabaseBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * Admin-only access check
     */
    private function adminOnly()
    {
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Admin access required.');
        }
    }

    /**
     * System management dashboard
     */
    public function index()
    {
        $this->adminOnly();

        $versionFile = base_path('version.json');
        $version     = ['version' => '?', 'release_date' => '?', 'codename' => '?', 'changelog' => []];
        if (file_exists($versionFile)) {
            $raw     = file_get_contents($versionFile);
            $raw     = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // strip BOM
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $version = $decoded;
            }
        }


        // DB stats
        $tables = DB::select('SHOW TABLE STATUS');
        $dbSizeBytes = array_sum(array_map(fn($t) => ($t->Data_length ?? 0) + ($t->Index_length ?? 0), $tables));
        $tableCount  = count($tables);

        // Storage info
        $storagePath = storage_path();
        $storageSize = $this->dirSize($storagePath);

        // Cache stats
        $cacheStats = [
            [
                'label' => 'Bootstrap Cache',
                'icon'  => 'fas fa-cubes',
                'color' => '#6366f1',
                'count' => count(glob(base_path('bootstrap/cache/*.php')) ?: []),
                'unit'  => 'cached files',
            ],
            [
                'label' => 'View Cache',
                'icon'  => 'fas fa-eye',
                'color' => '#10b981',
                'count' => count(glob(storage_path('framework/views/*.php')) ?: []),
                'unit'  => 'cached files',
            ],
            [
                'label' => 'Data Cache',
                'icon'  => 'fas fa-database',
                'color' => '#f59e0b',
                'count' => count(glob(storage_path('framework/cache/data/*')) ?: []),
                'unit'  => 'cached files',
            ],
            [
                'label' => 'Storage Size',
                'icon'  => 'fas fa-hdd',
                'color' => '#8b5cf6',
                'count' => $storageSize > 1048576
                    ? round($storageSize / 1048576, 1) . ' MB'
                    : round($storageSize / 1024, 0) . ' KB',
                'unit'  => 'total size',
            ],
        ];

        // Backup & Email settings
        $backupEmail       = AppSetting::get('backup_email', 'admin@example.com');
        $backupAutoEnabled = AppSetting::get('backup_auto_enabled', '1');
        $backupCronToken   = AppSetting::get('backup_cron_token', 'invoflow_backup_key_2026');
        $recentBackups     = $this->backupService->listBackups();

        return view('system.index', compact(
            'version', 'dbSizeBytes', 'tableCount', 'storageSize', 'cacheStats',
            'backupEmail', 'backupAutoEnabled', 'backupCronToken', 'recentBackups'
        ));
    }

    /**
     * Save Auto Backup Email Settings
     */
    public function saveBackupSettings(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'backup_email' => 'required|email',
            'backup_cron_token' => 'required|string|min:6',
        ]);

        AppSetting::set('backup_email', trim($request->backup_email));
        AppSetting::set('backup_auto_enabled', $request->has('backup_auto_enabled') ? '1' : '0');
        AppSetting::set('backup_cron_token', trim($request->backup_cron_token));

        return back()->with('system_success', '✅ Backup & Email settings saved successfully!');
    }

    /**
     * Trigger manual DB backup and email it immediately
     */
    public function triggerEmailBackup(Request $request)
    {
        $this->adminOnly();

        $email = $request->input('target_email');
        $result = $this->backupService->createBackup(true, $email);

        if ($result['success']) {
            if ($result['email_sent']) {
                return back()->with('system_success', "✅ Database backup ({$result['file_size']}) created and emailed successfully to {$result['email_recipient']}!");
            } else {
                return back()->with('system_success', "⚠️ Backup created ({$result['file_size']}), but email sending failed: {$result['email_error']}. Make sure Mail SMTP credentials are set in .env.");
            }
        }

        return back()->with('system_error', 'Failed to generate database backup.');
    }

    /**
     * Download specific historical backup file
     */
    public function downloadSpecificBackup($filename)
    {
        $this->adminOnly();

        $safeName = basename($filename);
        $filePath = storage_path('app/backups/' . $safeName);

        if (!file_exists($filePath)) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($filePath, $safeName);
    }

    /**
     * Public secure Cron endpoint for Hostinger / cPanel Cron jobs
     */
    public function cronBackup(Request $request)
    {
        $token = $request->query('token');
        $validToken = AppSetting::get('backup_cron_token', 'invoflow_backup_key_2026');

        if (empty($token) || $token !== $validToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized cron token.',
            ], 403);
        }

        $autoEnabled = AppSetting::get('backup_auto_enabled', '1');
        if ($autoEnabled !== '1') {
            return response()->json([
                'success' => true,
                'message' => 'Auto backup is disabled in settings.',
            ]);
        }

        $result = $this->backupService->createBackup(true);

        return response()->json([
            'success'   => $result['success'],
            'message'   => 'Automated daily database backup executed.',
            'details'   => $result,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Download DB Backup as Compressed ZIP (or SQL) file
     */
    public function backupDownload()
    {
        $this->adminOnly();

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $result = $this->backupService->createBackup(false);

        if (!$result['success'] || !file_exists($result['file_path'])) {
            return back()->with('system_error', 'Backup generate karne me error aaya: ' . ($result['error'] ?? 'Unknown error'));
        }

        return response()->download($result['file_path'], $result['file_name']);
    }

    /**
     * Restore DB from uploaded SQL or ZIP file
     */
    public function restoreUpload(Request $request)
    {
        $this->adminOnly();

        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $request->validate([
            'sql_file' => 'required|file|max:102400', // 100MB max
        ]);

        $file = $request->file('sql_file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $content = '';

        if ($ext === 'zip') {
            if (!class_exists('ZipArchive')) {
                return back()->with('system_error', 'PHP Zip extension server par available nahi hai.');
            }
            $zip = new \ZipArchive();
            if ($zip->open($file->getRealPath()) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if (str_ends_with(strtolower($stat['name']), '.sql')) {
                        $content = $zip->getFromIndex($i);
                        break;
                    }
                }
                $zip->close();
            } else {
                return back()->with('system_error', 'Uploaded ZIP file corrupt hai ya open nahi hui.');
            }
        } else {
            $content = file_get_contents($file->getRealPath());
        }

        if (empty(trim($content))) {
            return back()->with('system_error', 'SQL / ZIP file khali hai ya usme koi valid .sql file nahi mili!');
        }

        // Safety check
        if (!str_contains($content, 'CREATE TABLE') && !str_contains($content, 'INSERT INTO')) {
            return back()->with('system_error', 'Valid SQL backup file nahi lag rahi. InvoFlow backup file use karo.');
        }

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Split into statements
            $statements = array_filter(
                array_map('trim', preg_split('/;\s*\n/', $content)),
                fn($s) => !empty($s) && !str_starts_with($s, '--')
            );

            $executed = 0;
            foreach ($statements as $stmt) {
                if (empty(trim($stmt))) continue;

                // Skip huge product_sync_logs insert statements to prevent max_allowed_packet crash
                if (preg_match('/INSERT\s+INTO\s+`?product_sync_logs`?/i', $stmt)) {
                    if (strlen($stmt) > 50000) { // > 50KB
                        continue;
                    }
                }

                DB::unprepared($stmt);
                $executed++;
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return back()->with('system_success', "✅ Restore successful! $executed SQL statements executed.");
        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return back()->with('system_error', 'Restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Apply uploaded update ZIP
     */
    public function applyUpdate(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'update_zip' => 'required|file|mimes:zip|max:102400', // 100MB
        ]);

        if (!extension_loaded('zip')) {
            return back()->with('system_error', 'PHP zip extension nahi hai server par!');
        }

        $file   = $request->file('update_zip');
        $tmpDir = storage_path('app/update_tmp_' . time());
        @mkdir($tmpDir, 0755, true);

        $zipPath = $tmpDir . '/update.zip';
        $file->move($tmpDir, 'update.zip');

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->cleanup($tmpDir);
            return back()->with('system_error', 'ZIP file open nahi hua. Corrupt file?');
        }

        $extractTo = $tmpDir . '/extracted';
        @mkdir($extractTo, 0755, true);
        $zip->extractTo($extractTo);
        $zip->close();

        // Smart copy: skip .env and storage/
        $protected = ['.env', 'storage'];
        $appRoot   = base_path();
        $copied = 0;
        $skipped = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractTo, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $rel      = ltrim(str_replace($extractTo, '', $item->getPathname()), '/\\');
            $topLevel = explode('/', str_replace('\\', '/', $rel))[0];

            if (in_array($topLevel, $protected)) {
                $skipped++;
                continue;
            }

            $dest = $appRoot . DIRECTORY_SEPARATOR . $rel;
            if ($item->isDir()) {
                if (!is_dir($dest)) @mkdir($dest, 0755, true);
            } else {
                @copy($item->getPathname(), $dest);
                $copied++;
            }
        }

        $this->cleanup($tmpDir);

        // Clear bootstrap cache
        foreach (glob(base_path('bootstrap/cache/*.php')) as $f) {
            @unlink($f);
        }

        // Update version.json if present in ZIP
        $newVersionMsg = '';
        $vf = base_path('version.json');
        if (file_exists($vf)) {
            $ver = json_decode(file_get_contents($vf), true);
            $newVersionMsg = ' | New version: v' . ($ver['version'] ?? '?');
        }

        return back()->with('system_success',
            "✅ Update applied! $copied files copied, $skipped protected (skip .env & storage).$newVersionMsg Cache cleared."
        );
    }

    /**
     * Clear all Laravel caches
     */
    public function clearCache()
    {
        $this->adminOnly();

        $cleared = [];
        // Bootstrap cache
        foreach (glob(base_path('bootstrap/cache/*.php')) as $f) {
            @unlink($f);
            $cleared[] = 'bootstrap/cache/' . basename($f);
        }
        // Framework views
        foreach (glob(storage_path('framework/views/*.php')) as $f) {
            @unlink($f);
            $cleared[] = 'framework/views/' . basename($f);
        }
        // Framework cache data
        $cacheDir = storage_path('framework/cache/data');
        if (is_dir($cacheDir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) { if ($f->isFile()) @unlink($f->getPathname()); }
            $cleared[] = 'framework/cache/data (all files)';
        }

        $count = count($cleared);
        return back()->with('system_success', "✅ Cache cleared! $count cache files deleted.");
    }

    private function dirSize(string $path): int
    {
        $size = 0;
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) { if ($f->isFile()) $size += $f->getSize(); }
        } catch (\Exception $e) {}
        return $size;
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) { $f->isDir() ? @rmdir($f) : @unlink($f); }
        @rmdir($dir);
    }
}
