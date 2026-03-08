<?php
/* --- 1. Bar Display View Bootstrap --- */

require_once("../../private/initalize.php");
require(PRIVATE_PATH . "/master_code/db-conn.php");
require(PRIVATE_PATH . "/master_code/pub-schema-bootstrap.php");

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$pubTracking = ensure_pub_tracking($conn);
$activePubId = (int) $pubTracking['active_pub_id'];

/* --- 2. Data Fetching + AJAX Partial --- */
if (isset($_GET['fetch_view'])) {
    // Fetch orders with pre-aggregated item status counters (avoids per-order follow-up queries).
    $query = "
        SELECT
            o.order_id,
            o.order_number,
            o.pub_order_number,
            o.customer_name,
            o.status AS order_status,
            o.created_at,
            COALESCE(om_stats.total_count, 0) AS milkshake_total,
            COALESCE(om_stats.delivered_count, 0) AS milkshake_delivered,
            COALESCE(ot_stats.total_count, 0) AS toast_total,
            COALESCE(ot_stats.delivered_count, 0) AS toast_delivered,
            CASE
                WHEN COALESCE(om_stats.not_done_count, 0) = 0
                     AND COALESCE(ot_stats.not_done_count, 0) = 0 THEN 'Done'
                WHEN COALESCE(om_stats.in_progress_count, 0) > 0
                     OR COALESCE(ot_stats.in_progress_count, 0) > 0 THEN 'In Progress'
                WHEN COALESCE(om_stats.received_count, 0) > 0
                     OR COALESCE(ot_stats.received_count, 0) > 0 THEN 'Received'
                ELSE 'Pending'
            END AS calculated_status
        FROM orders o
        LEFT JOIN (
            SELECT
                order_id,
                COUNT(*) AS total_count,
                SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN status = 'Received' THEN 1 ELSE 0 END) AS received_count,
                SUM(CASE WHEN status NOT IN ('Done', 'Delivered') THEN 1 ELSE 0 END) AS not_done_count
            FROM order_milkshakes
            GROUP BY order_id
        ) om_stats ON om_stats.order_id = o.order_id
        LEFT JOIN (
            SELECT
                order_id,
                COUNT(*) AS total_count,
                SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN status = 'Received' THEN 1 ELSE 0 END) AS received_count,
                SUM(CASE WHEN status NOT IN ('Done', 'Delivered') THEN 1 ELSE 0 END) AS not_done_count
            FROM order_toasts
            GROUP BY order_id
        ) ot_stats ON ot_stats.order_id = o.order_id
        WHERE o.event_id = ?
          AND o.created_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
        ORDER BY o.created_at DESC
    ";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $activePubId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orders = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    // Buckets
    $preparing = [];    // Will hold 'Pending' and 'Received'
    $inProgress = [];   // Will hold 'In Progress'
    $doneDelivered = []; // Will hold 'Done' and 'Delivered'

    foreach ($orders as $o) {
        $s = strtolower($o['calculated_status']);
        
        if ($s === 'pending' || $s === 'received') {
            $o['display_status'] = 'Preparing';
            $preparing[] = $o;
        } elseif ($s === 'in progress') {
            $o['display_status'] = 'In-progress';
            $inProgress[] = $o;
        } elseif ($s === 'done') {
            $total_items = ((int) ($o['milkshake_total'] ?? 0)) + ((int) ($o['toast_total'] ?? 0));
            $delivered_items = ((int) ($o['milkshake_delivered'] ?? 0)) + ((int) ($o['toast_delivered'] ?? 0));
            $o['display_status'] = ($delivered_items == $total_items && $total_items > 0) ? 'Delivered' : 'Done';
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
            $displayOrderNumber = $o['pub_order_number'] ?? $o['order_number'] ?? $o['order_id'];
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
            $displayOrderNumber = $o['pub_order_number'] ?? $o['order_number'] ?? $o['order_id'];
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
            $displayOrderNumber = $o['pub_order_number'] ?? $o['order_number'] ?? $o['order_id'];
            
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
            <div id="list-preparing" class="order-list">
                </div>
        </div>

        <div class="column col-center">
            <div class="col-header">In-progress</div>
            <div id="list-inprogress" class="order-list">
                </div>
        </div>

        <div class="column">
            <div class="col-header">Done/Delivered</div>
            <div id="list-done-delivered" class="order-list">
                </div>
        </div>

    </div>

    <script src="../js/live-poller.js"></script>
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

        createLivePoller({
            endpoint: '?fetch_view=1',
            onData: function (html) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                document.getElementById('list-preparing').innerHTML = doc.getElementById('col-preparing').innerHTML;
                document.getElementById('list-inprogress').innerHTML = doc.getElementById('col-inprogress').innerHTML;
                document.getElementById('list-done-delivered').innerHTML = doc.getElementById('col-done-delivered').innerHTML;
            },
            onTick: function () {
                applyDeliveredGracePeriod();
            },
            onError: function (err) {
                console.error('Display update failed', err);
            },
        });
    </script>
</body>
</html>