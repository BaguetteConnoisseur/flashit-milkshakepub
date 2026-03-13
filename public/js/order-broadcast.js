// order-broadcast.js
// Universal AJAX + WebSocket broadcast for all views

function sendOrderStatusUpdate(orderId, status, extra = {}) {
    // Send AJAX POST to backend
    const payload = Object.assign({
        order_id: orderId,
        status: status,
        ...extra,
        broadcast: 1
    });
    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(resp => resp.json())
    .then(data => {
        // Always refresh grid after AJAX success
        refreshOrderGrid();
    });
}

function refreshOrderGrid() {
    fetch(window.location.pathname + '?fetch_view=1')
        .then(resp => resp.text())
        .then(html => {
            document.getElementById('ticket-grid').innerHTML = html;
        });
}
// Listen for dropdown/button changes
function setupOrderStatusHandlers() {
    document.querySelectorAll('[data-order-status]').forEach(function(el) {
        el.addEventListener('change', function(e) {
            const orderId = el.dataset.orderId;
            const status = el.value;
            sendOrderStatusUpdate(orderId, status);
        });
    });
    document.querySelectorAll('[data-order-action]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const orderId = btn.dataset.orderId;
            const action = btn.dataset.orderAction;
            sendOrderStatusUpdate(orderId, action);
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    setupOrderStatusHandlers();
    refreshOrderGrid();
});

// WebSocket update handler (universal)
window.handleOrderUpdate = function(orderUpdate) {
    // orderUpdate: { order_id, status, data }
    // Always refresh grid on broadcast
    refreshOrderGrid();
};
