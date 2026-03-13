<?php
/* --- 1. Delivery Station View Bootstrap --- */

require_once("../../private/initialize.php");
require(PRIVATE_PATH . "/core/db-connection.php");
require(PRIVATE_PATH . "/core/schema-bootstrap.php");

require_login();

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$pubTracking = ensure_pub_tracking($conn);
$activePubId = (int) $pubTracking['active_pub_id'];

function localize_status_label($status) {
    $map = [
        'Pending' => 'Väntar',
        'Received' => 'Mottagen',
        'In Progress' => 'Pågår',
        'Done' => 'Klar',
        'Delivered' => 'Levererad',
    ];

    return $map[$status] ?? $status;
}

/* --- 2. Actions (POST) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    // 1. Deliver Entire Order
    if (isset($_POST['deliver_all'])) {
        $order_id = intval($_POST['order_id']);

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'Delivered' WHERE order_id = ? AND event_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $activePubId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE order_milkshakes om JOIN orders o ON o.order_id = om.order_id SET om.status = 'Delivered' WHERE om.order_id = ? AND o.event_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $activePubId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE order_toasts ot JOIN orders o ON o.order_id = ot.order_id SET ot.status = 'Delivered' WHERE ot.order_id = ? AND o.event_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $activePubId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 2. Deliver Individual Milkshake
    if (isset($_POST['deliver_milkshake'])) {
        $id = intval($_POST['item_id']);
        
        $stmt = mysqli_prepare($conn, "UPDATE order_milkshakes om JOIN orders o ON o.order_id = om.order_id SET om.status = 'Delivered' WHERE om.order_milkshake_id = ? AND o.event_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $activePubId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $stmt = mysqli_prepare($conn, "UPDATE orders o SET o.status = 'Delivered' WHERE o.order_id = (SELECT om.order_id FROM order_milkshakes om JOIN orders ox ON ox.order_id = om.order_id WHERE om.order_milkshake_id = ? AND ox.event_id = ? LIMIT 1) AND NOT EXISTS (SELECT 1 FROM order_milkshakes om2 WHERE om2.order_id = o.order_id AND om2.status <> 'Delivered') AND NOT EXISTS (SELECT 1 FROM order_toasts ot2 WHERE ot2.order_id = o.order_id AND ot2.status <> 'Delivered')");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $activePubId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 3. Deliver Individual Toast
    if (isset($_POST['deliver_toast'])) {
        $id = intval($_POST['item_id']);
        
        $stmt = mysqli_prepare($conn, "UPDATE order_toasts ot JOIN orders o ON o.order_id = ot.order_id SET ot.status = 'Delivered' WHERE ot.order_toast_id = ? AND o.event_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $activePubId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $stmt = mysqli_prepare($conn, "UPDATE orders o SET o.status = 'Delivered' WHERE o.order_id = (SELECT ot.order_id FROM order_toasts ot JOIN orders ox ON ox.order_id = ot.order_id WHERE ot.order_toast_id = ? AND ox.event_id = ? LIMIT 1) AND NOT EXISTS (SELECT 1 FROM order_milkshakes om2 WHERE om2.order_id = o.order_id AND om2.status <> 'Delivered') AND NOT EXISTS (SELECT 1 FROM order_toasts ot2 WHERE ot2.order_id = o.order_id AND ot2.status <> 'Delivered')");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $activePubId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 4. Take Back Delivered Order (Revert to Done)
    if (isset($_POST['take_back_order'])) {
        $order_id = intval($_POST['order_id']);

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'Done' WHERE order_id = ? AND event_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $activePubId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE order_milkshakes om JOIN orders o ON o.order_id = om.order_id SET om.status = 'Done' WHERE om.order_id = ? AND o.event_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $activePubId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE order_toasts ot JOIN orders o ON o.order_id = ot.order_id SET ot.status = 'Done' WHERE ot.order_id = ? AND o.event_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $activePubId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

/* --- 3. Data Fetching Helpers --- */
function getOrders($conn, $activePubId) {
    // 1. Fetch ALL Orders (Active first, then Delivered. Within those groups, oldest first)
    // We add a limit (e.g., 50) to prevent the page from crashing after a year of usage.
    $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE event_id = ? ORDER BY CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END, created_at ASC LIMIT 50");
    mysqli_stmt_bind_param($stmt, 'i', $activePubId);
    mysqli_stmt_execute($stmt);
    $orders = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    if (empty($orders)) return [];

    // Get IDs for fetching items
    $order_ids = array_column($orders, 'order_id');
    $order_ids = array_map('intval', $order_ids);

    if (empty($order_ids)) return [];

    // Create placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $types = str_repeat('i', count($order_ids) + 1);

    // 2. Fetch Milkshakes for these orders
    $stmt = mysqli_prepare($conn, "SELECT om.*, m.name FROM order_milkshakes om JOIN milkshakes m ON om.milkshake_id = m.milkshake_id JOIN orders o ON o.order_id = om.order_id WHERE o.event_id = ? AND om.order_id IN ($placeholders)");
    mysqli_stmt_bind_param($stmt, $types, $activePubId, ...$order_ids);
    mysqli_stmt_execute($stmt);
    $milkshakes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    // 3. Fetch Toasts for these orders
    $stmt = mysqli_prepare($conn, "SELECT ot.*, t.name FROM order_toasts ot JOIN toasts t ON ot.toast_id = t.toast_id JOIN orders o ON o.order_id = ot.order_id WHERE o.event_id = ? AND ot.order_id IN ($placeholders)");
    mysqli_stmt_bind_param($stmt, $types, $activePubId, ...$order_ids);
    mysqli_stmt_execute($stmt);
    $toasts = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $milkshakesByOrder = [];
    foreach ($milkshakes as $m) {
        $orderId = (int) $m['order_id'];
        $m['type'] = 'milkshake';
        $milkshakesByOrder[$orderId][] = $m;
    }

    $toastsByOrder = [];
    foreach ($toasts as $t) {
        $orderId = (int) $t['order_id'];
        $t['type'] = 'toast';
        $toastsByOrder[$orderId][] = $t;
    }

    // 4. Attach items to orders
    foreach ($orders as &$order) {
        $orderId = (int) $order['order_id'];
        $orderMilkshakes = $milkshakesByOrder[$orderId] ?? [];
        $orderToasts = $toastsByOrder[$orderId] ?? [];
        $order['items'] = array_merge($orderMilkshakes, $orderToasts);
        $order['ready_to_serve'] = true; // Assume ready
        $order['has_items'] = !empty($order['items']);
        $order['is_fully_delivered'] = false;

        foreach ($order['items'] as $item) {
            if ($item['status'] !== 'Done' && $item['status'] !== 'Delivered') {
                $order['ready_to_serve'] = false;
            }
        }
        
        if (!$order['has_items']) {
            $order['ready_to_serve'] = false;
        }

        $allDelivered = $order['has_items'];
        foreach ($order['items'] as $item) {
            if ($item['status'] !== 'Delivered') {
                $allDelivered = false;
                break;
            }
        }

        $order['is_fully_delivered'] = $allDelivered;
    }

    usort($orders, function ($a, $b) {
        $priorityA = $a['is_fully_delivered'] ? 2 : ($a['ready_to_serve'] ? 0 : 1);
        $priorityB = $b['is_fully_delivered'] ? 2 : ($b['ready_to_serve'] ? 0 : 1);

        if ($priorityA !== $priorityB) {
            return $priorityA <=> $priorityB;
        }

        return strtotime($a['created_at']) <=> strtotime($b['created_at']);
    });

    return $orders;
}

