<?php
// Helper for syncing order status with its items
function syncOrderStatusWithItems($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT status FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$statuses) return;
    if (count($statuses) > 0 && count(array_unique($statuses)) === 1 && $statuses[0] === 'Done') {
        $newStatus = 'Done';
    } elseif (in_array('In Progress', $statuses, true) || in_array('Done', $statuses, true)) {
        $newStatus = 'In Progress';
    } else {
        $newStatus = 'Pending';
    }
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->execute([$newStatus, $order_id]);
}
