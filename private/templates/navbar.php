<?php
/**
 * Admin Navigation Bar
 * Include this in all admin views except bar-view
 * <?php require(TEMPLATE_PATH . "/navbar.php"); ?>
 */
?>

<style>
    .admin-navbar {
        background-color: rgba(255, 255, 255, 0.98);
        padding: 12px 20px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 15px;
        backdrop-filter: blur(2px);
    }

    .navbar-brand {
        font-size: 32px;
        font-weight: 800;
        color: #09cdda;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-right: auto;
        padding: 4px 0;
        transition: all 0.2s ease;
        border: none;
        background: none;
        cursor: pointer;
    }

    .navbar-brand:hover {
        color: #0bb3be;
        transform: scale(1.05);
    }

    .navbar-brand .logo-icon {
        font-size: 42px;
        display: inline-flex;
        align-items: baseline;
        vertical-align: middle;
        margin-top: -8px;
    }

    .admin-navbar #connection-status {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(0, 0, 0, 0.05);
        border: 1px solid #d1d5db;
        color: #6b7280;
        transition: color 0.3s, background 0.3s, border-color 0.3s;
        white-space: nowrap;
    }

    .admin-navbar #connection-status[data-status="live"] {
        color: #16a34a;
        background: rgba(34, 197, 94, 0.1);
        border-color: rgba(34, 197, 94, 0.3);
    }

    .admin-navbar #connection-status[data-status="offline"] {
        color: #dc2626;
        background: rgba(220, 38, 38, 0.1);
        border-color: rgba(220, 38, 38, 0.3);
    }

    .admin-navbar #connection-status[data-status="sleeping"] {
        color: #6b7280;
        background: rgba(107, 114, 128, 0.1);
        border-color: rgba(107, 114, 128, 0.25);
    }

    .admin-navbar #connection-status[data-status="reconnecting"] {
        color: #dc2626;
        background: rgba(220, 38, 38, 0.1);
        border-color: rgba(220, 38, 38, 0.3);
    }
</style>

<nav class="admin-navbar">
    <a href="/" class="navbar-brand">
        <span class="logo-icon">🥤</span>
        FlashIT
    </a>
    <div id="connection-status" data-status="sleeping">● Sleeping</div>
</nav>

