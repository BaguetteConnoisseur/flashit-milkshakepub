<?php
require_once("/var/www/html/private/initialize.php");

// Handle logout and login actions BEFORE any output
$showError = handle_login_post();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Toast Station</title>
    <style>
        body { font-family: sans-serif; background: #fff5e6; padding: 20px; }
        .item { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 5px solid #d35400; }
    </style>
</head>
<body>
    <h1>🍞 Toast Station</h1>
    <div id="orders"></div>

    <script src="/js/ws.js"></script>
    <script>
        async function loadOrders() {
            console.log("Fetching orders..."); // Debug line
            const r = await fetch("/api/get_orders.php");
            const data = await r.json();
            console.log("Data received:", data); // Check the console (F12) to see what came back

            if (!Array.isArray(data)) return;

            const el = document.getElementById("orders");
            el.innerHTML = "";

            data.filter(i => i.category === "toast").forEach(i => {
                const div = document.createElement("div");
                div.className = "item";
                div.innerHTML = `
                    <strong>${i.name}</strong> — <small>${i.status}</small><br><br>
                    <button onclick="update(${i.order_item_id}, 'Ready')">Mark as Ready</button>
                `;
                el.appendChild(div);
            });
        }

        function update(id, status) {
            fetch("/api/update_item.php", {
                method: "POST",
                body: JSON.stringify({item_id: id, status: status}),
                headers: {"Content-Type": "application/json"}
            });
        }
        loadOrders();
    </script>
</body>
</html>