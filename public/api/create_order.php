<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(__DIR__ . "/../../src/broadcast.php");

$db = db();

// 1. Grab the JSON data sent from your frontend
$input = file_get_contents('php://input');
$request = json_decode($input, true);

try {
    // 2. Use the Active Pub ID from the session (the 'ensure_pub_tracking' logic)
    $activePubId = $_SESSION['active_pub_id'] ?? 1; 

    // 3. Insert into 'orders'
    $stmt = $db->prepare("INSERT INTO orders (event_id, order_number, customer_name) VALUES (?, ?, ?)");
    
    // Use a unique order number (PUB-TIME) or the one from Toast API if syncing
    $orderNumber = "ORD-" . date('His'); 
    $customerName = $request['customer_name'] ?? "Guest";

    $stmt->execute([$activePubId, $orderNumber, $customerName]);
    $order_id = $db->lastInsertId();

    // 4. Process the actual items in the cart
    // We expect $request['items'] to be an array of slugs: ['oreo-supreme', 'toast-standard']
    $items_to_add = $request['items'] ?? [];

    if (empty($items_to_add)) {
        throw new Exception("No items in order");
    }

    $stmt_item = $db->prepare("INSERT INTO order_items (order_id, item_id, status) VALUES (?, ?, ?)");

    // Prepare the lookup and the insert
    $stmt_get_id = $db->prepare("SELECT item_id FROM menu_items WHERE slug = ?");
    $stmt_item = $db->prepare("INSERT INTO order_items (order_id, item_id, status) VALUES (?, ?, ?)");

    foreach($items_to_add as $slug) {
        // 1. Find the real ID using the obvious slug
        $stmt_get_id->execute([$slug]);
        $item = $stmt_get_id->fetch();

        if ($item) {
            // 2. Insert using that ID
            $stmt_item->execute([
                $order_id,
                $item['item_id'],
                "Pending"
            ]);
        }
    }

    // 5. Notify the stations (Bar, Toast, etc.)
    broadcast([
        "type" => "new_order",
        "order_id" => $order_id,
        "pub_name" => $_SESSION['active_pub_name'] ?? 'Pub'
    ]);

    echo json_encode(["ok" => true, "order_id" => $order_id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}