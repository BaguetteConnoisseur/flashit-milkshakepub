<?php
/* --- 1. Statistics View Bootstrap --- */
require_once(__DIR__ . '/../../private/initialize.php');

$db = db();

// Get active pub/event
$activePubId = (int) ($_SESSION['active_pub_id'] ?? 0);
$activePubName = $_SESSION['active_pub_name'] ?? '';
$selectedPubId = $activePubId;
$selectedPubName = $activePubName;
$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    // Delete pub logic
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
            $db->beginTransaction();
            try {
                // Delete order_items for orders in this event
                $db->prepare("DELETE oi FROM order_items oi JOIN orders o ON o.order_id = oi.order_id WHERE o.event_id = ?")
                    ->execute([$targetPubId]);
                // Delete orders for this event
                $db->prepare("DELETE FROM orders WHERE event_id = ?")
                    ->execute([$targetPubId]);
                // Delete event_menu_items for this event
                $db->prepare("DELETE FROM event_menu_items WHERE event_id = ?")
                    ->execute([$targetPubId]);
                // Delete the pub event (only if inactive)
                $db->prepare("DELETE FROM pub_events WHERE event_id = ? AND is_active = 0")
                    ->execute([$targetPubId]);
                $db->commit();
                $feedback = ['type' => 'success', 'message' => 'Vald pub och dess orderhistorik togs bort.'];
            } catch (Throwable $e) {
                $db->rollBack();
                $feedback = ['type' => 'error', 'message' => 'Kunde inte ta bort vald pub.'];
            }
        }
    }
}

