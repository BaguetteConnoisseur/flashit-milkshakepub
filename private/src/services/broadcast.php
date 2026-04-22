<?php
function broadcast($data) {
    require_once(__DIR__ . '/../../config.php');

    $secret = BROADCAST_SECRET;
    if ($secret === '') {
        error_log('Broadcast skipped: BROADCAST_SECRET is not configured');
        return false;
    }

    $url = "http://websocket:8082/broadcast";
    
    $ch = curl_init($url);
    if ($ch === false) {
        error_log('Broadcast init failed for URL: ' . $url);
        return false;
    }

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1500);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 3000);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Broadcast-Secret: ' . $secret,
    ]);
    
    $response = curl_exec($ch);
    if ($response === false) {
        error_log('Broadcast cURL error: ' . curl_error($ch));
    }

    curl_close($ch);
    return $response;
}