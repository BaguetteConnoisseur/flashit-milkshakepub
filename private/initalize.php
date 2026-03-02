<?php
// Create root var with base path support
$base_path = getenv('BASE_PATH') ?: '';

$public_pos = strpos($_SERVER['SCRIPT_NAME'], '/public');
if ($public_pos !== false) {
    // Traditional setup: /some/path/public/index.php
    $doc_root = substr($_SERVER['SCRIPT_NAME'], 0, $public_pos + 7);
} else {
    // Docker/direct public serving: /index.php or /milkshakepub/index.php
    $doc_root = $base_path;
}
define("WWW_ROOT", $doc_root);

// Define file paths
define("PRIVATE_PATH", dirname(__FILE__));
define("PROJECT_PATH", dirname(PRIVATE_PATH));
define("PUBLIC_PATH", PROJECT_PATH . '/public');
define("SHARED_PATH", PRIVATE_PATH . '/shared');

// Require code libraries
require("master_code/top-user-check.php");
require("functions.php");
