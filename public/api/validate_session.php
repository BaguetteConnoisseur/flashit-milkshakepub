<?php
require_once(__DIR__ . '/../../private/session_bootstrap.php');

header('Content-Type: text/plain; charset=utf-8');

if (!empty($_SESSION['logged_in'])) {
    echo 'OK';
    return;
}

echo 'NO';
