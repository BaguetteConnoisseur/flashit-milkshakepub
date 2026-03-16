<?php
header('Content-Type: text/plain');
echo "--- FLASHIT SYSTEM CHECK ---\n";

// 1. Check Database
try {
    require_once "../private/src/database/db.php";
    $db = db();
    echo "✅ DATABASE: Connected (Flashit_beställnigsark)\n";
} catch (Exception $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
}

// 2. Check WebSocket API (Internal)
$ch = curl_init("http://websocket:8082/broadcast");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2);
curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code > 0) {
    echo "✅ WEBSOCKET API: Internal port 8082 is reachable\n";
} else {
    echo "❌ WEBSOCKET API ERROR: Cannot reach Node server on port 8082\n";
}

// 3. Check PHP Version
echo "✅ PHP VERSION: " . phpversion() . "\n";