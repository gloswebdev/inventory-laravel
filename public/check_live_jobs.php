<?php
// Live DB check script
// Save to: public_html/check_live_jobs.php

$appRoot = dirname(__DIR__) . '/invoflow';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

header('Content-Type: text/plain');
echo "=== LIVE HOSTINGER QUERY JOBS ===" . PHP_EOL;

try {
    $jobs = DB::table('query_jobs')->orderBy('id', 'desc')->limit(10)->get();
    foreach ($jobs as $j) {
        echo "ID: {$j->id} | Status: {$j->status} | Rows: {$j->result_count} | Token: {$j->job_token} | Error: {$j->error_message} | Exec Time: {$j->execution_seconds}s | Created: {$j->created_at} | Updated: {$j->updated_at}" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

// Check the counts of jobs
echo PHP_EOL . "Status counts:" . PHP_EOL;
try {
    $counts = DB::table('query_jobs')->selectRaw('status, count(*) as count')->groupBy('status')->get();
    foreach ($counts as $c) {
        echo "  {$c->status}: {$c->count}" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
