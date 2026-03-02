<?php
session_start();

if (isset($_POST['logout-account'])) {
	session_destroy();
	header("Location: " . WWW_ROOT . "/index.php");
}

$loggedIn = false;

if (isset($_SESSION['absolute-username']) && isset($_SESSION['absolute-password'])) {
	$absoluteUsername = $_SESSION['absolute-username'];
	$absolutePassword = $_SESSION['absolute-password'];

	$admin_user = getenv('ADMIN_USERNAME') ?: 'admin';
	$admin_pass = getenv('ADMIN_PASSWORD') ?: 'CHANGE_ME';

	if ($absoluteUsername == $admin_user && $absolutePassword == $admin_pass) {
		$loggedIn = true;
	} else {
		session_destroy();
		header("Location: " . WWW_ROOT . "/index.php");
	}
}
