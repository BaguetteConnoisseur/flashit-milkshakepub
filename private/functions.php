<?php

/**
 * Global shared helper file.
 *
 * This file is intentionally required from `private/initalize.php`
 * as a central extension point for app-wide utility functions.
 */

function csrf_token() {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_token_input($fieldName = 'csrf_token') {
    $field = htmlspecialchars((string) $fieldName, ENT_QUOTES, 'UTF-8');
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');

    return '<input type="hidden" name="' . $field . '" value="' . $token . '">';
}

function csrf_token_is_valid($token, $fieldName = 'csrf_token') {
    if (!is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = $_SESSION[$fieldName] ?? $_SESSION['csrf_token'] ?? '';

    return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function require_csrf_token($fieldName = 'csrf_token') {
    $postedToken = $_POST[$fieldName] ?? '';

    if (!csrf_token_is_valid($postedToken, $fieldName)) {
        http_response_code(403);
        exit('Ogiltig begäran. Ladda om sidan och försök igen.');
    }
}
