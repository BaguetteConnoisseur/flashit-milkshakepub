SET NAMES utf8mb4 COLLATE utf8mb4_swedish_ci;

ALTER DATABASE flashit_milkshakepub
    CHARACTER SET = utf8mb4
    COLLATE = utf8mb4_swedish_ci;

CREATE TABLE IF NOT EXISTS orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    order_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS event_id INT UNSIGNED NULL,
    ADD INDEX IF NOT EXISTS idx_orders_event_id (event_id);

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

CREATE TABLE IF NOT EXISTS order_milkshakes (
    order_milkshake_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    milkshake_id INT UNSIGNED NOT NULL,
    comment TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
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
