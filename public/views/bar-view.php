<?php
/* --- Bar Display View --- */
require_once(__DIR__ . '/../../private/initialize.php');

$pdo = db();
$activePubId = isset($_SESSION['active_pub_id']) ? (int)$_SESSION['active_pub_id'] : null;

/* --- Configuration --- */
$BAR_VIEW_MAX_VISIBLE_CARDS = 7;

/* --- AJAX partial: returns three column divs --- */
if (isset($_GET['fetch_view'])) {
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.order_number, o.customer_name,
               o.status AS order_status, o.created_at
        FROM orders o
        WHERE o.event_id = :event_id
                    AND COALESCE(o.order_origin, 'customer') = 'customer'
          AND o.created_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
        ORDER BY o.created_at DESC
    ");
    $stmt->execute(['event_id' => $activePubId]);
    $orders = $stmt->fetchAll();

    $preparing = []; $inProgress = []; $doneDelivered = [];
    foreach ($orders as $o) {
        $s = strtolower($o['order_status']);
        if ($s === 'pending' || $s === 'received') {
            $o['display_status'] = 'Väntar';
            $preparing[] = $o;
        } elseif ($s === 'in progress') {
            $o['display_status'] = 'Tillagas';
            $inProgress[] = $o;
        } elseif ($s === 'done' || $s === 'delivered') {
            $o['display_status'] = ($s === 'done') ? 'Klar' : 'Hämtad';
            $o['is_delivered'] = ($s === 'delivered');
            $doneDelivered[] = $o;
        }
    }

    // Col 1 – Waiting
    echo '<div id="col-preparing">';
    if (empty($preparing)) {
        echo '<div class="empty-msg">Inga väntande beställningar</div>';
    } else {
        $total = count($preparing);
        $visible = ($total > $BAR_VIEW_MAX_VISIBLE_CARDS) ? $BAR_VIEW_MAX_VISIBLE_CARDS - 1 : $total;

        for ($i = 0; $i < $visible; $i++) {
            $o = $preparing[$i];
            $num = htmlspecialchars($o['order_number'] ?? $o['order_id']);
            $name = htmlspecialchars($o['customer_name']);
            echo "<div class=\"order-card status-waiting\" data-order-id=\"{$o['order_id']}\">
                    <span class=\"order-num\">#{$num}</span>
                    <span class=\"customer-name\">{$name}</span>
                  </div>";
        }

        if ($total > $BAR_VIEW_MAX_VISIBLE_CARDS) {
            $remaining = $total - ($BAR_VIEW_MAX_VISIBLE_CARDS - 1);
            echo "<div class=\"order-card overflow-card\" aria-label=\"{$remaining} fler beställningar\">
                    <span class=\"overflow-count\">+{$remaining}</span>
                    <span class=\"overflow-text\">fler beställningar</span>
                  </div>";
        }
    }
    echo '</div>';

    // Col 2 – In progress
    echo '<div id="col-inprogress">';
    if (empty($inProgress)) {
        echo '<div class="empty-msg">Inga aktiva beställningar</div>';
    } else {
        $total = count($inProgress);
        $visible = ($total > $BAR_VIEW_MAX_VISIBLE_CARDS) ? $BAR_VIEW_MAX_VISIBLE_CARDS - 1 : $total;

        for ($i = 0; $i < $visible; $i++) {
            $o = $inProgress[$i];
            $num = htmlspecialchars($o['order_number'] ?? $o['order_id']);
            $name = htmlspecialchars($o['customer_name']);
            echo "<div class=\"order-card status-progress\" data-order-id=\"{$o['order_id']}\">
                    <span class=\"order-num\">#{$num}</span>
                    <span class=\"customer-name\">{$name}</span>
                  </div>";
        }

        if ($total > $BAR_VIEW_MAX_VISIBLE_CARDS) {
            $remaining = $total - ($BAR_VIEW_MAX_VISIBLE_CARDS - 1);
            echo "<div class=\"order-card overflow-card\" aria-label=\"{$remaining} fler beställningar\">
                    <span class=\"overflow-count\">+{$remaining}</span>
                    <span class=\"overflow-text\">fler beställningar</span>
                  </div>";
        }
    }
    echo '</div>';

    // Col 3 – Done / Delivered
    echo '<div id="col-done-delivered">';
    if (empty($doneDelivered)) {
        echo '<div class="empty-msg">Inga klara beställningar</div>';
    } else {
        $total = count($doneDelivered);
        $visible = ($total > $BAR_VIEW_MAX_VISIBLE_CARDS) ? $BAR_VIEW_MAX_VISIBLE_CARDS - 1 : $total;

        for ($i = 0; $i < $visible; $i++) {
            $o = $doneDelivered[$i];
            $num = htmlspecialchars($o['order_number'] ?? $o['order_id']);
            $name = htmlspecialchars($o['customer_name']);
            $isDel = !empty($o['is_delivered']);
            $extra = $isDel ? ' is-delivered' : '';
            $badgeClass = $isDel ? 'badge-delivered' : 'badge-done';
            echo "<div class=\"order-card status-done{$extra}\" data-order-id=\"{$o['order_id']}\" data-delivered=\"" . ($isDel ? '1' : '0') . "\">
                    <span class=\"order-num\">#{$num}</span>
                    <span class=\"customer-name\">{$name}</span>
                  </div>";
        }

        if ($total > $BAR_VIEW_MAX_VISIBLE_CARDS) {
            $remaining = $total - ($BAR_VIEW_MAX_VISIBLE_CARDS - 1);
            echo "<div class=\"order-card overflow-card\" aria-label=\"{$remaining} fler beställningar\">
                    <span class=\"overflow-count\">+{$remaining}</span>
                    <span class=\"overflow-text\">fler beställningar</span>
                  </div>";
        }
    }
    echo '</div>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flashit Milkshake Pub — Beställningsstatus</title>
    <link rel="icon" type="image/svg+xml" href="<?= app_asset_url('img/logo/favicon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:           #f3f4f6;
            --panel:        #ffffff;
            --border:       #e5e7eb;
            --column-border:#e5e7eb;
            --col-center:   #ffffff;

            --text-main:    #1f2937;
            --text-sub:     #6b7280;
            --text-num:     #9ca3af;

            --waiting-glow: #3b82f6;
            --progress-glow:#f59e0b;
            --done-glow:    #22c55e;

            --waiting-bg:   #eff6ff;
            --progress-bg:  #fef3c7;
            --done-bg:      #dcfce7;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            height: 100dvh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* ── Header ── */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            height: 72px;
            background: var(--panel);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-main);
        }
        .brand-icon { font-size: 1.6rem; }

        .top-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        #clock {
            font-family: 'Space Mono', monospace;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: 0.04em;
        }

        #connection-status {
            font-family: 'Space Mono', monospace;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            color: var(--text-sub);
            transition: color 0.3s, background 0.3s;
        }
        #connection-status[data-status="live"]  { color: #16a34a; background: rgba(34,197,94,.1); border-color: rgba(34,197,94,.3); }
        #connection-status[data-status="offline"],
        #connection-status[data-status="reconnecting"] { color: #dc2626; background: rgba(220,38,38,.1); border-color: rgba(220,38,38,.3); }

        /* ── Column headers ── */
        .col-labels {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            flex-shrink: 0;
            border-bottom: 1px solid var(--border);
        }

        .col-label {
            padding: 0.85rem 1.5rem;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 0.15em;
            text-align: center;
            text-transform: uppercase;
            color: var(--text-sub);
            border-bottom: 3px solid var(--column-border);
        }
        .col-label:last-child { border-right: none; }
        .col-label.active-col { color: var(--progress-glow); border-bottom-color: var(--progress-glow); }
        .col-label.done-col   { color: var(--done-glow); border-bottom-color: var(--done-glow); }

        /* ── Board ── */
        .board {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            flex: 1;
            overflow: hidden;
        }

        .col {
            padding: 1.25rem;
            border-right: 1px solid var(--border);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .col:last-child { border-right: none; }
        .col-mid { background: var(--col-center); }

        /* Scrollbar */
        .col::-webkit-scrollbar { width: 4px; }
        .col::-webkit-scrollbar-track { background: transparent; }
        .col::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

        /* ── Order cards ── */
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .order-card {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            padding: 1rem 1.1rem;
            min-width: 0;
            border-radius: 10px;
            border: 1px solid var(--border);
            animation: cardIn 0.3s ease both;
            transition: opacity 0.4s;
        }

        .status-waiting {
            background: var(--waiting-bg);
            border-left: 3px solid var(--waiting-glow);
        }
        .status-progress {
            background: var(--progress-bg);
            border-left: 3px solid var(--progress-glow);
            box-shadow: 0 0 12px rgba(245,158,11,.1);
        }
        .status-done {
            background: var(--done-bg);
            border-left: 3px solid var(--done-glow);
            box-shadow: 0 0 12px rgba(34,197,94,.1);
        }
        .status-done.is-delivered {
            opacity: 0.4;
            border-left-color: var(--text-sub);
            background: transparent;
            box-shadow: none;
        }
        .status-done.is-delivered .customer-name {
            text-decoration: line-through;
            color: var(--text-sub);
        }

        .order-num {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 0.7rem;
            color: var(--text-num);
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .overflow-card {
            background: #f9fafb;
            border: 1px dashed #cbd5e1;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 65px;
        }

        .overflow-count {
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1;
            color: var(--text-main);
        }

        .overflow-text {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-sub);
            font-weight: 700;
        }

        .customer-name {
            font-size: 1.55rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.1;
            color: var(--text-main);
            display: block;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Done column — make customer name pop more */
        .col:last-child .customer-name { font-size: 1.75rem; }

        /* Empty state */
        .empty-msg {
            color: var(--text-sub);
            font-size: 0.8rem;
            text-align: center;
            margin-top: 2rem;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            opacity: 0.5;
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <div class="brand">
            <span class="brand-icon">🥤</span>
            Flashit Milkshake Pub
        </div>
        <div class="top-right">
            <div id="clock">--:--:--</div>
            <div id="connection-status">● Ansluter…</div>
        </div>
    </div>

    <div class="col-labels">
        <div class="col-label">Väntar</div>
        <div class="col-label active-col">Tillagas</div>
        <div class="col-label done-col">Klar / levereras</div>
    </div>

    <div class="board">
        <div class="col" id="list-preparing"></div>
        <div class="col col-mid" id="list-inprogress"></div>
        <div class="col" id="list-done-delivered"></div>
    </div>

    <script> window.isPublicView = true; </script>
    <script src="<?= app_asset_url('js/ws.js') ?>"></script>
    <script>
        // ── Clock ──
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent =
                String(now.getHours()).padStart(2,'0') + ':' +
                String(now.getMinutes()).padStart(2,'0') + ':' +
                String(now.getSeconds()).padStart(2,'0');
        }
        setInterval(updateClock, 1000);
        updateClock();

        // ── Delivered grace period ──
        const HIDE_DELAY_MS = 12000;
        const deliveredFirstSeen = new Map();

        function applyDeliveredGracePeriod() {
            const list = document.getElementById('list-done-delivered');
            if (!list) return;
            const now = Date.now();
            const currentIds = new Set();

            list.querySelectorAll('.order-card[data-delivered="1"]').forEach(card => {
                const id = card.dataset.orderId;
                if (!id) return;
                currentIds.add(id);
                if (!deliveredFirstSeen.has(id)) { deliveredFirstSeen.set(id, now); return; }
                if (now - deliveredFirstSeen.get(id) >= HIDE_DELAY_MS) card.remove();
            });

            for (const id of Array.from(deliveredFirstSeen.keys())) {
                if (!currentIds.has(id)) deliveredFirstSeen.delete(id);
            }

            if (!list.querySelector('.order-card')) {
                list.innerHTML = '<div class="empty-msg">Inga klara beställningar</div>';
            }
        }

        // ── Load / refresh columns ──
        async function loadOrders() {
            try {
                const r = await fetch(window.location.pathname + '?fetch_view=1');
                const html = await r.text();
                const tmp = document.createElement('div');
                tmp.innerHTML = html;

                const map = [
                    ['list-preparing',    'col-preparing'],
                    ['list-inprogress',   'col-inprogress'],
                    ['list-done-delivered','col-done-delivered'],
                ];
                map.forEach(([listId, srcId]) => {
                    const dest = document.getElementById(listId);
                    const src  = tmp.querySelector('#' + srcId);
                    if (dest && src) dest.innerHTML = src.innerHTML;
                });

                applyDeliveredGracePeriod();
            } catch(e) {
                console.error('[bar-view] Failed to load orders:', e);
            }
        }

        // Initial load
        document.addEventListener('DOMContentLoaded', loadOrders);
        // Run grace period check every second
        setInterval(applyDeliveredGracePeriod, 10000);
    </script>
