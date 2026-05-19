<?php
/**
 * InvoFlow — API Connectivity Diagnostic
 * Upload to: public_html/api_test.php
 * Open in browser: https://yourdomain.com/api_test.php
 * DELETE THIS FILE after testing!
 */

// ─── Basic security: only allow from your IP ─────────────────────────────────
// Uncomment and set your IP to restrict access
// if ($_SERVER['REMOTE_ADDR'] !== 'YOUR.IP.HERE') die('Forbidden');

$apiUrl = 'https://logicapi.algebraerp.com/API/SYNWOOD/ProductWiseInventory';
$apiKey = 'e2a4fuye2a4fuy9swssw122sbkn0m82y83g14';
?>
<!DOCTYPE html>
<html>
<head>
<title>API Diagnostic — InvoFlow</title>
<style>
body { font-family: monospace; background: #0f0c29; color: #e0e0e0; padding: 24px; }
h2 { color: #818cf8; }
.pass { color: #4ade80; font-weight: bold; }
.fail { color: #f87171; font-weight: bold; }
.warn { color: #fbbf24; font-weight: bold; }
.box { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 16px; margin: 12px 0; }
pre { white-space: pre-wrap; word-break: break-all; font-size: 13px; color: #a5b4fc; }
</style>
</head>
<body>
<h2>🔍 InvoFlow — Live Stock API Diagnostic</h2>

<?php
// ─── 1. PHP Extensions Check ──────────────────────────────────────────────────
echo "<div class='box'><h3>1. PHP Extensions</h3>";
$exts = ['curl', 'openssl', 'json', 'mbstring'];
foreach ($exts as $ext) {
    $ok = extension_loaded($ext);
    echo "<p>" . ($ok ? "✅" : "❌") . " <b>$ext</b>: " . ($ok ? "<span class='pass'>Loaded</span>" : "<span class='fail'>NOT Loaded</span>") . "</p>";
}
echo "<p>PHP Version: <span class='pass'>" . phpversion() . "</span></p>";
echo "</div>";

// ─── 2. cURL Info ────────────────────────────────────────────────────────────
echo "<div class='box'><h3>2. cURL Info</h3>";
if (function_exists('curl_version')) {
    $cv = curl_version();
    echo "<p class='pass'>✅ cURL Available: v" . $cv['version'] . "</p>";
    echo "<p>SSL: " . $cv['ssl_version'] . "</p>";
} else {
    echo "<p class='fail'>❌ cURL NOT available</p>";
}
echo "</div>";

// ─── 3. DNS Resolution Check ─────────────────────────────────────────────────
echo "<div class='box'><h3>3. DNS Resolution</h3>";
$host = 'logicapi.algebraerp.com';
$ip = gethostbyname($host);
if ($ip !== $host) {
    echo "<p class='pass'>✅ DNS resolved: <b>$host</b> → $ip</p>";
} else {
    echo "<p class='fail'>❌ DNS resolution FAILED for: $host</p>";
    echo "<p class='warn'>This means Hostinger is blocking DNS lookup for this domain.</p>";
}
echo "</div>";

// ─── 4. TCP Connection Test ──────────────────────────────────────────────────
echo "<div class='box'><h3>4. TCP Port Check (HTTPS:443)</h3>";
$conn = @fsockopen('ssl://logicapi.algebraerp.com', 443, $errno, $errstr, 10);
if ($conn) {
    fclose($conn);
    echo "<p class='pass'>✅ TCP connection to logicapi.algebraerp.com:443 — SUCCESS</p>";
} else {
    echo "<p class='fail'>❌ TCP connection FAILED: $errstr (Error: $errno)</p>";
    echo "<p class='warn'>Hostinger firewall is blocking outbound HTTPS connections to this server.</p>";
}
echo "</div>";

// ─── 5. cURL Direct API Call ─────────────────────────────────────────────────
echo "<div class='box'><h3>5. Direct cURL API Call</h3>";
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['apikey' => $apiKey, 'Branch' => 'ALL', 'Item' => 'ALL']),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_VERBOSE        => false,
    ]);

    $startTime = microtime(true);
    $response  = curl_exec($ch);
    $elapsed   = round((microtime(true) - $startTime) * 1000);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrNo = curl_errno($ch);
    curl_close($ch);

    if ($curlError) {
        echo "<p class='fail'>❌ cURL Error ($curlErrNo): <b>$curlError</b></p>";
        
        // Explain common errors
        $hints = [
            6  => "⚠️ CURLE_COULDNT_RESOLVE_HOST — DNS not resolving. Hostinger may be blocking this domain.",
            7  => "⚠️ CURLE_COULDNT_CONNECT — Port 443 blocked by Hostinger firewall.",
            28 => "⚠️ CURLE_OPERATION_TIMEDOUT — Request timed out. API server unreachable from Hostinger.",
            35 => "⚠️ CURLE_SSL_CONNECT_ERROR — SSL handshake failed.",
            60 => "⚠️ CURLE_SSL_CACERT — SSL certificate issue.",
        ];
        if (isset($hints[$curlErrNo])) {
            echo "<p class='warn'>" . $hints[$curlErrNo] . "</p>";
        }
    } else {
        echo "<p class='pass'>✅ HTTP Status: $httpCode | Time: {$elapsed}ms</p>";
        if ($response) {
            $json = json_decode($response, true);
            if (isset($json['response'])) {
                $apiResp = $json['response'];
                if ($apiResp === 'success') {
                    $count = count($json['resultdata'] ?? []);
                    echo "<p class='pass'>✅ API Response: SUCCESS — $count stock records received!</p>";
                } else {
                    echo "<p class='fail'>❌ API returned: $apiResp</p>";
                    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
                }
            } else {
                echo "<p class='warn'>⚠️ Unexpected response:</p>";
                echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
            }
        }
    }
} else {
    echo "<p class='fail'>❌ cURL not available</p>";
}
echo "</div>";

// ─── 6. allow_url_fopen Check ────────────────────────────────────────────────
echo "<div class='box'><h3>6. PHP Config</h3>";
echo "<p>allow_url_fopen: " . (ini_get('allow_url_fopen') ? "<span class='pass'>ON</span>" : "<span class='fail'>OFF</span>") . "</p>";
echo "<p>allow_url_include: " . (ini_get('allow_url_include') ? "<span class='warn'>ON</span>" : "<span class='pass'>OFF (safe)</span>") . "</p>";
echo "<p>disable_functions: <pre>" . ini_get('disable_functions') . "</pre></p>";
echo "</div>";

// ─── Result ───────────────────────────────────────────────────────────────────
echo "<div class='box' style='border-color:#f87171'>";
echo "<h3>⚠️ Is file ko turant DELETE karo test ke baad!</h3>";
echo "<p>File: <code>public_html/api_test.php</code></p>";
echo "</div>";
?>

</body>
</html>
