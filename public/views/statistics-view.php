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

function getPubMetrics($conn, $pubId) {
    $pubId = (int) $pubId;

    $orders = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE event_id = $pubId"))['c'] ?? 0);
    $milkshakes = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM order_milkshakes om JOIN orders o ON o.order_id = om.order_id WHERE o.event_id = $pubId"))['c'] ?? 0);
    $toasts = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM order_toasts ot JOIN orders o ON o.order_id = ot.order_id WHERE o.event_id = $pubId"))['c'] ?? 0);

    return [
        'orders' => $orders,
        'milkshakes' => $milkshakes,
        'toasts' => $toasts,
        'items' => $milkshakes + $toasts,
    ];
}

$pubTracking = ensure_pub_tracking($conn);
$activePubId = (int) $pubTracking['active_pub_id'];

$feedback = null;

if (isset($_POST['start_new_pub'])) {
    $pubName = trim($_POST['new_pub_name'] ?? '');

    if ($pubName === '') {
        $pubName = 'Pub ' . date('Y-m-d H:i');
    }

    mysqli_begin_transaction($conn);

    try {
        mysqli_query($conn, "UPDATE sales_events SET is_active = 0, ended_at = NOW() WHERE is_active = 1");
        $safePubName = escape($conn, $pubName);
        mysqli_query($conn, "INSERT INTO sales_events (event_name, is_active) VALUES ('$safePubName', 1)");
        $activePubId = (int) mysqli_insert_id($conn);

        mysqli_commit($conn);
        $feedback = ['type' => 'success', 'message' => 'New pub started. New orders will now be tracked in that pub.'];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $feedback = ['type' => 'error', 'message' => 'Could not start a new pub.'];
    }
}

if (isset($_POST['delete_pub'])) {
    $targetPubId = (int) ($_POST['target_pub_id'] ?? 0);
    $confirmation = trim($_POST['confirm_delete_pub'] ?? '');

    if ($targetPubId <= 0) {
        $feedback = ['type' => 'error', 'message' => 'Invalid pub selection.'];
    } elseif ($targetPubId === $activePubId) {
        $feedback = ['type' => 'error', 'message' => 'You cannot delete the active pub. Start a new pub first.'];
    } elseif ($confirmation !== 'DELETE PUB') {
        $feedback = ['type' => 'error', 'message' => 'Confirmation text must be exactly: DELETE PUB'];
    } else {
        mysqli_begin_transaction($conn);

        try {
            mysqli_query($conn, "DELETE om FROM order_milkshakes om JOIN orders o ON o.order_id = om.order_id WHERE o.event_id = $targetPubId");
            mysqli_query($conn, "DELETE ot FROM order_toasts ot JOIN orders o ON o.order_id = ot.order_id WHERE o.event_id = $targetPubId");
            mysqli_query($conn, "DELETE FROM orders WHERE event_id = $targetPubId");
            mysqli_query($conn, "DELETE FROM sales_events WHERE event_id = $targetPubId AND is_active = 0");

            mysqli_commit($conn);
            $feedback = ['type' => 'success', 'message' => 'Selected pub and its order history were deleted.'];
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $feedback = ['type' => 'error', 'message' => 'Could not delete selected pub.'];
        }
    }
}

$allPubs = mysqli_fetch_all(mysqli_query(
    $conn,
    "SELECT
        e.event_id,
        e.event_name,
        e.started_at,
        e.ended_at,
        e.is_active,
        (SELECT COUNT(*) FROM orders o WHERE o.event_id = e.event_id) AS total_orders,
        (SELECT COUNT(*) FROM order_milkshakes om JOIN orders o ON o.order_id = om.order_id WHERE o.event_id = e.event_id) AS total_milkshakes,
        (SELECT COUNT(*) FROM order_toasts ot JOIN orders o ON o.order_id = ot.order_id WHERE o.event_id = e.event_id) AS total_toasts
     FROM sales_events e
     ORDER BY e.started_at DESC"
), MYSQLI_ASSOC);

$selectedPubId = (int) ($_GET['pub_id'] ?? $_GET['event_id'] ?? $activePubId);
$pubIds = array_map(static function ($pub) { return (int) $pub['event_id']; }, $allPubs);
if (!in_array($selectedPubId, $pubIds, true) && !empty($pubIds)) {
    $selectedPubId = $activePubId;
}

$comparePubId = (int) ($_GET['compare_pub_id'] ?? $_GET['compare_event_id'] ?? 0);
if (!in_array($comparePubId, $pubIds, true) || $comparePubId === $selectedPubId) {
    $comparePubId = 0;
}

$selectedPubName = 'Selected Pub';
foreach ($allPubs as $pub) {
    if ((int) $pub['event_id'] === $selectedPubId) {
        $selectedPubName = $pub['event_name'];
        break;
    }
}

