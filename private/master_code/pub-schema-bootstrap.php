<?php

/* --- 1. Pub Schema Bootstrap --- */

if (!function_exists('ensure_pub_tracking')) {
    function ensure_pub_tracking($conn) {
        /* 1. Core Pub/Event Schema */
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS sales_events (
                event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_name VARCHAR(120) NOT NULL,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ended_at TIMESTAMP NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci
        ");

        /* 2. Orders Table Compatibility + Indexes */
        $columnResult = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'event_id'");
        if (!$columnResult || mysqli_num_rows($columnResult) === 0) {
            mysqli_query($conn, "ALTER TABLE orders ADD COLUMN event_id INT UNSIGNED NULL");
        }

        $indexResult = mysqli_query($conn, "SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_event_id'");
        if (!$indexResult || mysqli_num_rows($indexResult) === 0) {
            mysqli_query($conn, "CREATE INDEX idx_orders_event_id ON orders(event_id)");
        }

        $ordersCreatedAtIndexResult = mysqli_query($conn, "SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_event_created_at'");
        if (!$ordersCreatedAtIndexResult || mysqli_num_rows($ordersCreatedAtIndexResult) === 0) {
            mysqli_query($conn, "CREATE INDEX idx_orders_event_created_at ON orders(event_id, created_at)");
        }

        $ordersStatusIndexResult = mysqli_query($conn, "SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_event_status_created_at'");
        if (!$ordersStatusIndexResult || mysqli_num_rows($ordersStatusIndexResult) === 0) {
            mysqli_query($conn, "CREATE INDEX idx_orders_event_status_created_at ON orders(event_id, status, created_at)");
        }

        $legacyOrderNumberUniqueIndexes = mysqli_query(
            $conn,
            "SHOW INDEX FROM orders WHERE Column_name = 'order_number' AND Non_unique = 0"
        );
        if ($legacyOrderNumberUniqueIndexes) {
            $droppedKeys = [];
            while ($indexRow = mysqli_fetch_assoc($legacyOrderNumberUniqueIndexes)) {
                $keyName = $indexRow['Key_name'] ?? '';
                if ($keyName !== '' && $keyName !== 'PRIMARY' && !isset($droppedKeys[$keyName])) {
                    mysqli_query($conn, "ALTER TABLE orders DROP INDEX `{$keyName}`");
                    $droppedKeys[$keyName] = true;
                }
            }
        }

        $pubOrderColumnResult = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'pub_order_number'");
        if (!$pubOrderColumnResult || mysqli_num_rows($pubOrderColumnResult) === 0) {
            mysqli_query($conn, "ALTER TABLE orders ADD COLUMN pub_order_number INT UNSIGNED NULL");
        }

        $pubOrderUniqueIndexResult = mysqli_query($conn, "SHOW INDEX FROM orders WHERE Key_name = 'uq_orders_event_pub_order_number'");
        if (!$pubOrderUniqueIndexResult || mysqli_num_rows($pubOrderUniqueIndexResult) === 0) {
            mysqli_query($conn, "CREATE UNIQUE INDEX uq_orders_event_pub_order_number ON orders(event_id, pub_order_number)");
        }

        /* 3. Resolve Active Pub Context */
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

    /* 4. Item Table Performance Indexes */
        $hasOrderMilkshakesTable = mysqli_fetch_assoc(mysqli_query($conn, "SHOW TABLES LIKE 'order_milkshakes'"));
        if ($hasOrderMilkshakesTable) {
            $milkshakeOrderIdIndexResult = mysqli_query($conn, "SHOW INDEX FROM order_milkshakes WHERE Key_name = 'idx_order_milkshakes_order_id'");
            if (!$milkshakeOrderIdIndexResult || mysqli_num_rows($milkshakeOrderIdIndexResult) === 0) {
                mysqli_query($conn, "CREATE INDEX idx_order_milkshakes_order_id ON order_milkshakes(order_id)");
            }

            $milkshakeOrderStatusIndexResult = mysqli_query($conn, "SHOW INDEX FROM order_milkshakes WHERE Key_name = 'idx_order_milkshakes_order_status'");
            if (!$milkshakeOrderStatusIndexResult || mysqli_num_rows($milkshakeOrderStatusIndexResult) === 0) {
                mysqli_query($conn, "CREATE INDEX idx_order_milkshakes_order_status ON order_milkshakes(order_id, status)");
            }

            $milkshakeItemIndexResult = mysqli_query($conn, "SHOW INDEX FROM order_milkshakes WHERE Key_name = 'idx_order_milkshakes_milkshake_id'");
            if (!$milkshakeItemIndexResult || mysqli_num_rows($milkshakeItemIndexResult) === 0) {
                mysqli_query($conn, "CREATE INDEX idx_order_milkshakes_milkshake_id ON order_milkshakes(milkshake_id)");
            }
        }

        $hasOrderToastsTable = mysqli_fetch_assoc(mysqli_query($conn, "SHOW TABLES LIKE 'order_toasts'"));
        if ($hasOrderToastsTable) {
            $toastOrderIdIndexResult = mysqli_query($conn, "SHOW INDEX FROM order_toasts WHERE Key_name = 'idx_order_toasts_order_id'");
            if (!$toastOrderIdIndexResult || mysqli_num_rows($toastOrderIdIndexResult) === 0) {
                mysqli_query($conn, "CREATE INDEX idx_order_toasts_order_id ON order_toasts(order_id)");
            }

            $toastOrderStatusIndexResult = mysqli_query($conn, "SHOW INDEX FROM order_toasts WHERE Key_name = 'idx_order_toasts_order_status'");
            if (!$toastOrderStatusIndexResult || mysqli_num_rows($toastOrderStatusIndexResult) === 0) {
                mysqli_query($conn, "CREATE INDEX idx_order_toasts_order_status ON order_toasts(order_id, status)");
            }

            $toastItemIndexResult = mysqli_query($conn, "SHOW INDEX FROM order_toasts WHERE Key_name = 'idx_order_toasts_toast_id'");
            if (!$toastItemIndexResult || mysqli_num_rows($toastItemIndexResult) === 0) {
                mysqli_query($conn, "CREATE INDEX idx_order_toasts_toast_id ON order_toasts(toast_id)");
            }
        }

        /* 5. Pub Menu Link Tables */
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS pub_milkshakes (
                event_id INT UNSIGNED NOT NULL,
                milkshake_id INT UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (event_id, milkshake_id),
                CONSTRAINT fk_pub_milkshakes_event FOREIGN KEY (event_id) REFERENCES sales_events(event_id) ON DELETE CASCADE,
                CONSTRAINT fk_pub_milkshakes_item FOREIGN KEY (milkshake_id) REFERENCES milkshakes(milkshake_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci
        ");

        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS pub_toasts (
                event_id INT UNSIGNED NOT NULL,
                toast_id INT UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (event_id, toast_id),
                CONSTRAINT fk_pub_toasts_event FOREIGN KEY (event_id) REFERENCES sales_events(event_id) ON DELETE CASCADE,
                CONSTRAINT fk_pub_toasts_item FOREIGN KEY (toast_id) REFERENCES toasts(toast_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci
        ");

        return [
            'active_pub_id' => $activePubId,
            'active_pub_name' => $activePubName,
        ];
    }
}

if (!function_exists('ensure_pub_menu_links')) {
    function ensure_pub_menu_links($conn, $activePubId) {
        /* --- 2. Ensure Active Pub Menu Links --- */

        /* 1. Normalize Input */
        $activePubId = (int) $activePubId;

        /* 2. Seed Milkshake Links if Missing */
        $hasMilkshakeRows = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM pub_milkshakes WHERE event_id = $activePubId"
        ));

        if ((int) ($hasMilkshakeRows['c'] ?? 0) === 0) {
            mysqli_query(
                $conn,
                "INSERT INTO pub_milkshakes (event_id, milkshake_id, is_active)
                 SELECT $activePubId, milkshake_id, 1 FROM milkshakes"
            );
        }

        /* 3. Seed Toast Links if Missing */
        $hasToastRows = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM pub_toasts WHERE event_id = $activePubId"
        ));

        if ((int) ($hasToastRows['c'] ?? 0) === 0) {
            mysqli_query(
                $conn,
                "INSERT INTO pub_toasts (event_id, toast_id, is_active)
                 SELECT $activePubId, toast_id, 1 FROM toasts"
            );
        }
    }
}
