<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// ─── App Root Path ────────────────────────────────────────────────────────────
// Standard Laravel: public/index.php → app root is one level up
$appRoot = dirname(__DIR__) . '/invoflow';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appRoot . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appRoot . '/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appRoot . '/bootstrap/app.php';

// Tell Laravel that public files are served from public_html/ (this dir)
// so Vite manifest is found at public_html/build/manifest.json
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
