<?php
require_once __DIR__ . "/../../private/initialize.php";
require_once __DIR__ . "/../../private/src/database/db.php";

header('Content-Type: application/json');

try {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    $order_id = $data['order_id'] ?? null;
    $manual_status = $data['status'] ?? null;
    $validStatuses = ['Pending', 'In Progress', 'Done', 'Delivered', 'Cancelled'];
    if (!$order_id) {
        echo json_encode(["error" => "Missing order_id"]);
        exit;
    }
    if (!$manual_status || !in_array($manual_status, $validStatuses, true)) {
        echo json_encode(["error" => "Invalid or missing status"]);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->execute([$manual_status, $order_id]);
    echo json_encode(["success" => true, "order_id" => $order_id, "status" => $manual_status]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
