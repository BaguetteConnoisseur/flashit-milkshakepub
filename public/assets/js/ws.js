const ws = new WebSocket("ws://" + window.location.host + "/ws/");

ws.onopen = () => {
    setStatus('live');
    console.log("Connected to Flashit WebSocket!");
};

ws.onmessage = (e) => {
    const msg = JSON.parse(e.data);
    console.log("New Update Received:", msg);

    // === HANDLING UPDATES FOR ALL VIEWS ===
    // If you want something to happen for ALL views, put it here:
    // Example: refresh orders everywhere
    if (typeof loadOrders === "function") {
        loadOrders();
    }

    // === HANDLING MULTIPLE VIEW-SPECIFIC FUNCTIONS ===
    // You can call as many functions as you want, if they exist in the view:
    // Example: updateToast(), updateDelivery(), playSound(), etc.
    if (typeof updateToast === "function") {
        updateToast(msg);
    }
    if (typeof updateDelivery === "function") {
        updateDelivery(msg);
    }
    // ...add more as needed

    // === ADVANCED: REGISTRATION PATTERN ===
    // You can also let views register their own handlers:
    // In ws.js (global):
    //   window.wsHandlers = window.wsHandlers || [];
    //   function registerWSHandler(fn) { window.wsHandlers.push(fn); }
    //   ...
    //   ws.onmessage = (e) => { ... window.wsHandlers.forEach(fn => fn(msg)); ... }
    // In a view:
    //   registerWSHandler(function(msg) { /* custom logic */ });
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