// Fetch all pubs/events
$allPubs = $db->query("SELECT event_id, event_name, started_at, ended_at, is_active FROM pub_events ORDER BY started_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Allow leaderboard filter by pub
$leaderboardPubId = isset($_GET['leaderboard_pub_id']) && $_GET['leaderboard_pub_id'] !== 'all' ? (int) $_GET['leaderboard_pub_id'] : null;
$leaderboardPubName = 'Alla pubar';
if ($leaderboardPubId !== null) {
    foreach ($allPubs as $pub) {
        if ((int) $pub['event_id'] === $leaderboardPubId) {
            $leaderboardPubName = $pub['event_name'];
            break;
        }
    }
}

// KPIs for selected pub
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE event_id = ?");
$stmt->execute([$selectedPubId]);
$totalOrders = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.order_id = oi.order_id WHERE o.event_id = ?");
$stmt->execute([$selectedPubId]);
$totalItemsSold = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN menu_items mi ON oi.item_id = mi.item_id WHERE o.event_id = ? AND mi.category = 'milkshake'");
$stmt->execute([$selectedPubId]);
$totalMilkshakesSold = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN menu_items mi ON oi.item_id = mi.item_id WHERE o.event_id = ? AND mi.category = 'toast'");
$stmt->execute([$selectedPubId]);
$totalToastsSold = (int) $stmt->fetchColumn();

// Per-item sales for selected pub
$milkshakeSales = $db->prepare("SELECT mi.name, COUNT(*) AS total_sold FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN menu_items mi ON oi.item_id = mi.item_id WHERE o.event_id = ? AND mi.category = 'milkshake' GROUP BY mi.item_id, mi.name ORDER BY total_sold DESC, mi.name ASC");
$milkshakeSales->execute([$selectedPubId]);
$milkshakeSales = $milkshakeSales->fetchAll(PDO::FETCH_ASSOC);

$toastSales = $db->prepare("SELECT mi.name, COUNT(*) AS total_sold FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN menu_items mi ON oi.item_id = mi.item_id WHERE o.event_id = ? AND mi.category = 'toast' GROUP BY mi.item_id, mi.name ORDER BY total_sold DESC, mi.name ASC");
$toastSales->execute([$selectedPubId]);
$toastSales = $toastSales->fetchAll(PDO::FETCH_ASSOC);

// Calculate average per pub for milkshakes
$milkshakeAverages = [];
$stmt = $db->query("SELECT mi.item_id, mi.name,
    COUNT(oi.order_item_id) AS total_sold,
    COUNT(DISTINCT o.event_id) AS num_pubs_sold
    FROM menu_items mi
    LEFT JOIN order_items oi ON oi.item_id = mi.item_id
    LEFT JOIN orders o ON o.order_id = oi.order_id
    WHERE mi.category = 'milkshake'
    GROUP BY mi.item_id, mi.name
    HAVING total_sold > 0
    ORDER BY total_sold DESC, mi.name ASC");
foreach ($stmt as $row) {
    $avg = $row['num_pubs_sold'] > 0 ? round($row['total_sold'] / $row['num_pubs_sold'], 2) : 0;
    $milkshakeAverages[] = [
        'name' => $row['name'],
        'total_sold' => $row['total_sold'],
        'num_pubs_sold' => $row['num_pubs_sold'],
        'avg_per_pub' => $avg
    ];
}

// Calculate average per pub for toasts
$toastAverages = [];
$stmt = $db->query("SELECT mi.item_id, mi.name,
    COUNT(oi.order_item_id) AS total_sold,
    COUNT(DISTINCT o.event_id) AS num_pubs_sold
    FROM menu_items mi
    LEFT JOIN order_items oi ON oi.item_id = mi.item_id
    LEFT JOIN orders o ON o.order_id = oi.order_id
    WHERE mi.category = 'toast'
    GROUP BY mi.item_id, mi.name
    HAVING total_sold > 0
    ORDER BY total_sold DESC, mi.name ASC");
foreach ($stmt as $row) {
    $avg = $row['num_pubs_sold'] > 0 ? round($row['total_sold'] / $row['num_pubs_sold'], 2) : 0;
    $toastAverages[] = [
        'name' => $row['name'],
        'total_sold' => $row['total_sold'],
        'num_pubs_sold' => $row['num_pubs_sold'],
        'avg_per_pub' => $avg
    ];
}

// Leaderboard (top 10) for selected pub or all time
if ($leaderboardPubId === null) {
    // All time
    $topMilkshakes = $db->query("SELECT mi.name AS item_name, COUNT(*) AS sold FROM order_items oi JOIN menu_items mi ON oi.item_id = mi.item_id WHERE mi.category = 'milkshake' GROUP BY mi.item_id, mi.name ORDER BY sold DESC, mi.name ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $topToasts = $db->query("SELECT mi.name AS item_name, COUNT(*) AS sold FROM order_items oi JOIN menu_items mi ON oi.item_id = mi.item_id WHERE mi.category = 'toast' GROUP BY mi.item_id, mi.name ORDER BY sold DESC, mi.name ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Specific pub
    $stmt = $db->prepare("SELECT mi.name AS item_name, COUNT(*) AS sold FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN menu_items mi ON oi.item_id = mi.item_id WHERE o.event_id = ? AND mi.category = 'milkshake' GROUP BY mi.item_id, mi.name ORDER BY sold DESC, mi.name ASC LIMIT 10");
    $stmt->execute([$leaderboardPubId]);
    $topMilkshakes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $db->prepare("SELECT mi.name AS item_name, COUNT(*) AS sold FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN menu_items mi ON oi.item_id = mi.item_id WHERE o.event_id = ? AND mi.category = 'toast' GROUP BY mi.item_id, mi.name ORDER BY sold DESC, mi.name ASC LIMIT 10");
    $stmt->execute([$leaderboardPubId]);
    $topToasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pub history
$pubHistory = $db->query("SELECT e.event_id, e.event_name, e.started_at, e.ended_at, e.is_active,
    (SELECT COUNT(*) FROM orders o WHERE o.event_id = e.event_id) AS total_orders,
    (SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN menu_items mi ON oi.item_id = mi.item_id WHERE o.event_id = e.event_id AND mi.category = 'milkshake') AS total_milkshakes,
    (SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN menu_items mi ON oi.item_id = mi.item_id WHERE o.event_id = e.event_id AND mi.category = 'toast') AS total_toasts
    FROM pub_events e ORDER BY e.started_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik</title>
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

        .leaderboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        .leaderboard-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .leaderboard-title {
            margin: 0;
        }

        .leaderboard-subtitle {
            color: var(--text-sub);
            font-size: 0.9rem;
            margin: 0.35rem 0 0;
        }

        .leaderboard-filter {
            margin-left: auto;
            flex-wrap: nowrap;
            gap: 0.5rem;
        }

        .leaderboard-filter label {
            white-space: nowrap;
            color: var(--text-sub);
            font-size: 0.9rem;
        }

        .leaderboard-filter select {
            min-width: 190px;
            max-width: 260px;
        }

        #leaderboard-section.is-loading {
            opacity: 0.65;
            transition: opacity 0.15s ease;
        }

        @media (max-width: 900px) {
            .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid-2 { grid-template-columns: 1fr; }
            .leaderboard-grid { grid-template-columns: 1fr; }
            .leaderboard-header { flex-direction: column; align-items: stretch; }
            .leaderboard-filter { margin-left: 0; }
            .leaderboard-filter select { width: 100%; max-width: none; }
        }
    </style>
</head>
<body>
    <?php require(TEMPLATE_PATH . "/admin_navbar.php"); ?>

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
                <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 1rem;">Genomsnittligt antal sålda per pub (topp 5)</p>
                <?php if (empty($milkshakeAverages)): ?>
                    <p class="empty">Ingen milkshake-försäljning ännu.</p>
                <?php else: ?>
                    <?php $topMilkshakeAverageSales = array_slice($milkshakeAverages, 0, 5); ?>
                    <ol class="list">
                        <?php foreach ($topMilkshakeAverageSales as $row): ?>
                            <li><?= htmlspecialchars($row['name']) ?> — <strong><?= $row['avg_per_pub'] ?></strong> <span style="color: #9ca3af; font-size: 0.85rem;">(<?= (int) $row['total_sold'] ?> totalt, <?= (int) $row['num_pubs_sold'] ?> pub<?= (int) $row['num_pubs_sold'] !== 1 ? 'ar' : '' ?>)</span></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Toastförsäljning per smak</h2>
                <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 1rem;">Genomsnittligt antal sålda per pub (topp 5)</p>
                <?php if (empty($toastAverages)): ?>
                    <p class="empty">Ingen toast-försäljning ännu.</p>
                <?php else: ?>
                    <?php $topToastAverageSales = array_slice($toastAverages, 0, 5); ?>
                    <ol class="list">
                        <?php foreach ($topToastAverageSales as $row): ?>
                            <li><?= htmlspecialchars($row['name']) ?> — <strong><?= $row['avg_per_pub'] ?></strong> <span style="color: #9ca3af; font-size: 0.85rem;">(<?= (int) $row['total_sold'] ?> totalt, <?= (int) $row['num_pubs_sold'] ?> pub<?= (int) $row['num_pubs_sold'] !== 1 ? 'ar' : '' ?>)</span></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>

        <section id="leaderboard-section" class="card" style="margin-top: 1rem;">
            <div class="leaderboard-header">
                <div>
                    <h2 class="leaderboard-title">Topplista</h2>
                    <p class="leaderboard-subtitle">Mest sålda produkter<?= $leaderboardPubId !== null ? ' för ' . htmlspecialchars($leaderboardPubName) : ' genom tiderna' ?>.</p>
                </div>

                <form method="get" class="pub-tools leaderboard-filter">
                    <label for="leaderboard_pub_id">Välj pub:</label>
                    <select name="leaderboard_pub_id" id="leaderboard_pub_id">
                        <option value="all" <?= $leaderboardPubId === null ? 'selected' : '' ?>>Alla pubar</option>
                        <?php foreach ($allPubs as $pub): ?>
                            <option value="<?= (int) $pub['event_id'] ?>" <?= $leaderboardPubId === (int) $pub['event_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pub['event_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript>
                        <button type="submit">Visa</button>
                    </noscript>
                </form>
            </div>

            <div class="leaderboard-grid" style="margin-top: 1rem;">
                <div>
                    <h3 style="margin: 0 0 0.8rem; font-size: 1.05rem;">Topp milkshakes</h3>
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
                </div>

                <div>
                    <h3 style="margin: 0 0 0.8rem; font-size: 1.05rem;">Topp toasts</h3>
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
                </div>
            </div>
        </section>

        <section class="card" style="margin-bottom: 1.5rem;">
            <h2>Pubhistorik</h2>
            <?php if (empty($pubHistory)): ?>
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
                        <?php foreach ($pubHistory as $pub): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($pub['event_name']) ?>
                                    <?php if ((int) $pub['is_active'] === 1): ?>
                                        <span class="badge-active">AKTIV</span>
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

    <script>
        (function () {
            const leaderboardSectionSelector = '#leaderboard-section';
            let activeRequest = null;

            document.addEventListener('change', async function (event) {
                const target = event.target;
                if (!(target instanceof HTMLSelectElement) || target.id !== 'leaderboard_pub_id') {
                    return;
                }

                const leaderboardSection = document.querySelector(leaderboardSectionSelector);
                if (!leaderboardSection) {
                    return;
                }

                const selectedPubId = target.value;
                const url = new URL(window.location.href);

                if (selectedPubId === 'all') {
                    url.searchParams.delete('leaderboard_pub_id');
                } else {
                    url.searchParams.set('leaderboard_pub_id', selectedPubId);
                }

                if (activeRequest) {
                    activeRequest.abort();
                }

                activeRequest = new AbortController();
                leaderboardSection.classList.add('is-loading');
                target.disabled = true;

                try {
                    const response = await fetch(url.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: activeRequest.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load leaderboard section');
                    }

                    const html = await response.text();
                    const parser = new DOMParser();
                    const fetchedDocument = parser.parseFromString(html, 'text/html');
                    const nextLeaderboardSection = fetchedDocument.querySelector(leaderboardSectionSelector);

                    if (!nextLeaderboardSection) {
                        window.location.href = url.toString();
                        return;
                    }

                    leaderboardSection.innerHTML = nextLeaderboardSection.innerHTML;
                    window.history.replaceState({}, '', url.pathname + url.search + url.hash);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        window.location.href = url.toString();
                    }
                } finally {
                    activeRequest = null;
                    const currentLeaderboardSection = document.querySelector(leaderboardSectionSelector);
                    if (currentLeaderboardSection) {
                        currentLeaderboardSection.classList.remove('is-loading');
                        const currentSelect = currentLeaderboardSection.querySelector('#leaderboard_pub_id');
                        if (currentSelect) {
                            currentSelect.disabled = false;
                        }
                    }
                }
            });
        })();
    </script>
</body>
</html>