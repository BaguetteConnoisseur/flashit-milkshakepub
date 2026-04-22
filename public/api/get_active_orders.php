<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(__DIR__ . "/../../private/src/services/order_manager.php");

header('Content-Type: application/json');

try {
    $pdo = db();
    $eventId = (int) $_SESSION['active_pub_id'];
    $orders = new OrderManager($pdo, $eventId);
    echo json_encode($orders->getActiveOrders());
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
