<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(__DIR__ . "/../../private/src/database/db.php");

header('Content-Type: application/json');

try {
    $pdo = db();
    // Get the current event id from session
    $event_id = $_SESSION['active_pub_id'] ?? null;
    if (!$event_id) {
        echo json_encode(["error" => "No active event found."]);
        exit;
    }
    // Query for active milkshakes for this event
    $sql = "SELECT mi.*
            FROM menu_items mi
            JOIN event_menu_items emi ON mi.item_id = emi.item_id
            WHERE emi.event_id = :event_id
              AND emi.is_active = 1
              AND mi.category = 'milkshake'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['event_id' => $event_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
