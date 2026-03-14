<?php
require_once("/var/www/html/private/initialize.php");

// Handle logout and login actions BEFORE any output
$showError = handle_login_post();
?>

<h1>Milkshake Station</h1>

<div id="orders"></div>

<script src="/js/ws.js"></script>

<script>

async function loadOrders() {
    try {
        const r = await fetch("/api/get_orders.php");
        const data = await r.json();

        // 🛑 THE FIX: Check if data is an array
        if (!Array.isArray(data)) {
            console.error("Data received is not an array:", data);
            return;
        }

        const el = document.getElementById("orders");
        el.innerHTML = "";

        data.filter(i => i.type === "milkshake").forEach(i => {
            const div = document.createElement("div");
            div.className = "order-item";
            div.innerHTML = `
                <strong>${i.name}</strong> - Status: ${i.status}
                <button onclick="update(${i.item_id},'making')">Start</button>
                <button onclick="update(${i.item_id},'ready')">Ready</button>
            `;
            el.appendChild(div);
        });
    } catch (err) {
        console.error("Failed to load orders:", err);
    }
}

loadOrders();

</script>