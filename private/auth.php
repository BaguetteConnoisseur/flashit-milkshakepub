<?php
/**
 * Checks if a user is currently logged in.
 */
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Guard function for admin pages.
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: /index.php");
        exit;
    }
}

/**
 * Processes all auth-related POST requests (Login & Logout).
 */
function handle_login_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    // 1. Handle Logout
    if (isset($_POST['logout-account'])) {
        // require_csrf_token(); // Uncomment once CSRF is fully implemented
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("Location: /index.php");
        exit;
    }

    // 2. Handle Login
    if (isset($_POST['login'])) {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        // Uses constants from config.php
        if (hash_equals(ADMIN_USER, (string)$user) && hash_equals(ADMIN_PASS, (string)$pass)) {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['last_login'] = time();
            
            header("Location: /index.php");
            exit;
        } else {
            return "Felaktiga inloggningsuppgifter.";
        }
    }

    return false;
}