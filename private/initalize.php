<?php
ini_set('default_charset', 'UTF-8');

$appTimezone = getenv('APP_TIMEZONE') ?: 'Europe/Stockholm';
date_default_timezone_set($appTimezone);
define("APP_TIMEZONE", $appTimezone);

$base_path = getenv('BASE_PATH') ?: '';

$public_pos = strpos($_SERVER['SCRIPT_NAME'], '/public');
if ($public_pos !== false) {
    $doc_root = substr($_SERVER['SCRIPT_NAME'], 0, $public_pos + 7);
} else {
    $doc_root = $base_path;
}
define("WWW_ROOT", $doc_root);

// Define file paths
define("PRIVATE_PATH", dirname(__FILE__));
define("PROJECT_PATH", dirname(PRIVATE_PATH));
define("PUBLIC_PATH", PROJECT_PATH . '/public');
define("SHARED_PATH", PRIVATE_PATH . '/shared');

// Require code libraries
require("functions.php");
require("master_code/top-user-check.php");

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
