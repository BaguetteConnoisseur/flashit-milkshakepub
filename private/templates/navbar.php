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
        color: #764ba2;
        transform: scale(1.05);
    }

    .navbar-brand .logo-icon {
        font-size: 42px;
        display: inline-flex;
        align-items: baseline;
        vertical-align: middle;        margin-top: -8px;    }

    .admin-navbar a:not(.navbar-brand),
    .admin-navbar button {
        color: #7ea4dd;
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 6px;
        transition: all 0.2s ease;
        font-weight: 500;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1.5px solid #7ea4dd;
        background: none;
        cursor: pointer;
    }

    .admin-navbar a:not(.navbar-brand):hover,
    .admin-navbar button:hover {
        background-color: rgba(126, 164, 221, 0.14);
        color: #6f93c8;
        border-color: #6f93c8;
    }

    .admin-navbar .logout-btn {
        padding: 10px 20px;
        background: #e53e3e;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        float: right;
    }

    .admin-navbar .logout-btn:hover {
        background: #c53030;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(229, 62, 62, 0.4);
        border-color: #c53030;
    }
</style>

<nav class="admin-navbar">
    <a href="/" class="navbar-brand">
        <span class="logo-icon">🥤</span>
        FlashIT
    </a>
    
    <form method="post" action="" style="margin: 0;">
        <?= csrf_token_input() ?>
        <button type="submit" name="logout-account" class="logout-btn">
            Logga ut
        </button>
    </form>
</nav>

