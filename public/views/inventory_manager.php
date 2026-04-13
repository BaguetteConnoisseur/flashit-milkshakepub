<?php
require_once(__DIR__ . '/../../private/initialize.php');
$activePubName = $_SESSION['active_pub_name'];

?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/assets/img/logo/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lagerhanterare</title>
    <style>
        /* --- 3. Layout & Theme --- */
        :root {
            --bg: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-sub: #6b7280;
            --border: #e5e7eb;
            --primary: #2563eb;
            --danger: #ef4444;
            --accent-milkshake: #3b82f6;
            --accent-toast: #f97316;
        }

        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            padding: 2rem;
        }

        h1, h2 { margin-top: 0; font-weight: 700; color: var(--text-main); }
        h1 { margin-top: 1.5rem; margin-bottom: 2rem; text-align: center; }
        h2 { font-size: 1.25rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }

        /* Grid Layout */
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        @media (max-width: 900px) {
            .grid-container { grid-template-columns: 1fr; }
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }

        /* Tables */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { text-align: left; padding: 12px 16px; color: var(--text-sub); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); }
        td { padding: 12px 16px; border-bottom: 1px solid var(--bg); vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        
        /* Specific Column Widths */
        .inventory-table td:nth-child(2) { font-weight: 600; color: var(--text-main); width: 18%; } /* Name */
        .inventory-table td:nth-child(3) { color: var(--text-sub); width: 25%; } /* Desc */
        .inventory-table td:nth-child(4) { font-size: 0.85rem; color: var(--text-sub); width: 25%; } /* Ingredients */
        .inventory-table td:nth-child(5) { width: 10%; text-align: center; } /* Color */
        .inventory-table td:nth-child(6) { width: 10%; } /* Action */

        /* Buttons */
        .btn-remove {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fee2e2;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-remove:hover { background: var(--danger); color: white; border-color: var(--danger); }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: opacity 0.2s;
        }
        .btn-submit:hover { opacity: 0.9; }

        .action-stack {
            display: flex;
            flex-direction: row;
            gap: 0.45rem;
            min-width: 0;
            align-items: center;
            flex-wrap: nowrap;
        }

        .action-stack form {
            margin: 0;
        }

        .btn-action {
            display: inline-block;
            text-align: center;
            text-decoration: none;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid var(--border);
            background: white;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
            min-width: 64px;
        }

        .btn-add-pub {
            border-color: #15803d;
            color: #ffffff;
            background: #16a34a;
            white-space: nowrap;
        }

        .btn-edit-item {
            border-color: #7e22ce;
            color: #ffffff;
            background: #9333ea;
        }

        .btn-add-pub:hover {
            background: #15803d;
            border-color: #15803d;
            color: #ffffff;
        }

        .btn-edit-item:hover {
            background: #7e22ce;
            border-color: #7e22ce;
            color: #ffffff;
        }

        .btn-action:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .color-swatch {
            width: 22px;
            height: 22px;
            border: 1px solid #ccc;
            margin: 0 auto;
            border-radius: 4px;
        }

        .inactive-milkshake-table td:nth-child(1) { width: 28%; font-weight: 600; }
        .inactive-milkshake-table td:nth-child(2) { width: 52%; color: var(--text-sub); font-size: 0.85rem; }
        .inactive-milkshake-table td:nth-child(3) { width: 8%; text-align: center; }
        .inactive-milkshake-table td:nth-child(4) { width: 12%; }

        .inactive-milkshake-table .btn-action {
            min-width: 72px;
            padding: 4px 8px;
            font-size: 0.72rem;
        }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--text-sub); }
        input[type="text"], textarea {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            box-sizing: border-box;
            background: #f9fafb;
            color: var(--text-main);
            transition: border 0.2s;
        }
        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
        }

        /* Accents */
        .milkshake-section h2 { color: var(--accent-milkshake); border-bottom-color: var(--accent-milkshake); }
        .toast-section h2 { color: var(--accent-toast); border-bottom-color: var(--accent-toast); }

        .btn-milkshake { background: var(--accent-milkshake); }
        .btn-toast { background: var(--accent-toast); }

    </style>
