<?php
$logFile = 'C:\\Users\\Administrator\\.gemini\\antigravity-ide\\brain\\b422202e-5c2c-4a13-b6e1-cea683c4a7f3\\.system_generated\\logs\\transcript.jsonl';
$lines = file($logFile);

// Let's first collect all file contents seen in VIEW_FILE
$viewedChunks = [];
foreach ($lines as $line) {
    $data = json_decode($line, true);
    if (!$data) continue;
    if ($data['type'] === 'VIEW_FILE' && strpos($data['content'], 'Total Lines') !== false) {
        $viewedChunks[] = [
            'step' => $data['step_index'],
            'content' => $data['content']
        ];
    }
}

echo "Found " . count($viewedChunks) . " VIEW_FILE chunks.\n";
// Let's dump the one with the maximum length
$maxLength = 0;
$bestChunk = '';
foreach ($viewedChunks as $c) {
    if (strlen($c['content']) > $maxLength) {
        $maxLength = strlen($c['content']);
        $bestChunk = $c['content'];
    }
}

// Let's extract the actual lines from the best chunk
preg_match_all('/^\d+:\s*(.*)$/m', $bestChunk, $matches);
$recoveredCode = implode("\n", $matches[1]);
file_put_contents('C:\\Users\\Administrator\\recovered_raw.blade.php', $recoveredCode);
echo "Recovered file of length " . strlen($recoveredCode) . " bytes from VIEW_FILE.\n";
