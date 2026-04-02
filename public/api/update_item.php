<?php
require_once __DIR__ . "/../../private/initialize.php";
require_once __DIR__ . "/../../private/src/services/broadcast.php";
require_once __DIR__ . '/helpers/order_status_auto_update.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$csrf_token = $data["csrf_token"] ?? '';
if (!csrf_token_is_valid($csrf_token)) {
    http_response_code(403);
    echo json_encode(["error" => "Invalid CSRF token"]);
    exit;
}

$item_id = isset($data["item_id"]) ? (int) $data["item_id"] : 0;
$status = $data["status"] ?? '';
if ($item_id <= 0 || $status === '') {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid item_id/status"]);
    exit;
}

$activePubId = (int) ($_SESSION['active_pub_id'] ?? 0);
if ($activePubId <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "No active event found"]);
    exit;
}

// Check to only allow valid statuses for order_items
$allowed_statuses = ['Pending', 'In Progress', 'Done', 'Delivered'];
if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid status for order item: $status"]);
    exit;
}

$db = db();

// Ensure the item belongs to the current active event.
$ownershipStmt = $db->prepare(
    "SELECT oi.order_item_id
     FROM order_items oi
     JOIN orders o ON o.order_id = oi.order_id
     WHERE oi.order_item_id = ? AND o.event_id = ?
     LIMIT 1"
);
$ownershipStmt->execute([$item_id, $activePubId]);
if (!$ownershipStmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(["error" => "Order item not found for active event"]);
    exit;
}

$stmt = $db->prepare("
UPDATE order_items
SET status=?
WHERE order_item_id=?
");

$stmt->execute([$status, $item_id]);

// Update order status using helper
syncOrderStatusWithItems($db, $item_id);

broadcast([
    "type"=>"item_updated",
    "item_id"=>$item_id,
    "status"=>$status
]);

echo json_encode(["ok"=>true]);