<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(__DIR__ . "/../../private/src/services/broadcast.php");

$db = db();

// 1. Grab the JSON data sent from your frontend
$input = file_get_contents('php://input');
$request = json_decode($input, true);
$csrf_token = $request['csrf_token'] ?? '';
if (!csrf_token_is_valid($csrf_token)) {
    http_response_code(403);
    echo json_encode(["error" => "Invalid CSRF token"]);
    exit;
}

try {
    // Start transaction for atomicity and speed
    $db->beginTransaction();

    // 2. Use the Active Pub ID from the session (the 'ensure_pub_tracking' logic)
    $activePubId = $_SESSION['active_pub_id'] ?? 1;

    // Get next order_number for this event
    $stmt = $db->prepare("SELECT COALESCE(MAX(order_number), 0) + 1 AS next_num FROM orders WHERE event_id = ?");
    $stmt->execute([$activePubId]);
    $next_order_number = $stmt->fetchColumn();

    $customerName = $request['customer_name'] ?? "Guest";

    // Insert order with order_number only
    $stmt = $db->prepare("INSERT INTO orders (event_id, order_number, customer_name) VALUES (?, ?, ?)");
    $stmt->execute([$activePubId, $next_order_number, $customerName]);
    $order_id = $db->lastInsertId();

    // 4. Process the actual items in the cart
    $items_to_add = $request['items'] ?? [];
    if (empty($items_to_add)) {
        throw new Exception("No items in order");
    }

    $slugs = [];
    foreach ($items_to_add as $item) {
        if (is_array($item) && isset($item['slug'])) {
            $slugs[] = $item['slug'];
        } else if (is_string($item)) {
            $slugs[] = $item;
        }
    }
    if (empty($slugs)) {
        throw new Exception("No valid item slugs");
    }

    // Batch lookup item IDs
    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $stmt_get_ids = $db->prepare("SELECT item_id, slug FROM menu_items WHERE slug IN ($placeholders)");
    $stmt_get_ids->execute($slugs);
    $slug_to_id = [];
    while ($row = $stmt_get_ids->fetch(PDO::FETCH_ASSOC)) {
        $slug_to_id[$row['slug']] = $row['item_id'];
    }

    // Prepare values for batch insert into order_items
    $order_items_values = [];
    foreach ($items_to_add as $item) {
        if (is_array($item) && isset($item['slug']) && isset($slug_to_id[$item['slug']])) {
            $comment = isset($item['comment']) ? $item['comment'] : '';
            $order_items_values[] = '(' . implode(',', [
                $db->quote($order_id),
                $db->quote($slug_to_id[$item['slug']]),
                $db->quote('Pending'),
                $db->quote($comment)
            ]) . ')';
        } else if (is_string($item) && isset($slug_to_id[$item])) {
            $order_items_values[] = '(' . implode(',', [
                $db->quote($order_id),
                $db->quote($slug_to_id[$item]),
                $db->quote('Pending'),
                $db->quote('')
            ]) . ')';
        }
    }

    if (empty($order_items_values)) {
        throw new Exception("No valid items found");
    }

    // Batch insert order_items
    $sql = "INSERT INTO order_items (order_id, item_id, status, item_comment) VALUES " . implode(',', $order_items_values);
    $db->exec($sql);

    // Commit transaction
    $db->commit();

    // 5. Notify the stations (Bar, Toast, etc.)
    broadcast([
        "type" => "new_order",
        "order_id" => $order_id,
        "pub_name" => $_SESSION['active_pub_name'] ?? 'Pub'
    ]);

    echo json_encode(["ok" => true, "order_id" => $order_id]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}