<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(__DIR__ . "/../../private/src/database/db.php");

header('Content-Type: application/json');

try {
    $pdo = db();
    $event_id = $_SESSION['active_pub_id'] ?? null;
    if (!$event_id) {
        echo json_encode(["error" => "No active event found."]);
        exit;
    }
    $sql = "
        SELECT 
            o.order_id, o.created_at, o.customer_name, o.order_comment, o.order_number, o.status,
            COALESCE(JSON_ARRAYAGG(
                CASE WHEN oi.order_item_id IS NOT NULL THEN
                    JSON_OBJECT(
                        'order_item_id', oi.order_item_id,
                        'status', oi.status,
                        'comment', oi.item_comment,
                        'item_id', oi.item_id,
                        'name', mi.name,
                        'category', mi.category
                    )
                END
            ), JSON_ARRAY()) AS items
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN menu_items mi ON oi.item_id = mi.item_id
        WHERE o.event_id = :event_id
        GROUP BY o.order_id
        ORDER BY o.created_at ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['event_id' => $event_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter out fully delivered orders
    $active_orders = [];
    foreach ($orders as &$order) {
        $order['items'] = json_decode($order['items'], true);
        $is_fully_delivered = true;
        if (empty($order['items'])) {
            $is_fully_delivered = false;
        } else {
            foreach ($order['items'] as $item) {
                if ($item['status'] !== 'Done') {
                    $is_fully_delivered = false;
                    break;
                }
            }
        }
        if (!$is_fully_delivered) {
            $active_orders[] = $order;
        }
    }

    echo json_encode($active_orders);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
