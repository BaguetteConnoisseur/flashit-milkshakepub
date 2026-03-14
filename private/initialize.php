<?php
session_start();

// 1. Define Paths
define("PRIVATE_PATH", dirname(__FILE__));
define("PROJECT_PATH", dirname(PRIVATE_PATH));
define("PUBLIC_PATH", PROJECT_PATH . '/public');

define("SHARED_PATH", PRIVATE_PATH . '/shared');
define("WWW_ROOT", '');

// 2. Load Core Requirements 
// We load db.php first so everything else can use the db() function
require_once(PROJECT_PATH . '/src/db.php');
require_once(PRIVATE_PATH . '/auth.php');

// 3. Event/Pub Tracking Logic
function ensure_pub_tracking() {
    $db = db(); 
    if (!isset($_SESSION['current_pub_id'])) {
        $stmt = $db->query("SELECT event_id, event_name FROM sales_events WHERE is_active = 1 LIMIT 1");
        $event = $stmt->fetch();
        if ($event) {
            $_SESSION['active_pub_id'] = (int)$event['event_id'];
            $_SESSION['active_pub_name'] = $event['event_name'];
        }
    }
    return $_SESSION['current_pub_id'] ?? null;
}

// Run the tracker
$pub = ensure_pub_tracking();

// 4. Protection Logic
$loggedIn = is_logged_in();
$currentUri = $_SERVER['REQUEST_URI'];
$isPublicPage = false;

$publicKeywords = ['index.php', 'bar-view.php', 'debug.php', '/api/', '/public/menu']; 

foreach ($publicKeywords as $keyword) {
    if (strpos($currentUri, $keyword) !== false) {
        $isPublicPage = true;
        break;
    }
}

if (!$loggedIn && !$isPublicPage) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($currentUri, '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["error" => "Login required"]);
        exit;
    }
    header("Location: /index.php");
    exit;
}

// 5. Helpers
function csrf_token_input() {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}