<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = "e2a4fuye2a4fuy9swssw122sbkn0m82y83g14";
$itemCode = "SWA001724";

echo "Fetching stock for item: $itemCode\n";

try {
    $response = Http::timeout(30)->post('https://logicapi.algebraerp.com/API/SYNWOOD/ProductWiseInventory', [
        "apikey" => $apiKey,
        "Branch" => "ALL",
        "Item" => "ALL"
    ]);

    if ($response->successful()) {
        $data = $response->json();
        if (isset($data['resultdata'])) {
            foreach ($data['resultdata'] as $item) {
                if ($item['User_Code'] === $itemCode) {
                    echo "Branch: " . $item['Branch_Name'] . " (" . $item['Branch_Code'] . ") - ClosingQty: " . $item['ClosingQty'] . "\n";
                }
            }
        } else {
            echo "No resultdata in response.\n";
            print_r($data);
        }
    } else {
        echo "API Call failed: " . $response->status() . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
