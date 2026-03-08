<?php
session_start();

function admin_credentials_are_valid($username, $password) {
	$admin_user = getenv('ADMIN_USERNAME') ?: '';
	$admin_pass = getenv('ADMIN_PASSWORD') ?: '';

	if (empty($admin_user) || empty($admin_pass)) {
		return false;
	}

	return hash_equals($admin_user, (string) $username) && hash_equals($admin_pass, (string) $password);
}

function attempt_login($username, $password) {
	if (!admin_credentials_are_valid($username, $password)) {
		return false;
	}

	session_regenerate_id(true);
	$_SESSION['absolute-username'] = (string) $username;
	$_SESSION['is-admin-authenticated'] = true;
	unset($_SESSION['absolute-password']);

	return true;
}

function handle_login_post($redirectPath = null) {
	if (!isset($_POST['login'])) {
		return false;
	}

	require_csrf_token();

	$username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
	$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

	if (attempt_login($username, $password)) {
		$location = $redirectPath ?? ($_SERVER['PHP_SELF'] ?? (WWW_ROOT . "/index.php"));
		header("Location: " . $location);
		exit;
	}

	return true;
}

if (isset($_POST['logout-account'])) {
	require_csrf_token();
	session_unset();
	session_destroy();
	header("Location: " . WWW_ROOT . "/index.php");
	exit;
}

$loggedIn = false;

if (!empty($_SESSION['is-admin-authenticated']) && isset($_SESSION['absolute-username'])) {
	$admin_user = getenv('ADMIN_USERNAME') ?: '';
	$absoluteUsername = (string) $_SESSION['absolute-username'];

	if (!empty($admin_user) && hash_equals($admin_user, $absoluteUsername)) {
		$loggedIn = true;
	} else {
		session_unset();
		session_destroy();
		header("Location: " . WWW_ROOT . "/index.php");
		exit;
	}
} elseif (isset($_SESSION['absolute-username']) && isset($_SESSION['absolute-password'])) {
	$absoluteUsername = $_SESSION['absolute-username'];
	$absolutePassword = $_SESSION['absolute-password'];

	if (admin_credentials_are_valid($absoluteUsername, $absolutePassword)) {
		session_regenerate_id(true);
		$_SESSION['absolute-username'] = (string) $absoluteUsername;
		$_SESSION['is-admin-authenticated'] = true;
		unset($_SESSION['absolute-password']);
		$loggedIn = true;
	} else {
		session_unset();
		session_destroy();
		header("Location: " . WWW_ROOT . "/index.php");
		exit;
	}
}

function require_login($redirectPath = null) {
	global $loggedIn;

	if ($loggedIn) {
		return;
	}

	$location = $redirectPath ?? (WWW_ROOT . "/index.php");
	header("Location: " . $location);
	exit;
}
