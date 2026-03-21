<?php
/**
 * Checks if a user is currently logged in.
 */
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
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
        require_csrf_token();
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
        $pass = $_POST['password'] ?? '';

        // Only check password
        if (hash_equals(ADMIN_PASS, (string)$pass)) {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['last_login'] = time();
            
            header("Location: /index.php");
            exit;
        } else {
            return "Felaktigt lösenord.";
        }
    }

    return false;
}