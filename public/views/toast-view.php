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

// --- LOGIC: HANDLE ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Next Step Button
    if (isset($_POST['advance_status'])) {
        $ot_id = intval($_POST['order_toast_id']);
        $current_status = $_POST['current_status'];
        $new_status = $current_status;

        if ($current_status === 'Pending' || $current_status === 'Received') {
            $new_status = 'In Progress';
        } elseif ($current_status === 'In Progress') {
            $new_status = 'Done';
        }

        if ($new_status !== $current_status) {
            mysqli_query($conn, "
                UPDATE order_toasts ot
                JOIN orders o ON o.order_id = ot.order_id
                SET ot.status = '$new_status'
                WHERE ot.order_toast_id = $ot_id AND o.event_id = $activePubId
            ");
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 2. Manual Dropdown Change
    if (isset($_POST['update_status_manual'])) {
        $ot_id = intval($_POST['order_toast_id']);
        $new_status = mysqli_real_escape_string($conn, $_POST['manual_status']);
        mysqli_query($conn, "
            UPDATE order_toasts ot
            JOIN orders o ON o.order_id = ot.order_id
            SET ot.status = '$new_status'
            WHERE ot.order_toast_id = $ot_id AND o.event_id = $activePubId
        ");
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- LOGIC: FETCH DATA FUNCTION ---
function getTickets($conn, $activePubId) {
    // 1. Get the Toasts (Primary Items)
    $query = "
        SELECT 
            ot.order_toast_id,
            ot.status,
            ot.comment AS item_comment,
            ot.order_id,
            t.name AS toast_name,
            o.order_number,
            o.pub_order_number,
            o.customer_name,
            o.created_at,
            o.order_comment AS main_comment
        FROM order_toasts ot
        JOIN toasts t ON ot.toast_id = t.toast_id
        JOIN orders o ON ot.order_id = o.order_id
        WHERE ot.status != 'Delivered' AND o.event_id = $activePubId
        ORDER BY 
            CASE WHEN ot.status = 'Done' THEN 1 ELSE 0 END,
            o.created_at ASC
    ";
    $result = mysqli_query($conn, $query);
    $tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);

    foreach ($tickets as &$ticket) {
        $ticket['linked_milkshakes'] = [];
    }
    unset($ticket);

    // 2. Get associated Milkshakes (Linked Items)
    if (!empty($tickets)) {
        // Extract all order IDs currently on screen
        $order_ids = array_unique(array_column($tickets, 'order_id'));
        $id_list = implode(',', array_map('intval', $order_ids));

        if (!empty($id_list)) {
            // NOTE: We are fetching Milkshakes here to display as context
            $milkshake_query = "
                SELECT om.order_id, m.name AS milkshake_name, om.status
                FROM order_milkshakes om
                JOIN milkshakes m ON om.milkshake_id = m.milkshake_id
                WHERE om.order_id IN ($id_list)
            ";
            $ms_result = mysqli_query($conn, $milkshake_query);
            $all_milkshakes = mysqli_fetch_all($ms_result, MYSQLI_ASSOC);

            $milkshakesByOrder = [];
            foreach ($all_milkshakes as $ms) {
                $orderId = (int) $ms['order_id'];
                $milkshakesByOrder[$orderId][] = $ms;
            }

            foreach ($tickets as &$ticket) {
                $ticket['linked_milkshakes'] = $milkshakesByOrder[(int) $ticket['order_id']] ?? [];
            }
            unset($ticket);
        }
    }

    return $tickets;
}

// --- LOGIC: FETCH SUMMARY FUNCTION ---
function getSummary($conn, $activePubId) {
    $query = "
        SELECT t.name, COUNT(*) as count
        FROM order_toasts ot
        JOIN toasts t ON ot.toast_id = t.toast_id
        JOIN orders o ON o.order_id = ot.order_id
        WHERE ot.status != 'Delivered' AND o.event_id = $activePubId
        GROUP BY t.name
        ORDER BY count DESC
    ";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$summary = getSummary($conn, $activePubId);

// --- AJAX HANDLER ---
if (isset($_GET['fetch_view'])) {
    $tickets = getTickets($conn, $activePubId);
    
    if (empty($tickets)) {
        echo '<div class="empty-state"><h2>Inga väntande toast.</h2></div>';
    } else {
        foreach($tickets as $t) {
            $statusClass = str_replace(' ', '', $t['status']); 
            if($t['status'] == 'In Progress') $statusClass = 'Progress';
            ?>
            <div class="ticket-card bar-ticket-<?= $statusClass ?>">
                <div class="status-bar bar-<?= $statusClass ?>"></div>
                <div class="card-body">
                    <div class="meta">
                        <span>#<?= htmlspecialchars($t['pub_order_number'] ?? $t['order_number'] ?? $t['order_id']) ?></span>
                        <span><?= date("H:i", strtotime($t['created_at'])) ?></span>
                    </div>
                    
                    <div class="item-name">🥪 <?= htmlspecialchars($t['toast_name']) ?></div>
                    
                    <?php if(!empty($t['item_comment'])): ?>
                        <div class="comment-box item-note">
                            <strong>Notering:</strong> <?= htmlspecialchars($t['item_comment']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if(!empty($t['main_comment'])): ?>
                        <div class="comment-box order-note">
                            <strong>Ordernotering:</strong> <?= htmlspecialchars($t['main_comment']) ?>
                        </div>
                    <?php endif; ?>

                    <div style="font-size: 0.9rem; color: var(--text-sub); margin-bottom: 1rem;">
                        Kund: <?= htmlspecialchars($t['customer_name']) ?>
                    </div>

                    <?php if(!empty($t['linked_milkshakes'])): ?>
                        <div class="linked-list">
                            <div class="linked-header">Med milkshakes:</div>
                            <?php foreach($t['linked_milkshakes'] as $ms): ?>
                                <div class="linked-item">
                                    <span>🥤 <?= htmlspecialchars($ms['milkshake_name']) ?></span>
                                    <span style="font-size:0.8em; opacity:0.7;">(<?= localize_status_label($ms['status']) ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
                <div class="controls">
                    <form method="POST" style="flex-grow:1; display:flex; gap:5px;">
                        <input type="hidden" name="order_toast_id" value="<?= $t['order_toast_id'] ?>">
                        <select name="manual_status" onchange="this.form.submit()">
                            <option value="" hidden>Uppdatera</option>
                            <option value="Pending" <?= ($t['status']=='Pending' || $t['status']=='Received') ? 'selected' : '' ?>>Mottagen</option>
                            <option value="In Progress" <?= $t['status']=='In Progress' ? 'selected' : '' ?>>Pågår</option>
                            <option value="Done" <?= $t['status']=='Done' ? 'selected' : '' ?>>Klar</option>
                            <option value="Delivered">Levererad</option>
                        </select>
                        <input type="hidden" name="update_status_manual" value="1">
                    </form>
                    <?php if ($t['status'] !== 'Done'): ?>
                        <form method="POST">
                            <input type="hidden" name="order_toast_id" value="<?= $t['order_toast_id'] ?>">
                            <input type="hidden" name="current_status" value="<?= $t['status'] ?>">
                            <?php if($t['status'] == 'Pending' || $t['status'] == 'Received'): ?>
                                <button type="submit" name="advance_status" class="btn-next">Starta &rarr;</button>
                            <?php elseif($t['status'] == 'In Progress'): ?>
                                <button type="submit" name="advance_status" class="btn-next btn-finish">Klar &#10003;</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
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
    <title>Toast-station</title>
    <link rel="icon" href="../img/logo/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="../img/logo/favicon.png" type="image/png">
    <style>
        :root {
            /* Light Mode Palette */
            --bg: #f3f4f6;          /* Light gray background */
            --card-bg: #ffffff;     /* White cards */
            --text-main: #1f2937;   /* Dark gray text */
            --text-sub: #6b7280;    /* Lighter gray text */
            
            --border: #e5e7eb;      /* Light border color */
            --accent: #f97316;      /* Orange accent for Toasts */
            
            --status-pending: #d1d5db;
            --status-progress: #eab308;
            --status-done: #22c55e;
            
            --note-item-bg: #fef9c3; /* Light Yellow */
            --note-item-text: #854d0e;
            
            --note-order-bg: #dbeafe; /* Light Blue */
            --note-order-text: #1e40af;
        }

        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; 
            background-color: var(--bg); 
            color: var(--text-main); 
            padding: 1rem; 
        }

        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 2rem; 
            border-bottom: 1px solid var(--border); 
            padding-bottom: 1rem; 
        }
        
        .summary {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        
        .summary-item {
            background: var(--card-bg);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 1.5rem; 
        }
        
        .ticket-card { 
            background: var(--card-bg); 
            border-radius: 12px; 
            overflow: hidden; 
            display: flex; 
            flex-direction: column; 
            border: 1px solid var(--border); 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); 
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .status-bar { height: 6px; width: 100%; }
        .bar-Pending, .bar-Received { background-color: var(--status-pending); }
        .bar-Progress { background-color: var(--status-progress); }
        .bar-Done { background-color: var(--status-done); }

        .bar-ticket-Progress {
            border: 20px solid var(--status-progress);
        }
        .bar-ticket-Done {
            border: 20px solid var(--status-done);
        }

        .card-body { padding: 1.5rem; flex-grow: 1; }
        .meta { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-sub); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .item-name { font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.2; }
        
        /* Comments */
        .comment-box { padding: 0.75rem; border-radius: 6px; font-size: 0.95rem; margin-bottom: 0.5rem; }
        .item-note { background: var(--note-item-bg); color: var(--note-item-text); border-left: 4px solid #ca8a04; }
        .order-note { background: var(--note-order-bg); color: var(--note-order-text); border-left: 4px solid #2563eb; }

        /* Linked Items Section */
        .linked-list { 
            background: #f9fafb; /* Light wash */
            border-radius: 6px; 
            padding: 0.75rem; 
            margin-top: 1rem; 
            border: 1px solid var(--border); 
        }
        .linked-header { font-size: 0.75rem; text-transform: uppercase; color: var(--text-sub); margin-bottom: 0.5rem; font-weight: 700; }
        .linked-item { font-size: 0.9rem; margin-bottom: 0.25rem; display: flex; justify-content: space-between; }

        .controls { 
            background: #f9fafb; /* Light wash */
            padding: 1rem; 
            display: flex; 
            gap: 0.5rem; 
            align-items: center; 
            border-top: 1px solid var(--border); 
        }

        /* Updated Select for Light Mode */
        select { 
            background: #ffffff; 
            color: var(--text-main); 
            border: 1px solid #d1d5db; 
            padding: 0.5rem; 
            border-radius: 6px; 
            font-size: 0.9rem; 
            flex-grow: 1; 
        }
        
        .btn-next { background: var(--accent); color: white; border: none; padding: 0.75rem 1.25rem; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 1rem; transition: background 0.2s; white-space: nowrap; }
        .btn-next:hover { opacity: 0.9; }
        .btn-finish { background: var(--status-done); color: white; }
        .btn-finish:hover { opacity: 0.9; }
        
        .empty-state { text-align: center; color: var(--text-sub); margin-top: 4rem; width: 100%; }
        #connection-status { font-size: 0.8rem; color: #10b981; }
    </style>
</head>
<body>
    <?php require(SHARED_PATH . "/admin_navbar.php"); ?>

    <div class="header">
        <div>
            <h1>🥪 Toast-station</h1>
            <?php if (!empty($summary)): ?>
                <div class="summary">
                    <?php foreach ($summary as $item): ?>
                        <span class="summary-item"><?= htmlspecialchars($item['name']) ?>: <?= $item['count'] ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div id="connection-status">● Live</div>
    </div>

    <div id="ticket-grid" class="grid">
        <div style="grid-column: 1/-1; text-align: center; color: var(--text-sub);">Laddar beställningar...</div>
    </div>
    <?php include(SHARED_PATH . "/public_footer.php"); ?>

    <script>
        function loadTickets() {
            fetch('?fetch_view=1')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('ticket-grid').innerHTML = html;
                    document.getElementById('connection-status').style.color = '#10b981';
                })
                .catch(err => {
                    console.error('Kunde inte hämta beställningar:', err);
                    document.getElementById('connection-status').style.color = '#ef4444';
                });
        }
        loadTickets();
        setInterval(loadTickets, 5000);
    </script>
</body>
</html>