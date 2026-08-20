<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DatabaseBackupService
{
    /**
     * Generate full database backup and optionally email it.
     */
    public function createBackup(bool $sendEmail = false, ?string $overrideEmail = null): array
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $dbName    = config('database.connections.mysql.database', 'inventory_laravel_db');
        $timestamp = date('Ymd_His');
        $baseName  = 'invoflow_backup_' . $timestamp;
        $sqlFile   = $backupDir . '/' . $baseName . '.sql';

        $dumpSuccess = false;

        // Option A: Try native mysqldump CLI first (Fastest: 1-2 seconds, 0 RAM)
        if ($this->tryMysqlDump($sqlFile, $dbName)) {
            $dumpSuccess = true;
        }

        // Option B: Fast Raw PDO Stream directly to disk
        if (!$dumpSuccess) {
            $handle = fopen($sqlFile, 'w');
            if (!$handle) {
                return [
                    'success' => false,
                    'error'   => 'Could not create backup file in storage directory. Check storage folder write permissions.',
                ];
            }

            try {
                fwrite($handle, "-- ========================================================\n");
                fwrite($handle, "-- InvoFlow Automated Database Backup\n");
                fwrite($handle, "-- Database: {$dbName}\n");
                fwrite($handle, "-- Generated: " . now()->format('Y-m-d H:i:s') . "\n");
                fwrite($handle, "-- Server: " . (gethostname() ?: 'Hostinger/Production') . "\n");
                fwrite($handle, "-- ========================================================\n\n");
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
                fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n");

                $pdo = DB::connection()->getPdo();
                $tables = DB::select('SHOW TABLES');
                $tableKey = 'Tables_in_' . $dbName;

                foreach ($tables as $tableObj) {
                    $table = $tableObj->$tableKey ?? array_values((array)$tableObj)[0];
                    if (empty($table)) continue;

                    // CREATE TABLE Statement
                    try {
                        $create = DB::select("SHOW CREATE TABLE `{$table}`");
                        $createSql = $create[0]->{'Create Table'} ?? array_values((array)$create[0])[1] ?? null;
                        if ($createSql) {
                            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                            fwrite($handle, $createSql . ";\n\n");
                        }
                    } catch (\Throwable $te) {
                        Log::warning("Could not get create table for {$table}: " . $te->getMessage());
                        continue;
                    }

                    // INSERT DATA using raw PDO fetch (very fast, low memory)
                    try {
                        $stmt = $pdo->query("SELECT * FROM `{$table}`");
                        if ($stmt) {
                            $stmt->setFetchMode(\PDO::FETCH_ASSOC);
                            $batch = [];

                            while ($row = $stmt->fetch()) {
                                $batch[] = $row;
                                if (count($batch) >= 100) {
                                    $this->writeInsertBatch($handle, $table, $batch);
                                    $batch = [];
                                }
                            }
                            if (!empty($batch)) {
                                $this->writeInsertBatch($handle, $table, $batch);
                                $batch = [];
                            }
                        }
                    } catch (\Throwable $de) {
                        Log::warning("Could not dump rows for {$table}: " . $de->getMessage());
                    }
                }

                fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
                fclose($handle);
                $handle = null;
                $dumpSuccess = true;
            } catch (\Throwable $e) {
                if ($handle) {
                    fclose($handle);
                }
                if (file_exists($sqlFile)) {
                    @unlink($sqlFile);
                }
                Log::error("Database backup failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                return [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        $totalTables = count(DB::select('SHOW TABLES'));

        // 2. Compress into ZIP if ZipArchive is enabled (saves 85%+ disk space & avoids timeout)
        $finalPath = $sqlFile;
        $finalName = $baseName . '.sql';

        if (class_exists('ZipArchive')) {
            $zipFile = $backupDir . '/' . $baseName . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $zip->addFile($sqlFile, $baseName . '.sql');
                $zip->close();
                @unlink($sqlFile); // remove uncompressed raw sql to save server disk
                $finalPath = $zipFile;
                $finalName = $baseName . '.zip';
            }
        }

        $fileSizeBytes = filesize($finalPath);
        $fileSizeFormatted = $fileSizeBytes > 1048576
            ? round($fileSizeBytes / 1048576, 2) . ' MB'
            : round($fileSizeBytes / 1024, 1) . ' KB';

        // 3. Email Backup if requested
        $emailSent = false;
        $emailError = null;
        $recipient = $overrideEmail ?: AppSetting::get('backup_email', config('mail.from.address'));

        if ($sendEmail && !empty($recipient)) {
            try {
                $appName = config('app.name', 'InvoFlow');
                $subject = "[{$appName} Backup] Auto Database Backup - " . now()->format('d M Y (H:i)');

                $emailBody = "Hello Admin,\n\n"
                    . "Your scheduled automatic database backup for {$appName} was created successfully.\n\n"
                    . "========================================\n"
                    . "Backup Details:\n"
                    . "========================================\n"
                    . "• Date & Time  : " . now()->format('d-m-Y H:i:s') . "\n"
                    . "• Database Name: {$dbName}\n"
                    . "• Total Tables : {$totalTables}\n"
                    . "• File Name    : {$finalName}\n"
                    . "• File Size    : {$fileSizeFormatted}\n"
                    . "• Environment  : " . config('app.env') . "\n"
                    . "• App URL      : " . config('app.url') . "\n\n"
                    . "The backup file is attached to this email. Please store it safely.\n\n"
                    . "Regards,\n{$appName} Automated Backup System";

                Mail::raw($emailBody, function ($message) use ($recipient, $subject, $finalPath, $finalName) {
                    $message->to($recipient)
                            ->subject($subject)
                            ->attach($finalPath, [
                                'as' => $finalName,
                                'mime' => str_ends_with($finalName, '.zip') ? 'application/zip' : 'text/plain',
                            ]);
                });

                $emailSent = true;
                Log::info("Database backup emailed successfully to {$recipient}");
            } catch (\Exception $e) {
                $emailError = $e->getMessage();
                Log::error("Failed to email database backup: " . $e->getMessage());
            }
        }

        // 4. Cleanup old backups (> 14 days)
        $this->cleanupOldBackups($backupDir, 14);

        return [
            'success'            => true,
            'file_path'          => $finalPath,
            'file_name'          => $finalName,
            'file_size'          => $fileSizeFormatted,
            'file_size_bytes'    => $fileSizeBytes,
            'total_tables'       => $totalTables,
            'email_sent'         => $emailSent,
            'email_recipient'    => $recipient,
            'email_error'        => $emailError,
            'created_at'         => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Delete backups older than $days
     */
    private function cleanupOldBackups(string $dir, int $days = 14): void
    {
        try {
            $threshold = time() - ($days * 86400);
            $files = glob($dir . '/invoflow_backup_*.*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $threshold) {
                        @unlink($file);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Backup cleanup warning: " . $e->getMessage());
        }
    }

    /**
     * List all available local backup files
     */
    public function listBackups(): array
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            return [];
        }

        $files = glob($backupDir . '/invoflow_backup_*.*');
        if (!$files) {
            return [];
        }

        rsort($files); // latest first
        $list = [];

        foreach ($files as $file) {
            $size = filesize($file);
            $list[] = [
                'filename'   => basename($file),
                'path'       => $file,
                'size'       => $size > 1048576 ? round($size / 1048576, 2) . ' MB' : round($size / 1024, 1) . ' KB',
                'created_at' => date('d M Y, h:i A', filemtime($file)),
                'timestamp'  => filemtime($file),
            ];
        }

        return $list;
    }

    /**
     * Write an insert statement batch directly to file stream
     */
    private function writeInsertBatch($handle, string $table, array $batch): void
    {
        if (empty($batch)) return;

        $cols = array_keys((array) $batch[0]);
        $colList = '`' . implode('`, `', $cols) . '`';

        $valuesList = [];
        foreach ($batch as $row) {
            $vals = array_map(function ($v) {
                if ($v === null) return 'NULL';
                return "'" . addslashes((string)$v) . "'";
            }, (array) $row);
            $valuesList[] = '(' . implode(', ', $vals) . ')';
        }

        fwrite($handle, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $valuesList) . ";\n\n");
    }

    /**
     * Try to execute native mysqldump CLI binary
     */
    private function tryMysqlDump(string $sqlFile, string $dbName): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $disabled = explode(',', (string)ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
        if (in_array('exec', $disabled)) {
            return false;
        }

        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $dumpBinaries = ['mysqldump', '/usr/bin/mysqldump', '/usr/local/mysql/bin/mysqldump', '/usr/local/bin/mysqldump', 'C:\\xampp\\mysql\\bin\\mysqldump.exe'];
        $foundBin = null;

        foreach ($dumpBinaries as $bin) {
            $output = [];
            $ret = 1;
            @exec("{$bin} --version 2>&1", $output, $ret);
            if ($ret === 0) {
                $foundBin = $bin;
                break;
            }
        }

        if (!$foundBin) {
            return false;
        }

        $cmd = sprintf(
            '%s --host=%s --port=%s --user=%s --password=%s --single-transaction --quick --skip-lock-tables %s > %s 2>&1',
            escapeshellcmd($foundBin),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($dbName),
            escapeshellarg($sqlFile)
        );

        $out = [];
        $res = 1;
        @exec($cmd, $out, $res);

        return $res === 0 && file_exists($sqlFile) && filesize($sqlFile) > 1024;
    }
}
