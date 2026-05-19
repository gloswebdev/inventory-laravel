<?php
// Path Finder — DELETE AFTER USE!
echo "<h2>Server Location</h2>";
echo "<p><b>This file is at:</b> " . __FILE__ . "</p>";
echo "<p><b>Parent dir (../):</b> " . realpath('../') . "</p>";

echo "<h2>Folders in parent directory (../)</h2>";
$parentDir = realpath('../');
$items = scandir($parentDir);
echo "<ul>";
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $fullPath = $parentDir . '/' . $item;
    $type = is_dir($fullPath) ? '📁 DIR' : '📄 FILE';
    echo "<li><b>$type</b>: $item</li>";
}
echo "</ul>";

echo "<h2>Check Common Paths</h2>";
$checkPaths = [
    '../invoflow/vendor/autoload.php',
    '../invoflow-app/vendor/autoload.php', 
    '../app/vendor/autoload.php',
    '../laravel/vendor/autoload.php',
    '../../vendor/autoload.php',
    '../vendor/autoload.php',
];
foreach ($checkPaths as $path) {
    $exists = file_exists($path);
    echo "<p>" . ($exists ? "✅" : "❌") . " $path</p>";
}
