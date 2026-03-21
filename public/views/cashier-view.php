<?php
require_once(__DIR__ . "/../../private/initialize.php");
require_once(PRIVATE_PATH . '/src/services/inventoryManager.php');

$pdo = db();
$activePubId = $_SESSION['active_pub_id'] ?? null;
$activePubName = $_SESSION['active_pub_name'] ?? '';

// --- 1. Inventory for Create Form ---
$inventory = new InventoryManager($pdo, $activePubId);
$milkshakes = $inventory->getItemsByCategory('milkshake', true);
$toasts = $inventory->getItemsByCategory('toast', true);

// --- 2. Handle POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    // CREATE ORDER
    if (isset($_POST['create_order'])) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_number), 0) + 1 AS next_num FROM orders WHERE event_id = ? FOR UPDATE");
            $stmt->execute([$activePubId]);
            $order_number = (int)($stmt->fetchColumn() ?: 1);
            $stmt = $pdo->prepare("INSERT INTO orders (event_id, order_number, customer_name, order_comment, status) VALUES (?, ?, ?, ?, 'Pending')");
            $stmt->execute([
                $activePubId,
                $order_number,
                trim($_POST['customer_name'] ?? ''),
                trim($_POST['order_comment'] ?? '')
            ]);
            $order_id = $pdo->lastInsertId();

            // Milkshakes
            foreach ($_POST['milkshakes'] ?? [] as $item_id => $qty) {
                $qty = (int)$qty;
                for ($i = 0; $i < $qty; $i++) {
                    $comment = trim($_POST['milkshake_comments']['m_' . $item_id . '_' . $i] ?? '');
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, item_id, status, item_comment) VALUES (?, ?, 'Pending', ?)");
                    $stmt->execute([$order_id, $item_id, $comment]);
                }
            }
            // Toasts
            foreach ($_POST['toasts'] ?? [] as $item_id => $qty) {
                $qty = (int)$qty;
                for ($i = 0; $i < $qty; $i++) {
                    $comment = trim($_POST['toast_comments']['t_' . $item_id . '_' . $i] ?? '');
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, item_id, status, item_comment) VALUES (?, ?, 'Pending', ?)");
                    $stmt->execute([$order_id, $item_id, $comment]);
                }
            }
            $pdo->commit();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo "<div style='color:red'>Fel: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    // UPDATE ORDER
    if (isset($_POST['update_order'])) {
        $order_id = (int)$_POST['order_id'];
        $main_status = $_POST['main_status'] ?? 'Pending';
        $main_comment = trim($_POST['main_comment'] ?? '');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, order_comment = ? WHERE order_id = ? AND event_id = ?");
            $stmt->execute([$main_status, $main_comment, $order_id, $activePubId]);

            // Update order_items
            foreach (($_POST['oi_status'] ?? []) as $oi_id => $status) {
                $comment = $_POST['oi_comment'][$oi_id] ?? '';
                $stmt = $pdo->prepare("UPDATE order_items SET status = ?, item_comment = ? WHERE order_item_id = ?");
                $stmt->execute([$status, $comment, $oi_id]);
            }
            $pdo->commit();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
        }
    }
    // DELETE ORDER
    if (isset($_POST['delete_order'])) {
        $order_id = (int)$_POST['order_id'];
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
            $pdo->prepare("DELETE FROM orders WHERE order_id = ? AND event_id = ?")->execute([$order_id, $activePubId]);
            $pdo->commit();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
        }
    }
}

// --- 3. Fetch Orders ---
$orders = [];
$modal_order = null;
$modal_items = [];
// Fetch all orders for this event
$stmt = $pdo->prepare("SELECT * FROM orders WHERE event_id = ? ORDER BY created_at DESC");
$stmt->execute([$activePubId]);
$orders = $stmt->fetchAll();

