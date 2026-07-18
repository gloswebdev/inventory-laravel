<?php
$logFile = 'C:\\Users\\Administrator\\.gemini\\antigravity-ide\\brain\\b422202e-5c2c-4a13-b6e1-cea683c4a7f3\\.system_generated\\logs\\transcript.jsonl';
$lines = file($logFile);

foreach ($lines as $line) {
    $data = json_decode($line, true);
    if (!$data) continue;
    if (isset($data['tool_calls']) && is_array($data['tool_calls'])) {
        foreach ($data['tool_calls'] as $call) {
            if (isset($call['args']['TargetFile']) && strpos($call['args']['TargetFile'], 'index.blade.php') !== false) {
                echo "Step: " . $data['step_index'] . " Tool: " . $call['name'] . "\n";
                if (isset($call['args']['ReplacementContent'])) {
                    echo "  Repl Length: " . strlen($call['args']['ReplacementContent']) . "\n";
                }
                if (isset($call['args']['CodeContent'])) {
                    echo "  Code Length: " . strlen($call['args']['CodeContent']) . "\n";
                }
            }
        }
    }
}
