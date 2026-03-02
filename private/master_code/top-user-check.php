<?php
session_start();

if (isset($_POST['logout-account'])) {
	session_destroy();
	header("Location: " . WWW_ROOT . "/index.php");
	exit;
}

$loggedIn = false;

if (isset($_SESSION['absolute-username']) && isset($_SESSION['absolute-password'])) {
	$absoluteUsername = $_SESSION['absolute-username'];
	$absolutePassword = $_SESSION['absolute-password'];

	$admin_user = getenv('ADMIN_USERNAME') ?: '';
	$admin_pass = getenv('ADMIN_PASSWORD') ?: '';

	if (!empty($admin_user) && !empty($admin_pass) && $absoluteUsername == $admin_user && $absolutePassword == $admin_pass) {
		$loggedIn = true;
	} else {
		session_destroy();
		header("Location: " . WWW_ROOT . "/index.php");
		exit;
	}
}
