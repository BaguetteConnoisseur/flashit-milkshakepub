<?php

require_once __DIR__ . "/../../private/src/database/db.php";
require_once __DIR__ . "/../../private/src/services/broadcast.php";
require_once __DIR__ . '/helpers/order_status_auto_update.php';

$data = json_decode(file_get_contents("php://input"), true);

$item_id = $data["item_id"];
$status = $data["status"];

// Check to only allow valid statuses for order_items
$allowed_statuses = ['Pending', 'In Progress', 'Done'];
if (!in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid status for order item: $status"]);
    exit;
}

$db = db();

$stmt = $db->prepare("
UPDATE order_items
SET status=?
WHERE order_item_id=?
");

$stmt->execute([$status, $item_id]);

// Get the order_id for this item and update order status
$stmt = $db->prepare("SELECT order_id FROM order_items WHERE order_item_id = ?");
$stmt->execute([$item_id]);
$order_id = $stmt->fetchColumn();
if ($order_id) {
    syncOrderStatusWithItems($db, $order_id);
}

broadcast([
    "type"=>"item_updated",
    "item_id"=>$item_id,
    "status"=>$status
]);

echo json_encode(["ok"=>true]);