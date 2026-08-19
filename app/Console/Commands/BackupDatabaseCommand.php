<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-database {--mail : Send backup to configured email} {--email= : Send backup to specific email address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate full database backup (compressed) and optionally email it to admin';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackupService $service)
    {
        $sendMail = $this->option('mail') || $this->option('email');
        $email = $this->option('email');

        $this->info('Starting automated database backup...');

        $result = $service->createBackup($sendMail, $email);

        if ($result['success']) {
            $this->info("✅ Database backup created successfully!");
            $this->line("• File: {$result['file_name']}");
            $this->line("• Size: {$result['file_size']}");
            $this->line("• Total Tables: {$result['total_tables']}");

            if ($result['email_sent']) {
                $this->info("📧 Backup emailed successfully to: {$result['email_recipient']}");
            } elseif ($sendMail && $result['email_error']) {
                $this->warn("⚠️ Backup created, but email failed: {$result['email_error']}");
            }
            return 0;
        }

        $this->error('Failed to create database backup.');
        return 1;
    }
}
