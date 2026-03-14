<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(__DIR__ . "/../../src/db.php");

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

try {
    $pdo = db(); 
    $sql = "SELECT 
                oi.order_item_id, 
                oi.status, 
                mi.name, 
                mi.category 
            FROM order_items oi
            JOIN menu_items mi ON oi.item_id = mi.item_id
            WHERE oi.status != 'Served'
            ORDER BY oi.order_item_id ASC";

    $stmt = $pdo->query($sql); 
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}