<?php
require_once __DIR__ . "/../../private/initialize.php";
require_once __DIR__ . "/../../private/src/services/order_manager.php";
require_once __DIR__ . "/../../private/src/services/broadcast.php";

header('Content-Type: application/json');

try {
    $pdo = db();
    $orders = new OrderManager($pdo, (int) $_SESSION['active_pub_id']);
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON payload"]);
        exit;
    }
    $csrf_token = $data['csrf_token'] ?? '';
    if (!csrf_token_is_valid($csrf_token)) {
        http_response_code(403);
        echo json_encode(["error" => "Invalid CSRF token"]);
        exit;
    }
    $order_id = $data['order_id'] ?? null;
    $manual_status = $data['status'] ?? null;
    $validStatuses = ['Pending', 'In Progress', 'Done', 'Delivered'];
    if (!$order_id) {
        http_response_code(400);
        echo json_encode(["error" => "Missing order_id"]);
        exit;
    }
    if (!$manual_status || !in_array($manual_status, $validStatuses, true)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid or missing status"]);
        exit;
    }

    $orders->updateOrderStatus((int) $order_id, $manual_status);
    
    // Broadcast the order status change so all views update in real-time
    broadcast([
        "type" => "order_updated",
        "order_id" => $order_id,
        "status" => $manual_status
    ]);
    
    echo json_encode(["success" => true, "order_id" => $order_id, "status" => $manual_status]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
