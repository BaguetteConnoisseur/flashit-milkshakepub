<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(__DIR__ . "/../../private/src/database/db.php");

header('Content-Type: application/json');

try {
    $pdo = db();
    $sql = "SELECT oi.order_item_id, oi.status, mi.name, mi.category, oi.order_id, o.event_id
            FROM order_items oi
            JOIN menu_items mi ON oi.item_id = mi.item_id
            JOIN orders o ON oi.order_id = o.order_id
            ORDER BY o.event_id, oi.order_item_id ASC";
    $stmt = $pdo->query($sql);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
