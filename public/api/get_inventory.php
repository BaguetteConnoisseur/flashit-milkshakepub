<?php
require_once(__DIR__ . '/../../private/initialize.php');
require_once(__DIR__ . '/../../private/src/services/InventoryManager.php');
header('Content-Type: application/json');

$activePubId = $_SESSION['active_pub_id'];
$inventory = new InventoryManager(db(), $activePubId);

// Fetch all inventory for the current event, grouped by category and active/inactive
$data = [
    'milkshakes' => [
        'active' => $inventory->getItemsByCategory('milkshake', true),
        'inactive' => $inventory->getItemsByCategory('milkshake', false)
    ],
    'toasts' => [
        'active' => $inventory->getItemsByCategory('toast', true),
        'inactive' => $inventory->getItemsByCategory('toast', false)
    ]
];

echo json_encode($data);