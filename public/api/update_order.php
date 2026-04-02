<?php
require_once __DIR__ . "/../../private/initialize.php";
require_once __DIR__ . "/../../private/src/services/broadcast.php";

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

    // Handle manual order status changes with smart item updates
    if ($manual_status === 'Delivered') {
        // When marking order as Delivered: upgrade Done items to Delivered
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->execute([$manual_status, $order_id]);
            
            // Upgrade items that are 'Done' to 'Delivered', leave others alone
            $stmt = $pdo->prepare("UPDATE order_items SET status = 'Delivered' WHERE order_id = ? AND status = 'Done'");
            $stmt->execute([$order_id]);
            
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    } elseif ($manual_status === 'Done') {
        // When reverting to Done (after being Delivered): downgrade Delivered items back to Done
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->execute([$manual_status, $order_id]);
            
            // Downgrade items that are 'Delivered' back to 'Done'
            $stmt = $pdo->prepare("UPDATE order_items SET status = 'Done' WHERE order_id = ? AND status = 'Delivered'");
            $stmt->execute([$order_id]);
            
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    } else {
        // For other statuses (Pending, In Progress), only update order, not items
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->execute([$manual_status, $order_id]);
    }
    
    // Broadcast the order status change so all views update in real-time
    broadcast([
        "type" => "order_updated",
        "order_id" => $order_id,
        "status" => $manual_status
    ]);
    
    echo json_encode(["success" => true, "order_id" => $order_id, "status" => $manual_status]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
