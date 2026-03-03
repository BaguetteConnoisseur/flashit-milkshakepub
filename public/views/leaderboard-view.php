<?php
require_once("../../private/initalize.php");
require(PRIVATE_PATH . "/master_code/db-conn.php");
require(PRIVATE_PATH . "/master_code/pub-schema-bootstrap.php");

if (!$loggedIn) {
    header("Location: " . WWW_ROOT . "/index.php");
    exit;
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

function escape($conn, $string) {
    return mysqli_real_escape_string($conn, $string);
}

ensure_pub_tracking($conn);

$topMilkshakes = mysqli_fetch_all(mysqli_query(
    $conn,
    "SELECT m.name AS item_name, COUNT(*) AS sold
     FROM order_milkshakes om
     JOIN milkshakes m ON m.milkshake_id = om.milkshake_id
     GROUP BY m.milkshake_id, m.name
     ORDER BY sold DESC, m.name ASC
     LIMIT 10"
), MYSQLI_ASSOC);

$topToasts = mysqli_fetch_all(mysqli_query(
    $conn,
    "SELECT t.name AS item_name, COUNT(*) AS sold
     FROM order_toasts ot
     JOIN toasts t ON t.toast_id = ot.toast_id
     GROUP BY t.toast_id, t.name
     ORDER BY sold DESC, t.name ASC
     LIMIT 10"
), MYSQLI_ASSOC);

$topAllItems = mysqli_fetch_all(mysqli_query(
    $conn,
    "SELECT item_name, SUM(sold) AS sold
     FROM (
         SELECT m.name AS item_name, COUNT(*) AS sold
         FROM order_milkshakes om
         JOIN milkshakes m ON m.milkshake_id = om.milkshake_id
         GROUP BY m.milkshake_id, m.name

         UNION ALL

         SELECT t.name AS item_name, COUNT(*) AS sold
         FROM order_toasts ot
         JOIN toasts t ON t.toast_id = ot.toast_id
         GROUP BY t.toast_id, t.name
     ) ranked
     GROUP BY item_name
     ORDER BY sold DESC, item_name ASC
     LIMIT 10"
), MYSQLI_ASSOC);

$topCustomers = mysqli_fetch_all(mysqli_query(
    $conn,
    "SELECT o.customer_name, COALESCE(SUM(oi.total_items), 0) AS items_count
     FROM orders o
     LEFT JOIN (
         SELECT order_id, SUM(item_count) AS total_items
         FROM (
             SELECT order_id, COUNT(*) AS item_count
             FROM order_milkshakes
             GROUP BY order_id

             UNION ALL

             SELECT order_id, COUNT(*) AS item_count
             FROM order_toasts
             GROUP BY order_id
         ) order_item_counts
         GROUP BY order_id
     ) oi ON oi.order_id = o.order_id
     GROUP BY o.customer_name
     ORDER BY items_count DESC, o.customer_name ASC
     LIMIT 10"
), MYSQLI_ASSOC);

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Topplista</title>
    <link rel="icon" href="../img/logo/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="../img/logo/favicon.png" type="image/png">
    <style>
        :root {
            --bg: #f3f4f6;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --primary: #2563eb;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text-main);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        h1 {
            margin: 1.5rem 0 0.4rem;
            font-size: 2rem;
        }

        .subtitle {
            color: var(--text-sub);
            margin-bottom: 1rem;
        }

        .controls {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .controls select,
        .controls button {
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .controls button {
            border: none;
            background: var(--primary);
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem;
        }

        .card h2 {
            margin: 0 0 0.8rem;
            font-size: 1.15rem;
        }

        .board {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .board li {
            display: grid;
            grid-template-columns: 2.2rem 1fr auto;
            gap: 0.6rem;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
        }

        .board li:last-child {
            border-bottom: none;
        }

        .rank {
            font-weight: 800;
            color: var(--text-sub);
            text-align: center;
        }

        .value {
            font-weight: 800;
        }

        .empty {
            margin: 0;
            color: var(--text-sub);
            font-style: italic;
        }

        @media (max-width: 900px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php require(SHARED_PATH . "/admin_navbar.php"); ?>

    <div class="container">
        <h1>Topplista</h1>
        <p class="subtitle">Mest sålda produkter och kunder genom tiderna.</p>

        <div class="grid">
            <section class="card">
                <h2>Topp milkshakes</h2>
                <?php if (empty($topMilkshakes)): ?>
                    <p class="empty">Inga milkshake-försäljningar ännu.</p>
                <?php else: ?>
                    <ol class="board">
                        <?php foreach ($topMilkshakes as $index => $row): ?>
                            <li>
                                <span class="rank">#<?= $index + 1 ?></span>
                                <span><?= htmlspecialchars($row['item_name']) ?></span>
                                <span class="value"><?= (int) $row['sold'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Topp toasts</h2>
                <?php if (empty($topToasts)): ?>
                    <p class="empty">Inga toast-försäljningar ännu.</p>
                <?php else: ?>
                    <ol class="board">
                        <?php foreach ($topToasts as $index => $row): ?>
                            <li>
                                <span class="rank">#<?= $index + 1 ?></span>
                                <span><?= htmlspecialchars($row['item_name']) ?></span>
                                <span class="value"><?= (int) $row['sold'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Topp produkter (kombinerat)</h2>
                <?php if (empty($topAllItems)): ?>
                    <p class="empty">Ingen produktförsäljning ännu.</p>
                <?php else: ?>
                    <ol class="board">
                        <?php foreach ($topAllItems as $index => $row): ?>
                            <li>
                                <span class="rank">#<?= $index + 1 ?></span>
                                <span><?= htmlspecialchars($row['item_name']) ?></span>
                                <span class="value"><?= (int) $row['sold'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Toppkunder (antal köpta produkter)</h2>
                <?php if (empty($topCustomers)): ?>
                    <p class="empty">Inga kundbeställningar ännu.</p>
                <?php else: ?>
                    <ol class="board">
                        <?php foreach ($topCustomers as $index => $row): ?>
                            <li>
                                <span class="rank">#<?= $index + 1 ?></span>
                                <span><?= htmlspecialchars($row['customer_name']) ?></span>
                                <span class="value"><?= (int) $row['items_count'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>
    </div>
</body>
</html>