// Attach summary for each order
foreach ($orders as &$order) {
    $stmt = $pdo->prepare("SELECT mi.name, mi.category, COUNT(*) as qty FROM order_items oi JOIN menu_items mi ON oi.item_id = mi.item_id WHERE oi.order_id = ? GROUP BY mi.item_id, mi.name, mi.category");
    $stmt->execute([$order['order_id']]);
    $summary = [];
    foreach ($stmt->fetchAll() as $row) {
        $summary[] = $row['name'] . ($row['qty'] > 1 ? " (x{$row['qty']})" : "");
    }
    $order['summary'] = implode(", ", $summary);
}
unset($order);

// Modal: fetch specific order and its items
if (isset($_GET['view_order'])) {
    $view_id = (int)$_GET['view_order'];
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND event_id = ?");
    $stmt->execute([$view_id, $activePubId]);
    $modal_order = $stmt->fetch();
    if ($modal_order) {
        $stmt = $pdo->prepare("SELECT oi.*, mi.name, mi.category FROM order_items oi JOIN menu_items mi ON oi.item_id = mi.item_id WHERE oi.order_id = ?");
        $stmt->execute([$view_id]);
        $modal_items = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order System</title>
    <style>
        :root {
            --primary: #2563eb;
            --bg-light: #f3f4f6;
            --surface: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --border: #e5e7eb;
            --delivered: #9ca3af;
        }

        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            background: linear-gradient(180deg, #eef2ff 0%, var(--bg-light) 30%, #eef2ff 100%);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navigation */
        .page-nav {
            position: fixed;
            top: 1.25rem;
            left: 1.25rem;
            z-index: 100;
        }

        .cashier-shell {
            width: calc(100% - 10rem);
            max-width: 100%;
            margin: 4.25rem auto 1.25rem;
            display: grid;
            grid-template-columns: 380px minmax(0, 2.4fr);
            gap: 1rem;
            align-items: start;
        }

        .home-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--surface);
            color: var(--text-main);
            text-decoration: none;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .home-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .home-icon {
            font-size: 1.1rem;
        }

        /* --- 5. Left Column: Create --- */
        .col-create {
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 1.25rem;
            overflow: visible;
            box-shadow: 0 12px 28px rgba(31, 41, 55, 0.08);
            border-radius: 16px;
            max-height: none;
            position: static;
        }

        h1, h2, h3 { margin-top: 0; font-weight: 600; }
        h2 { font-size: 1.25rem; margin-bottom: 1rem; }

        .panel-kicker {
            margin: 0 0 0.4rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: var(--primary);
            text-transform: uppercase;
        }

        .panel-subtext {
            margin: -0.5rem 0 1rem;
            color: var(--text-sub);
            font-size: 0.88rem;
            line-height: 1.35;
        }

        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.9rem;
            box-sizing: border-box;
        }

        .item-row {
            background: #f9fafb;
            border: 1px solid var(--border);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }

        .item-row h4 {
            margin: 0 0 0.5rem 0;
            font-size: 0.95rem;
        }

        .item-comments {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
        }

        .menu-section-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.45rem;
        }
        
        .quantity-group {
            border: 1px solid var(--border);
            border-radius: 10px;
            max-height: none;
            overflow: visible;
            padding: 0.5rem;
            background: #fbfcff;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
        }
        .quantity-item { 
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.55rem;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #ffffff;
        }

        .quantity-item label {
            margin: 0;
            font-weight: 500;
            line-height: 1.25;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            justify-content: center;
        }
        
        .qty-btn {
            width: 30px;
            height: 30px;
            border: 1px solid var(--border);
            background: white;
            color: var(--text-main);
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            user-select: none;
        }

        .qty-minus {
            background: #fef2f2;
            border-color: #fecaca;
            color: #b91c1c;
        }

        .qty-plus {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }
        
        .qty-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: scale(1.02);
        }
        
        .qty-btn:active {
            transform: scale(0.95);
        }
        
        .qty-minus:hover {
            background: #ef4444;
            border-color: #ef4444;
        }

        .qty-plus:hover {
            background: #2563eb;
            border-color: #2563eb;
        }
        
        .quantity-input { 
            width: 34px; 
            height: 30px;
            padding: 0; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            text-align: center; 
            font-size: 0.9rem;
            font-weight: 700;
            line-height: 30px;
            background: #ffffff;
            color: var(--text-main);
            box-sizing: border-box;
            appearance: textfield;
            -moz-appearance: textfield;
        }

        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            font-weight: 600;
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn:hover { opacity: 0.95; transform: translateY(-1px); }
        .btn-danger { background-color: #ef4444; margin-top: 1rem; }

        /* --- 6. Right Column: Order List --- */
        .col-list {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 12px 28px rgba(31, 41, 55, 0.08);
            padding: 1.25rem;
            overflow: visible;
            max-height: none;
        }

        .order-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
        }

        .order-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            cursor: pointer;
            transition: transform 0.14s, box-shadow 0.14s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 16px rgba(31, 41, 55, 0.08);
            border-color: var(--primary);
        }

        /* Delivered State */
        .order-card.status-delivered {
            opacity: 0.6;
            background-color: #f9fafb;
            border-color: transparent;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .view-toggle {
            display: flex;
            gap: 0.5rem;
        }

        .view-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            background: #f8fafc;
            color: var(--text-sub);
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .view-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .view-btn:hover {
            opacity: 0.8;
        }

        /* List View */
        .order-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .order-card.list-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            gap: 1rem;
        }

        .order-card.list-item .card-header {
            flex: 0 0 120px;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .order-card.list-item .customer-name {
            flex: 1;
            font-weight: 600;
        }

        .order-card.list-item .order-time {
            flex: 0 0 120px;
            text-align: right;
            font-size: 0.85rem;
            color: var(--text-sub);
        }

        .order-card.list-item .order-summary {
            flex: 2;
            font-size: 0.9rem;
            color: var(--text-sub);
        }

        .order-card.list-item hr {
            display: none;
        }
        .order-card.status-delivered:hover {
            opacity: 1;
            border-color: var(--border);
        }

        .card-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem; }
        .order-number { font-size: 0.75rem; color: var(--text-sub); font-family: monospace; }
        .order-time { font-size: 0.75rem; color: var(--text-sub); }
        .customer-name { font-weight: 700; font-size: 1.1rem; margin-bottom: 0.25rem; }
        .order-summary { font-size: 0.9rem; color: var(--text-sub); line-height: 1.4; }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-pending { background: #fff7ed; color: #c2410c; }
        .badge-in-progress { background: #fef3c7; color: #92400e; }
        .badge-done { background: #dcfce7; color: #166534; }
        .badge-delivered { background: #f3f4f6; color: #374151; }

        /* --- 7. Modal --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: var(--surface);
            width: 600px;
            max-width: 90%;
            max-height: 90vh;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            display: flex; flex-direction: column;
            overflow: hidden;
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            background: #f9fafb;
        }
        .modal-body { padding: 1.5rem; overflow-y: auto; }
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex; justify-content: space-between;
        }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-sub); text-decoration: none; }

        .item-row {
            background: #f9fafb;
            border: 1px solid var(--border);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .item-row h4 { margin: 0 0 0.5rem 0; font-size: 1rem; }
        .row-split { display: flex; gap: 1rem; }
        .row-split > div { flex: 1; }

        @media (max-width: 1100px) {
            .cashier-shell {
                grid-template-columns: 1fr;
                margin-top: 4.75rem;
            }

            .col-create,
            .col-list {
                max-height: none;
                position: static;
            }

            .page-nav {
                top: 0.75rem;
                left: 0.75rem;
            }
        }

        @media (max-width: 1600px) {
            .order-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1350px) {
            .order-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 800px) {
            .order-grid {
                grid-template-columns: 1fr;
            }

            .quantity-group {
                grid-template-columns: 1fr;
            }
        }

    </style>
</head>
<body>

    <nav class="page-nav">
        <a href="<?= WWW_ROOT ?>/index.php" class="home-btn">
            <span class="home-icon">🏠</span>
            Hem
        </a>
    </nav>

    <main class="cashier-shell">

    <section class="col-create">
        <p class="panel-kicker">Kassaarbetsyta</p>
        <h2>Ny beställning</h2>
        <p class="panel-subtext">Skapa beställningar snabbt, tryck på artikelnamn för detaljer och skicka vidare till stationerna.</p>
        <button type="button" class="btn" onclick="openCreateOrderModal()">+ Ny beställning</button>
    </section>

    <section class="col-list">
        <div class="section-header">
            <div>
                <p class="panel-kicker" style="margin-bottom:0.2rem;">Aktiv kö</p>
                <h2 style="margin-bottom:0;">Pågående beställningar</h2>
            </div>
            <div class="view-toggle">
                <button id="card-view-btn" class="view-btn active" onclick="setView('card')">Kortvy</button>
                <button id="list-view-btn" class="view-btn" onclick="setView('list')">Listvy</button>
            </div>
        </div>
        <div id="order-container" class="order-grid">
            <?php foreach($orders as $order): 
                $statusClass = strtolower($order['status']) === 'delivered' ? 'status-delivered' : '';
                $badgeClass = 'badge-' . str_replace(' ', '-', strtolower($order['status']));
            ?>
                <a href="?view_order=<?= $order['order_id'] ?>" class="order-card <?= $statusClass ?>">
                    <div class="card-header">
                        <span class="order-number">Beställning: #<?= htmlspecialchars($order['order_number'] ?? $order['order_id']) ?></span>
                        <span class="status-badge <?= $badgeClass ?>"><?= $order['status'] ?></span>
                    </div>
                    <div class="customer-name"><?= htmlspecialchars($order['customer_name']) ?></div>
                    <div class="order-time"><?= isset($order['created_at']) ? date("H:i", strtotime($order['created_at'])) : '' ?></div>
                    <hr style="border: 0; border-top: 1px solid var(--border); margin: 0.5rem 0;">
                    <div class="order-summary">
                        <?= $order['summary'] ? htmlspecialchars(substr($order['summary'], 0, 50)) . (strlen($order['summary']) > 50 ? '...' : '') : 'Inga artiklar' ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    </main>

    <?php if ($modal_order): ?>
    <div class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 style="margin:0">Order #<?= htmlspecialchars($modal_order['pub_order_number'] ?? $modal_order['order_number'] ?? $modal_order['order_id']) ?></h2>
                    <span style="font-size:0.9rem; color:var(--text-sub)"><?= htmlspecialchars($modal_order['customer_name']) ?></span>
                </div>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="close-btn">&times;</a>
            </div>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" method="POST" style="display:contents;">
                <?= csrf_token_input() ?>
                <input type="hidden" name="order_id" value="<?= $modal_order['order_id'] ?>">
                
                <div class="modal-body">
                    <div class="row-split" style="margin-bottom: 2rem;">
                        <div class="form-group">
                            <label>Beställningsstatus</label>
                            <select name="main_status">
                                <?php $s = $modal_order['status']; ?>
                                <option value="Pending" <?= $s=='Pending'?'selected':'' ?>>Väntar</option>
                                <option value="In Progress" <?= $s=='In Progress'?'selected':'' ?>>Pågår</option>
                                <option value="Done" <?= $s=='Done'?'selected':'' ?>>Klar</option>
                                <option value="Delivered" <?= $s=='Delivered'?'selected':'' ?>>Levererad</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Huvudkommentar</label>
                            <input type="text" name="main_comment" value="<?= htmlspecialchars($modal_order['order_comment']) ?>">
                        </div>
                    </div>

                    <h3 style="border-bottom:1px solid var(--border); padding-bottom:0.5rem; margin-bottom:1rem;">Artiklar</h3>

                    <?php foreach($modal_items as $item): ?>
                        <div class="item-row">
                            <h4>
                                <?= $item['category'] === 'milkshake' ? '🥤' : '🥪' ?>
                                <?= htmlspecialchars($item['name']) ?>
                            </h4>
                            <div class="row-split">
                                <div>
                                    <label>Status</label>
                                    <select name="oi_status[<?= $item['order_item_id'] ?>]" style="padding:0.25rem;">
                                        <option value="Pending" <?= $item['status']=='Pending'?'selected':'' ?>>Väntar</option>
                                        <option value="In Progress" <?= $item['status']=='In Progress'?'selected':'' ?>>Pågår</option>
                                        <option value="Done" <?= $item['status']=='Done'?'selected':'' ?>>Klar</option>
                                        <option value="Delivered" <?= $item['status']=='Delivered'?'selected':'' ?>>Levererad</option>
                                    </select>
                                </div>
                                <div>
                                    <label>Notering</label>
                                    <input type="text" name="oi_comment[<?= $item['order_item_id'] ?>]" value="<?= htmlspecialchars($item['item_comment']) ?>" placeholder="Lägg till notering...">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="delete_order" class="btn btn-danger" style="width:auto; margin:0;" onclick="return confirm('Radera hela beställningen?');">Radera beställning</button>
                    <button type="submit" name="update_order" class="btn" style="width:auto; margin:0;">Spara ändringar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Create Order Modal -->
    <div id="create-order-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">Ny beställning</h3>
                <button type="button" class="close-btn" onclick="closeCreateOrderModal()">✕</button>
            </div>
            <div class="modal-body">
                <form id="create-order-form" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" method="POST">
                    <?= csrf_token_input() ?>
                    
                    <div class="form-group">
                        <label>Kundnamn</label>
                        <input type="text" name="customer_name" required placeholder="t.ex. Fillidutten">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="font-weight: 600; margin-bottom: 0.75rem; display: block;">Milkshakes</label>
                            <div id="milkshakes-container">
                                <?php foreach($milkshakes as $m): ?>
                                    <div class="item-row" data-item-type="milkshake" data-item-id="<?= $m['item_id'] ?>">
                                        <h4><?= htmlspecialchars($m['name']) ?></h4>
                                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem;">
                                            <button type="button" class="qty-btn qty-minus" onclick="adjustQtyModal('m_<?= $m['item_id'] ?>', -1)">−</button>
                                            <input type="number" name="milkshakes[<?= $m['item_id'] ?>]" value="0" min="0" id="m_<?= $m['item_id'] ?>" class="quantity-input" onchange="updateItemComments(this)">
                                            <button type="button" class="qty-btn qty-plus" onclick="adjustQtyModal('m_<?= $m['item_id'] ?>', 1)">+</button>
                                        </div>
                                        <div id="comments-m_<?= $m['item_id'] ?>" class="item-comments" style="display: none;"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <label style="font-weight: 600; margin-bottom: 0.75rem; display: block;">Toasts</label>
                            <div id="toasts-container">
                                <?php foreach($toasts as $t): ?>
                                    <div class="item-row" data-item-type="toast" data-item-id="<?= $t['item_id'] ?>">
                                        <h4><?= htmlspecialchars($t['name']) ?></h4>
                                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem;">
                                            <button type="button" class="qty-btn qty-minus" onclick="adjustQtyModal('t_<?= $t['item_id'] ?>', -1)">−</button>
                                            <input type="number" name="toasts[<?= $t['item_id'] ?>]" value="0" min="0" id="t_<?= $t['item_id'] ?>" class="quantity-input" onchange="updateItemComments(this)">
                                            <button type="button" class="qty-btn qty-plus" onclick="adjustQtyModal('t_<?= $t['item_id'] ?>', 1)">+</button>
                                        </div>
                                        <div id="comments-t_<?= $t['item_id'] ?>" class="item-comments" style="display: none;"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Allmän kommentar</label>
                        <textarea name="order_comment" rows="2" placeholder="Allmänna anteckningar..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="close-btn" onclick="closeCreateOrderModal()" style="background: none; padding: 0.5rem 1rem; border: 1px solid var(--border); border-radius: 8px; font-size: 1rem;">Avbryt</button>
                <button type="submit" form="create-order-form" name="create_order" class="btn" style="width: auto; margin: 0;">Skapa beställning</button>
            </div>
        </div>
    </div>
    
    <script>
        function openCreateOrderModal() {
            document.getElementById('create-order-modal').style.display = 'flex';
        }

        function closeCreateOrderModal() {
            document.getElementById('create-order-modal').style.display = 'none';
            document.getElementById('create-order-form').reset();
        }

        function adjustQtyModal(inputId, delta) {
            const input = document.getElementById(inputId);
            const currentValue = parseInt(input.value) || 0;
            const newValue = Math.max(0, currentValue + delta);
            input.value = newValue;
            updateItemComments(input);
        }

        function updateItemComments(input) {
            // Get the input field's ID and current quantity value
            const inputId = input.id;
            const qty = parseInt(input.value) || 0;
            const commentsContainer = document.getElementById('comments-' + inputId);
            
            // Determine if this is milkshake (m) or toast (t)
            const prefix = inputId.charAt(0);
            const type = prefix === 'm' ? 'milkshake_comments' : 'toast_comments';
            const baseId = inputId.substring(2);
            
            // Get the item name from the item row heading
            const itemRow = input.closest('.item-row');
            const itemName = itemRow ? itemRow.querySelector('h4').textContent : 'Artikel';
            
            // Save existing values before clearing
            const savedValues = {};
            commentsContainer.querySelectorAll('input[type="text"]').forEach(inp => {
                const match = inp.name.match(/\[(.*?)\]/);
                if (match) savedValues[match[1]] = inp.value;
            });
            
            // Clear previous comment fields
            commentsContainer.innerHTML = '';
            
            // If quantity > 0, create note fields for each item
            if (qty > 0) {
                commentsContainer.style.display = 'block';
                // Loop through each unit and create a note field
                for (let i = 0; i < qty; i++) {
                    const commentKey = prefix + '_' + baseId + '_' + i;
                    const div = document.createElement('div');
                    div.style.marginBottom = '0.5rem';
                    
                    // Create input field
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = type + '[' + commentKey + ']';
                    input.value = savedValues[commentKey] || '';
                    input.placeholder = 'Lägg till notering...';
                    input.style.cssText = 'width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; box-sizing: border-box;';
                    
                    // Create label
                    const label = document.createElement('label');
                    label.style.cssText = 'font-size: 0.85rem; color: var(--text-sub); display: block; margin-bottom: 0.25rem;';
                    label.textContent = 'Notering för ' + itemName + ' ' + (i + 1);
                    
                    // Add label and input to container
                    div.appendChild(label);
                    div.appendChild(input);
                    commentsContainer.appendChild(div);
                }
            } else {
                // Hide comments container if quantity is 0
                commentsContainer.style.display = 'none';
            }
        }

        // Close modal if clicking on overlay
        document.getElementById('create-order-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateOrderModal();
            }
        });
        function setView(viewType) {
            const container = document.getElementById('order-container');
            const cardBtn = document.getElementById('card-view-btn');
            const listBtn = document.getElementById('list-view-btn');
            const cards = container.querySelectorAll('.order-card');
            
            // Update button states
            cardBtn.classList.toggle('active', viewType === 'card');
            listBtn.classList.toggle('active', viewType === 'list');
            
            // Update container class
            container.className = viewType === 'card' ? 'order-grid' : 'order-list';
            
            // Update card classes
            cards.forEach(card => {
                card.classList.toggle('list-item', viewType === 'list');
            });
            
            // Save preference
            localStorage.setItem('cashierViewPreference', viewType);
        }
        
        function adjustQuantity(inputId, delta) {
            const input = document.getElementById(inputId);
            const currentValue = parseInt(input.value) || 0;
            const newValue = Math.max(0, currentValue + delta);
            input.value = newValue;
        }
        
        // Load saved preference on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('cashierViewPreference') || 'card';
            setView(savedView);
        });
    </script>
    
</body>
</html>