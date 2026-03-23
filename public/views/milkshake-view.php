<?php
require_once(__DIR__ . '/../../private/initialize.php');
require_once(PRIVATE_PATH . '/src/database/db.php');

?>

<!DOCTYPE html>
<html>
<head>
    <title>Toast-station</title>
    <style>
        :root {
            --bg: #f3f4f6;
            --bg-light: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --accent: #f97316;
            --status-pending: #d1d5db;
            --status-progress: #eab308;
            --status-done: #22c55e;
            --note-item-bg: #fef9c3;
            --note-item-text: #854d0e;
            --note-order-bg: #dbeafe;
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
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
        }

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
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .status-bar {
            height: 6px;
            width: 100%;
        }

        .bar-Pending, .bar-Received {
            background-color: var(--status-pending);
        }
        .bar-Progress {
            background-color: var(--status-progress);
        }
        .bar-Done {
            background-color: var(--status-done);
        }
        .bar-ticket-Progress {
            border: 10px solid var(--status-progress);
        }
        .bar-ticket-Done {
            border: 10px solid var(--status-done);
        }

        .card-body {
            padding: 1.5rem;
            flex-grow: 1;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--text-sub);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .item-name {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .comment-box {
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        .item-note {
            background: var(--note-item-bg);
            color: var(--note-item-text);
            border-left: 4px solid #ca8a04;
        }

        .order-note {
            background: var(--note-order-bg);
            color: var(--note-order-text);
            border-left: 4px solid #2563eb;
        }

        .linked-list {
            background: #f9fafb;
            border-radius: 6px;
            padding: 0.75rem;
            margin-top: 1rem;
            border: 1px solid #e5e7eb;
        }

        .linked-header {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-sub);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .linked-item {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            display: flex;
            justify-content: space-between;
        }

        .controls {
            background: #f9fafb;
            padding: 1rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
            border-top: 1px solid #e5e7eb;
        }

        select {
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid #d1d5db;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.9rem;
            flex-grow: 1;
        }

        .btn-next {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.2s;
            white-space: nowrap;
        }
        .btn-next:hover {
            opacity: 0.9;
        }

        .btn-finish {
            background: var(--status-done);
            color: white;
        }
        .btn-finish:hover {
            opacity: 0.9;
        }

        .empty-state {
            text-align: center;
            color: var(--text-sub);
            margin-top: 4rem;
            width: 100%;
        }

        #connection-status {
            font-size: 0.8rem;
            color: #10b981;
        }
    </style>
</head>
<body>
    <?php require(TEMPLATE_PATH . "/admin_navbar.php"); ?>

    <div class="header">
        <div>
            <h1>🥤 Milkshake-station</h1>
            <div id="milkshake-summary"></div>
        </div>
        <div id="connection-status">● Live</div>
    </div>
    <div id="ticket-grid" class="grid">
        <div style="grid-column: 1/-1; text-align: center; color: var(--text-sub);">Laddar beställningar...</div>
    </div>

    <script src="/assets/js/ws.js"></script>
    <script src="/assets/js/shared.js"></script>
    <script>
    // --- 1. Globals & Utility Functions ---
    window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? '' ?>';


    // --- 2. DOM Rendering ---
    function createMilkshakeCard(order, milkshakeItem) {
        const card = document.createElement('div');
        let statusClass = milkshakeItem.status.replace(' ', '');
        if (milkshakeItem.status === 'In Progress' || milkshakeItem.status === 'InProgress') statusClass = 'Progress';
        card.className = `ticket-card bar-ticket-${statusClass}`;

        const statusBar = document.createElement('div');
        statusBar.className = `status-bar bar-${statusClass}`;
        card.appendChild(statusBar);

        const body = document.createElement('div');
        body.className = 'card-body';

        const meta = document.createElement('div');
        meta.className = 'meta';
        meta.innerHTML = `
            <span>#${escapeHtml(order.pub_order_number ?? order.order_number ?? order.order_id)}</span>
            <span>${order.created_at ? new Date(order.created_at).toLocaleTimeString('sv-SE', {hour: '2-digit', minute:'2-digit'}) : ''}</span>
        `;
        body.appendChild(meta);

        const itemName = document.createElement('div');
        itemName.className = 'item-name';
        itemName.innerHTML = `${escapeHtml(milkshakeItem.name)}`;
        body.appendChild(itemName);

        if (milkshakeItem.comment) {
            const note = document.createElement('div');
            note.className = 'comment-box item-note';
            note.innerHTML = `<strong>Notering:</strong> ${escapeHtml(milkshakeItem.comment)}`;
            body.appendChild(note);
        }

        if (order.order_comment) {
            const orderNote = document.createElement('div');
            orderNote.className = 'comment-box order-note';
            orderNote.innerHTML = `<strong>Ordernotering:</strong> ${escapeHtml(order.order_comment)}`;
            body.appendChild(orderNote);
        }

        const customer = document.createElement('div');
        customer.style = 'font-size: 0.9rem; color: var(--text-sub); margin-bottom: 1rem;';
        customer.textContent = `Kund: ${order.customer_name ? escapeHtml(order.customer_name) : ''}`;
        body.appendChild(customer);

        card.appendChild(body);

        const controls = document.createElement('div');
        controls.className = 'controls';
        const csrf = window.CSRF_TOKEN ? `<input type='hidden' name='csrf_token' value='${window.CSRF_TOKEN}'>` : '';
        controls.innerHTML = `
            <form class="manual-status-form" style="flex-grow:1; display:flex; gap:5px;">
                <input type="hidden" name="order_milkshake_id" value="${milkshakeItem.order_item_id}">
                <select name="manual_status">
                    <option value="" hidden>Uppdatera</option>
                    <option value="Pending" ${milkshakeItem.status === 'Pending' ? 'selected' : ''}>Mottagen</option>
                    <option value="In Progress" ${milkshakeItem.status === 'In Progress' ? 'selected' : ''}>Pågår</option>
                    <option value="Done" ${milkshakeItem.status === 'Done' ? 'selected' : ''}>Klar</option>
                </select>
                <input type="hidden" name="update_status_manual" value="1">
                ${csrf}
            </form>
            ${milkshakeItem.status !== 'Done' ? `
                <form class="advance-status-form">
                    <input type="hidden" name="order_milkshake_id" value="${milkshakeItem.order_item_id}">
                    <input type="hidden" name="current_status" value="${milkshakeItem.status}">
                    ${csrf}
                    ${milkshakeItem.status === 'Pending' ?
                        '<button type="submit" name="advance_status" class="btn-next">Starta &rarr;</button>' :
                        (milkshakeItem.status === 'In Progress' ? '<button type="submit" name="advance_status" class="btn-next btn-finish">Klar &#10003;</button>' : '')
                    }
                </form>
            ` : ''}
        `;
        card.appendChild(controls);
        return card;
    }

    // --- 3. API Logic ---
    function update(id, status, csrf_token) {
        fetch("/api/update_item.php", {
            method: "POST",
            body: JSON.stringify({item_id: id, status: status, csrf_token: csrf_token || window.CSRF_TOKEN || ''}),
            headers: {"Content-Type": "application/json"}
        });
    }

    // --- 4. Milkshake Summary (PDO) ---
    function renderMilkshakeSummary(orders) {
        const mount = document.getElementById('milkshake-summary');

        // Remove any previous summary panel
        mount.innerHTML = '';

        // Only consider milkshake items with status 'Pending'
        const pendingMilkshakes = [];
        (orders || []).forEach(order => {
            (order.items || []).forEach(item => {
                if (item.category === 'milkshake' && item.status === 'Pending') {
                    pendingMilkshakes.push(item);
                }
            });
        });

        if (pendingMilkshakes.length === 0) {
            return;
        }

        // Count by milkshake name
        const summaryMap = {};
        pendingMilkshakes.forEach(item => {
            summaryMap[item.name] = (summaryMap[item.name] || 0) + 1;
        });
        const summaryArr = Object.entries(summaryMap)
            .map(([name, count]) => ({ name, count }))
            .sort((a, b) => b.count - a.count);
        const total = pendingMilkshakes.length;

        // Build and insert summary panel
        const panel = document.createElement('div');
        panel.className = 'summary-panel';
        panel.innerHTML = `
            <div class="summary-head">
                <span>Ej påbörjade beställningar</span>
                <span class="summary-total">Totalt: ${total}</span>
            </div>
            <div class="summary">
                ${summaryArr.map(item => `
                    <div class="summary-item">
                        <span class="summary-name">${escapeHtml(item.name)}</span>
                        <span class="summary-count">${item.count}</span>
                    </div>
                `).join('')}
            </div>
        `;
        mount.appendChild(panel);
    }

    // --- 5. Main Loader ---
    async function loadOrders() {
        const r = await fetch("/api/get_event_orders.php");
        let data = await r.json();
        const grid = document.getElementById("ticket-grid");
        grid.innerHTML = '';

        // Handle case where there is no milshakes
        if (!Array.isArray(data) || data.length === 0) {
            renderMilkshakeSummary([]);
            grid.innerHTML = '<div class="empty-state"><h2>Inga väntande milkshakes.</h2></div>';
            return;
        }



        // Flatten all milkshake items (excluding Delivered) with their parent order
        let allMilkshakeItems = [];
        data.forEach(order => {
            (order.items || []).forEach(item => {
                if (item.category === 'milkshake' && item.status !== 'Delivered') {
                    allMilkshakeItems.push({ order, milkshakeItem: item });
                }
            });
        });

        // Sort all milkshake items globally: Pending, In Progress, Done
        const statusOrder = { 'Pending': 0, 'In Progress': 1, 'Done': 2 };
        allMilkshakeItems.sort((a, b) => {
            const aStatus = statusOrder[a.milkshakeItem.status] ?? 99;
            const bStatus = statusOrder[b.milkshakeItem.status] ?? 99;
            // If same status, sort by order_number
            if (aStatus === bStatus) {
                return (a.order.order_number ?? 0) - (b.order.order_number ?? 0);
            }
            return aStatus - bStatus;
        });

        if (allMilkshakeItems.length === 0) {
            grid.innerHTML = '<div class="empty-state"><h2>Inga väntande milkshakes.</h2></div>';
        } else {
            allMilkshakeItems.forEach(({ order, milkshakeItem }) => {
                grid.appendChild(createMilkshakeCard(order, milkshakeItem));
            });
        }

        // Render milkshake summary
        renderMilkshakeSummary(data);
    }



    // --- 6. Event Binding ---
    document.addEventListener('DOMContentLoaded', () => {
        const grid = document.getElementById('ticket-grid');
        grid.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'manual_status') {
                e.preventDefault();
                const form = e.target.closest('form');
                const item_id = form.querySelector('[name="order_milkshake_id"]').value;
                const status = e.target.value;
                const csrf_token = form.querySelector('[name="csrf_token"]').value;
                update(item_id, status, csrf_token);
            }
        });
        grid.addEventListener('submit', function(e) {
            if (e.target && (e.target.classList.contains('advance-status-form'))) {
                e.preventDefault();
                const form = e.target;
                const item_id = form.querySelector('[name="order_milkshake_id"]').value;
                let status = form.querySelector('[name="current_status"]').value;
                const csrf_token = form.querySelector('[name="csrf_token"]').value;
                if (status === 'Pending' || status === 'Received') status = 'In Progress';
                else if (status === 'In Progress') status = 'Done';
                else return;
                update(item_id, status, csrf_token);
            }
        });
    });

    // --- 7. Initial Load ---
    loadOrders();
    </script>
</body>
</html>