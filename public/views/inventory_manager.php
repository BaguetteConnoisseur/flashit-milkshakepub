<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(__DIR__ . "/../../private/src/services/InventoryManager.php");

// 1. Get the active pub info from the session
$activePubId = $_SESSION['active_pub_id']; 
$activePubName = $_SESSION['active_pub_name'];

// 2. Initialize the Manager
$pdo = db();
$inventory = new InventoryManager($pdo, $activePubId);

/* --- 1. Form Actions --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Note: ensure initialize.php or auth.php defines require_csrf_token()
    // require_csrf_token(); 

    if (isset($_POST['add-milkshake']) || isset($_POST['add-toast'])) {
        $category = isset($_POST['add-milkshake']) ? 'milkshake' : 'toast';
        $inventory->addItem([
            'category'    => $category,
            'name'        => trim($_POST['name'] ?? $_POST['milkshake-name']),
            'description' => trim($_POST['description'] ?? ''),
            'ingredients' => trim($_POST['ingredients'] ?? ''),
            'color'       => trim($_POST['color'] ?? '#ffffff')
        ]);
        header("Location: inventory_manager.php");
        exit;
    }

    if (isset($_POST['toggle-status'])) {
        $itemId = (int)$_POST['item-id'];
        $isActive = (int)$_POST['new-status'] === 1;
        $inventory->toggleActive($itemId, $isActive);
        header("Location: inventory_manager.php");
        exit;
    }
}

/* --- 2. Data Fetching --- */
$milkshakeInventory = $inventory->getItemsByCategory('milkshake', true);
$inactiveMilkshakeInventory = $inventory->getItemsByCategory('milkshake', false);
$toastInventory = $inventory->getItemsByCategory('toast', true);
$inactiveToastInventory = $inventory->getItemsByCategory('toast', false);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lagerhanterare</title>
    <style>
        /* --- 3. Layout & Theme --- */
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
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
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
            padding: 4px 10px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid var(--border);
            background: white;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
            min-width: 64px;
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
    <?php require(TEMPLATE_PATH . "/admin_navbar.php"); ?>

    <h1>Lagerhanterare: <?= htmlspecialchars($activePubName) ?></h1>

    <div class="grid-container">
        
        <section class="card milkshake-section">
            <h2>Aktiva milkshakes</h2>
            <div class="table-wrapper">
                <?php if (empty($milkshakeInventory)): ?>
                    <p style="color:var(--text-sub); text-align:center;">Inga aktiva milkshakes för denna pub.</p>
                <?php else: ?>
                    <table class="inventory-table">
                        <thead>
                            <tr><th>ID</th><th>Namn</th><th>Beskrivning</th><th>Ingredienser</th><th>Färg</th><th>Åtgärd</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($milkshakeInventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_id']) ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= htmlspecialchars($item['ingredients']) ?></td>
                                <td style="text-align: center;"><div style="width: 25px; height: 25px; background-color: <?= htmlspecialchars($item['color']) ?>; border: 1px solid #ccc; margin: 0 auto; border-radius:4px;"></div></td>
                                <td>
                                    <form method="post">
                                        <?= csrf_token_input() ?>
                                        <input type="hidden" name="item-id" value="<?= $item['item_id'] ?>">
                                        <input type="hidden" name="new-status" value="0">
                                        <input type="submit" name="toggle-status" class="btn-remove" value="Inaktivera">
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
                            <tr><th>ID</th><th>Namn</th><th>Beskrivning</th><th>Ingredienser</th><th>Färg</th><th>Åtgärd</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($toastInventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_id']) ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td><?= htmlspecialchars($item['ingredients']) ?></td>
                                <td style="text-align: center;"><div style="width: 25px; height: 25px; background-color: <?= htmlspecialchars($item['color']) ?>; border: 1px solid #ccc; margin: 0 auto; border-radius:4px;"></div></td>
                                <td>
                                    <form method="post">
                                        <?= csrf_token_input() ?>
                                        <input type="hidden" name="item-id" value="<?= $item['item_id'] ?>">
                                        <input type="hidden" name="new-status" value="0">
                                        <input type="submit" name="toggle-status" class="btn-remove" value="Inaktivera">
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
            <h2>Tidigare milkshakes</h2>
            <div class="table-wrapper">
                <?php if (empty($inactiveMilkshakeInventory)): ?>
                    <p style="color:var(--text-sub); text-align:center;">Inga inaktiva milkshakes.</p>
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
                                    <td><div class="color-swatch" style="background-color: <?= htmlspecialchars($item['color']) ?>;"></div></td>
                                    <td>
                                        <div class="action-stack">
                                            <form method="post">
                                                <?= csrf_token_input() ?>
                                                <input type="hidden" name="item-id" value="<?= $item['item_id'] ?>">
                                                <input type="hidden" name="new-status" value="1">
                                                <input type="submit" name="toggle-status" class="btn-action btn-add-pub" value="Lägg till">
                                            </form>
                                            <a href="edit_milkshake.php?id=<?= $item['item_id'] ?>" class="btn-action btn-edit-item">Redigera</a>
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
            <h2>Tidigare toasts</h2>
            <div class="table-wrapper">
                <?php if (empty($inactiveToastInventory)): ?>
                    <p style="color:var(--text-sub); text-align:center;">Inga inaktiva toasts.</p>
                <?php else: ?>
                    <table class="inactive-toast-table">
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
                                    <td><div class="color-swatch" style="background-color: <?= htmlspecialchars($item['color']) ?>;"></div></td>
                                    <td>
                                        <div class="action-stack">
                                            <form method="post">
                                                <?= csrf_token_input() ?>
                                                <input type="hidden" name="item-id" value="<?= $item['item_id'] ?>">
                                                <input type="hidden" name="new-status" value="1">
                                                <input type="submit" name="toggle-status" class="btn-action btn-add-pub" value="Lägg till">
                                            </form>
                                            <a href="edit_toast.php?id=<?= $item['item_id'] ?>" class="btn-action btn-edit-item">Redigera</a>
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
            <form method="post">
                <?= csrf_token_input() ?>
                <div class="form-group"><label>Namn</label><input name="milkshake-name" type="text" required></div>
                <div class="form-group"><label>Beskrivning</label><textarea name="description" rows="2" required></textarea></div>
                <div class="form-group"><label>Ingredienser</label><textarea name="ingredients" rows="2" required></textarea></div>
                <div class="form-group"><label>Färg</label><input name="color" type="color" value="#3b82f6"></div>
                <input name="add-milkshake" type="submit" class="btn-submit btn-milkshake" value="Spara Milkshake">
            </form>
        </section>

        <section class="card toast-section">
            <h2>Lägg till ny toast</h2>
            <form method="post">
                <?= csrf_token_input() ?>
                <div class="form-group"><label>Namn</label><input name="name" type="text" required></div>
                <div class="form-group"><label>Beskrivning</label><textarea name="description" rows="2" required></textarea></div>
                <div class="form-group"><label>Ingredienser</label><textarea name="ingredients" rows="2" required></textarea></div>
                <div class="form-group"><label>Färg</label><input name="color" type="color" value="#f97316"></div>
                <input name="add-toast" type="submit" class="btn-submit btn-toast" value="Spara Toast">
            </form>
        </section>

    </div>

    <?php include(TEMPLATE_PATH . "/public_footer.php"); ?>
</body>