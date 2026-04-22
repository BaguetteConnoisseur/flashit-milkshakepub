<?php
require_once(__DIR__ . '/../../private/initialize.php');
require_once(__DIR__ . '/../../private/src/services/inventory_manager.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$response = ['success' => false, 'error' => ''];
$activePubId = $_SESSION['active_pub_id'];
$inventory = new InventoryManager(db(), $activePubId);

// Support both JSON and classic form POST
$isJson = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
$data = [];
if ($isJson) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
} else {
    $data = $_POST;
}

// CSRF token: always required, from JSON or POST
$csrfToken = $data['csrf_token'] ?? '';
if (!csrf_token_is_valid($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ogiltig begaran. Ladda om sidan och forsok igen.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add milkshake or toast
        if (($data['action'] ?? '') === 'add') {
            $category = $data['category'] ?? '';
            $name = trim($data['name'] ?? '');
            $slug = generate_unique_slug($name);
            $inventory->addItem([
                'category'    => $category,
                'name'        => $name,
                'slug'        => $slug,
                'description' => trim($data['description'] ?? ''),
                'ingredients' => trim($data['ingredients'] ?? ''),
                'color'       => trim($data['color'] ?? '#ffffff')
            ]);
            $response['success'] = true;
        }
        // Toggle active/inactive
        elseif (($data['action'] ?? '') === 'toggle') {
            $itemId = (int)($data['item_id'] ?? 0);
            $isActive = (int)($data['new_status'] ?? 0) === 1;
            $inventory->toggleActive($itemId, $isActive);
            $response['success'] = true;
        }
    }
} catch (Throwable $e) {
    $response['error'] = $e->getMessage();
}
echo json_encode($response);