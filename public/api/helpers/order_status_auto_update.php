<?php
/**
 * Helper for syncing order status with its items.
 *
 * Keeps the parent order's status in sync with the statuses of all its items.
 *
 * How it works:
 * 1. Finds the order for the given item ID.
 * 2. Fetches the status of every item in that order.
 * 3. Determines the new order status:
 *    - If all items are 'Delivered', sets order to 'Delivered'.
 *    - If all items are 'Done' or 'Delivered' (none 'Pending' or 'In Progress'), sets order to 'Done'.
 *    - If any item is 'In Progress' or 'Done', sets order to 'In Progress'.
 *    - Otherwise, sets order to 'Pending'.
 * 4. Updates the order's status in the database.
 *
 * Usage:
 *   Call this after updating any order item's status:
 *     syncOrderStatusWithItems($pdo, $item_id);
 *
 * Extend this logic if you add new item/order statuses in the future.
 */

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
