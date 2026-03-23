// cashier.js - Modal and order logic for cashier view

function openCreateOrderModal() {
    document.getElementById('create-order-modal').style.display = 'flex';
}

function closeCreateOrderModal() {
    document.getElementById('create-order-modal').style.display = 'none';
    document.getElementById('create-order-form').reset();
}

function adjustQtyModal(inputId, delta) {
    const input = document.getElementById(inputId);
    const currentValue = parseInt(input.value) || 0;
    const newValue = Math.max(0, currentValue + delta);
    input.value = newValue;
    updateItemComments(input);
}

function updateItemComments(input) {
    const inputId = input.id;
    const qty = parseInt(input.value) || 0;
    const commentsContainer = document.getElementById('comments-' + inputId);
    const prefix = inputId.charAt(0);
    const type = prefix === 'm' ? 'milkshake_comments' : 'toast_comments';
    const baseId = inputId.substring(2);
    const itemRow = input.closest('.item-row');
    const itemName = itemRow ? itemRow.querySelector('h4').textContent : 'Artikel';
    const savedValues = {};
    commentsContainer.querySelectorAll('input[type="text"]').forEach(inp => {
        const match = inp.name.match(/\[(.*?)\]/);
        if (match) savedValues[match[1]] = inp.value;
    });
    commentsContainer.innerHTML = '';
    if (qty > 0) {
        commentsContainer.style.display = 'block';
        for (let i = 0; i < qty; i++) {
            const commentKey = prefix + '_' + baseId + '_' + i;
            const div = document.createElement('div');
            div.style.marginBottom = '0.5rem';
            const input = document.createElement('input');
            input.type = 'text';
            input.name = type + '[' + commentKey + ']';
            input.value = savedValues[commentKey] || '';
            input.placeholder = 'Lägg till notering...';
            input.style.cssText = 'width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; box-sizing: border-box;';
            const label = document.createElement('label');
            label.style.cssText = 'font-size: 0.85rem; color: var(--text-sub); display: block; margin-bottom: 0.25rem;';
            label.textContent = 'Notering för ' + itemName + ' ' + (i + 1);
            div.appendChild(label);
            div.appendChild(input);
            commentsContainer.appendChild(div);
        }
    } else {
        commentsContainer.style.display = 'none';
    }
}

document.getElementById('create-order-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCreateOrderModal();
    }
});

function setView(viewType) {
    const container = document.getElementById('order-container');
    const cardBtn = document.getElementById('card-view-btn');
    const listBtn = document.getElementById('list-view-btn');
    const cards = container.querySelectorAll('.order-card');
    cardBtn.classList.toggle('active', viewType === 'card');
    listBtn.classList.toggle('active', viewType === 'list');
    container.className = viewType === 'card' ? 'order-grid' : 'order-list';
    cards.forEach(card => {
        card.classList.toggle('list-item', viewType === 'list');
    });
    localStorage.setItem('cashierViewPreference', viewType);
}

document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('cashierViewPreference') || 'card';
    setView(savedView);
});

// --- Intercept Create Order Form Submit and Send JSON ---
document.getElementById('create-order-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const customerName = form.customer_name.value.trim();
    const orderComment = form.order_comment.value.trim();
    const csrfToken = form.querySelector('input[name="csrf_token"]').value;
    const items = [];
    function collectItems(containerSelector) {
        const result = [];
        document.querySelectorAll(containerSelector + ' .item-row').forEach(row => {
            const slug = row.getAttribute('data-item-slug');
            const qtyInput = row.querySelector('input[type="number"]');
            const qty = parseInt(qtyInput.value) || 0;
            if (qty > 0) {
                const comments = row.querySelectorAll('.item-comments input[type="text"]');
                for (let i = 0; i < qty; i++) {
                    let comment = '';
                    if (comments[i]) comment = comments[i].value.trim();
                    result.push({ slug, comment });
                }
            }
        });
        return result;
    }
    items.push(...collectItems('#milkshakes-container'));
    items.push(...collectItems('#toasts-container'));
    if (!customerName) {
        console.error('Kundnamn krävs.');
        return;
    }
    if (items.length === 0) {
        console.error('Välj minst en artikel.');
        return;
    }
    const payload = {
        csrf_token: csrfToken,
        customer_name: customerName,
        order_comment: orderComment,
        items: items
    };
    const submitBtn = form.querySelector('button[type="submit"]');
    const spinner = document.getElementById('create-order-spinner');
    if (submitBtn) submitBtn.disabled = true;
    if (spinner) spinner.style.display = 'inline-block';
    try {
        const resp = await fetch('/api/create_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (resp.ok && data.ok) {
            closeCreateOrderModal();
            form.reset();
            if (typeof updateOrderList === 'function') updateOrderList();
        } else {
            console.error(data.error || 'Kunde inte skapa beställning.');
        }
    } catch (err) {
        console.error('Nätverksfel:', err.message);
    } finally {
        if (submitBtn) submitBtn.disabled = false;
        if (spinner) spinner.style.display = 'none';
    }
});
