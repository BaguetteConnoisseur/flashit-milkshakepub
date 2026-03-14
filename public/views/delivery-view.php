<?php
require_once("/var/www/html/private/initialize.php");

// Handle logout and login actions BEFORE any output
$showError = handle_login_post();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Delivery Station</title>
    <style>
        body { font-family: sans-serif; background: #e6f7ff; padding: 20px; }
        .order-card { background: white; padding: 20px; margin: 15px 0; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .status-ready { color: green; font-weight: bold; }
        .status-pending { color: orange; }
    </style>
</head>
<body>
    <h1>📦 Delivery / Pickup</h1>
    <div id="delivery-list"></div>

    <script src="/js/ws.js"></script>
    <script>
        async function loadOrders() {
            const r = await fetch("/api/get_orders.php");
            const data = await r.json();
            if (!Array.isArray(data)) return;

            const container = document.getElementById("delivery-list");
            container.innerHTML = "";

            // 1. Group items by Order ID
            const orders = {};
            data.forEach(item => {
                if (!orders[item.order_id]) orders[item.order_id] = [];
                orders[item.order_id].push(item);
            });

            // 2. Display each order
            Object.entries(orders).forEach(([orderId, items]) => {
                const isComplete = items.every(i => i.status === 'ready');
                
                const div = document.createElement("div");
                div.className = "order-card";
                
                let itemsHtml = items.map(i => `
                    <li>${i.name} - <span class="status-${i.status}">${i.status}</span></li>
                `).join('');

                div.innerHTML = `
                    <h3>Order #${orderId} ${isComplete ? '✅ READY' : '⏳ In Progress'}</h3>
                    <ul>${itemsHtml}</ul>
                    ${isComplete ? '<button onclick="completeOrder('+orderId+')">Hand to Customer</button>' : ''}
                `;
                container.appendChild(div);
            });
        }

        // Optional: In the future, you could add an API to delete/archive completed orders
        function completeOrder(id) { alert("Order " + id + " delivered!"); }

        loadOrders();
    </script>
</body>
</html>