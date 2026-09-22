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
     * Download DB Backup as Instant Streamed SQL file (Zero RAM, Zero Timeout)
     */
    public function backupDownload()
    {
        $this->adminOnly();

        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $dbName   = config('database.connections.mysql.database', 'inventory_laravel_db');
        $filename = 'invoflow_backup_' . date('Ymd_His') . '.sql';

        return response()->streamDownload(function () use ($dbName) {
            @set_time_limit(0);
            @ini_set('memory_limit', '1024M');

            $out = fopen('php://output', 'w');

            fwrite($out, "-- ========================================================\n");
            fwrite($out, "-- InvoFlow Instant Database Backup (Live Streamed)\n");
            fwrite($out, "-- Database: {$dbName}\n");
            fwrite($out, "-- Generated: " . now()->format('Y-m-d H:i:s') . "\n");
            fwrite($out, "-- Server: " . (gethostname() ?: 'Production') . "\n");
            fwrite($out, "-- ========================================================\n\n");
            fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($out, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n");

            if (ob_get_level() > 0) @ob_flush();
            @flush();

            $pdo = DB::connection()->getPdo();
            $tables = DB::select('SHOW TABLES');
            $tableKey = 'Tables_in_' . $dbName;

            // Skip heavy tables that are synced locally and would cause hosting timeout
            $skipTables = ['mssql_sales_records'];

            foreach ($tables as $tableObj) {
                $table = $tableObj->$tableKey ?? array_values((array)$tableObj)[0];
                if (empty($table)) continue;
                if (in_array($table, $skipTables)) {
                    fwrite($out, "-- SKIPPED: `{$table}` (large sync table, excluded from backup)\n\n");
                    if (ob_get_level() > 0) @ob_flush();
                    @flush();
                    continue;
                }

                // CREATE TABLE Statement
                try {
                    $create = DB::select("SHOW CREATE TABLE `{$table}`");
                    $createSql = $create[0]->{'Create Table'} ?? array_values((array)$create[0])[1] ?? null;
                    if ($createSql) {
                        fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");
                        fwrite($out, $createSql . ";\n\n");
                    }
                } catch (\Throwable $te) {
                    continue;
                }

                // INSERT DATA streamed via unbuffered PDO statement (Zero RAM buffering)
                try {
                    $stmt = $pdo->prepare("SELECT * FROM `{$table}`", [\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]);
                    $stmt->execute();
                    $stmt->setFetchMode(\PDO::FETCH_ASSOC);

                    $batch = [];
                    $batchCount = 0;

                    while ($row = $stmt->fetch()) {
                        $batch[] = $row;
                        $batchCount++;

                        if ($batchCount >= 500) {
                            $cols = array_keys($batch[0]);
                            $colList = '`' . implode('`, `', $cols) . '`';
                            $valuesList = [];
                            foreach ($batch as $r) {
                                $vals = array_map(function ($v) {
                                    if ($v === null) return 'NULL';
                                    return "'" . addslashes((string)$v) . "'";
                                }, $r);
                                $valuesList[] = '(' . implode(', ', $vals) . ')';
                            }
                            fwrite($out, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $valuesList) . ";\n\n");
                            $batch = [];
                            $batchCount = 0;

                            if (ob_get_level() > 0) @ob_flush();
                            @flush();
                        }
                    }

                    if (!empty($batch)) {
                        $cols = array_keys($batch[0]);
                        $colList = '`' . implode('`, `', $cols) . '`';
                        $valuesList = [];
                        foreach ($batch as $r) {
                            $vals = array_map(function ($v) {
                                if ($v === null) return 'NULL';
                                return "'" . addslashes((string)$v) . "'";
                            }, $r);
                            $valuesList[] = '(' . implode(', ', $vals) . ')';
                        }
                        fwrite($out, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $valuesList) . ";\n\n");
                        $batch = [];
                    }

                    $stmt->closeCursor();
                } catch (\Throwable $de) {}

                if (ob_get_level() > 0) @ob_flush();
                @flush();
            }

            fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'application/sql; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Accel-Buffering'   => 'no',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /**
     * Restore DB from uploaded SQL or ZIP file
     */
    public function restoreUpload(Request $request)
    {
        $this->adminOnly();

        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $request->validate([
            'sql_file' => 'required|file|max:307200', // 300MB max
        ]);

        $file = $request->file('sql_file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $tempSqlPath = null;

        if ($ext === 'zip') {
            if (!class_exists('ZipArchive')) {
                return back()->with('system_error', 'PHP Zip extension is not installed on this server.');
            }
            $zip = new \ZipArchive();
            if ($zip->open($file->getRealPath()) === true) {
                $foundSql = false;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if (str_ends_with(strtolower($stat['name']), '.sql')) {
                        $tempSqlPath = storage_path('app/temp_restore_' . time() . '.sql');
                        file_put_contents($tempSqlPath, $zip->getFromIndex($i));
                        $foundSql = true;
                        break;
                    }
                }
                $zip->close();

                if (!$foundSql || !file_exists($tempSqlPath)) {
                    return back()->with('system_error', 'Uploaded ZIP file does not contain any valid .sql file.');
                }
            } else {
                return back()->with('system_error', 'Uploaded ZIP file is corrupt or could not be opened.');
            }
        } else {
            $tempSqlPath = $file->getRealPath();
        }

        // Quick check on first 4KB to detect HTML error responses
        $headerCheck = '';
        $fCheck = @fopen($tempSqlPath, 'r');
        if ($fCheck) {
            $headerCheck = fread($fCheck, 4096);
            fclose($fCheck);
        }

        $trimmedHeader = ltrim($headerCheck);
        if (str_starts_with($trimmedHeader, '<!') || str_starts_with($trimmedHeader, '<html') || str_starts_with($trimmedHeader, '<?xml')) {
            if (isset($tempSqlPath) && str_contains($tempSqlPath, 'temp_restore_')) {
                @unlink($tempSqlPath);
            }
            if (str_contains($headerCheck, 'FatalError') || str_contains($headerCheck, 'Internal Server Error') || str_contains($headerCheck, 'Allowed memory size')) {
                return back()->with('system_error', '❌ The uploaded file is an HTML Error Page (500 Server Out of Memory / Fatal Error from the live server), NOT a valid SQL backup. Please generate a fresh database backup from the live server using mysqldump or the email backup feature.');
            }
            return back()->with('system_error', '❌ The uploaded file is an HTML webpage, not a valid SQL database backup.');
        }

        if (empty(trim($headerCheck))) {
            if (isset($tempSqlPath) && str_contains($tempSqlPath, 'temp_restore_')) {
                @unlink($tempSqlPath);
            }
            return back()->with('system_error', 'The uploaded backup file is completely empty.');
        }

        $dbName = config('database.connections.mysql.database', 'inventory_laravel_db');

        // 1. Drop all existing tables upfront with FOREIGN_KEY_CHECKS=0 to eliminate orphan FK constraints & conflicts
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            $existingTables = DB::select('SHOW TABLES');
            $tableKey = 'Tables_in_' . $dbName;
            foreach ($existingTables as $tObj) {
                $t = $tObj->$tableKey ?? array_values((array)$tObj)[0] ?? null;
                if (!empty($t)) {
                    DB::statement("DROP TABLE IF EXISTS `{$t}`");
                }
            }
        } catch (\Throwable $dropAllEx) {}

        // 2. Try native MySQL CLI import first (Fastest: 1-2s, 0 RAM, 100% MariaDB compatible)
        if ($this->runMysqlCliImport($tempSqlPath, $dbName)) {
            if (isset($tempSqlPath) && str_contains($tempSqlPath, 'temp_restore_')) {
                @unlink($tempSqlPath);
            }
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable $e) {}

            return back()->with('system_success', "✅ Database restored successfully via native MySQL engine!");
        }

        // 3. Fallback: PHP Stream-based SQL line-by-line processor
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');

            $handle = fopen($tempSqlPath, 'r');
            if (!$handle) {
                throw new \Exception('Could not open SQL file for processing.');
            }

            $currentStmt = '';
            $executed = 0;
            $inComment = false;

            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') continue;

                // Handle multi-line block comments /* ... */
                if ($inComment) {
                    if (str_contains($trimmed, '*/')) {
                        $inComment = false;
                    }
                    continue;
                }
                if (str_starts_with($trimmed, '/*') && !str_starts_with($trimmed, '/*!')) {
                    if (!str_contains($trimmed, '*/')) {
                        $inComment = true;
                    }
                    continue;
                }

                // Skip line comments
                if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                    continue;
                }

                $currentStmt .= $line;

                if (str_ends_with($trimmed, ';')) {
                    $stmtTrimmed = trim($currentStmt);
                    if (empty($stmtTrimmed)) {
                        $currentStmt = '';
                        continue;
                    }

                    // Skip excessive product_sync_logs INSERT statements (> 100KB) to prevent packet errors
                    if (preg_match('/INSERT\s+INTO\s+`?product_sync_logs`?/i', $stmtTrimmed) && strlen($stmtTrimmed) > 100000) {
                        $currentStmt = '';
                        continue;
                    }

                    try {
                        DB::unprepared($currentStmt);
                        $executed++;
                    } catch (\Throwable $queryError) {
                        $msg = $queryError->getMessage();
                        // Ignore harmless DROP errors or non-breaking warnings
                        if (
                            !str_starts_with(strtoupper($stmtTrimmed), 'DROP TABLE') &&
                            !str_contains($msg, 'already exists') &&
                            !str_contains($msg, 'Duplicate column') &&
                            !str_contains($msg, 'Duplicate key name') &&
                            !str_contains($msg, 'Duplicate entry')
                        ) {
                            fclose($handle);
                            if (str_contains($tempSqlPath, 'temp_restore_')) {
                                @unlink($tempSqlPath);
                            }
                            DB::statement('SET FOREIGN_KEY_CHECKS=1');
                            return back()->with('system_error', 'Restore failed on statement: ' . $msg);
                        }
                    }

                    $currentStmt = '';
                }
            }

            fclose($handle);

            if (str_contains($tempSqlPath, 'temp_restore_')) {
                @unlink($tempSqlPath);
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return back()->with('system_success', "✅ Database restored successfully! $executed SQL statements executed.");
        } catch (\Exception $e) {
            if (isset($tempSqlPath) && str_contains($tempSqlPath, 'temp_restore_')) {
                @unlink($tempSqlPath);
            }
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

        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $request->validate([
            'update_zip' => 'required|file|mimes:zip|max:307200', // 300MB
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

    /**
     * Find mysql CLI binary path
     */
    private function findMysqlCli(): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $binaries = [
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'mysql',
            '/usr/bin/mysql',
            '/usr/local/bin/mysql',
            '/usr/local/mysql/bin/mysql',
        ];

        foreach ($binaries as $bin) {
            $output = [];
            $ret = 1;
            @exec("{$bin} --version 2>&1", $output, $ret);
            if ($ret === 0) {
                return $bin;
            }
        }
        return null;
    }

    /**
     * Import SQL file directly via native MySQL CLI client
     */
    private function runMysqlCliImport(string $sqlFilePath, string $dbName): bool
    {
        $bin = $this->findMysqlCli();
        if (!$bin) return false;

        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $user = config('database.connections.mysql.username', 'root');
        $pass = config('database.connections.mysql.password', '');

        $cmd = [
            $bin,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $user,
            '--default-character-set=utf8mb4',
            $dbName,
        ];
        if (!empty($pass)) {
            $cmd[] = '--password=' . $pass;
        }

        $descriptorspec = [
            0 => ['file', $sqlFilePath, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            return false;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);
        return $returnCode === 0;
    }
}
