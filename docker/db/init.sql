SET NAMES utf8mb4 COLLATE utf8mb4_swedish_ci;

ALTER DATABASE flashit_milkshakepub
    CHARACTER SET = utf8mb4
    COLLATE = utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NULL,
    order_number VARCHAR(50) NOT NULL,
    pub_order_number INT UNSIGNED NULL,
    customer_name VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    order_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orders_event_pub_order_number (event_id, pub_order_number),
    KEY idx_orders_event_created_at (event_id, created_at),
    KEY idx_orders_event_status_created_at (event_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS sales_events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(120) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

INSERT INTO sales_events (event_name, is_active)
SELECT 'Initial Event', 1
WHERE NOT EXISTS (SELECT 1 FROM sales_events);

-- Ensure orders table has event tracking columns (for upgrades)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'event_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE orders ADD COLUMN event_id INT UNSIGNED NULL', 'SELECT "event_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'pub_order_number');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE orders ADD COLUMN pub_order_number INT UNSIGNED NULL', 'SELECT "pub_order_number already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure indexes exist
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_orders_event_id');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_orders_event_id ON orders(event_id)', 'SELECT "idx_orders_event_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'uq_orders_event_pub_order_number');
SET @sql = IF(@idx_exists = 0, 'CREATE UNIQUE INDEX uq_orders_event_pub_order_number ON orders(event_id, pub_order_number)', 'SELECT "uq already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_orders_event_created_at');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_orders_event_created_at ON orders(event_id, created_at)', 'SELECT "idx_orders_event_created_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_orders_event_status_created_at');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_orders_event_status_created_at ON orders(event_id, status, created_at)', 'SELECT "idx_orders_event_status_created_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS milkshakes (
    milkshake_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    ingredients TEXT,
    color VARCHAR(50) NOT NULL DEFAULT '#FFFFFF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS toasts (
    toast_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    ingredients TEXT,
    color VARCHAR(50) NOT NULL DEFAULT '#FFFFFF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS pub_milkshakes (
    event_id INT UNSIGNED NOT NULL,
    milkshake_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (event_id, milkshake_id),
    CONSTRAINT fk_pub_milkshakes_event
        FOREIGN KEY (event_id)
        REFERENCES sales_events(event_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pub_milkshakes_item
        FOREIGN KEY (milkshake_id)
        REFERENCES milkshakes(milkshake_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS pub_toasts (
    event_id INT UNSIGNED NOT NULL,
    toast_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (event_id, toast_id),
    CONSTRAINT fk_pub_toasts_event
        FOREIGN KEY (event_id)
        REFERENCES sales_events(event_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pub_toasts_item
        FOREIGN KEY (toast_id)
        REFERENCES toasts(toast_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS order_milkshakes (
    order_milkshake_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    milkshake_id INT UNSIGNED NOT NULL,
    comment TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    KEY idx_order_milkshakes_order_id (order_id),
    KEY idx_order_milkshakes_order_status (order_id, status),
    KEY idx_order_milkshakes_milkshake_id (milkshake_id),
    CONSTRAINT fk_order_milkshakes_order
        FOREIGN KEY (order_id)
        REFERENCES orders(order_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_milkshakes_milkshake
        FOREIGN KEY (milkshake_id)
        REFERENCES milkshakes(milkshake_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS order_toasts (
    order_toast_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    toast_id INT UNSIGNED NOT NULL,
    comment TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    KEY idx_order_toasts_order_id (order_id),
    KEY idx_order_toasts_order_status (order_id, status),
    KEY idx_order_toasts_toast_id (toast_id),
    CONSTRAINT fk_order_toasts_order
        FOREIGN KEY (order_id)
        REFERENCES orders(order_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_toasts_toast
        FOREIGN KEY (toast_id)
        REFERENCES toasts(toast_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

INSERT INTO milkshakes (name, description, ingredients, color)
VALUES
    ('Oreo milkshake', 'Milkshake med vaniljglass och Oreo', 'Mjölk, Glass, Oreobitar', '#7e4a27'),
    ('Daim milkshake', 'Milkshake med Daim-karameller', 'Mjölk, Glass, Daim', '#c1af28'),
    ('Lakris milkshake', 'Milkshake med lakriskarameller', 'Mjölk, Glass, Lakris godis', '#322e2f')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO toasts (name, description, ingredients, color)
VALUES
    ('Standard toast', 'Toast med skinka och ost', 'Skinka, Ost, Pesto, Tomatpuré, Oregano, Smör', '#F7C87D'),
    ('Chilicheese toast', 'Chilicheese på rostatbröd', 'chillicheese', '#B8D98B'),
    ('Desert toast', 'Efterrätts toast med choklad och banan', 'Choklad, Banan', '#ce915c')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO pub_milkshakes (event_id, milkshake_id, is_active)
SELECT e.event_id, m.milkshake_id, 1
FROM sales_events e
CROSS JOIN milkshakes m
LEFT JOIN pub_milkshakes pm
    ON pm.event_id = e.event_id
    AND pm.milkshake_id = m.milkshake_id
WHERE pm.event_id IS NULL;

INSERT INTO pub_toasts (event_id, toast_id, is_active)
SELECT e.event_id, t.toast_id, 1
FROM sales_events e
CROSS JOIN toasts t
LEFT JOIN pub_toasts pt
    ON pt.event_id = e.event_id
    AND pt.toast_id = t.toast_id
WHERE pt.event_id IS NULL;

-- Ensure order_milkshakes indexes exist (for upgrades)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'order_milkshakes' AND index_name = 'idx_order_milkshakes_order_id');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_order_milkshakes_order_id ON order_milkshakes(order_id)', 'SELECT "idx exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'order_milkshakes' AND index_name = 'idx_order_milkshakes_order_status');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_order_milkshakes_order_status ON order_milkshakes(order_id, status)', 'SELECT "idx exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'order_milkshakes' AND index_name = 'idx_order_milkshakes_milkshake_id');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_order_milkshakes_milkshake_id ON order_milkshakes(milkshake_id)', 'SELECT "idx exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure order_toasts indexes exist (for upgrades)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'order_toasts' AND index_name = 'idx_order_toasts_order_id');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_order_toasts_order_id ON order_toasts(order_id)', 'SELECT "idx exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'order_toasts' AND index_name = 'idx_order_toasts_order_status');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_order_toasts_order_status ON order_toasts(order_id, status)', 'SELECT "idx exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() AND table_name = 'order_toasts' AND index_name = 'idx_order_toasts_toast_id');
SET @sql = IF(@idx_exists = 0, 'CREATE INDEX idx_order_toasts_toast_id ON order_toasts(toast_id)', 'SELECT "idx exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;