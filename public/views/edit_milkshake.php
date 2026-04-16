<?php
/* --- 1. Edit Milkshake Bootstrap --- */
require_once(__DIR__ . '/../../private/initialize.php');
require_once(PRIVATE_PATH . '/src/services/inventory_manager.php');

$pdo = db();
$activePubId = $_SESSION['active_pub_id'];
$inventory = new InventoryManager($pdo, $activePubId);

$itemId = intval($_GET['id'] ?? $_POST['milkshake-id'] ?? 0);
$feedback = null;
$item = null;

if ($itemId > 0) {
    $item = $inventory->getItemById($itemId);
    if (!$item || $item['category'] !== 'milkshake') {
        header('Location: ' . app_url('inventory_manager'));
        exit;
    }
} else {
    header('Location: ' . app_url('inventory_manager'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    if (isset($_POST['save-milkshake'])) {
        $milkshakeName = trim($_POST['milkshake-name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $ingredients = trim($_POST['ingredients'] ?? '');
        $color = trim($_POST['color'] ?? '');

        if ($milkshakeName === '' || $description === '' || $ingredients === '' || $color === '') {
            $feedback = ['type' => 'error', 'message' => 'Alla fält måste fyllas i.'];
        } else {
            $inventory->updateItem($itemId, [
                'name' => $milkshakeName,
                'description' => $description,
                'ingredients' => $ingredients,
                'color' => $color
            ]);
            header('Location: ' . app_url('inventory_manager'));
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <link rel="icon" type="image/svg+xml" href="<?= app_asset_url('img/logo/favicon.svg') ?>">
    <link rel="alternate icon" type="image/png" href="<?= app_asset_url('img/logo/favicon.png') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redigera Milkshake</title>
    <style>
        :root {
            --bg: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --border: #e5e7eb;
            --primary: #2563eb;
            --danger: #dc2626;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
        }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            margin: 0;
        }
        .container {
            max-width: 760px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
        }
        h1 {
            margin: 1.25rem 0 1rem;
            font-size: 1.9rem;
        }
        .subtitle {
            color: var(--text-sub);
            margin-bottom: 1rem;
        }
        .feedback {
            border-radius: 8px;
            padding: 0.75rem 0.9rem;
            margin-bottom: 0.9rem;
            font-weight: 500;
        }
        .feedback.error {
            background: var(--error-bg);
            color: var(--error-text);
        }
        .row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.8rem;
            margin-bottom: 0.9rem;
        }
        label {
            font-size: 0.9rem;
            color: var(--text-sub);
            display: block;
            margin-bottom: 0.35rem;
        }
        input[type="text"], textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.65rem 0.75rem;
            font-size: 0.95rem;
            font-family: inherit;
            background: #fff;
        }
        input[type="color"] {
            width: 52px;
            height: 34px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 2px;
            background: #fff;
            cursor: pointer;
        }
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        .actions {
            display: flex;
            gap: 0.6rem;
            margin-top: 0.4rem;
        }
        .btn {
            border: none;
            border-radius: 8px;
            padding: 0.65rem 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .btn-secondary {
            background: #eef2ff;
            color: #1e3a8a;
            border: 1px solid #c7d2fe;
        }
    </style>
</head>
<body>
    <?php require(TEMPLATE_PATH . "/navbar.php"); ?>
    <div class="container">
        <h1>Redigera milkshake</h1>
        <p class="subtitle">
            Du redigerar: <strong><?= htmlspecialchars($item['name']) ?></strong>
        </p>
        <?php if ($feedback): ?>
            <div class="feedback <?= $feedback['type'] ?>"><?= htmlspecialchars($feedback['message']) ?></div>
        <?php endif; ?>
        <section class="card">
            <form method="post" action="/edit_milkshake?id=<?= (int)$item['item_id'] ?>">
                <?= csrf_token_input() ?>
                <input type="hidden" name="milkshake-id" value="<?= (int) $item['item_id'] ?>">
                <div class="row">
                    <div>
                        <label for="milkshake-name">Namn</label>
                        <input id="milkshake-name" type="text" name="milkshake-name" value="<?= htmlspecialchars($item['name']) ?>" required>
                    </div>
                    <div>
                        <label for="description">Beskrivning</label>
                        <textarea id="description" name="description" required><?= htmlspecialchars($item['description']) ?></textarea>
                    </div>
                    <div>
                        <label for="ingredients">Ingredienser</label>
                        <textarea id="ingredients" name="ingredients" required><?= htmlspecialchars($item['ingredients']) ?></textarea>
                    </div>
                    <div>
                        <label for="color">Färg</label>
                        <input id="color" type="color" name="color" value="<?= htmlspecialchars($item['color']) ?>" required>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary" name="save-milkshake">Spara</button>
                    <a class="btn btn-secondary" href="<?= app_url('inventory_manager') ?>">Avbryt</a>
                </div>
            </form>
        </section>
    </div>
    <?php include(TEMPLATE_PATH . "/public_footer.php"); ?>
</body>
</html>
?>