/* --- 4. AJAX Partial Renderer --- */
if (isset($_GET['fetch_view'])) {
    $orders = getOrders($conn, $activePubId);
    
    if (empty($orders)) {
        echo '<div class="empty-state"><h2>Inga beställningar hittades.</h2></div>';
    } else {
        foreach($orders as $o) {
            $isDelivered = $o['is_fully_delivered'];
            $isReady = $o['ready_to_serve'];
            
            // Determine Card Class
            $cardClass = '';
            if ($isDelivered) {
                $cardClass = 'card-completed';
            } elseif ($isReady) {
                $cardClass = 'card-ready';
            }
            ?>
            <div class="ticket-card <?= $cardClass ?>">
                <div class="card-header">
                    <div class="meta">
                        <span>#<?= htmlspecialchars($o['pub_order_number'] ?? $o['order_number'] ?? $o['order_id']) ?></span>
                        <span><?= date("H:i", strtotime($o['created_at'])) ?></span>
                    </div>
                    <div class="customer">
                        <?= htmlspecialchars($o['customer_name']) ?>
                        <?php if($isDelivered): ?> <span style="font-size:0.8rem; opacity:0.6;">(Levererad)</span><?php endif; ?>
                    </div>
                    <?php if(!empty($o['order_comment'])): ?>
                        <div class="order-note">📝 <?= htmlspecialchars($o['order_comment']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <?php foreach($o['items'] as $item): 
                        $isItemReady = ($item['status'] == 'Done');
                        $isItemDelivered = ($item['status'] == 'Delivered');
                        $itemClass = $isItemDelivered ? 'item-delivered' : ($isItemReady ? 'item-done' : 'item-pending');
                        $icon = $item['type'] == 'milkshake' ? '🥤' : '🥪';
                    ?>
                        <div class="item-row <?= $itemClass ?>">
                            <div class="item-info">
                                <span class="item-icon"><?= $icon ?></span>
                                <div>
                                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <?php if(!empty($item['comment'])): ?>
                                        <div class="item-comment">⚠️ <?= htmlspecialchars($item['comment']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="item-status">
                                <?php if (!$isItemDelivered): ?>
                                    <span class="status-text"><?= localize_status_label($item['status']) ?></span>
                                    
                                    <?php if ($isItemReady && !$isDelivered): ?>
                                        <form method="POST" style="display:inline;">
                                            <?= csrf_token_input() ?>
                                            <input type="hidden" name="item_id" value="<?= $item['type'] == 'milkshake' ? $item['order_milkshake_id'] : $item['order_toast_id'] ?>">
                                            <button type="submit" name="deliver_<?= $item['type'] ?>" class="btn-mini">✓</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>Levererad</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!$isDelivered): ?>
                    <div class="card-footer">
                        <form method="POST">
                            <?= csrf_token_input() ?>
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <?php if ($isReady): ?>
                                <button type="submit" name="deliver_all" class="btn-main btn-success">
                                    LEVERERA BESTÄLLNING
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-main btn-disabled" disabled>
                                    Väntar på köket...
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="card-footer">
                        <form method="POST" style="display: flex; justify-content: flex-end;">
                            <?= csrf_token_input() ?>
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <button type="submit" name="take_back_order" class="btn-take-back" title="Återta denna beställning">
                                ↶
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leveransstation</title>
    <link rel="icon" href="../img/logo/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="../img/logo/favicon.png" type="image/png">
    <style>
        /* --- 5. Layout & Theme --- */
        :root {
            --bg: #f3f4f6; 
            --bg-light: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --accent: #6366f1; /* Indigo for Delivery */
            
            --status-pending: #d1d5db;
            --status-progress: #eab308;
            --status-done: #22c55e;
            
            --note-item-bg: #fef9c3;
            --note-item-text: #854d0e;
            --note-order-bg: #dbeafe;
            --note-order-text: #1e40af;
        }

        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 0; background: linear-gradient(180deg, #eef2ff 0%, var(--bg-light) 30%, #eef2ff 100%); color: var(--text-main); padding: 1rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
        
        /* Card Styling */
        .ticket-card { background: var(--card-bg); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: all 0.3s; }
        
        /* -- STATE STYLES -- */
        
        /* 1. Ready to Serve (Green Border) */
        .card-ready { border: 2px solid var(--status-done); box-shadow: 0 10px 15px -3px rgba(34, 197, 94, 0.2); }
        
        /* 2. Completed / Delivered (Greyed Out) */
        .card-completed { opacity: 0.6; background: #f9fafb; border-color: transparent; box-shadow: none; filter: grayscale(80%); }
        .card-completed:hover { opacity: 0.8; }

        .card-header { padding: 1.25rem; border-bottom: 1px solid #f3f4f6; background: #f9fafb; }
        .meta { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-sub); text-transform: uppercase; margin-bottom: 0.5rem; }
        .customer { font-size: 1.25rem; font-weight: 700; }
        .order-note { margin-top: 0.5rem; background: var(--note-order-bg); color: var(--note-order-text); padding: 0.5rem; border-radius: 6px; font-size: 0.9rem; }

        .card-body { padding: 0; flex-grow: 1; }
        
        /* Item Rows */
        .item-row { padding: 1rem 1.25rem; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
        .item-row:last-child { border-bottom: none; }
        
        /* Item Status coloring */
        .item-delivered { opacity: 0.5; background: #f9fafb; text-decoration: line-through; }
        .item-pending .status-text { color: var(--text-sub); font-style: italic; }
        .item-done .item-name { color: var(--status-done); font-weight: 600; }

        .item-info { display: flex; gap: 0.75rem; align-items: center; }
        .item-icon { font-size: 1.2rem; }
        .item-name { font-size: 1rem; }
        .item-comment { font-size: 0.8rem; background: var(--note-item-bg); color: var(--note-item-text); padding: 0.2rem 0.4rem; border-radius: 4px; margin-top: 0.25rem; display: inline-block; }

        .item-status { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; }
        
        /* Mini button for individual delivery */
        .btn-mini { background: var(--status-done); color: white; border: none; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; }
        .btn-mini:hover { background: #16a34a; }

        /* Footer */
        .card-footer { padding: 1.25rem; background: #f9fafb; border-top: 1px solid #e5e7eb; }
        .btn-main { width: 100%; padding: 1rem; border-radius: 8px; border: none; font-weight: 700; font-size: 1rem; cursor: pointer; transition: transform 0.1s; }
        .btn-success { background: var(--status-done); color: white; box-shadow: 0 4px 6px rgba(34, 197, 94, 0.3); }
        .btn-success:hover { background: #16a34a; transform: translateY(-2px); }
        .btn-disabled { background: #e5e7eb; color: #9ca3af; cursor: not-allowed; }

        /* Take back button - subtle */
        .btn-take-back { 
            background: transparent; 
            color: #9ca3af; 
            border: 1px solid #e5e7eb; 
            width: 32px; 
            height: 32px; 
            border-radius: 4px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.2rem; 
            transition: all 0.2s; 
        }
        .btn-take-back:hover { 
            background: #f3f4f6; 
            color: #6b7280; 
            border-color: #d1d5db; 
        }

        #connection-status { font-size: 0.8rem; color: #10b981; }
        .empty-state { text-align: center; color: var(--text-sub); margin-top: 4rem; width: 100%; }
    </style>
</head>
<body>
    <?php require(SHARED_PATH . "/admin_navbar.php"); ?>

    <div class="header">
        <h1>📦 Leveransstation</h1>
        <div id="connection-status">● Live</div>
    </div>

    <div id="ticket-grid" class="grid">
        <div style="grid-column: 1/-1; text-align: center; color: var(--text-sub);">Laddar beställningar...</div>
    </div>
    <?php include(SHARED_PATH . "/public_footer.php"); ?>
    <script>
        function loadOrders() {
            fetch('?fetch_view=1')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('ticket-grid').innerHTML = html;
                    document.getElementById('connection-status').style.color = '#10b981';
                })
                .catch(err => {
                    console.error('Error fetching orders:', err);
                    document.getElementById('connection-status').style.color = '#ef4444';
                });
        }
        loadOrders();
        setInterval(loadOrders, 3000); 
    <script src="../js/live-updater.js"></script>
    <script>
        window.createLiveUpdater({
            wsUrl: window.WS_URL || (function() {
                var port = window.WS_PORT || '';
                var proto = location.protocol === 'https:' ? 'wss://' : 'ws://';
                var host = location.hostname;
                return port ? proto + host + ':' + port : proto + host;
            })(),
            statusSelector: '#connection-status',
            statusLabels: {
                live: '\u25cf Live',
                offline: '\u25cf Offline',
                sleeping: '\u25cf Sleeping',

            },
            onData: function (html) {
                document.getElementById('ticket-grid').innerHTML = html;
            },
            onOrderUpdate: function (order) {
                // Find the order card by pub_order_number/order_number/order_id
                var grid = document.getElementById('ticket-grid');
                var orderId = order.pub_order_number || order.order_number || order.order_id;
                var cards = grid.querySelectorAll('.ticket-card');
                var found = false;
                cards.forEach(function(card) {
                    var meta = card.querySelector('.meta span');
                    if (meta && meta.textContent.replace('#', '').trim() == orderId) {
                        // Re-render the card
                        var newCard = document.createElement('div');
                        newCard.className = 'ticket-card';
                        // Optionally add card-completed/card-ready classes
                        var isDelivered = order.is_fully_delivered || order.status === 'Delivered';
                        var isReady = order.ready_to_serve || order.status === 'Done';
                        if (isDelivered) newCard.classList.add('card-completed');
                        else if (isReady) newCard.classList.add('card-ready');

                        // Build card header
                        var header = document.createElement('div');
                        header.className = 'card-header';
                        var metaDiv = document.createElement('div');
                        metaDiv.className = 'meta';
                        metaDiv.innerHTML = '<span>#' + orderId + '</span><span>' + (order.created_at ? new Date(order.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '') + '</span>';
                        header.appendChild(metaDiv);
                        var customerDiv = document.createElement('div');
                        customerDiv.className = 'customer';
                        customerDiv.textContent = order.customer_name || '';
                        if (isDelivered) {
                            var deliveredSpan = document.createElement('span');
                            deliveredSpan.style.fontSize = '0.8rem';
                            deliveredSpan.style.opacity = '0.6';
                            deliveredSpan.textContent = '(Levererad)';
                            customerDiv.appendChild(deliveredSpan);
                        }
                        header.appendChild(customerDiv);
                        if (order.main_comment || order.order_comment) {
                            var noteDiv = document.createElement('div');
                            noteDiv.className = 'order-note';
                            noteDiv.textContent = '📝 ' + (order.main_comment || order.order_comment);
                            header.appendChild(noteDiv);
                        }
                        newCard.appendChild(header);

                        // Build card body
                        var body = document.createElement('div');
                        body.className = 'card-body';
                        (order.items || order.linked_milkshakes || []).forEach(function(item) {
                            var isItemReady = item.status === 'Done';
                            var isItemDelivered = item.status === 'Delivered';
                            var itemClass = isItemDelivered ? 'item-delivered' : (isItemReady ? 'item-done' : 'item-pending');
                            var icon = item.type === 'milkshake' || item.milkshake_name ? '🥤' : '🥪';
                            var row = document.createElement('div');
                            row.className = 'item-row ' + itemClass;
                            var info = document.createElement('div');
                            info.className = 'item-info';
                            var iconSpan = document.createElement('span');
                            iconSpan.className = 'item-icon';
                            iconSpan.textContent = icon;
                            info.appendChild(iconSpan);
                            var nameDiv = document.createElement('div');
                            var name = document.createElement('div');
                            name.className = 'item-name';
                            name.textContent = item.name || item.toast_name || item.milkshake_name || '';
                            nameDiv.appendChild(name);
                            if (item.comment || item.item_comment) {
                                var commentDiv = document.createElement('div');
                                commentDiv.className = 'item-comment';
                                commentDiv.textContent = '⚠️ ' + (item.comment || item.item_comment);
                                nameDiv.appendChild(commentDiv);
                            }
                            info.appendChild(nameDiv);
                            row.appendChild(info);
                            var statusDiv = document.createElement('div');
                            statusDiv.className = 'item-status';
                            if (!isItemDelivered) {
                                var statusText = document.createElement('span');
                                statusText.className = 'status-text';
                                statusText.textContent = item.status;
                                statusDiv.appendChild(statusText);
                            } else {
                                statusDiv.innerHTML = '<span>Levererad</span>';
                            }
                            row.appendChild(statusDiv);
                            body.appendChild(row);
                        });
                        newCard.appendChild(body);

                        // Replace the old card
                        card.parentNode.replaceChild(newCard, card);
                        found = true;
                    }
                });
                // Optionally, if not found, add new card
                if (!found) {
                    // Optionally append new order card
                }
            },
            onError: function (err) {
                console.error('Kunde inte hämta beställningar:', err);
            },
        });
    </script>
</body>
</html>