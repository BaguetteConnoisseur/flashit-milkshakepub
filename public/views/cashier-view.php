<?php
require_once(__DIR__ . "/../../private/initialize.php");

// Handle logout and login actions BEFORE any output
$showError = handle_login_post();
?>
<!DOCTYPE html>
<button onclick="order()">Create Order</button>

<script>

function order() {
    fetch("/api/create_order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ 
            customer_name: "Test User", 
            items: ['oreo-supreme', 'toast-standard']
        })
    });
}


</script>