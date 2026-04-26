<?php
require_once(__DIR__ . '/../../private/initialize.php');
require_once(PRIVATE_PATH . '/src/database/db.php');

?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/svg+xml" href="<?= app_asset_url('img/logo/favicon.svg') ?>">
    <link rel="alternate icon" type="image/png" href="<?= app_asset_url('img/logo/favicon.png') ?>">
    <title>Toast station</title>
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
            flex-direction: column;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
            gap: 0.5rem;
        }

        #toast-summary {
            width: min(68vw, 860px);
            max-width: 100%;
        }

        .summary-panel {
            margin-top: 0.75rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 0.75rem;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
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

        .summary-head-left {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            margin-left: 0;
        }

        .summary-guide {
            font-size: 0.72rem;
            font-weight: 600;
            color: #92400e;
            text-transform: none;
            letter-spacing: 0;
            line-height: 1.2;
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
            flex-wrap: wrap;
            overflow-x: hidden;
            overflow-y: visible;
            padding: 0.3rem 0 0.25rem;
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
            flex: 0 1 auto;
            cursor: pointer;
            user-select: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .summary-item.is-active {
            border-color: #f97316;
            background: #fff7ed;
            box-shadow: 0 10px 22px -14px rgba(249, 115, 22, 0.95);
            transform: translateY(-1px);
        }

        .summary-item.is-preview {
            border-color: #fdba74;
            background: #fffbeb;
        }

        .summary-item:hover {
            border-color: #f97316;
            box-shadow: 0 8px 18px -14px rgba(249, 115, 22, 0.8);
            transform: translateY(-1px);
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

        .ticket-card.is-filter-match {
            border-color: #f97316;
            box-shadow: 0 14px 28px -16px rgba(249, 115, 22, 0.85);
        }

        .ticket-card.is-filter-dimmed {
            opacity: 0.25;
        }

        .status-bar {
            height: 6px;
            width: 100%;
        }

        .bar-Pending {
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

        .ellipsis-single-line {
            display: block;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
            gap: 0.5rem;
            min-width: 0;
        }

        .linked-item-name {
            flex: 1;
            min-width: 0;
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
    </style>
</head>
<body>
    <?php require(TEMPLATE_PATH . "/navbar.php"); ?>

    <div class="header">
        <h1>🥪 Toast-station</h1>
        <div id="toast-summary"></div>
    </div>
    <div id="ticket-grid" class="grid">
        <div style="grid-column: 1/-1; text-align: center; color: var(--text-sub);">Laddar beställningar...</div>
    </div>

    <script src="<?= app_asset_url('js/ws.js') ?>"></script>
    <script src="<?= app_asset_url('js/shared.js') ?>"></script>
    <script>
    // --- 1. Globals & Utility Functions ---
    window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? '' ?>';
    let activeToastFilter = '';
    let hoverToastFilter = '';

    function normalizeToastName(name) {
        return String(name || '').trim().toLowerCase();
    }

    function getEffectiveToastFilter() {
        return activeToastFilter || hoverToastFilter;
    }

    // --- 2. DOM Rendering ---
    function createToastCard(order, toastItem) {
        const card = document.createElement('div');
        let statusClass = toastItem.status.replace(' ', '');
        if (toastItem.status === 'In Progress' || toastItem.status === 'InProgress') statusClass = 'Progress';
        card.className = `ticket-card bar-ticket-${statusClass}`;
        card.dataset.toastName = normalizeToastName(toastItem.name);

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
        itemName.className = 'item-name ellipsis-single-line';
        itemName.innerHTML = `${escapeHtml(toastItem.name)}`;
        body.appendChild(itemName);

        if (toastItem.comment) {
            const note = document.createElement('div');
            note.className = 'comment-box item-note';
            note.innerHTML = `<strong>Notering:</strong> ${escapeHtml(toastItem.comment)}`;
            body.appendChild(note);
        }

        if (order.order_comment) {
            const orderNote = document.createElement('div');
            orderNote.className = 'comment-box order-note';
            orderNote.innerHTML = `<strong>Ordernotering:</strong> ${escapeHtml(order.order_comment)}`;
            body.appendChild(orderNote);
        }

        const customer = document.createElement('div');
        customer.className = 'ellipsis-single-line';
        customer.style = 'font-size: 0.9rem; color: var(--text-sub); margin-bottom: 1rem;';
        customer.textContent = `Kund: ${order.customer_name ? escapeHtml(order.customer_name) : ''}`;
        body.appendChild(customer);

        const milkshakes = (order.items || []).filter(i => i.category === 'milkshake');
        if (milkshakes.length > 0) {
            const linked = document.createElement('div');
            linked.className = 'linked-list';
            linked.innerHTML = `
                <div class="linked-header">Med milkshakes:</div>
                ${milkshakes.map(ms => `
                    <div class="linked-item">
                        <span class="linked-item-name ellipsis-single-line">🥤 ${escapeHtml(ms.name)}</span>
                        <span style="font-size:0.8em; opacity:0.7;">(${localizeItemStatusLabel(ms.status)})</span>
                    </div>
                `).join('')}
            `;
            body.appendChild(linked);
        }

        card.appendChild(body);

        const controls = document.createElement('div');
        controls.className = 'controls';
        const csrf = window.CSRF_TOKEN ? `<input type='hidden' name='csrf_token' value='${window.CSRF_TOKEN}'>` : '';
        controls.innerHTML = `
            <form class="manual-status-form" style="flex-grow:1; display:flex; gap:5px;">
                <input type="hidden" name="order_toast_id" value="${toastItem.order_item_id}">
                <select name="manual_status">
                    <option value="" hidden>Uppdatera</option>
                    <option value="Pending" ${toastItem.status === 'Pending' ? 'selected' : ''}>Mottagen</option>
                    <option value="In Progress" ${toastItem.status === 'In Progress' ? 'selected' : ''}>Pågår</option>
                    <option value="Done" ${toastItem.status === 'Done' ? 'selected' : ''}>Klar</option>
                </select>
                <input type="hidden" name="update_status_manual" value="1">
                ${csrf}
            </form>
            ${toastItem.status !== 'Done' ? `
                <form class="advance-status-form">
                    <input type="hidden" name="order_toast_id" value="${toastItem.order_item_id}">
                    <input type="hidden" name="current_status" value="${toastItem.status}">
                    ${csrf}
                    ${toastItem.status === 'Pending' ?
                        '<button type="submit" name="advance_status" class="btn-next">Starta &rarr;</button>' :
                        (toastItem.status === 'In Progress' ? '<button type="submit" name="advance_status" class="btn-next btn-finish">Klar &#10003;</button>' : '')
                    }
                </form>
            ` : ''}
        `;
        card.appendChild(controls);
        return card;
    }

    // --- 3. API Logic ---
    function update(id, status, csrf_token) {
        fetch("<?= app_api_url('update_item.php') ?>", {
            method: "POST",
            body: JSON.stringify({item_id: id, status: status, csrf_token: csrf_token || window.CSRF_TOKEN || ''}),
            headers: {"Content-Type": "application/json"}
        });
    }

    // --- 4. Toast Summary & Filtering ---
    function renderToastSummary(orders) {
        const mount = document.getElementById('toast-summary');

        // Remove any previous summary panel
        mount.innerHTML = '';

        // Only consider toast items with status 'Pending'
        const pendingToasts = [];
        (orders || []).forEach(order => {
            (order.items || []).forEach(item => {
                if (item.category === 'toast' && item.status === 'Pending') {
                    pendingToasts.push(item);
                }
            });
        });

        if (pendingToasts.length === 0) {
            return;
        }

        // Count by toast name
        const summaryMap = {};
        pendingToasts.forEach(item => {
            summaryMap[item.name] = (summaryMap[item.name] || 0) + 1;
        });
        const summaryArr = Object.entries(summaryMap)
            .map(([name, count]) => ({ name, count }))
            .sort((a, b) => b.count - a.count);
        const total = pendingToasts.length;

        // Build and insert summary panel
        const panel = document.createElement('div');
        panel.className = 'summary-panel';
        panel.innerHTML = `
            <div class="summary-head">
                <div class="summary-head-left">
                    <span>Ej påbörjade beställningar</span>
                    <span class="summary-guide">Tips: Hovra eller klicka på en smak för att markera dess beställningar.</span>
                </div>
                <span class="summary-total">Totalt: ${total}</span>
            </div>
            <div class="summary">
                ${summaryArr.map(item => `
                    <div class="summary-item" role="button" tabindex="0" data-summary-name="${escapeHtml(item.name)}" aria-pressed="false">
                        <span class="summary-name">${escapeHtml(item.name)}</span>
                        <span class="summary-count">${item.count}</span>
                    </div>
                `).join('')}
            </div>
        `;
        mount.appendChild(panel);
    }

    function applyToastFilter(name) {
        activeToastFilter = normalizeToastName(name);
        applyToastFilterState();
    }

    function applyToastFilterState() {
        const filterName = getEffectiveToastFilter();

        const grid = document.getElementById('ticket-grid');
        const summary = document.getElementById('toast-summary');
        if (!grid || !summary) return;

        const cards = grid.querySelectorAll('.ticket-card[data-toast-name]');
        const chips = summary.querySelectorAll('.summary-item[data-summary-name]');

        if (!filterName) {
            cards.forEach(card => card.classList.remove('is-filter-match', 'is-filter-dimmed'));
            chips.forEach(chip => chip.classList.remove('is-active', 'is-preview'));
            chips.forEach(chip => chip.setAttribute('aria-pressed', 'false'));
            return;
        }

        cards.forEach(card => {
            const matches = card.dataset.toastName === filterName;
            card.classList.toggle('is-filter-match', matches);
            card.classList.toggle('is-filter-dimmed', !matches);
        });

        chips.forEach(chip => {
            const normalized = normalizeToastName(chip.dataset.summaryName);
            const activeMatch = !!activeToastFilter && normalized === activeToastFilter;
            const previewMatch = !activeToastFilter && !!hoverToastFilter && normalized === hoverToastFilter;
            chip.classList.toggle('is-active', activeMatch);
            chip.classList.toggle('is-preview', previewMatch);
            chip.setAttribute('aria-pressed', activeMatch ? 'true' : 'false');
        });
    }

    // --- 5. Main Loader ---
    async function loadOrders() {
        const r = await fetch("<?= app_api_url('get_active_orders.php') ?>");
        let data = await r.json();
        const grid = document.getElementById("ticket-grid");
        grid.innerHTML = '';

        // Handle case where there is no toasts
        if (!Array.isArray(data) || data.length === 0) {
            renderToastSummary([]);
            grid.innerHTML = '<div class="empty-state"><h2>Inga väntande toast.</h2></div>';
            return;
        }

        // Collect all toast items with their parent order
        let allToastItems = [];
        data.forEach(order => {
            (order.items || []).forEach(item => {
                if (item.category === 'toast') {
                    allToastItems.push({ order, toastItem: item });
                }
            });
        });

        // Sort: all 'Done' toast items last, all others by order_id ascending
        allToastItems.sort((a, b) => {
            const aDone = a.toastItem.status === 'Done';
            const bDone = b.toastItem.status === 'Done';
            if (aDone !== bDone) {
                return aDone ? 1 : -1;
            }
            return (a.order.order_id ?? 0) - (b.order.order_id ?? 0);
        });

        if (allToastItems.length === 0) {
            grid.innerHTML = '<div class="empty-state"><h2>Inga väntande toast.</h2></div>';
        } else {
            allToastItems.forEach(({ order, toastItem }) => {
                grid.appendChild(createToastCard(order, toastItem));
            });
        }

        // Render toast summary
        renderToastSummary(data);
        applyToastFilterState();
    }



    // --- 6. Event Binding ---
    document.addEventListener('DOMContentLoaded', () => {
        const summary = document.getElementById('toast-summary');
        if (summary) {
            summary.addEventListener('click', function(e) {
                const chip = e.target.closest('.summary-item[data-summary-name]');
                if (!chip) return;
                const selectedName = chip.dataset.summaryName || '';
                applyToastFilter(normalizeToastName(selectedName) === activeToastFilter ? '' : selectedName);
            });

            summary.addEventListener('mouseover', function(e) {
                if (activeToastFilter) return;
                const chip = e.target.closest('.summary-item[data-summary-name]');
                if (!chip) return;
                hoverToastFilter = normalizeToastName(chip.dataset.summaryName || '');
                applyToastFilterState();
            });

            summary.addEventListener('mouseout', function(e) {
                if (activeToastFilter) return;
                const fromChip = e.target.closest('.summary-item[data-summary-name]');
                if (!fromChip) return;
                const toChip = e.relatedTarget && e.relatedTarget.closest ? e.relatedTarget.closest('.summary-item[data-summary-name]') : null;
                if (toChip) return;
                hoverToastFilter = '';
                applyToastFilterState();
            });

            summary.addEventListener('keydown', function(e) {
                const chip = e.target.closest('.summary-item[data-summary-name]');
                if (!chip) return;
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                const selectedName = chip.dataset.summaryName || '';
                applyToastFilter(normalizeToastName(selectedName) === activeToastFilter ? '' : selectedName);
            });
        }

        const grid = document.getElementById('ticket-grid');
        grid.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'manual_status') {
                e.preventDefault();
                const form = e.target.closest('form');
                const item_id = form.querySelector('[name="order_toast_id"]').value;
                const status = e.target.value;
                const csrf_token = form.querySelector('[name="csrf_token"]').value;
                update(item_id, status, csrf_token);
            }
        });
        grid.addEventListener('submit', function(e) {
            if (e.target && (e.target.classList.contains('advance-status-form'))) {
                e.preventDefault();
                const form = e.target;
                const item_id = form.querySelector('[name="order_toast_id"]').value;
                let status = form.querySelector('[name="current_status"]').value;
                const csrf_token = form.querySelector('[name="csrf_token"]').value;
                if (status === 'Pending') status = 'In Progress';
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