<?php
// Simple script to check API data without full Laravel bootstrap if it fails
$apiKey = "e2a4fuye2a4fuy9swssw122sbkn0m82y83g14";
$itemCode = "SWA001724";

$url = 'https://logicapi.algebraerp.com/API/SYNWOOD/ProductWiseInventory';
$data = array(
    "apikey" => $apiKey,
    "Branch" => "ALL",
    "Item" => "ALL"
);

$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'timeout' => 30
    )
);

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    echo "API Call failed\n";
    exit(1);
}

$response = json_decode($result, true);
if (isset($response['resultdata'])) {
    echo "Live API Stock for $itemCode:\n";
    foreach ($response['resultdata'] as $item) {
        if ($item['User_Code'] === $itemCode) {
            echo "Branch: " . $item['Branch_Name'] . " (" . $item['Branch_Code'] . ") - Units: " . $item['ClosingQty'] . "\n";
        }
    }
} else {
    echo "No data found\n";
}