</head>
<body>
    <?php require(TEMPLATE_PATH . "/navbar.php"); ?>

    <h1>Lagerhanterare: <?= htmlspecialchars($activePubName) ?></h1>

    <div class="grid-container" id="inventory-root">
        <!-- Inventory tables and forms will be rendered here by JS -->
    </div>

    <template id="inventory-templates">
        <section class="card milkshake-section" data-section="active-milkshakes">
            <h2>Aktiva milkshakes</h2>
            <div class="table-wrapper"></div>
        </section>
        <section class="card toast-section" data-section="active-toasts">
            <h2>Aktiva toasts</h2>
            <div class="table-wrapper"></div>
        </section>
        <section class="card milkshake-section" data-section="inactive-milkshakes">
            <h2>Tidigare milkshakes</h2>
            <div class="table-wrapper"></div>
        </section>
        <section class="card toast-section" data-section="inactive-toasts">
            <h2>Tidigare toasts</h2>
            <div class="table-wrapper"></div>
        </section>
        <section class="card milkshake-section" data-section="add-milkshake">
            <h2>Lägg till ny milkshake</h2>
            <form id="add-milkshake-form">
                <?= csrf_token_input() ?>
                <div class="form-group"><label>Namn</label><input name="name" type="text" required></div>
                <div class="form-group"><label>Beskrivning</label><textarea name="description" rows="2" required></textarea></div>
                <div class="form-group"><label>Ingredienser</label><textarea name="ingredients" rows="2" required></textarea></div>
                <div class="form-group"><label>Färg</label><input name="color" type="color" value="#3b82f6"></div>
                <input name="add-milkshake" type="submit" class="btn-submit btn-milkshake" value="Spara Milkshake">
            </form>
        </section>
        <section class="card toast-section" data-section="add-toast">
            <h2>Lägg till ny toast</h2>
            <form id="add-toast-form">
                <?= csrf_token_input() ?>
                <div class="form-group"><label>Namn</label><input name="name" type="text" required></div>
                <div class="form-group"><label>Beskrivning</label><textarea name="description" rows="2" required></textarea></div>
                <div class="form-group"><label>Ingredienser</label><textarea name="ingredients" rows="2" required></textarea></div>
                <div class="form-group"><label>Färg</label><input name="color" type="color" value="#f97316"></div>
                <input name="add-toast" type="submit" class="btn-submit btn-toast" value="Spara Toast">
            </form>
        </section>
    </template>

    <script src="/assets/js/shared.js"></script>
    <script>
    // --- SPA Inventory Manager JS ---
    const root = document.getElementById('inventory-root');
    const templates = document.getElementById('inventory-templates').content;

    function renderInventory(inventory) {
        root.innerHTML = '';
        // Sections: active milkshakes, active toasts, inactive milkshakes, inactive toasts, add forms
        const sections = [
            ['active-milkshakes', inventory.milkshakes.active, 'milkshake', true],
            ['active-toasts', inventory.toasts.active, 'toast', true],
            ['inactive-milkshakes', inventory.milkshakes.inactive, 'milkshake', false],
            ['inactive-toasts', inventory.toasts.inactive, 'toast', false],
        ];
        for (const [section, items, category, isActive] of sections) {
            const node = templates.querySelector(`[data-section="${section}"]`).cloneNode(true);
            const wrapper = node.querySelector('.table-wrapper');
            if (!items.length) {
                wrapper.innerHTML = `<p style="color:var(--text-sub); text-align:center;">Inga ${isActive ? 'aktiva' : 'inaktiva'} ${category === 'milkshake' ? 'milkshakes' : 'toasts'}${isActive ? ' för denna pub.' : '.'}</p>`;
            } else {
                const table = document.createElement('table');
                table.className = isActive ? 'inventory-table' : `inactive-${category}-table`;
                const thead = document.createElement('thead');
                thead.innerHTML = `<tr>${isActive ? '<th>Namn</th><th>Beskrivning</th><th>Ingredienser</th><th>Färg</th><th>Åtgärd</th>' : '<th>Namn</th><th>Ingredienser</th><th>Färg</th><th>Åtgärd</th>'}</tr>`;
                table.appendChild(thead);
                const tbody = document.createElement('tbody');
                for (const item of items) {
                    const tr = document.createElement('tr');
                    if (isActive) {
                        tr.innerHTML = `
                            <td>${escapeHtml(item.name)}</td>
                            <td>${escapeHtml(item.description)}</td>
                            <td>${escapeHtml(item.ingredients)}</td>
                            <td style="text-align: center;"><div style="width: 25px; height: 25px; background-color: ${escapeHtml(item.color)}; border: 1px solid #ccc; margin: 0 auto; border-radius:4px;"></div></td>
                            <td><button class="btn-remove" data-action="toggle" data-id="${item.item_id}" data-status="0">Inaktivera</button></td>
                        `;
                    } else {
                        tr.innerHTML = `
                            <td>${escapeHtml(item.name)}</td>
                            <td>${escapeHtml(item.ingredients)}</td>
                            <td><div class="color-swatch" style="background-color: ${escapeHtml(item.color)};"></div></td>
                            <td>
                                <div class="action-stack">
                                    <button class="btn-action btn-add-pub" data-action="toggle" data-id="${item.item_id}" data-status="1">Lägg till</button>
                                    <a href="edit_${category}.php?id=${item.item_id}" class="btn-action btn-edit-item">Redigera</a>
                                </div>
                            </td>
                        `;
                    }
                    tbody.appendChild(tr);
                }
                table.appendChild(tbody);
                wrapper.appendChild(table);
            }
            root.appendChild(node);
        }
        // Add forms
        root.appendChild(templates.querySelector('[data-section="add-milkshake"]').cloneNode(true));
        root.appendChild(templates.querySelector('[data-section="add-toast"]').cloneNode(true));
        attachEventHandlers();
    }

    async function fetchInventory() {
        const res = await fetch('/api/get_inventory.php');
        if (!res.ok) throw new Error('Kunde inte hämta inventarielista');
        return await res.json();
    }

    async function sendAction(data) {
        const csrfToken = window.CSRF_TOKEN || (document.querySelector('input[name="csrf_token"]')?.value) || '';
        const res = await fetch('/api/inventory_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...data, csrf_token: csrfToken })
        });
        return await res.json();
    }

    function attachEventHandlers() {
        // Toggle active/inactive
        root.querySelectorAll('[data-action="toggle"]').forEach(btn => {
            btn.onclick = async e => {
                e.preventDefault();
                btn.disabled = true;
                await sendAction({ action: 'toggle', item_id: btn.dataset.id, new_status: btn.dataset.status });
                await updateInventory();
            };
        });
        // Add milkshake
        const milkshakeForm = root.querySelector('#add-milkshake-form');
        if (milkshakeForm) {
            milkshakeForm.onsubmit = async e => {
                e.preventDefault();
                const fd = new FormData(milkshakeForm);
                await sendAction({
                    action: 'add',
                    category: 'milkshake',
                    name: fd.get('name'),
                    description: fd.get('description'),
                    ingredients: fd.get('ingredients'),
                    color: fd.get('color')
                });
                milkshakeForm.reset();
                await updateInventory();
            };
        }
        // Add toast
        const toastForm = root.querySelector('#add-toast-form');
        if (toastForm) {
            toastForm.onsubmit = async e => {
                e.preventDefault();
                const fd = new FormData(toastForm);
                await sendAction({
                    action: 'add',
                    category: 'toast',
                    name: fd.get('name'),
                    description: fd.get('description'),
                    ingredients: fd.get('ingredients'),
                    color: fd.get('color')
                });
                toastForm.reset();
                await updateInventory();
            };
        }
    }

    async function updateInventory() {
        const data = await fetchInventory();
        renderInventory(data);
    }

    // Initial load
    updateInventory();
    </script>

    <?php include(TEMPLATE_PATH . "/public_footer.php"); ?>
</body>