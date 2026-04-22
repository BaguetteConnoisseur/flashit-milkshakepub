<?php

/**
 * Global shared helper file.
 *
 * This file is intentionally required from `private/initialize.php`
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

function app_url($path = '') {
    $path = ltrim((string) $path, '/');

    return '/' . $path;
}

function app_asset_url($path) {
    return app_url('assets/' . ltrim((string) $path, '/'));
}

function app_api_url($path) {
    return app_url('api/' . ltrim((string) $path, '/'));
}

/**
 * Returns a normalized request path from a URI.
 */
function normalize_request_path($uri) {
    $path = parse_url((string) $uri, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
        return '/';
    }

    $normalized = rtrim($path, '/');
    return $normalized === '' ? '/' : $normalized;
}

/**
 * Defines which paths are publicly accessible without login.
 */
function is_public_path($uri) {
    $path = normalize_request_path($uri);

    $publicExactPaths = [
        '/',
        '/index.php',
        '/bar',
        '/views/bar-view.php',
    ];

    if (in_array($path, $publicExactPaths, true)) {
        return true;
    }

    // Keep existing behavior for menu routes under /public/menu.
    return strpos($path, '/public/menu') === 0;
}

/**
 * Detects if request should receive JSON auth errors.
 */
function is_api_or_ajax_request($uri) {
    $path = normalize_request_path($uri);

    if (strpos($path, '/api/') === 0 || strpos($path, '/public/api/') === 0) {
        return true;
    }

    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return is_string($requestedWith) && strcasecmp($requestedWith, 'XMLHttpRequest') === 0;
}

/**
 * Ensures the session has the current active pub/event info.
 * Sets $_SESSION['active_pub_id'] and $_SESSION['active_pub_name'] if not set.
 * Returns the current pub/event id or null.
 */

function ensure_pub_tracking() {
    $db = db();
    if (!isset($_SESSION['active_pub_id'])) {
        $stmt = $db->query("SELECT event_id, event_name FROM pub_events WHERE is_active = 1 LIMIT 1");
        $event = $stmt->fetch();
        if ($event) {
            $_SESSION['active_pub_id'] = (int)$event['event_id'];
            $_SESSION['active_pub_name'] = $event['event_name'];
        }
    }
    return isset($_SESSION['active_pub_id']) ? (int)$_SESSION['active_pub_id'] : null;
}

/**
 * Deactivates all milkshake and toast items except those with item_id 1-6 for the given event.
 * @param int $eventId
 */
function ensure_pub_menu_links(int $eventId) {
    $db = db();
    // Remove all existing links for this event
    $db->prepare("DELETE FROM event_menu_items WHERE event_id = ?")->execute([$eventId]);
        // Find the previous event (the most recent event_id less than the new one)
        $stmt = $db->prepare("SELECT event_id FROM pub_events WHERE event_id < ? ORDER BY event_id DESC LIMIT 1");
        $stmt->execute([$eventId]);
        $prevEventId = $stmt->fetchColumn();

        if ($prevEventId) {
            // Copy menu item links and their active state from the previous event
            $db->prepare("INSERT INTO event_menu_items (event_id, item_id, is_active)
                SELECT :event_id, item_id, is_active FROM event_menu_items WHERE event_id = :prev_event_id")
                ->execute(['event_id' => $eventId, 'prev_event_id' => $prevEventId]);
        } else {
            // Fallback: if no previous event, default to 1-6 active, others inactive
            $db->prepare("INSERT INTO event_menu_items (event_id, item_id, is_active)
                SELECT :event_id, item_id, CASE WHEN item_id BETWEEN 1 AND 6 THEN 1 ELSE 0 END FROM menu_items")
                ->execute(['event_id' => $eventId]);
        }
}

/**
 * Generate a unique slug for a menu item based on its name.
 * Ensures the slug is unique in the menu_items table.
 * Only uses lowercase letters, numbers, and dashes.
 */
function generate_unique_slug($name) {
    $db = db();
    // Slug: lowercase, replace spaces/specials with dashes, remove leading/trailing dashes
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    // If slug is empty (e.g. name had no valid chars), generate a random one
    if ($slug === '') {
        $slug = substr(bin2hex(random_bytes(8)), 0, 16);
    }

    // Check for uniqueness and append a number if needed
    $i = 2;
    $unique = $slug;
    while (true) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM menu_items WHERE slug = ?");
        $stmt->execute([$unique]);
        if ($stmt->fetchColumn() == 0) break;
        $unique = $slug . '-' . $i;
        $i++;
    }
    return $unique;
}