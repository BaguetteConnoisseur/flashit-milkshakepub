<?php
/* --- 1. Statistics View Bootstrap --- */

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

$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

/* 2. Actions (Delete Historical Pub) */
if (isset($_POST['delete_pub'])) {
    $targetPubId = (int) ($_POST['target_pub_id'] ?? 0);
    $confirmation = trim($_POST['confirm_delete_pub'] ?? '');

    if ($targetPubId <= 0) {
        $feedback = ['type' => 'error', 'message' => 'Ogiltigt pubval.'];
    } elseif ($targetPubId === $activePubId) {
        $feedback = ['type' => 'error', 'message' => 'Du kan inte ta bort den aktiva puben. Starta en ny pub först.'];
    } elseif ($confirmation !== 'DELETE PUB') {
        $feedback = ['type' => 'error', 'message' => 'Bekräftelsetexten måste vara exakt: DELETE PUB'];
    } else {
        mysqli_begin_transaction($conn);

        try {
            $stmtDeleteMilkshakes = mysqli_prepare($conn, "DELETE om FROM order_milkshakes om JOIN orders o ON o.order_id = om.order_id WHERE o.event_id = ?");
            if (!$stmtDeleteMilkshakes) {
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtDeleteMilkshakes, 'i', $targetPubId);
            if (!mysqli_stmt_execute($stmtDeleteMilkshakes)) {
                throw new Exception(mysqli_stmt_error($stmtDeleteMilkshakes));
            }
            mysqli_stmt_close($stmtDeleteMilkshakes);

            $stmtDeleteToasts = mysqli_prepare($conn, "DELETE ot FROM order_toasts ot JOIN orders o ON o.order_id = ot.order_id WHERE o.event_id = ?");
            if (!$stmtDeleteToasts) {
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtDeleteToasts, 'i', $targetPubId);
            if (!mysqli_stmt_execute($stmtDeleteToasts)) {
                throw new Exception(mysqli_stmt_error($stmtDeleteToasts));
            }
            mysqli_stmt_close($stmtDeleteToasts);

            $stmtDeleteOrders = mysqli_prepare($conn, "DELETE FROM orders WHERE event_id = ?");
            if (!$stmtDeleteOrders) {
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtDeleteOrders, 'i', $targetPubId);
            if (!mysqli_stmt_execute($stmtDeleteOrders)) {
                throw new Exception(mysqli_stmt_error($stmtDeleteOrders));
            }
            mysqli_stmt_close($stmtDeleteOrders);

            $inactiveFlag = 0;
            $stmtDeleteEvent = mysqli_prepare($conn, "DELETE FROM sales_events WHERE event_id = ? AND is_active = ?");
            if (!$stmtDeleteEvent) {
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmtDeleteEvent, 'ii', $targetPubId, $inactiveFlag);
            if (!mysqli_stmt_execute($stmtDeleteEvent)) {
                throw new Exception(mysqli_stmt_error($stmtDeleteEvent));
            }
            mysqli_stmt_close($stmtDeleteEvent);

            mysqli_commit($conn);
            $feedback = ['type' => 'success', 'message' => 'Vald pub och dess orderhistorik togs bort.'];
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $feedback = ['type' => 'error', 'message' => 'Kunde inte ta bort vald pub.'];
        }
    }
}

/* 3. Data Fetching */
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

$selectedPubId = $activePubId;
$selectedPubName = $activePubName;

$stmtTotalOrders = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM orders WHERE event_id = ?");
mysqli_stmt_bind_param($stmtTotalOrders, 'i', $selectedPubId);
mysqli_stmt_execute($stmtTotalOrders);
mysqli_stmt_bind_result($stmtTotalOrders, $totalOrdersCount);
mysqli_stmt_fetch($stmtTotalOrders);
mysqli_stmt_close($stmtTotalOrders);
$totalOrders = (int) ($totalOrdersCount ?? 0);

