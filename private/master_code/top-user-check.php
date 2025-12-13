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

	if ($absoluteUsername == "flashit" && $absolutePassword == "flashit_msp") {
		$loggedIn = true;
	} else {
		session_destroy();
		header("Location: " . WWW_ROOT . "/index.php");
	}
}
