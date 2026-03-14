const ws = new WebSocket("ws://" + window.location.host + "/ws/");

ws.onopen = () => {
    console.log("Connected to Flashit WebSocket!");
};

ws.onmessage = (e) => {
    const msg = JSON.parse(e.data);
    console.log("New Update Received:", msg);

    // This calls the loadOrders() function sitting in your milkshake/toast.php
    if (typeof loadOrders === "function") {
        loadOrders();
    }
};

ws.onerror = (err) => {
    console.error("WebSocket Error:", err);
};