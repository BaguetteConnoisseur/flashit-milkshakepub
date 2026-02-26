<?php
require_once("../../private/initalize.php");
require(PRIVATE_PATH . "/master_code/db-conn.php");

if (isset($_POST['logout-account'])) {
    session_destroy();  
    header("Location: " . WWW_ROOT . "/index.php");
    exit;
}

if (!$loggedIn) {
    header("Location: " . WWW_ROOT . "/index.php");
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// --- LOGIC: HANDLE ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Deliver Entire Order
    if (isset($_POST['deliver_all'])) {
        $order_id = intval($_POST['order_id']);
        
        // Mark main order as Delivered
        mysqli_query($conn, "UPDATE orders SET status = 'Delivered' WHERE order_id = $order_id");
        
        // Mark all children as Delivered
        mysqli_query($conn, "UPDATE order_milkshakes SET status = 'Delivered' WHERE order_id = $order_id");
        mysqli_query($conn, "UPDATE order_toasts SET status = 'Delivered' WHERE order_id = $order_id");

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 2. Deliver Individual Milkshake
    if (isset($_POST['deliver_milkshake'])) {
        $id = intval($_POST['item_id']);
        mysqli_query($conn, "UPDATE order_milkshakes SET status = 'Delivered' WHERE order_milkshake_id = $id");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 3. Deliver Individual Toast
    if (isset($_POST['deliver_toast'])) {
        $id = intval($_POST['item_id']);
        mysqli_query($conn, "UPDATE order_toasts SET status = 'Delivered' WHERE order_toast_id = $id");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 4. Take Back Delivered Order (Revert to Done)
    if (isset($_POST['take_back_order'])) {
        $order_id = intval($_POST['order_id']);
        
        // Mark main order as Done (not Delivered)
        mysqli_query($conn, "UPDATE orders SET status = 'Done' WHERE order_id = $order_id");
        
        // Mark all children as Done (not Delivered)
        mysqli_query($conn, "UPDATE order_milkshakes SET status = 'Done' WHERE order_id = $order_id");
        mysqli_query($conn, "UPDATE order_toasts SET status = 'Done' WHERE order_id = $order_id");

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- LOGIC: FETCH DATA FUNCTION ---
function getOrders($conn) {
    // 1. Fetch ALL Orders (Active first, then Delivered. Within those groups, oldest first)
    // We add a limit (e.g., 50) to prevent the page from crashing after a year of usage.
    $query = "
        SELECT * FROM orders 
        ORDER BY 
            CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END, 
            created_at ASC
        LIMIT 50
    ";
    $result = mysqli_query($conn, $query);
    $orders = mysqli_fetch_all($result, MYSQLI_ASSOC);

    if (empty($orders)) return [];

    // Get IDs for fetching items
    $order_ids = array_column($orders, 'order_id');
    $id_list = implode(',', array_map('intval', $order_ids));

    if (empty($id_list)) return [];

    // 2. Fetch Milkshakes for these orders
    $sql_m = "SELECT om.*, m.name 
              FROM order_milkshakes om 
              JOIN milkshakes m ON om.milkshake_id = m.milkshake_id 
              WHERE om.order_id IN ($id_list)";
    $res_m = mysqli_query($conn, $sql_m);
    $milkshakes = mysqli_fetch_all($res_m, MYSQLI_ASSOC);

    // 3. Fetch Toasts for these orders
    $sql_t = "SELECT ot.*, t.name 
              FROM order_toasts ot 
              JOIN toasts t ON ot.toast_id = t.toast_id 
              WHERE ot.order_id IN ($id_list)";
    $res_t = mysqli_query($conn, $sql_t);
    $toasts = mysqli_fetch_all($res_t, MYSQLI_ASSOC);

    // 4. Attach items to orders
    foreach ($orders as &$order) {
        $order['items'] = [];
        $order['ready_to_serve'] = true; // Assume ready
        $order['has_items'] = false;
        $order['is_fully_delivered'] = ($order['status'] === 'Delivered');

        // Attach Milkshakes
        foreach ($milkshakes as $m) {
            if ($m['order_id'] == $order['order_id']) {
                $m['type'] = 'milkshake';
                $order['items'][] = $m;
                $order['has_items'] = true;
                
                // If any item is NOT Done and NOT Delivered, order isn't ready
                if ($m['status'] != 'Done' && $m['status'] != 'Delivered') {
                    $order['ready_to_serve'] = false;
                }
            }
        }

        // Attach Toasts
        foreach ($toasts as $t) {
            if ($t['order_id'] == $order['order_id']) {
                $t['type'] = 'toast';
                $order['items'][] = $t;
                $order['has_items'] = true;
                if ($t['status'] != 'Done' && $t['status'] != 'Delivered') {
                    $order['ready_to_serve'] = false;
                }
            }
        }
        
        if (!$order['has_items']) $order['ready_to_serve'] = false;
    }

    return $orders;
}

// --- AJAX HANDLER ---
if (isset($_GET['fetch_view'])) {
    $orders = getOrders($conn);
    
    if (empty($orders)) {
        echo '<div class="empty-state"><h2>No orders found.</h2></div>';
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
                        <span>#<?= $o['order_id'] ?></span>
                        <span><?= date("H:i", strtotime($o['created_at'])) ?></span>
                    </div>
                    <div class="customer">
                        <?= htmlspecialchars($o['customer_name']) ?>
                        <?php if($isDelivered): ?> <span style="font-size:0.8rem; opacity:0.6;">(Delivered)</span><?php endif; ?>
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
                                    <span class="status-text"><?= $item['status'] ?></span>
                                    
                                    <?php if ($isItemReady && !$isDelivered): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="item_id" value="<?= $item['type'] == 'milkshake' ? $item['order_milkshake_id'] : $item['order_toast_id'] ?>">
                                            <button type="submit" name="deliver_<?= $item['type'] ?>" class="btn-mini">✓</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>Delivered</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!$isDelivered): ?>
                    <div class="card-footer">
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <?php if ($isReady): ?>
                                <button type="submit" name="deliver_all" class="btn-main btn-success">
                                    DELIVER ORDER
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-main btn-disabled" disabled>
                                    Waiting for Kitchen...
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="card-footer">
                        <form method="POST" style="display: flex; justify-content: flex-end;">
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <button type="submit" name="take_back_order" class="btn-take-back" title="Take back this order">
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Station</title>
    <link rel="icon" href="../img/logo/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="../img/logo/favicon.png" type="image/png">
    <style>
        :root {
            --bg: #f3f4f6; 
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

        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: var(--bg); color: var(--text-main); padding: 1rem; }
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
        <h1>📦 Delivery Station</h1>
        <div id="connection-status">● Live</div>
    </div>

    <div id="ticket-grid" class="grid">
        <div style="grid-column: 1/-1; text-align: center; color: var(--text-sub);">Loading orders...</div>
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
    </script>
</body>
</html>