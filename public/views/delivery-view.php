<?php
require_once(__DIR__ . '/../../private/initialize.php');

?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/assets/img/logo/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leveransstation</title>

    <style>
        /* --- 5. Layout & Theme --- */
        :root {
            --bg: #f3f4f6; 
            --bg-light: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --accent: #6366f1; /* Delivery */
            
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
        
        /* 2. Delivered (Greyed Out) */
        .card-delivered { opacity: 0.6; background: #f9fafb; border-color: transparent; box-shadow: none; filter: grayscale(80%); }
        .card-delivered:hover { opacity: 0.8; }

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
    <?php require(TEMPLATE_PATH . "/navbar.php"); ?>

    <div class="header">
        <h1>📦 Leveransstation</h1>
        <div id="connection-status">● Live</div>
    </div>

    <div id="ticket-grid" class="grid">
        <div style="grid-column: 1/-1; text-align: center; color: var(--text-sub);">Laddar beställningar...</div>
    </div>
    <?php include(TEMPLATE_PATH . "/public_footer.php"); ?>

    <script src="/assets/js/ws.js"></script>
    <script src="/assets/js/shared.js"></script>
    <script>
    // --- DOM helpers for card rendering ---
    function createOrderCard(order) {
        const items = order.items || [];
        // Allow 'Deliver Order' if all items are either Done or Delivered
        const allDoneOrDelivered = items.length > 0 && items.every(item => item.status === 'Done' || item.status === 'Delivered');
        const allDelivered = items.length > 0 && items.every(item => item.status === 'Delivered');

        // Card class logic
        let cardClass = '';
        if (order.status === 'Delivered' || allDelivered) {
            cardClass = 'card-delivered';
        } else if (allDoneOrDelivered) {
            cardClass = 'card-ready';
        }

        // Card element
        const card = document.createElement('div');
        card.className = `ticket-card ${cardClass}`;

        // Card header
        const header = document.createElement('div');
        header.className = 'card-header';
        header.innerHTML = `
            <div class="meta">
                <span>#${order.order_number}</span>
                <span>${order.created_at ? new Date(order.created_at).toLocaleTimeString('sv-SE', {hour: '2-digit', minute:'2-digit'}) : ''}</span>
            </div>
            <div class="customer">
                ${order.customer_name ? escapeHtml(order.customer_name) : ''}
                ${(order.status === 'Delivered' || allDelivered) ? '<span style="font-size:0.8rem; opacity:0.6;">(Levererad)</span>' : ''}
            </div>
            ${order.order_comment ? `<div class="order-note">📝 ${escapeHtml(order.order_comment)}</div>` : ''}
        `;
        card.appendChild(header);

        // Card body (items)
        const body = document.createElement('div');
        body.className = 'card-body';
        items.forEach(item => {
            const isItemReady = item.status === 'Done';
            const isItemDelivered = item.status === 'Delivered';
            const itemClass = isItemDelivered ? 'item-delivered' : (isItemReady ? 'item-done' : 'item-pending');
            const icon = item.category === 'milkshake' ? '🥤' : '🥪';

            const row = document.createElement('div');
            row.className = `item-row ${itemClass}`;
            row.innerHTML = `
                <div class="item-info">
                    <span class="item-icon">${icon}</span>
                    <div>
                        <div class="item-name">${escapeHtml(item.name)}</div>
                        ${item.comment ? `<div class="item-comment">⚠️ ${escapeHtml(item.comment)}</div>` : ''}
                    </div>
                </div>
                <div class="item-status">
                    <span class="status-text">${localizeItemStatusLabel(item.status)}</span>
                    ${isItemReady? `
                        <button class="btn-mini" title="Leverera" onclick="deliverItem(${item.order_item_id}, this)">✓</button>
                    ` : ''}
                </div>
            `;
            body.appendChild(row);
        });
        card.appendChild(body);

        // Card footer
        const footer = document.createElement('div');
        footer.className = 'card-footer';
        if (!(order.status === 'Delivered' || allDelivered)) {
            if (allDoneOrDelivered) {
                const btn = document.createElement('button');
                btn.className = 'btn-main btn-success';
                btn.textContent = 'LEVERERA BESTÄLLNING';
                btn.onclick = async () => {
                    btn.disabled = true;
                    await updateOrderStatus(order.order_id, 'Delivered');
                };
                footer.appendChild(btn);
            } else {
                const btn = document.createElement('button');
                btn.className = 'btn-main btn-disabled';
                btn.textContent = 'Väntar på köket...';
                btn.disabled = true;
                footer.appendChild(btn);
            }
        } else {
            const btn = document.createElement('button');
            btn.className = 'btn-take-back';
            btn.title = 'Återta denna beställning';
            btn.innerHTML = '↶';
            btn.onclick = async () => {
                btn.disabled = true;
                await updateOrderStatus(order.order_id, 'Done');
            };
            footer.appendChild(btn);
        }
        card.appendChild(footer);

        return card;
    }

    // --- API: Update order status --- BUG: If we update an order to Delivered from the Cashier view with items still marked as Pending or In Progress, and revert from this view, the items will be set to Done instead of Pending. This is because the API endpoint for updating order status also updates all items to match the order status when set to Delivered or Done. A proper fix would involve separating the logic for manually setting an order to Delivered (which should not auto-update item statuses) from the automatic status syncing that happens when individual items are updated. For now, a workaround is to only use the "Take back" button on this view to revert a Delivered order back to Done, which will keep item statuses intact.
    async function updateOrderStatus(orderId, status) {
        const csrfToken = window.CSRF_TOKEN || (document.querySelector('input[name="csrf_token"]')?.value) || '';
        try {
            await fetch('/api/update_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, status, csrf_token: csrfToken })
            });
        } catch (e) {
            // Optionally show error
        }
        await loadOrders();
    }

    // --- API: Deliver individual item ---
    async function deliverItem(orderItemId, btn) {
        btn.disabled = true;
        const csrfToken = window.CSRF_TOKEN || (document.querySelector('input[name="csrf_token"]')?.value) || '';
        await fetch('/api/update_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: orderItemId, status: 'Delivered', csrf_token: csrfToken })
        });
        await loadOrders();
    }
    


    // --- Main loader: fetch and render orders ---

    async function loadOrders() {
        const r = await fetch("/api/get_event_orders.php");
        let data = await r.json();
        const grid = document.getElementById("ticket-grid");
        grid.innerHTML = '';

        if (!Array.isArray(data) || data.length === 0) {
            grid.innerHTML = '<div class="empty-state"><h2>Inga beställningar hittades.</h2></div>';
            return;
        }

        // Sort: all items done first, then status 'Done', then others, all by order_number
        data.sort((a, b) => {
            function sortRank(order) {
                if (Array.isArray(order.items) && order.items.length > 0 && order.items.every(item => item.status === 'Done' || item.status === 'Delivered')) return 0; // All items done
                if (order.status === 'Done') return 1; // Status done, but not all items done
                return 2;
            }
            const aRank = sortRank(a);
            const bRank = sortRank(b);
            if (aRank !== bRank) return aRank - bRank;
            return (a.order_number ?? 0) - (b.order_number ?? 0);
        });
        console.log("Loaded orders:", data);
        data.forEach(order => {
            grid.appendChild(createOrderCard(order));
        });
    }

    loadOrders();
    </script>
</body>
</html>