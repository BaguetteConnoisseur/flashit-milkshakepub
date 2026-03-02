<?php
require_once("../private/initalize.php");

// Handle POST requests before any output
$login_error = false;

if (isset($_POST['logout-account'])) {
    session_destroy();  
    header("Location: " . WWW_ROOT . "/index.php");
    exit;
}

if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $admin_user = getenv('ADMIN_USERNAME') ?: 'admin';
    $admin_pass = getenv('ADMIN_PASSWORD') ?: 'CHANGE_ME';

    if ($username === $admin_user && $password === $admin_pass) {
        $_SESSION['absolute-username'] = $username;
        $_SESSION['absolute-password'] = $password;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <style>
    .minimal-nav{
        display:flex;
        flex-direction:column;
        gap:10px;
        max-width:320px;
        margin:40px auto;
        font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
    }
    .minimal-nav a{
        display:block;
        text-decoration:none;
        color:#111;
        background:#fff;
        padding:10px 14px;
        border:1px solid #e9e9e9;
        border-radius:8px;
        font-size:14px;
        transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
        box-shadow:0 1px 0 rgba(0,0,0,0.03);
    }
    .minimal-nav a:hover,
    .minimal-nav a:focus{
        transform:translateY(-2px);
        box-shadow:0 8px 18px rgba(0,0,0,0.06);
        border-color:#d0d0d0;
        outline: none;
    }
    </style>

    <?php if ($login_error): ?>
        <p style='color:red;text-align:center;'>Invalid credentials. Please try again.</p>
    <?php endif; ?>

    <?php if (!$loggedIn): ?>
    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="minimal-login" style="max-width:320px;margin:20px auto 32px;display:flex;flex-direction:column;gap:8px;font-family:inherit;">
        <input type="text" name="username" aria-label="Username" placeholder="Username" required autocomplete="username"
                     style="padding:10px 14px;border:1px solid #e9e9e9;border-radius:8px;font-size:14px;outline:none;">
        <input type="password" name="password" aria-label="Password" placeholder="Password" required autocomplete="current-password"
                     style="padding:10px 14px;border:1px solid #e9e9e9;border-radius:8px;font-size:14px;outline:none;">
        <input type="submit" name="login" value="Log in" style="padding:10px 14px;border-radius:8px;border:0;background:#111;color:#fff;font-size:14px;cursor:pointer;">
    </form>
    <?php else: ?>

    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" style="max-width:320px;margin:20px auto 32px;display:flex;justify-content:flex-end;font-family:inherit;">
        <button type="submit" name="logout-account" style="padding:10px 14px;border-radius:8px;border:0;background:#e53e3e;color:#fff;font-size:14px;cursor:pointer;">
            Log out
        </button>
    </form>
    <?php endif; ?>

    <nav class="minimal-nav" aria-label="Views">
        <?php if ($loggedIn): ?>
        <a href="views/cashier-view.php">Cashier View</a>
        <a href="views/delivery-view.php">Delivery View</a>
        <a href="views/milkshake-view.php">Milkshake View</a>
        <a href="views/toast-view.php">Toast View</a>
        <a href="admin_action/inventory_manager.php">Inventory Manager</a>
        <?php endif; ?>
        <a href="views/bar-view.php">Bar View</a>
    </nav>
</body>
</html>