$totalOrders = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE event_id = $selectedPubId"))['c'] ?? 0);
$totalMilkshakesSold = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM order_milkshakes om JOIN orders o ON o.order_id = om.order_id WHERE o.event_id = $selectedPubId"))['c'] ?? 0);
$totalToastsSold = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM order_toasts ot JOIN orders o ON o.order_id = ot.order_id WHERE o.event_id = $selectedPubId"))['c'] ?? 0);
$totalItemsSold = $totalMilkshakesSold + $totalToastsSold;

$milkshakeSales = mysqli_fetch_all(mysqli_query(
    $conn,
    "SELECT m.name, COUNT(*) AS sold
     FROM order_milkshakes om
     JOIN orders o ON o.order_id = om.order_id
     JOIN milkshakes m ON m.milkshake_id = om.milkshake_id
    WHERE o.event_id = $selectedPubId
     GROUP BY m.milkshake_id, m.name
     ORDER BY sold DESC, m.name ASC"
), MYSQLI_ASSOC);

$toastSales = mysqli_fetch_all(mysqli_query(
    $conn,
    "SELECT t.name, COUNT(*) AS sold
     FROM order_toasts ot
     JOIN orders o ON o.order_id = ot.order_id
     JOIN toasts t ON t.toast_id = ot.toast_id
    WHERE o.event_id = $selectedPubId
     GROUP BY t.toast_id, t.name
     ORDER BY sold DESC, t.name ASC"
), MYSQLI_ASSOC);

