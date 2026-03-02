<?php

if (!function_exists('ensure_pub_tracking')) {
    function ensure_pub_tracking($conn) {
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS sales_events (
                event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_name VARCHAR(120) NOT NULL,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ended_at TIMESTAMP NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci
        ");

        $columnResult = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'event_id'");
        if (!$columnResult || mysqli_num_rows($columnResult) === 0) {
            mysqli_query($conn, "ALTER TABLE orders ADD COLUMN event_id INT UNSIGNED NULL");
        }

        $indexResult = mysqli_query($conn, "SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_event_id'");
        if (!$indexResult || mysqli_num_rows($indexResult) === 0) {
            mysqli_query($conn, "CREATE INDEX idx_orders_event_id ON orders(event_id)");
        }

        $activePubRow = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT event_id, event_name FROM sales_events WHERE is_active = 1 ORDER BY event_id DESC LIMIT 1"
        ));

        if (!$activePubRow) {
            $defaultPubName = 'Pub ' . date('Y-m-d');
            $safeDefaultPub = mysqli_real_escape_string($conn, $defaultPubName);
            mysqli_query($conn, "INSERT INTO sales_events (event_name, is_active) VALUES ('$safeDefaultPub', 1)");

            $activePubId = (int) mysqli_insert_id($conn);
            $activePubName = $defaultPubName;
        } else {
            $activePubId = (int) $activePubRow['event_id'];
            $activePubName = $activePubRow['event_name'];
        }

        mysqli_query($conn, "UPDATE orders SET event_id = $activePubId WHERE event_id IS NULL");

        return [
            'active_pub_id' => $activePubId,
            'active_pub_name' => $activePubName,
        ];
    }
}
