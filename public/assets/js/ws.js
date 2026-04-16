const APP_BASE_PATH = window.APP_BASE_PATH || '';

// Build WebSocket URL with public flag if needed
let wsUrl = (window.location.protocol === 'https:' ? 'wss://' : 'ws://') + window.location.host + APP_BASE_PATH + '/ws/';
if (typeof isPublicBarView !== 'undefined' && isPublicBarView) {
    wsUrl += "?public=1";
}

const ws = new WebSocket(wsUrl);

ws.onopen = () => {
    setStatus('live');
    console.log("Connected to Flashit WebSocket!");
};

ws.onmessage = (e) => {
    const msg = JSON.parse(e.data);
    console.log("New Update Received:", msg);

    // === HANDLING UPDATES FOR ALL VIEWS ===
    if (typeof loadOrders === "function") {
        loadOrders();
    }

    // === HANDLING MULTIPLE VIEW-SPECIFIC FUNCTIONS ===
    if (typeof updateToast === "function") {
        updateToast(msg);
    }
    if (typeof updateDelivery === "function") {
        updateDelivery(msg);
    }
};

ws.onerror = (err) => {
    setStatus('offline');
    console.error("WebSocket Error:", err);
};

ws.onclose = () => {
    setStatus('sleeping');
};

// Status label logic (shared for all views) - placed after main logic
const statusLabels = {
    live: '● Live',
    offline: '● Offline',
    sleeping: '● Sleeping',
};
const statusSelector = '#connection-status';
function setStatus(status) {
    const el = document.querySelector(statusSelector);
    if (el && statusLabels[status]) {
        el.textContent = statusLabels[status];
    }
}