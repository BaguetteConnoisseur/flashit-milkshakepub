<?php
require_once(__DIR__ . '/session_bootstrap.php');

// 1. Define Paths
define("PRIVATE_PATH", dirname(__FILE__));
define("PROJECT_PATH", dirname(PRIVATE_PATH));
define("PUBLIC_PATH", PROJECT_PATH . '/public');

define("TEMPLATE_PATH", PRIVATE_PATH . '/templates');
define("WWW_ROOT", '');

// 2. Load Core Requirements 
// We load db.php first so everything else can use the db() function
require_once(PRIVATE_PATH . '/src/database/db.php');
require_once(PRIVATE_PATH . '/auth.php');
require_once(PRIVATE_PATH . '/functions.php');

// 3. Run Event/Pub Tracking Logic
$pub = ensure_pub_tracking();

// 4. Protection Logic
$loggedIn = is_logged_in();
$currentUri = $_SERVER['REQUEST_URI'];
$isPublicPage = false;

$publicKeywords = ['index.php', 'bar-view.php', '/public/menu']; 

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