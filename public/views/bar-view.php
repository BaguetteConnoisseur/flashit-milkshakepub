<?php
require_once("../../private/initalize.php");
require(PRIVATE_PATH . "/master_code/db-conn.php");

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// --- DATA FETCHING & AJAX ---
if (isset($_GET['fetch_view'])) {
    
    // Fetch orders from the last 12 hours
    $query = "SELECT order_id, order_number, customer_name, status FROM orders 
              WHERE created_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
              ORDER BY created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $orders = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Buckets
    $kitchen = [];   // Will hold 'Received' and 'In Progress' (Preparing)
    $done = [];      // Will hold 'Done'
    $delivered = []; // Will hold 'Delivered'

    foreach ($orders as $o) {
        $s = strtolower($o['status']);
        
        // Map DB statuses to User Requested Display Text
        if ($s === 'delivered') {
            if (count($delivered) < 8) { // Show last 8 delivered
                $o['display_status'] = 'Delivered';
                $delivered[] = $o;
            }
        } elseif ($s === 'done') {
            $o['display_status'] = 'Done';
            $done[] = $o;
        } elseif ($s === 'in progress') {
            $o['display_status'] = 'Preparing';
            $kitchen[] = $o;
        } else {
            // 'Pending' or 'Received'
            $o['display_status'] = 'Received';
            $kitchen[] = $o;
        }
    }

    // --- RENDER HTML FRAGMENTS ---
    
    // 1. KITCHEN COLUMN (Received + Preparing)
    echo '<div id="col-kitchen">';
    foreach ($kitchen as $o) {
        // Different badge color for "Preparing" vs "Received"
        $badgeClass = ($o['display_status'] === 'Preparing') ? 'badge-prep' : 'badge-recv';
        
        echo '<div class="card card-kitchen">
                <div class="row-top">
                    <span class="ord-num">#' . htmlspecialchars($o['order_id']) . '</span>
                    <span class="status-pill ' . $badgeClass . '">' . $o['display_status'] . '</span>
                </div>
                <div class="cust-name">' . htmlspecialchars($o['customer_name']) . '</div>
              </div>';
    }
    echo '</div>';

    // 2. DONE COLUMN
    echo '<div id="col-done">';
    if (empty($done)) {
         echo '<div class="empty-msg">No orders waiting</div>';
    } else {
        foreach ($done as $o) {
            echo '<div class="card card-done">
                    <div class="row-top">
                        <span class="ord-num">#' . htmlspecialchars($o['order_id']) . '</span>
                        <span class="status-pill badge-done">Done</span>
                    </div>
                    <div class="cust-name">' . htmlspecialchars($o['customer_name']) . '</div>
                  </div>';
        }
    }
    echo '</div>';

    // 3. DELIVERED COLUMN
    echo '<div id="col-delivered">';
    foreach ($delivered as $o) {
        echo '<div class="card card-delivered">
                <div class="row-top">
                    <span class="ord-num">#' . htmlspecialchars($o['order_id']) . '</span>
                    <span class="status-pill badge-del">Delivered</span>
                </div>
                <div class="cust-name">' . htmlspecialchars($o['customer_name']) . '</div>
              </div>';
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
            --color-done: #22c55e;  /* Green for Done */
            --color-del: #9ca3af;   /* Light Grey for Delivered */
        }

        body {
            margin: 0;
            background-color: var(--bg);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
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

        /* --- Specific Styles per status --- */

        /* 1. Kitchen (Received / Preparing) */
        .card-kitchen { border-left: 4px solid var(--color-prep); }
        .badge-recv { background: #f3f4f6; color: var(--color-recv); }
        .badge-prep { background: #eff6ff; color: var(--color-prep); }

        /* 2. Done (Green & Large) */
        .card-done { 
            border-left: 6px solid var(--color-done); 
            transform: scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(34, 197, 94, 0.15);
        }
        .card-done .cust-name { font-size: 2rem; color: #000; }
        .badge-done { background: #dcfce7; color: #166534; font-size: 0.9rem; padding: 6px 12px; }
        
        /* Pop animation for Done items */
        .card-done { animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes popIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1.02); opacity: 1; } }

        /* 3. Delivered (Faded) */
        .card-delivered { border-left: 4px solid var(--color-del); opacity: 0.6; background: #f9fafb; }
        .card-delivered .cust-name { text-decoration: line-through; color: var(--text-sub); }
        .badge-del { background: #f3f4f6; color: var(--color-del); }

    </style>
</head>
<body>

    <header>
        Wait List
    </header>

    <div class="display-board">
        
        <div class="column">
            <div class="col-header">Preparing</div>
            <div id="list-kitchen" class="order-list">
                </div>
        </div>

        <div class="column col-center">
            <div class="col-header">Done</div>
            <div id="list-done" class="order-list">
                </div>
        </div>

        <div class="column">
            <div class="col-header">Delivered</div>
            <div id="list-delivered" class="order-list">
                </div>
        </div>

    </div>

    <script>
        function updateBoard() {
            fetch('?fetch_view=1')
                .then(response => response.text())
                .then(html => {
                    let parser = new DOMParser();
                    let doc = parser.parseFromString(html, 'text/html');

                    document.getElementById('list-kitchen').innerHTML = doc.getElementById('col-kitchen').innerHTML;
                    document.getElementById('list-done').innerHTML = doc.getElementById('col-done').innerHTML;
                    document.getElementById('list-delivered').innerHTML = doc.getElementById('col-delivered').innerHTML;
                })
                .catch(err => console.error('Display update failed', err));
        }

        updateBoard();
        setInterval(updateBoard, 5000);
    </script>
</body>
</html>