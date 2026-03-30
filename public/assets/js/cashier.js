// cashier.js - Modal and order logic for cashier view

//
// This script manages the cashier view's modal dialogs, item quantity adjustment,
// comment fields, view switching (card/list), and order creation via AJAX.
//
// Main sections:
//   1. Modal open/close and quantity adjustment
//   2. Dynamic comment fields for each item
//   3. View switching (card/list)
//   4. Create order form submission (AJAX)
//


// --- 1. Modal open/close and quantity adjustment ---

// Show the create order modal
function openCreateOrderModal() {
    document.getElementById('create-order-modal').style.display = 'flex';
}


// Hide the create order modal and reset the form
function closeCreateOrderModal() {
    document.getElementById('create-order-modal').style.display = 'none';
    document.getElementById('create-order-form').reset();
}


// Increase or decrease the quantity for an item in the modal
function adjustQtyModal(inputId, delta) {
    const input = document.getElementById(inputId);
    const currentValue = parseInt(input.value) || 0;
    const newValue = Math.max(0, currentValue + delta);
    input.value = newValue;
    updateItemComments(input);
}

// Transform the item row to show only the green 'Add item' button
function transformToAddButton(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const itemRow = input.closest('.item-row');
    if (!itemRow) return;
    // Hide quantity controls and comments
    const controls = itemRow.querySelector('.quantity-controls');
    if (controls) controls.style.display = 'none';
    const comments = itemRow.querySelector('.item-comments');
    if (comments) comments.style.display = 'none';
    // Add the green 'Add item' button if not present
    let addBtn = itemRow.querySelector('.add-item-btn');
    if (!addBtn) {
        addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'add-item-btn';
        addBtn.textContent = 'Lägg till produkt';
        addBtn.onclick = function() {
            transformToQuantitySelector(itemRow, inputId);
        };
        // Insert in the same place as the controls
        const controls = itemRow.querySelector('.quantity-controls');
        if (controls) {
            itemRow.insertBefore(addBtn, controls);
        } else {
            itemRow.insertBefore(addBtn, itemRow.children[1]);
        }
    }
    addBtn.style.display = '';
    input.style.display = 'none';
}

// Transform the item row to show the quantity selector and comments
function transformToQuantitySelector(itemRow, inputId) {
    const input = itemRow.querySelector(`#${inputId}`);
    if (!input) return;
    // Hide add button
    const addBtn = itemRow.querySelector('.add-item-btn');
    if (addBtn) addBtn.style.display = 'none';
    // Show quantity controls
    const controls = itemRow.querySelector('.quantity-controls');
    if (controls) {
        controls.style.display = 'flex';
        controls.style.alignItems = 'stretch'; // Prevent vertical centering
    }
    // Show input
    input.style.display = '';
    // Set value to 1 on add
    input.value = 1;
    updateItemComments(input);
    // Show comments
    const comments = itemRow.querySelector('.item-comments');
    if (comments) comments.style.display = '';
}

// On page load, transform all items to 'Add item' button if value is 0
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-row').forEach(row => {
        const input = row.querySelector('input[type="number"]');
        if (input && (parseInt(input.value) || 0) === 0) {
            transformToAddButton(input.id);
        } else if (input) {
            // If value > 0, ensure controls are visible and add button is hidden
            const controls = row.querySelector('.quantity-controls');
            if (controls) controls.style.display = 'flex';
            const addBtn = row.querySelector('.add-item-btn');
            if (addBtn) addBtn.style.display = 'none';
        }
    });
});


// Dynamically show/hide and preserve comment fields for each item based on quantity
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
    // Save existing comment values to preserve them when re-rendering
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


// Close modal if user clicks outside the modal content
document.getElementById('create-order-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCreateOrderModal();
    }
});


// --- 2. View switching (card/list) ---

// Switch between card and list view for orders
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


// On page load, restore the last used view (card/list)
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('cashierViewPreference') || 'card';
    setView(savedView);
});


// --- 3. Create order form submission (AJAX) ---

// Intercept the create order form submit, collect data, and send as JSON via fetch
document.getElementById('create-order-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const customerName = form.customer_name.value.trim();
    const orderComment = form.order_comment.value.trim();
    const csrfToken = form.querySelector('input[name="csrf_token"]').value;
    const items = [];

    // Collect all items and their comments from the modal
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

    // Prepare payload and send to backend
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
