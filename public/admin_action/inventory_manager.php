<?php
/* --- 1. Inventory Manager (Admin Action) Bootstrap --- */

require_once("../../private/initalize.php");
require(PRIVATE_PATH . "/master_code/db-conn.php");
require(PRIVATE_PATH . "/master_code/pub-schema-bootstrap.php");

require_login();

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$pubTracking = ensure_pub_tracking($conn);
$activePubId = (int) $pubTracking['active_pub_id'];
$activePubName = $pubTracking['active_pub_name'];
ensure_pub_menu_links($conn, $activePubId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

/* --- 2. Form Actions --- */

// Add milkshake
if (isset($_POST['add-milkshake'])) {
    $milkshakeName = trim($_POST['milkshake-name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $ingredients = trim($_POST['ingredients'] ?? '');
    $color = trim($_POST['color'] ?? '');

    if (!empty($milkshakeName) && !empty($description) && !empty($ingredients) && !empty($color)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO milkshakes (name, description, ingredients, color) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $milkshakeName, $description, $ingredients, $color);
        mysqli_stmt_execute($stmt);
        $newId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        $stmt = mysqli_prepare($conn, "INSERT INTO pub_milkshakes (event_id, milkshake_id, is_active) VALUES (?, ?, 1)");
        mysqli_stmt_bind_param($stmt, 'ii', $activePubId, $newId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

if (isset($_POST['add-toast'])) {
    $toastName = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $ingredients = trim($_POST['ingredients'] ?? '');
    $color = trim($_POST['color'] ?? '');

    if (!empty($toastName) && !empty($description) && !empty($ingredients) && !empty($color)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO toasts (name, description, ingredients, color) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $toastName, $description, $ingredients, $color);
        mysqli_stmt_execute($stmt);
        $newId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        $stmt = mysqli_prepare($conn, "INSERT INTO pub_toasts (event_id, toast_id, is_active) VALUES (?, ?, 1)");
        mysqli_stmt_bind_param($stmt, 'ii', $activePubId, $newId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

if (isset($_POST['deactivate-milkshake'])) {
    $itemId = intval($_POST['milkshake-id'] ?? 0);
    if ($itemId > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE pub_milkshakes SET is_active = 0 WHERE event_id = ? AND milkshake_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $activePubId, $itemId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

if (isset($_POST['reactivate-milkshake'])) {
    $itemId = intval($_POST['milkshake-id'] ?? 0);
    if ($itemId > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO pub_milkshakes (event_id, milkshake_id, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_active = 1");
        mysqli_stmt_bind_param($stmt, 'ii', $activePubId, $itemId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

if (isset($_POST['deactivate-toast'])) {
    $itemId = intval($_POST['toast-id'] ?? 0);
    if ($itemId > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE pub_toasts SET is_active = 0 WHERE event_id = ? AND toast_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $activePubId, $itemId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

if (isset($_POST['reactivate-toast'])) {
    $itemId = intval($_POST['toast-id'] ?? 0);
    if ($itemId > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO pub_toasts (event_id, toast_id, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_active = 1");
        mysqli_stmt_bind_param($stmt, 'ii', $activePubId, $itemId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "<script>window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    }
}

/* --- 3. Data Fetching --- */

$stmt = mysqli_prepare($conn, "SELECT m.milkshake_id AS item_id, m.name, m.description, m.ingredients, m.color, COALESCE(ms.sold_count, 0) AS sold_count FROM milkshakes m JOIN pub_milkshakes pm ON pm.milkshake_id = m.milkshake_id LEFT JOIN (SELECT milkshake_id, COUNT(*) AS sold_count FROM order_milkshakes GROUP BY milkshake_id) ms ON ms.milkshake_id = m.milkshake_id WHERE pm.event_id = ? AND pm.is_active = 1 ORDER BY m.milkshake_id ASC");
mysqli_stmt_bind_param($stmt, 'i', $activePubId);
mysqli_stmt_execute($stmt);
$milkshakeInventory = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT m.milkshake_id AS item_id, m.name, m.description, m.ingredients, m.color FROM milkshakes m LEFT JOIN pub_milkshakes pm ON pm.milkshake_id = m.milkshake_id AND pm.event_id = ? WHERE COALESCE(pm.is_active, 0) = 0 ORDER BY m.name ASC");
mysqli_stmt_bind_param($stmt, 'i', $activePubId);
mysqli_stmt_execute($stmt);
$inactiveMilkshakeInventory = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT t.toast_id AS item_id, t.name, t.description, t.ingredients, t.color, COALESCE(ts.sold_count, 0) AS sold_count FROM toasts t JOIN pub_toasts pt ON pt.toast_id = t.toast_id LEFT JOIN (SELECT toast_id, COUNT(*) AS sold_count FROM order_toasts GROUP BY toast_id) ts ON ts.toast_id = t.toast_id WHERE pt.event_id = ? AND pt.is_active = 1 ORDER BY t.toast_id ASC");
mysqli_stmt_bind_param($stmt, 'i', $activePubId);
mysqli_stmt_execute($stmt);
$toastInventory = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT t.toast_id AS item_id, t.name, t.description, t.ingredients, t.color FROM toasts t LEFT JOIN pub_toasts pt ON pt.toast_id = t.toast_id AND pt.event_id = ? WHERE COALESCE(pt.is_active, 0) = 0 ORDER BY t.name ASC");
mysqli_stmt_bind_param($stmt, 'i', $activePubId);
mysqli_stmt_execute($stmt);
$inactiveToastInventory = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lagerhanterare</title>
    <style>
        /* --- 4. Layout & Theme --- */
        :root {
            --bg: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --border: #e5e7eb;
            --primary: #2563eb;
            --danger: #ef4444;
            --accent-milkshake: #3b82f6;
            --accent-toast: #f97316;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 2rem;
        }

        h1, h2 { margin-top: 0; font-weight: 700; color: var(--text-main); }
        h1 { margin-top: 1.5rem; margin-bottom: 2rem; text-align: center; }
        h2 { font-size: 1.25rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }

        /* Grid Layout */
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        @media (max-width: 900px) {
            .grid-container { grid-template-columns: 1fr; }
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }

        /* Tables */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { text-align: left; padding: 12px 16px; color: var(--text-sub); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); }
        td { padding: 12px 16px; border-bottom: 1px solid var(--bg); vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        
        /* Specific Column Widths */
        .inventory-table td:nth-child(2) { font-weight: 600; color: var(--text-main); width: 18%; } /* Name */
        .inventory-table td:nth-child(3) { color: var(--text-sub); width: 25%; } /* Desc */
        .inventory-table td:nth-child(4) { font-size: 0.85rem; color: var(--text-sub); width: 25%; } /* Ingredients */
        .inventory-table td:nth-child(5) { width: 10%; text-align: center; } /* Color */
        .inventory-table td:nth-child(6) { width: 10%; } /* Action */

        /* Buttons */
        .btn-remove {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fee2e2;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-remove:hover { background: var(--danger); color: white; border-color: var(--danger); }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: opacity 0.2s;
        }
        .btn-submit:hover { opacity: 0.9; }

        .action-stack {
            display: flex;
            flex-direction: row;
            gap: 0.45rem;
            min-width: 0;
            align-items: center;
            flex-wrap: nowrap;
        }

        .action-stack form {
            margin: 0;
        }

        .btn-action {
            display: inline-block;
            text-align: center;
            text-decoration: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid var(--border);
            background: white;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
            min-width: 86px;
        }

        .btn-add-pub {
            border-color: #15803d;
            color: #ffffff;
            background: #16a34a;
        }

        .btn-edit-item {
            border-color: #7e22ce;
            color: #ffffff;
            background: #9333ea;
        }

        .btn-add-pub:hover {
            background: #15803d;
            border-color: #15803d;
            color: #ffffff;
        }

        .btn-edit-item:hover {
            background: #7e22ce;
            border-color: #7e22ce;
            color: #ffffff;
        }

        .btn-action:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .color-swatch {
            width: 22px;
            height: 22px;
            border: 1px solid #ccc;
            margin: 0 auto;
            border-radius: 4px;
        }

        .inactive-milkshake-table td:nth-child(1) { width: 28%; font-weight: 600; }
        .inactive-milkshake-table td:nth-child(2) { width: 52%; color: var(--text-sub); font-size: 0.85rem; }
        .inactive-milkshake-table td:nth-child(3) { width: 8%; text-align: center; }
        .inactive-milkshake-table td:nth-child(4) { width: 12%; }

        .inactive-milkshake-table .btn-action {
            min-width: 72px;
            padding: 4px 8px;
            font-size: 0.72rem;
        }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--text-sub); }
        input[type="text"], textarea {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            box-sizing: border-box;
            background: #f9fafb;
            color: var(--text-main);
            transition: border 0.2s;
        }
        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
        }

        /* Accents */
        .milkshake-section h2 { color: var(--accent-milkshake); border-bottom-color: var(--accent-milkshake); }
        .toast-section h2 { color: var(--accent-toast); border-bottom-color: var(--accent-toast); }

        .btn-milkshake { background: var(--accent-milkshake); }
        .btn-toast { background: var(--accent-toast); }

    </style>
</head>
<body>
        <?php require(SHARED_PATH . "/admin_navbar.php"); ?>

    <h1>Lagerhanterare</h1>

    <div class="grid-container">
        
        <section class="card milkshake-section">
            <h2>Aktiva milkshakes</h2>
            <div class="table-wrapper">
                <?php if (empty($milkshakeInventory)): ?>
                    <p style="color:var(--text-sub); text-align:center;">Inga aktiva milkshakes för denna pub.</p>
                <?php else: ?>
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Namn</th>
                                <th>Beskrivning</th>
                                <th>Ingredienser</th>
                                <th>Färg</th>
                                <th>Sålda</th>
                                <th>Åtgärd</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($milkshakeInventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_id']) ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= htmlspecialchars($item['ingredients']) ?></td>
                                <td style="text-align: center;"><div style="width: 30px; height: 30px; background-color: <?= htmlspecialchars($item['color']) ?>; border: 1px solid #ccc; margin: 0 auto;"></div></td>
                                <td><?= (int) ($item['sold_count'] ?? 0) ?></td>
                                <td>
                                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= csrf_token_input() ?>
                                        <input type="hidden" name="milkshake-id" value="<?= $item['item_id'] ?>">
                                        <input type="submit" name="deactivate-milkshake" class="btn-remove" value="Inaktivera" onclick="return confirm('Inaktivera denna milkshake för denna pub?');">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

        <section class="card toast-section">
            <h2>Aktiva toasts</h2>
            <div class="table-wrapper">
                <?php if (empty($toastInventory)): ?>
                    <p style="color:var(--text-sub); text-align:center;">Inga aktiva toasts för denna pub.</p>
                <?php else: ?>
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Namn</th>
                                <th>Beskrivning</th>
                                <th>Ingredienser</th>
                                <th>Färg</th>
                                <th>Sålda</th>
                                <th>Åtgärd</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($toastInventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_id']) ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= htmlspecialchars($item['ingredients']) ?></td>
                                <td style="text-align: center;"><div style="width: 30px; height: 30px; background-color: <?= htmlspecialchars($item['color']) ?>; border: 1px solid #ccc; margin: 0 auto;"></div></td>
                                <td><?= (int) ($item['sold_count'] ?? 0) ?></td>
                                <td>
                                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= csrf_token_input() ?>
                                        <input type="hidden" name="toast-id" value="<?= $item['item_id'] ?>">
                                        <input type="submit" name="deactivate-toast" class="btn-remove" value="Inaktivera" onclick="return confirm('Inaktivera denna toast för denna pub?');">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

        <section class="card milkshake-section">
            <h2>Tidigare milkshakes (inaktiva i denna MSP)</h2>
            <div class="table-wrapper">
                <?php if (empty($inactiveMilkshakeInventory)): ?>
                    <p style="color:var(--text-sub); text-align:center;">Inga tidigare milkshakes tillgängliga.</p>
                <?php else: ?>
                    <table class="inactive-milkshake-table">
                        <thead>
                            <tr>
                                <th>Namn</th>
                                <th>Ingredienser</th>
                                <th>Färg</th>
                                <th>Åtgärd</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inactiveMilkshakeInventory as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['ingredients']) ?></td>
                                    <td style="text-align: center;"><div class="color-swatch" style="background-color: <?= htmlspecialchars($item['color']) ?>;"></div></td>
                                    <td>
                                        <div class="action-stack">
                                            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= csrf_token_input() ?>
                                                <input type="hidden" name="milkshake-id" value="<?= $item['item_id'] ?>">
                                                <input type="submit" name="reactivate-milkshake" class="btn-action btn-add-pub" value="Lägg till i pub">
                                            </form>
                                            <a class="btn-action btn-edit-item" href="edit_milkshake.php?id=<?= (int) $item['item_id'] ?>">Redigera</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

        <section class="card toast-section">
            <h2>Tidigare toasts (inaktiva i denna MSP)</h2>
            <div class="table-wrapper">
                <?php if (empty($inactiveToastInventory)): ?>
                    <p style="color:var(--text-sub); text-align:center;">Inga tidigare toasts tillgängliga.</p>
                <?php else: ?>
                    <table class="inactive-milkshake-table">
                        <thead>
                            <tr>
                                <th>Namn</th>
                                <th>Ingredienser</th>
                                <th>Färg</th>
                                <th>Åtgärd</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inactiveToastInventory as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= htmlspecialchars($item['ingredients']) ?></td>
                                    <td style="text-align: center;"><div class="color-swatch" style="background-color: <?= htmlspecialchars($item['color']) ?>;"></div></td>
                                    <td>
                                        <div class="action-stack">
                                            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= csrf_token_input() ?>
                                                <input type="hidden" name="toast-id" value="<?= $item['item_id'] ?>">
                                                <input type="submit" name="reactivate-toast" class="btn-action btn-add-pub" value="Lägg till i pub">
                                            </form>
                                            <a class="btn-action btn-edit-item" href="edit_toast.php?id=<?= (int) $item['item_id'] ?>">Redigera</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

        <section class="card milkshake-section">
            <h2>Lägg till ny milkshake</h2>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" method="post">
                <?= csrf_token_input() ?>
                <div class="form-group">
                    <label for="milkshake-name">Namn</label>
                    <input id="milkshake-name" name="milkshake-name" type="text" required maxlength="255" placeholder="e.g. Chocolate Supreme">
                </div>
                <div class="form-group">
                    <label for="m-desc">Beskrivning</label>
                    <textarea id="m-desc" name="description" rows="3" required placeholder="Short description for the menu..."></textarea>
                </div>
                <div class="form-group">
                    <label for="m-ing">Ingredienser (separerade med semikolon)</label>
                    <textarea id="m-ing" name="ingredients" rows="2" required placeholder="e.g. Milk; Chocolate Ice Cream; Cocoa"></textarea>
                </div>
                <div class="form-group">
                    <label for="m-color">Färg</label>
                    <input id="m-color" name="color" type="color" required value="#ffffff">
                </div>
                <input name="add-milkshake" type="submit" class="btn-submit btn-milkshake" value="Lägg till milkshake">
            </form>
        </section>

        <section class="card toast-section">
            <h2>Lägg till ny toast</h2>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" method="post">
                <?= csrf_token_input() ?>
                <div class="form-group">
                    <label for="toast-name">Namn</label>
                    <input id="toast-name" name="name" type="text" required maxlength="255" placeholder="e.g. Ham & Cheese">
                </div>
                <div class="form-group">
                    <label for="t-desc">Beskrivning</label>
                    <textarea id="t-desc" name="description" rows="3" required placeholder="Short description for the menu..."></textarea>
                </div>
                <div class="form-group">
                    <label for="t-ing">Ingredienser (separerade med semikolon)</label>
                    <textarea id="t-ing" name="ingredients" rows="2" required placeholder="e.g. Sourdough; Ham; Cheddar; Butter"></textarea>
                </div>
                <div class="form-group">
                    <label for="t-color">Färg</label>
                    <input id="t-color" name="color" type="color" required value="#ffffff">
                </div>
                <input name="add-toast" type="submit" class="btn-submit btn-toast" value="Lägg till toast">
            </form>
        </section>
    </div>
    <?php include(SHARED_PATH . "/public_footer.php"); ?>

</body>
</html>
