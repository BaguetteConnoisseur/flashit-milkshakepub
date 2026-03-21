<?php
require_once __DIR__ . "/../../private/initialize.php";
require_once __DIR__ . "/../../private/src/database/db.php";

header('Content-Type: application/json');

try {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
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
        echo json_encode(["error" => "Missing order_id"]);
        exit;
    }
    if (!$manual_status || !in_array($manual_status, $validStatuses, true)) {
        echo json_encode(["error" => "Invalid or missing status"]);
        exit;
    }

    // Update order and all items if status is Delivered or Done
    if ($manual_status === 'Delivered' || $manual_status === 'Done') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->execute([$manual_status, $order_id]);

            // Update all items in this order to match the manual status
            $stmt = $pdo->prepare("UPDATE order_items SET status = ? WHERE order_id = ?");
            $stmt->execute([$manual_status, $order_id]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    } else {
        // Only update order status
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->execute([$manual_status, $order_id]);
    }
    echo json_encode(["success" => true, "order_id" => $order_id, "status" => $manual_status]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
