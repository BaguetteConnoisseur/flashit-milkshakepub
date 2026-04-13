<?php
require_once __DIR__ . "/../../private/initialize.php";
require_once __DIR__ . "/../../private/src/services/order_manager.php";
require_once __DIR__ . "/../../private/src/services/broadcast.php";

header('Content-Type: application/json');

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

$item_id = isset($data['item_id']) ? (int) $data['item_id'] : 0;
$status = $data['status'] ?? '';
if ($item_id <= 0 || $status === '') {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid item_id/status"]);
    exit;
}

$activePubId = (int) $_SESSION['active_pub_id'];
$allowedStatuses = ['Pending', 'In Progress', 'Done', 'Delivered'];
if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid status for order item: $status"]);
    exit;
}

try {
    $db = db();
    $orders = new OrderManager($db, $activePubId);
    $orderId = $orders->updateOrderItemStatus($item_id, $status);

    broadcast([
        'type' => 'item_updated',
        'item_id' => $item_id,
        'status' => $status,
    ]);

    echo json_encode(["ok" => true, "order_id" => $orderId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}