$stmtTotalMilkshakes = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM order_milkshakes om JOIN orders o ON o.order_id = om.order_id WHERE o.event_id = ?");
mysqli_stmt_bind_param($stmtTotalMilkshakes, 'i', $selectedPubId);
mysqli_stmt_execute($stmtTotalMilkshakes);
mysqli_stmt_bind_result($stmtTotalMilkshakes, $totalMilkshakesCount);
mysqli_stmt_fetch($stmtTotalMilkshakes);
mysqli_stmt_close($stmtTotalMilkshakes);
$totalMilkshakesSold = (int) ($totalMilkshakesCount ?? 0);

$stmtTotalToasts = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM order_toasts ot JOIN orders o ON o.order_id = ot.order_id WHERE o.event_id = ?");
mysqli_stmt_bind_param($stmtTotalToasts, 'i', $selectedPubId);
mysqli_stmt_execute($stmtTotalToasts);
mysqli_stmt_bind_result($stmtTotalToasts, $totalToastsCount);
mysqli_stmt_fetch($stmtTotalToasts);
mysqli_stmt_close($stmtTotalToasts);
$totalToastsSold = (int) ($totalToastsCount ?? 0);
$totalItemsSold = $totalMilkshakesSold + $totalToastsSold;

$milkshakeSales = mysqli_fetch_all(mysqli_query(
    $conn,
    "SELECT 
        m.milkshake_id,
        m.name,
        (SELECT COUNT(*) FROM order_milkshakes WHERE milkshake_id = m.milkshake_id) AS total_sold,
        (SELECT COUNT(DISTINCT event_id) FROM pub_milkshakes WHERE milkshake_id = m.milkshake_id AND is_active = 1) AS num_pubs_active
     FROM milkshakes m
     WHERE (SELECT COUNT(DISTINCT event_id) FROM pub_milkshakes WHERE milkshake_id = m.milkshake_id AND is_active = 1) > 0
     ORDER BY total_sold DESC, m.name ASC"
), MYSQLI_ASSOC);

// Calculate average per pub
foreach ($milkshakeSales as &$item) {
    $item['avg_per_pub'] = $item['num_pubs_active'] > 0 ? round($item['total_sold'] / $item['num_pubs_active'], 2) : 0;
}
unset($item);

$toastSales = [];
$stmtToastSales = mysqli_prepare(
    $conn,
    "SELECT t.name, COUNT(*) AS sold
     FROM order_toasts ot
     JOIN orders o ON o.order_id = ot.order_id
     JOIN toasts t ON t.toast_id = ot.toast_id
     WHERE o.event_id = ?
     GROUP BY t.toast_id, t.name
     ORDER BY sold DESC, t.name ASC"
);
mysqli_stmt_bind_param($stmtToastSales, 'i', $selectedPubId);
mysqli_stmt_execute($stmtToastSales);
mysqli_stmt_bind_result($stmtToastSales, $toastName, $toastSold);
while (mysqli_stmt_fetch($stmtToastSales)) {
    $toastSales[] = ['name' => $toastName, 'sold' => (int) $toastSold];
}
mysqli_stmt_close($stmtToastSales);

$eventProductBreakdown = [];
$eventBreakdownResult = mysqli_query(
    $conn,
    "SELECT
        b.event_id,
        b.product_type,
        b.product_name,
        b.sold
     FROM (
        SELECT
            o.event_id,
            'Milkshake' AS product_type,
            m.name AS product_name,
            COUNT(*) AS sold
        FROM order_milkshakes om
        JOIN orders o ON o.order_id = om.order_id
        JOIN milkshakes m ON m.milkshake_id = om.milkshake_id
        GROUP BY o.event_id, m.milkshake_id, m.name

        UNION ALL

        SELECT
            o.event_id,
            'Toast' AS product_type,
            t.name AS product_name,
            COUNT(*) AS sold
        FROM order_toasts ot
        JOIN orders o ON o.order_id = ot.order_id
        JOIN toasts t ON t.toast_id = ot.toast_id
        GROUP BY o.event_id, t.toast_id, t.name
     ) b
     ORDER BY b.event_id, b.sold DESC, b.product_name ASC"
);

