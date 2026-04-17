// cashier.js - Modal and order logic for cashier view

//
// Main sections:
//   1. Modal open/close and quantity adjustment
//   2. View switching (card/list)
//   3. Order details modal (AJAX open/update/delete)
//   4. Create order form submission (AJAX)
//


// --- 1. Modal open/close and quantity adjustment ---
const CASHIER_APP_BASE_PATH = window.APP_BASE_PATH || '';
function openCreateOrderModal() {
    initializeCreateOrderRows();
    document.getElementById('create-order-modal').style.display = 'flex';
}

// Initialize item rows: qty=0 shows add button, qty>0 shows quantity controls
function initializeCreateOrderRows() {
    document.querySelectorAll('#create-order-form .item-row').forEach(row => {
        const input = row.querySelector('input[type="number"]');
        if (!input) return;

        if ((parseInt(input.value, 10) || 0) === 0) {
            transformToAddButton(input.id);
            return;
        }
        const controls = row.querySelector('.quantity-controls');
        if (controls) controls.style.display = 'flex';
        const addBtn = row.querySelector('.add-item-btn');
        if (addBtn) addBtn.style.display = 'none';
    });
}


function closeCreateOrderModal() {
    document.getElementById('create-order-modal').style.display = 'none';
    document.getElementById('create-order-form').reset();
    initializeCreateOrderRows();
}


// Adjust quantity via +/- buttons, keep value ≥ 0, refresh comment fields
function adjustQtyModal(inputId, delta) {
    const input = document.getElementById(inputId);
    const currentValue = parseInt(input.value) || 0;
    const newValue = Math.max(0, currentValue + delta);
    input.value = newValue;
    updateItemComments(input);
}

// Transform row to "Add item" button state (qty = 0): hide controls, show add button
function transformToAddButton(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const itemRow = input.closest('.item-row');
    if (!itemRow) return;
    
    const controls = itemRow.querySelector('.quantity-controls');
    if (controls) controls.style.display = 'none';
    const comments = itemRow.querySelector('.item-comments');
    if (comments) comments.style.display = 'none';
    
    let addBtn = itemRow.querySelector('.add-item-btn');
    if (!addBtn) {
        addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'add-item-btn';
        addBtn.textContent = 'Lägg till produkt';
        addBtn.onclick = function() {
            transformToQuantitySelector(itemRow, inputId);
        };
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

// Transform row to quantity selector state: show controls, hide add button, set qty to 1
function transformToQuantitySelector(itemRow, inputId) {
    const input = itemRow.querySelector(`#${inputId}`);
    if (!input) return;
    
    const addBtn = itemRow.querySelector('.add-item-btn');
    if (addBtn) addBtn.style.display = 'none';
    
    const controls = itemRow.querySelector('.quantity-controls');
    if (controls) {
        controls.style.display = 'flex';
        controls.style.alignItems = 'stretch';
    }
    
    input.style.display = '';
    input.value = 1;
    updateItemComments(input);
    
    const comments = itemRow.querySelector('.item-comments');
    if (comments) comments.style.display = '';
}

// On page load, initialize all create modal item rows to their starting state.
document.addEventListener('DOMContentLoaded', initializeCreateOrderRows);


// Build comment input fields based on quantity; if qty=0, show add button instead
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
            const commentInputId = `${inputId}-comment-${i}`;
            const input = document.createElement('input');
            input.type = 'text';
            input.name = type + '[' + commentKey + ']';
            input.value = savedValues[commentKey] || '';
            input.placeholder = 'Lägg till notering...';
            input.style.cssText = 'width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.9rem; box-sizing: border-box;';
            input.id = commentInputId;
            const label = document.createElement('label');
            label.style.cssText = 'font-size: 0.85rem; color: var(--text-sub); display: block; margin-bottom: 0.25rem;';
            label.textContent = 'Notering för ' + itemName + ' ' + (i + 1);
            label.setAttribute('for', commentInputId);
            div.appendChild(label);
            div.appendChild(input);
            commentsContainer.appendChild(div);
        }
    } else {
        commentsContainer.style.display = 'none';
        transformToAddButton(inputId);
    }
}


// Close modal if user clicks the background overlay.
document.getElementById('create-order-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCreateOrderModal();
    }
});


