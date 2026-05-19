<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SystemController extends Controller
{
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
        $version = file_exists($versionFile)
            ? json_decode(file_get_contents($versionFile), true)
            : ['version' => 'unknown', 'release_date' => '—', 'codename' => '—', 'changelog' => []];

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

        return view('system.index', compact('version', 'dbSizeBytes', 'tableCount', 'storageSize', 'cacheStats'));
    }

    /**
     * Download DB Backup as SQL file
     */
    public function backupDownload()
    {
        $this->adminOnly();

        $dbName   = config('database.connections.mysql.database');
        $filename = 'invoflow_backup_' . date('Ymd_His') . '.sql';

        $sql  = "-- InvoFlow Database Backup\n";
        $sql .= "-- Generated: " . now()->format('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: $dbName\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = DB::select('SHOW TABLES');
        $tableKey = 'Tables_in_' . $dbName;

        foreach ($tables as $tableObj) {
            $table = $tableObj->$tableKey;

            // CREATE TABLE
            $create = DB::select("SHOW CREATE TABLE `$table`");
            $createSql = $create[0]->{'Create Table'};
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createSql . ";\n\n";

            // INSERT DATA
            $rows = DB::table($table)->get();
            if ($rows->count() > 0) {
                $cols = array_keys((array) $rows->first());
                $colList = '`' . implode('`, `', $cols) . '`';

                $chunks = $rows->chunk(100);
                foreach ($rows->chunk(100) as $chunk) {
                    $values = $chunk->map(function ($row) {
                        $vals = array_map(function ($v) {
                            if ($v === null) return 'NULL';
                            return "'" . addslashes((string)$v) . "'";
                        }, (array) $row);
                        return '(' . implode(', ', $vals) . ')';
                    })->implode(",\n");
                    $sql .= "INSERT INTO `$table` ($colList) VALUES\n$values;\n\n";
                }
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return response($sql, 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Content-Length'      => strlen($sql),
        ]);
    }

    /**
     * Restore DB from uploaded SQL file
     */
    public function restoreUpload(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'sql_file' => 'required|file|mimes:sql,txt|max:51200', // 50MB
        ]);

        $file    = $request->file('sql_file');
        $content = file_get_contents($file->getRealPath());

        if (empty(trim($content))) {
            return back()->with('system_error', 'SQL file khali hai!');
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
