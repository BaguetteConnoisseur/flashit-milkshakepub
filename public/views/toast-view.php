<?php
/* --- 1. Toast Station View Bootstrap --- */

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

// Function to broadcast the updated order data as JSON to the WebSocket server
function broadcast_order_update($conn, $order_toast_id, $activePubId, $new_status) {
    // Fetch the updated order
    $stmt = mysqli_prepare($conn, "SELECT ot.order_toast_id, ot.status, ot.comment AS item_comment, ot.order_id, t.name AS toast_name, o.order_number, o.pub_order_number, o.customer_name, o.created_at, o.order_comment AS main_comment FROM order_toasts ot JOIN toasts t ON ot.toast_id = t.toast_id JOIN orders o ON ot.order_id = o.order_id WHERE ot.order_toast_id = ? AND o.event_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $order_toast_id, $activePubId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($order) {
        // Optionally fetch linked milkshakes
        $order_id = (int)$order['order_id'];
        $stmt = mysqli_prepare($conn, "SELECT m.name AS milkshake_name, om.status FROM order_milkshakes om JOIN milkshakes m ON om.milkshake_id = m.milkshake_id WHERE om.order_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $order_id);
        mysqli_stmt_execute($stmt);
        $ms_result = mysqli_stmt_get_result($stmt);
        $order['linked_milkshakes'] = mysqli_fetch_all($ms_result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        // Prepare JSON payload
        $payload = [
            'type' => 'order_update',
            'order_id' => $order_toast_id,
            'status' => $new_status,
            'data' => $order
        ];
        $json = json_encode($payload);

        // Use backend HTTP broadcast
        $ch = curl_init('http://localhost:8082/broadcast');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $result = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        error_log('Broadcast payload: ' . $json);
        error_log('Broadcast result: ' . $result);
        if ($curl_error) {
            error_log('Broadcast curl error: ' . $curl_error);
        }
    }
}

/* --- 2. Actions (POST) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    require_csrf_token();
    $input = json_decode(file_get_contents('php://input'), true);
    $ot_id = intval($input['order_id'] ?? 0);
    $new_status = $input['status'] ?? null;
    if ($ot_id && $new_status) {
        $stmt = mysqli_prepare($conn, "UPDATE order_toasts ot JOIN orders o ON o.order_id = ot.order_id SET ot.status = ? WHERE ot.order_toast_id = ? AND o.event_id = ?");
        mysqli_stmt_bind_param($stmt, 'sii', $new_status, $ot_id, $activePubId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        broadcast_order_update($conn, $ot_id, $activePubId, $new_status);
        echo json_encode(['success' => true, 'order_id' => $ot_id, 'status' => $new_status]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

/* --- 3. Data Fetching Helpers --- */
function getTickets($conn, $activePubId) {
    // 1. Get the Toasts (Primary Items)
    $stmt = mysqli_prepare($conn, "SELECT ot.order_toast_id, 
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
        WHERE ot.status != 'Delivered' AND o.event_id = ? 
        ORDER BY CASE WHEN ot.status = 'Done' THEN 1 ELSE 0 END, 
        o.created_at ASC");
    mysqli_stmt_bind_param($stmt, 'i', $activePubId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);
                    if (isset($_GET['ws_test'])) {
                        $payload = [
                            'type' => 'order_update',
                            'order' => ['test' => 'manual', 'timestamp' => time()]
                        ];
                        $json = json_encode($payload);
                        $sock = @fsockopen('127.0.0.1', 8081, $errno, $errstr, 1);
                        if ($sock) {
                            fwrite($sock, $json . "\n");
                            fclose($sock);
                            echo 'Test payload sent.';
                        } else {
                            echo 'Failed to connect to WebSocket server.';
                        }
                        exit;
                    }

    foreach ($tickets as &$ticket) {
        $ticket['linked_milkshakes'] = [];
    }
    unset($ticket);

    // 2. Get associated Milkshakes (Linked Items)
    if (!empty($tickets)) {
        // Extract all order IDs currently on screen
        $order_ids = array_unique(array_column($tickets, 'order_id'));
        $order_ids = array_map('intval', $order_ids);

        if (!empty($order_ids)) {
            // Fetch related milkshakes only for visible orders to give kitchen context without extra full-table scans.
            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
            $types = str_repeat('i', count($order_ids));
            
            $stmt = mysqli_prepare($conn, "SELECT om.order_id, m.name AS milkshake_name, om.status FROM order_milkshakes om JOIN milkshakes m ON om.milkshake_id = m.milkshake_id WHERE om.order_id IN ($placeholders)");
            mysqli_stmt_bind_param($stmt, $types, ...$order_ids);
            mysqli_stmt_execute($stmt);
            $ms_result = mysqli_stmt_get_result($stmt);
            $all_milkshakes = mysqli_fetch_all($ms_result, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);

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

/* 4. Summary Aggregation */
function getToastSummary($conn, $activePubId) {
    $stmt = mysqli_prepare($conn, "SELECT t.name, COUNT(*) as count FROM order_toasts ot JOIN toasts t ON ot.toast_id = t.toast_id JOIN orders o ON o.order_id = ot.order_id WHERE ot.status IN ('Pending', 'Received') AND o.event_id = ? GROUP BY t.name ORDER BY count DESC");
    mysqli_stmt_bind_param($stmt, 'i', $activePubId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $summary = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $summary;
}

$toastSummary = getToastSummary($conn, $activePubId);
$toastSummaryTotal = 0;
foreach ($toastSummary as $summaryItem) {
    $toastSummaryTotal += (int) ($summaryItem['count'] ?? 0);
}

/* --- 5. AJAX Partial Renderer --- */
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
                    <form style="flex-grow:1; display:flex; gap:5px;" onsubmit="return false;">
                        <select data-order-status data-order-id="<?= $t['order_toast_id'] ?>">
                            <option value="" hidden>Uppdatera</option>
                            <option value="Pending" <?= ($t['status']=='Pending' || $t['status']=='Received') ? 'selected' : '' ?>>Mottagen</option>
                            <option value="In Progress" <?= $t['status']=='In Progress' ? 'selected' : '' ?>>Pågår</option>
                            <option value="Done" <?= $t['status']=='Done' ? 'selected' : '' ?>>Klar</option>
                            <option value="Delivered">Levererad</option>
                        </select>
                    </form>
                    <?php if ($t['status'] !== 'Done'): ?>
                        <?php if($t['status'] == 'Pending' || $t['status'] == 'Received'): ?>
                            <button data-order-action="In Progress" data-order-id="<?= $t['order_toast_id'] ?>" class="btn-next">Starta &rarr;</button>
                        <?php elseif($t['status'] == 'In Progress'): ?>
                            <button data-order-action="Done" data-order-id="<?= $t['order_toast_id'] ?>" class="btn-next btn-finish">Klar &#10003;</button>
                        <?php endif; ?>
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
                .summary-panel {
                    margin-top: 0.75rem;
                    background: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 10px;
                    padding: 0.75rem;
                    width: fit-content;
                    max-width: 100%;
                }
                .summary-head {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 0.6rem;
                    color: #6b7280;
                    font-size: 0.8rem;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    font-weight: 700;
                }
                .summary-total {
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 999px;
                    padding: 0.2rem 0.55rem;
                    color: #1f2937;
                    font-size: 0.8rem;
                    font-weight: 700;
                    margin-left: 0.5rem;
                }
                .summary {
                    display: flex;
                    gap: 0.5rem;
                    flex-wrap: nowrap;
                    overflow-x: auto;
                    padding-bottom: 0.1rem;
                }
                .summary-item {
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 0.5rem 0.6rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.6rem;
                    flex: 0 0 auto;
                }
                .summary-name {
                    font-size: 0.85rem;
                    font-weight: 600;
                    color: #1f2937;
                    white-space: nowrap;
                }
                .summary-count {
                    min-width: 28px;
                    text-align: center;
                    font-size: 0.85rem;
                    font-weight: 800;
                    color: #1d4ed8;
                    background: #eff6ff;
                    border: 1px solid #bfdbfe;
                    border-radius: 999px;
                    padding: 0.1rem 0.4rem;
                }
        /* --- 6. Layout & Theme --- */
        :root {
            /* Light Mode Palette */
            --bg: #f3f4f6;          /* Light gray background */
            --bg-light: #f3f4f6;
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
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; 
            background: linear-gradient(180deg, #eef2ff 0%, var(--bg-light) 30%, #eef2ff 100%); 
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
            <?php if (!empty($toastSummary)): ?>
                <div class="summary-panel">
                    <div class="summary-head">
                        <span>Ej påbörjade beställningar</span>
                        <span class="summary-total">Totalt: <?= $toastSummaryTotal ?></span>
                    </div>
                    <div class="summary">
                        <?php foreach ($toastSummary as $item): ?>
                            <div class="summary-item">
                                <span class="summary-name"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="summary-count"><?= (int) $item['count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div id="connection-status">● Live</div>
    </div>

    <div id="grid-refresh-notice" style="display:none;position:fixed;top:20px;right:20px;background:#10b981;color:#fff;padding:12px 24px;border-radius:8px;z-index:9999;font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,0.15);">Grid updated!</div>
    <div id="ws-debug" style="position:fixed;top:60px;right:20px;background:#2563eb;color:#fff;padding:8px 16px;border-radius:8px;z-index:9999;font-size:0.95em;display:none;">WS: waiting...</div>
    <div id="ticket-grid" class="grid">
        <div style="grid-column: 1/-1; text-align: center; color: var(--text-sub);">Laddar beställningar...</div>
    </div>
    <?php include(SHARED_PATH . "/public_footer.php"); ?>

    <script src="../js/live-updater.js"></script>
    <script src="../js/order-broadcast.js"></script>
    <script>
        window.createLiveUpdater({
            wsUrl: 'ws://localhost:8081',
            statusSelector: '#connection-status',
            statusLabels: {
                live: '\u25cf Live',
                offline: '\u25cf Offline',
                sleeping: '\u25cf Sover (inaktiv 24h)',
            },
            onOrderUpdate: window.handleOrderUpdate,
            onError: function (err) {
                var wsDebug = document.getElementById('ws-debug');
                if (wsDebug) {
                    wsDebug.style.display = 'block';
                    wsDebug.textContent = 'WS: error';
                    setTimeout(function(){ wsDebug.style.display = 'none'; }, 2000);
                }
                console.error('Kunde inte hämta beställningar:', err);
            }
        });
    </script>
</body>
</html>