// --- 2. View switching (card/list) ---
// Switch between card and list view, persist to localStorage
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


// Restore saved view preference from localStorage (default: card)
function applySavedViewPreference() {
    const savedView = localStorage.getItem('cashierViewPreference') || 'card';
    setView(savedView);
}

// Apply view preference on load and after order list updates
document.addEventListener('DOMContentLoaded', applySavedViewPreference);
document.addEventListener('cashier:orders-updated', applySavedViewPreference);

// Remove any existing order details modal
function removeOrderModal() {
    const existing = document.querySelector('.js-order-modal');
    if (existing) {
        existing.remove();
    }
}

// --- 3. Order details modal (AJAX open/update/delete) ---
// Attach event handlers to order modal: close button, backdrop click, form submit
function attachOrderModalHandlers(modal) {
    const form = modal.querySelector('.js-order-edit-form');
    if (!form) {
        return;
    }

    modal.addEventListener('click', function (event) {
        if (event.target === modal || event.target.closest('.js-modal-close')) {
            removeOrderModal();
        }
    });

    form.addEventListener('submit', async function (event) {
        if (event.submitter && event.submitter.name === 'delete_order') {
            if (!confirm('Radera hela beställningen?')) {
                event.preventDefault();
                return;
            }
        }

        event.preventDefault();
        const formData = new FormData(form, event.submitter);
        formData.set('ajax', '1');

        try {
            const resp = await fetch(form.action, {
                method: 'POST',
                body: formData,
            });
            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Kunde inte uppdatera beställning.');
            }

            removeOrderModal();
            if (typeof updateOrderList === 'function') updateOrderList();
        } catch (err) {
            console.error(err.message || err);
        }
    });
}

// Fetch modal HTML via AJAX, inject into DOM, attach handlers
async function loadOrderModal(url) {
    try {
        const modalUrl = new URL(url, window.location.href);
        modalUrl.searchParams.set('ajax_modal', '1');
        
        const resp = await fetch(modalUrl.toString());
        if (!resp.ok) {
            throw new Error('Kunde inte hämta ordermodal.');
        }

        const html = await resp.text();
        if (!html.trim()) {
            return;
        }

        removeOrderModal();
        document.body.insertAdjacentHTML('beforeend', html);
        const modal = document.querySelector('.js-order-modal');
        if (modal) {
            attachOrderModalHandlers(modal);
        }
    } catch (err) {
        console.error(err.message || err);
    }
}

// Intercept order card clicks: prevent default nav, open modal via AJAX instead
document.addEventListener('click', function (event) {
    const orderCard = event.target.closest('a.order-card');
    if (!orderCard || !orderCard.closest('#order-container')) {
        return;
    }

    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
        return;
    }

    event.preventDefault();
    const href = orderCard.getAttribute('href');
    if (href) {
        loadOrderModal(href);
    }
});

// If modal already exists on page load, attach handlers
document.addEventListener('DOMContentLoaded', function () {
    const existingModal = document.querySelector('.js-order-modal');
    if (existingModal) {
        attachOrderModalHandlers(existingModal);
    }
});


// --- 4. Create order form submission (AJAX) ---
// Collect items, validate, send to API, refresh on success
document.getElementById('create-order-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const staffOrderCheckbox = form.querySelector('#is_staff_order');
    const orderOrigin = staffOrderCheckbox && staffOrderCheckbox.checked ? 'staff' : 'customer';
    const customerName = form.customer_name.value.trim();
    const orderComment = form.order_comment.value.trim();
    const csrfToken = form.querySelector('input[name="csrf_token"]').value;
    const items = [];

    // Collect items with qty > 0 from container
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

    // Collect milkshakes and toasts
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

    // Build JSON payload
    const payload = {
        csrf_token: csrfToken,
        customer_name: customerName,
        order_origin: orderOrigin,
        order_comment: orderComment,
        items: items
    };
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const spinner = document.getElementById('create-order-spinner');
    if (submitBtn) submitBtn.disabled = true;
    if (spinner) spinner.style.display = 'inline-block';
    
    try {
        const resp = await fetch(CASHIER_APP_BASE_PATH + '/api/create_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (resp.ok && data.ok) {
            closeCreateOrderModal();
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
