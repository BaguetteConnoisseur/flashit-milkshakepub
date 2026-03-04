<?php
/* --- 1. Milkshake Station View Bootstrap --- */

require_once("../../private/initalize.php");
require(PRIVATE_PATH . "/master_code/db-conn.php");
require(PRIVATE_PATH . "/master_code/pub-schema-bootstrap.php");

require_login();

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$pubTracking = ensure_pub_tracking($conn);
$activePubId = (int) $pubTracking['active_pub_id'];

/* --- 2. Actions (POST) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    // 1. Next Step Button
    if (isset($_POST['advance_status'])) {
        $om_id = intval($_POST['order_milkshake_id']);
        $current_status = $_POST['current_status'];
        $new_status = $current_status;

        if ($current_status === 'Pending' || $current_status === 'Received') {
            $new_status = 'In Progress';
        } elseif ($current_status === 'In Progress') {
            $new_status = 'Done';
        }

        if ($new_status !== $current_status) {
            $stmt = mysqli_prepare($conn, "UPDATE order_milkshakes om JOIN orders o ON o.order_id = om.order_id SET om.status = ? WHERE om.order_milkshake_id = ? AND o.event_id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $new_status, $om_id, $activePubId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // 2. Manual Dropdown Change
    if (isset($_POST['update_status_manual'])) {
        $om_id = intval($_POST['order_milkshake_id']);
        $new_status = $_POST['manual_status'];
        
        $stmt = mysqli_prepare($conn, "UPDATE order_milkshakes om JOIN orders o ON o.order_id = om.order_id SET om.status = ? WHERE om.order_milkshake_id = ? AND o.event_id = ?");
        mysqli_stmt_bind_param($stmt, 'sii', $new_status, $om_id, $activePubId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

/* --- 3. Data Fetching Helpers --- */
function getTickets($conn, $activePubId) {
    $stmt = mysqli_prepare($conn, "SELECT om.order_milkshake_id, om.status, om.comment AS item_comment, om.order_id, m.name AS milkshake_name, o.order_number, o.pub_order_number, o.customer_name, o.created_at, o.order_comment AS main_comment FROM order_milkshakes om JOIN milkshakes m ON om.milkshake_id = m.milkshake_id JOIN orders o ON om.order_id = o.order_id WHERE om.status != 'Delivered' AND o.event_id = ? ORDER BY CASE WHEN om.status = 'Done' THEN 1 ELSE 0 END, o.created_at ASC");
    mysqli_stmt_bind_param($stmt, 'i', $activePubId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $tickets;
}

/* 4. Summary Aggregation */
function getSummary($conn, $activePubId) {
    $stmt = mysqli_prepare($conn, "SELECT m.name, COUNT(*) as count FROM order_milkshakes om JOIN milkshakes m ON om.milkshake_id = m.milkshake_id JOIN orders o ON o.order_id = om.order_id WHERE om.status IN ('Pending', 'Received') AND o.event_id = ? GROUP BY m.name ORDER BY count DESC");
    mysqli_stmt_bind_param($stmt, 'i', $activePubId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $summary = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $summary;
}

$summary = getSummary($conn, $activePubId);
$summaryTotal = 0;
foreach ($summary as $summaryItem) {
    $summaryTotal += (int) ($summaryItem['count'] ?? 0);
}

/* --- 5. AJAX Partial Renderer --- */
if (isset($_GET['fetch_view'])) {
    $tickets = getTickets($conn, $activePubId);
    
    if (empty($tickets)) {
        echo '<div class="empty-state"><h2>Inga väntande milkshakes.</h2></div>';
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
                    
                    <div class="item-name"><?= htmlspecialchars($t['milkshake_name']) ?></div>
                    
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

                </div>
                <div class="controls">
                    <form method="POST" style="flex-grow:1; display:flex; gap:5px;">
                        <?= csrf_token_input() ?>
                        <input type="hidden" name="order_milkshake_id" value="<?= $t['order_milkshake_id'] ?>">
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
                            <?= csrf_token_input() ?>
                            <input type="hidden" name="order_milkshake_id" value="<?= $t['order_milkshake_id'] ?>">
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
    <title>Milkshake-station</title>
    <link rel="icon" href="../img/logo/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="../img/logo/favicon.png" type="image/png">
    <style>
        /* --- 6. Layout & Theme --- */
        :root {
            /* Light Mode Palette */
            --bg: #f3f4f6;          /* Light gray background */
            --card-bg: #ffffff;     /* White cards */
            --text-main: #1f2937;   /* Dark gray text */
            --text-sub: #6b7280;    /* Lighter gray text */
            
            --border: #e5e7eb;      /* Light border color */
            --accent: #2563eb;      /* Blue accent */
            
            --status-pending: #d1d5db; /* Light gray for pending */
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
        
        .summary-panel {
            margin-top: 0.75rem;
            background: #f9fafb;
            border: 1px solid var(--border);
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
            color: var(--text-sub);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
        }

        .summary-total {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 0.2rem 0.55rem;
            color: var(--text-main);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .summary {
            display: flex;
            gap: 0.5rem;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 0.1rem;
        }
        
        .summary-item {
            background: var(--card-bg);
            border: 1px solid var(--border);
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
            color: var(--text-main);
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
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); /* Lighter shadow */
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
            <h1>🥤 Milkshake-station</h1>
            <?php if (!empty($summary)): ?>
                <div class="summary-panel">
                    <div class="summary-head">
                        <span>Ej påbörjade beställningar</span>
                        <span class="summary-total">Totalt: <?= $summaryTotal ?></span>
                    </div>
                    <div class="summary">
                        <?php foreach ($summary as $item): ?>
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