if ($eventBreakdownResult) {
    while ($row = mysqli_fetch_assoc($eventBreakdownResult)) {
        $eventId = (int) $row['event_id'];
        $eventProductBreakdown[$eventId][] = [
            'type' => $row['product_type'],
            'name' => $row['product_name'],
            'sold' => (int) $row['sold'],
        ];
    }
    mysqli_free_result($eventBreakdownResult);
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik</title>
    <link rel="icon" href="../img/logo/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="../img/logo/favicon.png" type="image/png">
    <style>
        /* --- 4. Layout & Theme --- */
        :root {
            --bg: #f3f4f6;
            --bg-light: #f3f4f6;
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
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            background: linear-gradient(180deg, #eef2ff 0%, var(--bg-light) 30%, #eef2ff 100%);
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

        .event-breakdown {
            margin-top: 0.45rem;
        }

        .event-breakdown summary {
            cursor: pointer;
            color: var(--primary);
            font-size: 0.84rem;
            font-weight: 600;
            list-style: none;
        }

        .event-breakdown summary::-webkit-details-marker {
            display: none;
        }

        .event-breakdown summary::before {
            content: '▸';
            display: inline-block;
            margin-right: 0.35rem;
            transition: transform 0.15s ease;
        }

        .event-breakdown[open] summary::before {
            transform: rotate(90deg);
        }

        .breakdown-list {
            margin: 0.5rem 0 0;
            padding-left: 1rem;
        }

        .breakdown-list li {
            margin: 0.2rem 0;
            font-size: 0.85rem;
        }

        .breakdown-type {
            color: var(--text-sub);
            margin-right: 0.35rem;
        }

        .expand-list {
            margin-top: 0.6rem;
        }

        .expand-list summary {
            display: inline-block;
            cursor: pointer;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.35rem 0.6rem;
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--primary);
            background: #eff6ff;
            list-style: none;
        }

        .expand-list summary::-webkit-details-marker {
            display: none;
        }

        .expand-list summary::before {
            content: '▸';
            display: inline-block;
            margin-right: 0.35rem;
            transition: transform 0.15s ease;
        }

        .expand-list[open] summary::before {
            transform: rotate(90deg);
        }

        .expand-list[open] summary {
            margin-bottom: 0.45rem;
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
        <h1>Statistik</h1>
        <p class="subtitle">Statistik för aktiv pub: <strong><?= htmlspecialchars($selectedPubName) ?></strong></p>

        <?php if ($feedback): ?>
            <div class="notice <?= $feedback['type'] ?>"><?= htmlspecialchars($feedback['message']) ?></div>
        <?php endif; ?>

        <div class="kpi-grid">
            <div class="kpi">
                <div class="kpi-label">Totala beställningar (<?= htmlspecialchars($selectedPubName) ?>)</div>
                <div class="kpi-value"><?= $totalOrders ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Sålda produkter</div>
                <div class="kpi-value"><?= $totalItemsSold ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Sålda milkshakes</div>
                <div class="kpi-value"><?= $totalMilkshakesSold ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Beställda toasts</div>
                <div class="kpi-value"><?= $totalToastsSold ?></div>
            </div>
        </div>

        <div class="grid-2">
            <section class="card">
                <h2>Milkshakeförsäljning per smak</h2>
                <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 1rem;">Genomsnittligt antal sålda per pub</p>
                <?php if (empty($milkshakeSales)): ?>
                    <p class="empty">Ingen milkshake-försäljning ännu.</p>
                <?php else: ?>
                    <?php
                        $visibleMilkshakeSales = array_slice($milkshakeSales, 0, 7);
                        $hiddenMilkshakeSales = array_slice($milkshakeSales, 7);
                    ?>
                    <ol class="list">
                        <?php foreach ($visibleMilkshakeSales as $row): ?>
                            <li><?= htmlspecialchars($row['name']) ?> — <strong><?= $row['avg_per_pub'] ?></strong> <span style="color: #9ca3af; font-size: 0.85rem;">(<?= (int) $row['total_sold'] ?> totalt, <?= (int) $row['num_pubs_active'] ?> pub<?= (int) $row['num_pubs_active'] !== 1 ? 'ar' : '' ?>)</span></li>
                        <?php endforeach; ?>
                    </ol>

                    <?php if (!empty($hiddenMilkshakeSales)): ?>
                        <details class="expand-list">
                            <summary>Visa fler milkshakes (<?= count($hiddenMilkshakeSales) ?>)</summary>
                            <ol class="list" start="8">
                                <?php foreach ($hiddenMilkshakeSales as $row): ?>
                                    <li><?= htmlspecialchars($row['name']) ?> — <strong><?= $row['avg_per_pub'] ?></strong> <span style="color: #9ca3af; font-size: 0.85rem;">(<?= (int) $row['total_sold'] ?> totalt, <?= (int) $row['num_pubs_active'] ?> pub<?= (int) $row['num_pubs_active'] !== 1 ? 'ar' : '' ?>)</span></li>
                                <?php endforeach; ?>
                            </ol>
                        </details>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Toastförsäljning per smak</h2>
                <?php if (empty($toastSales)): ?>
                    <p class="empty">Ingen toast-försäljning ännu.</p>
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
            <h2>Översikt över pubhistorik</h2>
            <?php if (empty($allPubs)): ?>
                <p class="empty">Inga pubar skapade ännu.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Pub</th>
                            <th>Startad</th>
                            <th>Avslutad</th>
                            <th>Beställningar</th>
                            <th>Milkshakes</th>
                            <th>Toasts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allPubs as $pub): ?>
                            <?php $eventId = (int) $pub['event_id']; ?>
                            <?php $breakdownItems = $eventProductBreakdown[$eventId] ?? []; ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($pub['event_name']) ?>
                                    <?php if ((int) $pub['is_active'] === 1): ?>
                                        <span class="badge-active">AKTIV</span>
                                    <?php endif; ?>

                                    <details class="event-breakdown">
                                        <summary>Visa sålda produkter</summary>
                                        <?php if (empty($breakdownItems)): ?>
                                            <p class="empty" style="margin-top:0.4rem;">Inga produkter sålda i detta event.</p>
                                        <?php else: ?>
                                            <ul class="breakdown-list">
                                                <?php foreach ($breakdownItems as $item): ?>
                                                    <li>
                                                        <span class="breakdown-type"><?= htmlspecialchars($item['type']) ?>:</span>
                                                        <?= htmlspecialchars($item['name']) ?> — <strong><?= (int) $item['sold'] ?></strong>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </details>
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
            <h2>Riskzon: Ta bort vald pub</h2>
            <p>Välj vilken gammal pub som ska tas bort. Den aktiva puben visas inte i listan och kan inte tas bort.</p>
            <form method="post" class="pub-tools" onsubmit="return confirm('Ta bort vald pub och alla dess beställningar? Detta kan inte ångras.');">
                <?= csrf_token_input() ?>
                <select name="target_pub_id" required>
                    <option value="">Välj gammal pub</option>
                    <?php foreach ($allPubs as $pub): ?>
                        <?php if ((int) $pub['is_active'] === 0): ?>
                            <option value="<?= (int) $pub['event_id'] ?>">
                                <?= htmlspecialchars($pub['event_name']) ?>
                                (<?= htmlspecialchars($pub['started_at']) ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="confirm_delete_pub" placeholder="Skriv DELETE PUB" required autocomplete="off">
                <button type="submit" class="danger" name="delete_pub">Ta bort vald pub</button>
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
                <p class="empty" style="margin-top:0.75rem;">Inga gamla pubar att ta bort ännu.</p>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
