<?php
/**
 * Checks if a user is currently logged in.
 */
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Processes all auth-related POST requests (Login & Logout).
 *
 * Adds a simple session-based rate limiter for failed login attempts.
 */
function handle_login_post() {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    // --- Simple rate limiter: max 5 failed attempts per 5 minutes ---
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    // Remove attempts older than 10 minutes
    $_SESSION['login_attempts'] = array_filter(
        $_SESSION['login_attempts'],
        function($ts) { return $ts > time() - 600; }
    );
    if (count($_SESSION['login_attempts']) >= 5) {
        $minutes = ceil((600 - (time() - min($_SESSION['login_attempts']))) / 60);
        return "För många misslyckade inloggningar. Vänta $minutes minut(er) och försök igen.";
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

        // Check password using password_verify and hashed env var
        if (password_verify($pass, ADMIN_PASS_HASH)) {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['last_login'] = time();

            // Reset failed attempts on success
            $_SESSION['login_attempts'] = [];
            header("Location: /index.php");
            exit;
        } else {
            // Record failed attempt timestamp
            $_SESSION['login_attempts'][] = time();
            if (count($_SESSION['login_attempts']) >= 5) {
                $minutes = ceil((600 - (time() - min($_SESSION['login_attempts']))) / 60);
                return "För många misslyckade inloggningar. Vänta $minutes minut(er) och försök igen.";
            } else {
                return "Ogiltiga uppgifter. Försök igen.";
            }
        }
    }

    return false;
}