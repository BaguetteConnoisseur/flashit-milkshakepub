<?php
/* --- 1. Bar Display View Bootstrap --- */
require_once(__DIR__ . '/../../private/initialize.php');

$pdo = db();
$activePubId = isset($_SESSION['active_pub_id']) ? (int)$_SESSION['active_pub_id'] : null;

/* --- 2. Data Fetching + AJAX Partial --- */

if (isset($_GET['fetch_view'])) {
    // Fetch orders with pre-aggregated item status counters using order_items/menu_items
    $query = "
        SELECT
            o.order_id,
            o.order_number,
            o.customer_name,
            o.status AS order_status,
            o.created_at
        FROM orders o
        WHERE o.event_id = :event_id
          AND o.created_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
        ORDER BY o.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['event_id' => $activePubId]);
    $orders = $stmt->fetchAll();


    // Buckets
    $preparing = [];
    $inProgress = [];
    $doneDelivered = [];

    foreach ($orders as $o) {
        $status = strtolower($o['order_status']);
        if ($status === 'pending' || $status === 'received') {
            $o['display_status'] = ucfirst($status) === 'Received' ? 'Preparing' : ucfirst($status);
            $preparing[] = $o;
        } elseif ($status === 'in progress') {
            $o['display_status'] = 'In-progress';
            $inProgress[] = $o;
        } elseif ($status === 'done' || $status === 'delivered') {
            $o['display_status'] = ucfirst($status);
            $doneDelivered[] = $o;
        }
    }

    /* --- 3. Render HTML Fragments --- */
    
    // 1. PREPARING COLUMN
    echo '<div id="col-preparing">';
    if (empty($preparing)) {
         echo '<div class="empty-msg">No orders preparing</div>';
    } else {
        foreach ($preparing as $o) {
            $displayOrderNumber = $o['order_number'] ?? $o['order_id'];
            echo '<div class="card card-preparing">
                    <div class="row-top">
                        <span class="ord-num">#' . htmlspecialchars($displayOrderNumber) . '</span>
                        <span class="status-pill badge-prep">' . $o['display_status'] . '</span>
                    </div>
                    <div class="cust-name">' . htmlspecialchars($o['customer_name']) . '</div>
                  </div>';
        }
    }
    echo '</div>';

    // 2. IN-PROGRESS COLUMN
    echo '<div id="col-inprogress">';
    if (empty($inProgress)) {
         echo '<div class="empty-msg">No orders in progress</div>';
    } else {
        foreach ($inProgress as $o) {
            $displayOrderNumber = $o['order_number'] ?? $o['order_id'];
            echo '<div class="card card-inProgress">
                    <div class="row-top">
                        <span class="ord-num">#' . htmlspecialchars($displayOrderNumber) . '</span>
                        <span class="status-pill badge-cook">' . $o['display_status'] . '</span>
                    </div>
                    <div class="cust-name">' . htmlspecialchars($o['customer_name']) . '</div>
                  </div>';
        }
    }
    echo '</div>';

    // 3. DONE/DELIVERED COLUMN
    echo '<div id="col-done-delivered">';
    if (empty($doneDelivered)) {
         echo '<div class="empty-msg">No completed orders</div>';
    } else {
        foreach ($doneDelivered as $o) {
            $badgeClass = ($o['display_status'] === 'Done') ? 'badge-done' : 'badge-del';
            $extraClass = ($o['display_status'] === 'Delivered') ? ' delivered' : '';
            $statusKey = strtolower($o['display_status']);
            $displayOrderNumber = $o['order_number'] ?? $o['order_id'];
            
            echo '<div class="card card-doneDelivered' . $extraClass . '" data-order-id="' . htmlspecialchars($o['order_id']) . '" data-display-status="' . htmlspecialchars($statusKey) . '">
                    <div class="row-top">
                        <span class="ord-num">#' . htmlspecialchars($displayOrderNumber) . '</span>
                        <span class="status-pill ' . $badgeClass . '">' . $o['display_status'] . '</span>
                    </div>
                    <div class="cust-name">' . htmlspecialchars($o['customer_name']) . '</div>
                  </div>';
        }
    }
    echo '</div>';

    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Display</title>
    <style>
        /* --- 4. Layout & Theme --- */
        :root {
            /* Light Mode Palette */
            --bg: #f3f4f6;          /* Light Grey Background */
            --header-bg: #ffffff;   /* White Header */
            --column-border: #e5e7eb;
            
            --text-main: #1f2937;   /* Dark Grey Text */
            --text-sub: #6b7280;    /* Light Grey Text */
            
            --card-bg: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            
            /* Status Colors */
            --color-recv: #6b7280;  /* Grey for Received */
            --color-prep: #3b82f6;  /* Blue for Preparing */
            --color-cook: #f59e0b;  /* Orange for Cooking */
            --color-ready: #f59e0b; /* Orange for Ready */
            --color-done: #22c55e;  /* Green for Done */
            --color-del: #9ca3af;   /* Light Grey for Delivered */
        }

        body {
            margin: 0;
            background-color: var(--bg);
            color: var(--text-main);
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Top Header */
        header {
            background: var(--header-bg);
            padding: 1.5rem 2rem;
            text-align: center;
            border-bottom: 1px solid var(--column-border);
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 800;
            font-size: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            color: var(--text-main);
        }

        /* Main Grid */
        .display-board {
            display: grid;
            grid-template-columns: 1fr 1.2fr 1fr; /* Center column slightly wider */
            height: 100%;
            overflow: hidden;
        }

        /* Columns */
        .column {
            padding: 1.5rem;
            border-right: 1px dashed var(--column-border);
            display: flex;
            flex-direction: column;
            background: var(--bg);
        }
        .column:last-child { border-right: none; }

        .col-header {
            font-size: 1.25rem;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--column-border);
            color: var(--text-sub);
        }

        /* Middle Column Emphasis */
        .col-center { background: #ffffff; border-left: 1px solid var(--column-border); border-right: 1px solid var(--column-border); }
        .col-center .col-header { color: var(--color-done); border-bottom-color: var(--color-done); }

        /* List Container */
        .order-list {
            flex-grow: 1;
            overflow-y: hidden; 
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .empty-msg { text-align: center; color: var(--text-sub); opacity: 0.6; margin-top: 2rem; font-style: italic; }

        /* Cards */
        .card {
            background: var(--card-bg);
            padding: 1.25rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border: 1px solid #e5e7eb;
            position: relative;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .row-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .ord-num { font-family: monospace; font-size: 1.1rem; color: var(--text-sub); font-weight: 600; }
        .cust-name { font-size: 1.6rem; font-weight: 800; color: var(--text-main); line-height: 1.2; }
        
        .status-pill { font-size: 0.75rem; text-transform: uppercase; font-weight: bold; padding: 4px 10px; border-radius: 99px; }

        /* --- 5. Specific Styles per status --- */

        /* 1. Preparing (Received / Preparing) */
        .card-preparing { border-left: 4px solid var(--color-prep); }
        .badge-recv { background: #f3f4f6; color: var(--color-recv); }
        .badge-prep { background: #eff6ff; color: var(--color-prep); }

        /* 2. In Progress (Cooking / Ready) */
        .card-inProgress { border-left: 4px solid var(--color-cook); }
        .badge-cook { background: #fef3c7; color: var(--color-cook); }
        .badge-ready { background: #fef3c7; color: var(--color-ready); }

        /* 3. Done & Delivered (Done / Delivered) */
        .card-doneDelivered { 
            border-left: 6px solid var(--color-done); 
            box-shadow: 0 10px 15px -3px rgba(34, 197, 94, 0.15);
        }
        .card-doneDelivered .cust-name { font-size: 2rem; color: #000; }
        .badge-done { background: #dcfce7; color: #166534; font-size: 0.9rem; padding: 6px 12px; }
        .badge-del { background: #f3f4f6; color: var(--color-del); }
        

        /* Delivered styling within Done & Delivered */
        .card-doneDelivered.delivered { opacity: 0.6; background: #f9fafb; }
        .card-doneDelivered.delivered .cust-name { text-decoration: line-through; color: var(--text-sub); }

    </style>
</head>
<body>

    <header>
        Wait List
    </header>
    <div class="display-board">
        <div class="column">
            <div class="col-header">RECEIVED</div>
            <div id="list-preparing" class="order-list"></div>
        </div>
        <div class="column col-center">
            <div class="col-header">In-progress</div>
            <div id="list-inprogress" class="order-list"></div>
        </div>
        <div class="column">
            <div class="col-header">Done/Delivered</div>
            <div id="list-done-delivered" class="order-list"></div>
        </div>
    </div>

    <script src="/assets/js/ws.js"></script>
    <script>
        const DELIVERY_HIDE_DELAY_MS = 10000;
        const deliveredFirstSeen = new Map();

        function applyDeliveredGracePeriod() {
            const doneList = document.getElementById('list-done-delivered');
            if (!doneList) return;

            const now = Date.now();
            const currentDeliveredIds = new Set();
            const deliveredCards = doneList.querySelectorAll('.card-doneDelivered.delivered');

            deliveredCards.forEach(card => {
                const orderId = card.dataset.orderId;
                if (!orderId) return;

                currentDeliveredIds.add(orderId);

                if (!deliveredFirstSeen.has(orderId)) {
                    deliveredFirstSeen.set(orderId, now);
                    return;
                }

                const firstSeenAt = deliveredFirstSeen.get(orderId);
                if (now - firstSeenAt >= DELIVERY_HIDE_DELAY_MS) {
                    card.remove();
                }
            });

            for (const orderId of Array.from(deliveredFirstSeen.keys())) {
                if (!currentDeliveredIds.has(orderId)) {
                    deliveredFirstSeen.delete(orderId);
                }
            }

            if (!doneList.querySelector('.card')) {
                doneList.innerHTML = '<div class="empty-msg">No completed orders</div>';
            }
        }

        // Fetch and update the three columns using the bar-view.php AJAX partial
        async function loadOrders() {
            try {
                const r = await fetch(window.location.pathname + '?fetch_view=1');
                const html = await r.text();
                // Create a temporary container to parse the returned HTML
                const temp = document.createElement('div');
                temp.innerHTML = html;
                // Replace each column by id
                const colPreparing = document.getElementById('list-preparing');
                const colInProgress = document.getElementById('list-inprogress');
                const colDoneDelivered = document.getElementById('list-done-delivered');
                const newPreparing = temp.querySelector('#col-preparing');
                const newInProgress = temp.querySelector('#col-inprogress');
                const newDoneDelivered = temp.querySelector('#col-done-delivered');
                if (colPreparing && newPreparing) colPreparing.innerHTML = newPreparing.innerHTML;
                if (colInProgress && newInProgress) colInProgress.innerHTML = newInProgress.innerHTML;
                if (colDoneDelivered && newDoneDelivered) colDoneDelivered.innerHTML = newDoneDelivered.innerHTML;
                applyDeliveredGracePeriod();
            } catch (e) {
                console.error('Failed to load orders:', e);
            }
        }

        // Initial load
        document.addEventListener('DOMContentLoaded', loadOrders);
        // Run grace period check every second
        setInterval(applyDeliveredGracePeriod, 10000);
    </script>
