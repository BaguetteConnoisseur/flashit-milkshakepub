<?php
// Helper for syncing order status with its items
function syncOrderStatusWithItems($pdo, $item_id) {

    // Fetch order_id from item_id
    $stmt = $pdo->prepare("SELECT order_id FROM order_items WHERE order_item_id = ?");
    $stmt->execute([$item_id]);
    $order_id = $stmt->fetchColumn();

    // Fetch all statuses for this order
    $stmt = $pdo->prepare("SELECT status FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$statuses) return;

    // If all items are 'Delivered', mark order as 'Delivered'
    if (count($statuses) > 0 && count(array_unique($statuses)) === 1 && $statuses[0] === 'Delivered') {
        $newStatus = 'Delivered';
    }
    // Else if all items are 'Done' or 'Delivered', mark order as 'Done'
    elseif (count($statuses) > 0 && !in_array('Pending', $statuses, true) && !in_array('In Progress', $statuses, true) && array_reduce($statuses, function($carry, $s) { return $carry && ($s === 'Done' || $s === 'Delivered'); }, true)) {
        $newStatus = 'Done';
    }
    // Else if any are 'In Progress' or 'Done', mark as 'In Progress'
    elseif (in_array('In Progress', $statuses, true) || in_array('Done', $statuses, true)) {
        $newStatus = 'In Progress';
    } else {
        $newStatus = 'Pending';
    }

    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->execute([$newStatus, $order_id]);
}
