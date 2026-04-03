<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(__DIR__ . "/../../private/src/services/order_manager.php");
require_once(__DIR__ . "/../../private/src/services/broadcast.php");

$db = db();
header('Content-Type: application/json');

// 1. Grab the JSON data sent from your frontend
$input = file_get_contents('php://input');
$request = json_decode($input, true);
$csrf_token = $request['csrf_token'] ?? '';
if (!csrf_token_is_valid($csrf_token)) {
    http_response_code(403);
    echo json_encode(["error" => "Invalid CSRF token"]);
    exit;
}

try {
    $orders = new OrderManager($db, (int) $_SESSION['active_pub_id']);
    $order_id = $orders->createOrder($request);

    // 5. Notify the stations (Bar, Toast, etc.)
    broadcast([
        "type" => "new_order",
        "order_id" => $order_id,
        "pub_name" => $_SESSION['active_pub_name'] ?? 'Pub'
    ]);

    echo json_encode(["ok" => true, "order_id" => $order_id]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}