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
    $sql = "SELECT oi.order_item_id, oi.status, mi.name, mi.category, oi.order_id
            FROM order_items oi
            JOIN menu_items mi ON oi.item_id = mi.item_id
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.status != 'Served' AND o.event_id = :event_id
            ORDER BY oi.order_item_id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['event_id' => $event_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