$comparison = null;
if ($comparePubId > 0) {
    $currentMetrics = getPubMetrics($conn, $selectedPubId);
    $compareMetrics = getPubMetrics($conn, $comparePubId);
    $compareName = 'Compared Pub';

    foreach ($allPubs as $pub) {
        if ((int) $pub['event_id'] === $comparePubId) {
            $compareName = $pub['event_name'];
            break;
        }
    }

    $comparison = [
        'compareName' => $compareName,
        'ordersDiff' => $currentMetrics['orders'] - $compareMetrics['orders'],
        'itemsDiff' => $currentMetrics['items'] - $compareMetrics['items'],
        'milkshakeDiff' => $currentMetrics['milkshakes'] - $compareMetrics['milkshakes'],
        'toastDiff' => $currentMetrics['toasts'] - $compareMetrics['toasts'],
    ];
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics</title>
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
            --danger: #dc2626;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            background: var(--bg);
            color: var(--text-main);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        h1 {
            margin: 1.5rem 0 1rem;
            font-size: 2rem;
        }

        .subtitle {
            margin-bottom: 1.25rem;
            color: var(--text-sub);
        }

        .notice {
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .notice.success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid #86efac;
        }

        .notice.error {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid #fecaca;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .card h2 {
            margin: 0 0 0.8rem;
            font-size: 1.15rem;
        }

        .pub-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .pub-tools input,
        .pub-tools select,
        .pub-tools button {
            padding: 0.6rem 0.7rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 0.95rem;
        }

        .pub-tools button {
            border: none;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .pub-tools button.danger {
            background: var(--danger);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .kpi {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem;
        }

        .kpi-label {
            color: var(--text-sub);
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }

        .kpi-value {
            font-size: 1.8rem;
            font-weight: 800;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .list {
            margin: 0;
            padding-left: 1.2rem;
        }

        .list li {
            margin: 0.35rem 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }

        th, td {
            border-bottom: 1px solid var(--border);
            text-align: left;
            padding: 0.65rem;
            vertical-align: top;
        }

        th {
            color: var(--text-sub);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .empty {
            color: var(--text-sub);
            font-style: italic;
            margin: 0;
        }

        .danger-zone {
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 1rem;
            background: #fff1f2;
        }

        .danger-zone h2 {
            margin-top: 0;
            color: var(--danger);
        }

        .badge-active {
            display: inline-block;
            margin-left: 0.4rem;
            font-size: 0.72rem;
            background: #dcfce7;
            color: #166534;
            padding: 0.15rem 0.4rem;
            border-radius: 999px;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php require(SHARED_PATH . "/admin_navbar.php"); ?>

    <div class="container">
        <h1>Statistics</h1>
        <p class="subtitle">Track each pub separately and compare sales across pubs.</p>

        <?php if ($feedback): ?>
            <div class="notice <?= $feedback['type'] ?>"><?= htmlspecialchars($feedback['message']) ?></div>
        <?php endif; ?>

        <section class="card" style="margin-top: 1rem;">
            <h2>Pub Controls</h2>
            <form method="get" class="pub-tools" style="margin-bottom:0.9rem;">
                <label for="pub_id">View Pub</label>
                <select id="pub_id" name="pub_id">
                    <?php foreach ($allPubs as $pub): ?>
                        <option value="<?= (int) $pub['event_id'] ?>" <?= ((int) $pub['event_id'] === $selectedPubId) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pub['event_name']) ?><?= ((int) $pub['is_active'] === 1) ? ' (Active Pub)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="compare_pub_id">Compare With</label>
                <select id="compare_pub_id" name="compare_pub_id">
                    <option value="0">None</option>
                    <?php foreach ($allPubs as $pub): ?>
                        <?php if ((int) $pub['event_id'] !== $selectedPubId): ?>
                            <option value="<?= (int) $pub['event_id'] ?>" <?= ((int) $pub['event_id'] === $comparePubId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pub['event_name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Apply</button>
            </form>

            <form method="post" class="pub-tools">
                <input type="text" name="new_pub_name" placeholder="New pub name (optional)">
                <button type="submit" name="start_new_pub">Start New Pub</button>
            </form>

            <p style="margin:0.85rem 0 0; color: var(--text-sub);">
                Currently viewing: <strong><?= htmlspecialchars($selectedPubName) ?></strong>
            </p>
        </section>

        <?php if ($comparison): ?>
            <section class="card">
                <h2>Comparison vs <?= htmlspecialchars($comparison['compareName']) ?></h2>
                <p style="margin:0; color: var(--text-sub);">
                    Orders: <?= $comparison['ordersDiff'] ?>,
                    Items: <?= $comparison['itemsDiff'] ?>,
                    Milkshakes: <?= $comparison['milkshakeDiff'] ?>,
                    Toasts: <?= $comparison['toastDiff'] ?>
                </p>
            </section>
        <?php endif; ?>

        <div class="kpi-grid">
            <div class="kpi">
                <div class="kpi-label">Total Orders (<?= htmlspecialchars($selectedPubName) ?>)</div>
                <div class="kpi-value"><?= $totalOrders ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Items Sold</div>
                <div class="kpi-value"><?= $totalItemsSold ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Milkshakes Sold</div>
                <div class="kpi-value"><?= $totalMilkshakesSold ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Toasts Ordered</div>
                <div class="kpi-value"><?= $totalToastsSold ?></div>
            </div>
        </div>

        <div class="grid-2">
            <section class="card">
                <h2>Milkshake Sales by Flavor</h2>
                <?php if (empty($milkshakeSales)): ?>
                    <p class="empty">No milkshake sales yet.</p>
                <?php else: ?>
                    <ol class="list">
                        <?php foreach ($milkshakeSales as $row): ?>
                            <li><?= htmlspecialchars($row['name']) ?> — <strong><?= (int) $row['sold'] ?></strong></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Toast Sales by Flavor</h2>
                <?php if (empty($toastSales)): ?>
                    <p class="empty">No toast sales yet.</p>
                <?php else: ?>
                    <ol class="list">
                        <?php foreach ($toastSales as $row): ?>
                            <li><?= htmlspecialchars($row['name']) ?> — <strong><?= (int) $row['sold'] ?></strong></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>

        <section class="card" style="margin-bottom: 1.5rem;">
            <h2>Pub History Overview</h2>
            <?php if (empty($allPubs)): ?>
                <p class="empty">No pubs created yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Pub</th>
                            <th>Started</th>
                            <th>Ended</th>
                            <th>Orders</th>
                            <th>Milkshakes</th>
                            <th>Toasts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allPubs as $pub): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($pub['event_name']) ?>
                                    <?php if ((int) $pub['is_active'] === 1): ?>
                                        <span class="badge-active">ACTIVE</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($pub['started_at']) ?></td>
                                <td><?= htmlspecialchars($pub['ended_at'] ?? '—') ?></td>
                                <td><?= (int) $pub['total_orders'] ?></td>
                                <td><?= (int) $pub['total_milkshakes'] ?></td>
                                <td><?= (int) $pub['total_toasts'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="danger-zone" style="margin-top: 1rem;">
            <h2>Danger Zone: Delete Selected Pub</h2>
            <p>Select which old pub to delete. The active pub is not shown in this list and cannot be deleted.</p>
            <form method="post" class="pub-tools" onsubmit="return confirm('Delete selected pub and all its orders? This cannot be undone.');">
                <select name="target_pub_id" required>
                    <option value="">Select old pub</option>
                    <?php foreach ($allPubs as $pub): ?>
                        <?php if ((int) $pub['is_active'] === 0): ?>
                            <option value="<?= (int) $pub['event_id'] ?>">
                                <?= htmlspecialchars($pub['event_name']) ?>
                                (<?= htmlspecialchars($pub['started_at']) ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="confirm_delete_pub" placeholder="Type DELETE PUB" required autocomplete="off">
                <button type="submit" class="danger" name="delete_pub">Delete Selected Pub</button>
            </form>

            <?php
                $hasOldPubs = false;
                foreach ($allPubs as $pub) {
                    if ((int) $pub['is_active'] === 0) {
                        $hasOldPubs = true;
                        break;
                    }
                }
            ?>
            <?php if (!$hasOldPubs): ?>
                <p class="empty" style="margin-top:0.75rem;">No old pubs available to delete yet.</p>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
