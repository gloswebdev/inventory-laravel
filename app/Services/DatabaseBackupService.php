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

        // 1. Generate SQL Dump Content
        $sql  = "-- ========================================================\n";
        $sql .= "-- InvoFlow Automated Database Backup\n";
        $sql .= "-- Database: {$dbName}\n";
        $sql .= "-- Generated: " . now()->format('Y-m-d H:i:s') . "\n";
        $sql .= "-- Server: " . (gethostname() ?: 'Localhost') . "\n";
        $sql .= "-- ========================================================\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";

        $tables = DB::select('SHOW TABLES');
        $tableKey = 'Tables_in_' . $dbName;
        $totalTables = count($tables);

        foreach ($tables as $tableObj) {
            $table = $tableObj->$tableKey ?? array_values((array)$tableObj)[0];

            // CREATE TABLE Statement
            $create = DB::select("SHOW CREATE TABLE `{$table}`");
            $createSql = $create[0]->{'Create Table'} ?? array_values((array)$create[0])[1];
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $createSql . ";\n\n";

            // INSERT DATA in chunks
            $rows = DB::table($table)->get();
            if ($rows->count() > 0) {
                $cols = array_keys((array) $rows->first());
                $colList = '`' . implode('`, `', $cols) . '`';

                foreach ($rows->chunk(100) as $chunk) {
                    $values = $chunk->map(function ($row) {
                        $vals = array_map(function ($v) {
                            if ($v === null) return 'NULL';
                            return "'" . addslashes((string)$v) . "'";
                        }, (array) $row);
                        return '(' . implode(', ', $vals) . ')';
                    })->implode(",\n");

                    $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES\n{$values};\n\n";
                }
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // 2. Compress into ZIP if ZipArchive is enabled (saves 85%+ disk space & email size)
        $finalPath = $sqlFile;
        $finalName = $baseName . '.sql';

        if (class_exists('ZipArchive')) {
            $zipFile = $backupDir . '/' . $baseName . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $zip->addFromString($baseName . '.sql', $sql);
                $zip->close();
                $finalPath = $zipFile;
                $finalName = $baseName . '.zip';
            } else {
                file_put_contents($sqlFile, $sql);
            }
        } else {
            file_put_contents($sqlFile, $sql);
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
}
