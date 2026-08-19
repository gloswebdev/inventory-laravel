<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automated Daily Database Backup & Email at 23:50 (Every Night)
Schedule::command('app:backup-database --mail')
    ->dailyAt('23:50')
    ->name('daily-database-backup-mail')
    ->withoutOverlapping();

