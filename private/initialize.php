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
ensure_pub_tracking();

// 4. Protection Logic
$loggedIn = is_logged_in();
$currentUri = $_SERVER['REQUEST_URI'];

$isPublicPage = is_public_path($currentUri);

if (!$loggedIn && !$isPublicPage) {
    if (is_api_or_ajax_request($currentUri)) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["error" => "Login required"]);
        exit;
    }
    header('Location: ' . app_url(''));
    exit;
}