<?php
/* --- 1. Edit Milkshake (Admin Action) Bootstrap --- */

require_once("../../private/initialize.php");
require(PRIVATE_PATH . "/core/db-connection.php");

require_login();

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* 2. Resolve Target Item */
$itemId = intval($_GET['id'] ?? $_POST['milkshake-id'] ?? 0);
if ($itemId <= 0) {
    header("Location: " . WWW_ROOT . "/admin_action/inventory_manager.php");
    exit;
}

$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

/* 3. Handle Save Action */
if (isset($_POST['save-milkshake'])) {
    $milkshakeName = trim($_POST['milkshake-name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $ingredients = trim($_POST['ingredients'] ?? '');
    $color = trim($_POST['color'] ?? '');

    if ($milkshakeName === '' || $description === '' || $ingredients === '' || $color === '') {
        $feedback = ['type' => 'error', 'message' => 'Alla fält måste fyllas i.'];
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE milkshakes SET name = ?, description = ?, ingredients = ?, color = ? WHERE milkshake_id = ?");
        mysqli_stmt_bind_param($stmt, 'ssssi', $milkshakeName, $description, $ingredients, $color, $itemId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header("Location: " . WWW_ROOT . "/admin_action/inventory_manager.php");
        exit;
    }
}

/* 4. Fetch Existing Item */
$stmt = mysqli_prepare($conn, "SELECT m.milkshake_id AS item_id, m.name, m.description, m.ingredients, m.color FROM milkshakes m WHERE m.milkshake_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $itemId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$milkshake = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$milkshake) {
    mysqli_close($conn);
    header("Location: " . WWW_ROOT . "/admin_action/inventory_manager.php");
    exit;
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redigera milkshake</title>
    <style>
        /* --- 5. Layout & Theme --- */
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
    <?php require(SHARED_PATH . "/admin_navbar.php"); ?>

    <div class="container">
        <h1>Redigera milkshake</h1>
        <p class="subtitle">
            Du redigerar: <strong><?= htmlspecialchars($milkshake['name']) ?></strong>
        </p>

        <?php if ($feedback): ?>
            <div class="feedback <?= $feedback['type'] ?>"><?= htmlspecialchars($feedback['message']) ?></div>
        <?php endif; ?>

        <section class="card">
            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
                <?= csrf_token_input() ?>
                <input type="hidden" name="milkshake-id" value="<?= (int) $milkshake['item_id'] ?>">

                <div class="row">
                    <div>
                        <label for="milkshake-name">Namn</label>
                        <input id="milkshake-name" type="text" name="milkshake-name" value="<?= htmlspecialchars($milkshake['name']) ?>" required>
                    </div>

                    <div>
                        <label for="description">Beskrivning</label>
                        <textarea id="description" name="description" required><?= htmlspecialchars($milkshake['description']) ?></textarea>
                    </div>

                    <div>
                        <label for="ingredients">Ingredienser</label>
                        <textarea id="ingredients" name="ingredients" required><?= htmlspecialchars($milkshake['ingredients']) ?></textarea>
                    </div>

                    <div>
                        <label for="color">Färg</label>
                        <input id="color" type="color" name="color" value="<?= htmlspecialchars($milkshake['color']) ?>" required>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary" name="save-milkshake">Spara</button>
                    <a class="btn btn-secondary" href="<?= WWW_ROOT ?>/admin_action/inventory_manager.php">Avbryt</a>
                </div>
            </form>
        </section>
    </div>

    <?php include(SHARED_PATH . "/public_footer.php"); ?>
</body>
</html>
