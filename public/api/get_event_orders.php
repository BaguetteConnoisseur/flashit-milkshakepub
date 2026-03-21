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
			o.order_id, o.status, o.customer_name, o.order_comment, o.order_number, o.created_at,
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

	// Decode the items JSON for each order
	foreach ($orders as &$order) {
		$order['items'] = json_decode($order['items'], true);
		// Add ready_to_serve and is_fully_delivered flags
		$order['ready_to_serve'] = true;
		$order['is_fully_delivered'] = true;
		$has_items = !empty($order['items']);
		if (!$has_items) {
			$order['ready_to_serve'] = false;
			$order['is_fully_delivered'] = false;
		} else {
			foreach ($order['items'] as $item) {
				if ($item['status'] !== 'Done') {
					$order['ready_to_serve'] = false;
				}
				if ($item['status'] !== 'Done') {
					$order['is_fully_delivered'] = false;
				}
			}
		}
	}

	echo json_encode($orders);
} catch (Exception $e) {
	echo json_encode(["error" => $e->getMessage()]);
}
