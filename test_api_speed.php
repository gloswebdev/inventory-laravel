bhai <?php
$start = microtime(true);
$response = file_get_contents('https://logicapi.algebraerp.com/API/SYNWOOD/ProductWiseInventory', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode([
            "apikey" => "e2a4fuye2a4fuy9swssw122sbkn0m82y83g14",
            "Branch" => "ALL",
            "Item" => "ALL"
        ]),
        'timeout' => 30
    ]
]));
$end = microtime(true);

if ($response === false) {
    echo "API Request Failed\n";
} else {
    $data = json_decode($response, true);
    echo "API Response Time: " . round($end - $start, 4) . "s\n";
    if (isset($data['resultdata'])) {
        echo "Items in response: " . count($data['resultdata']) . "\n";
    } else {
        echo "Response format unknown\n";
    }
}
