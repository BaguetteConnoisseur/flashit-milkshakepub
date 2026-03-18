-- 1. DATABASE INITIALIZATION
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 2. PUB EVENTS
CREATE TABLE IF NOT EXISTS pub_events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(120) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- 3. THE MASTER MENU (Updated with SLUG)
CREATE TABLE IF NOT EXISTS menu_items (
    item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL, -- The "Obvious" ID (e.g., 'oreo-supreme')
    category ENUM('milkshake', 'toast', 'other') NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    ingredients TEXT,
    color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    is_archived TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- 4. PUB-EVENT-ITEM MAPPING
CREATE TABLE IF NOT EXISTS event_menu_items (
    event_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (event_id, item_id),
    CONSTRAINT fk_emi_event FOREIGN KEY (event_id) REFERENCES pub_events(event_id) ON DELETE CASCADE,
    CONSTRAINT fk_emi_item FOREIGN KEY (item_id) REFERENCES menu_items(item_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. ORDERS
CREATE TABLE IF NOT EXISTS orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    order_number INT UNSIGNED NULL,
    customer_name VARCHAR(100) NOT NULL,
    status ENUM('Pending', 'In Progress', 'Done', 'Delivered') DEFAULT 'Pending',
    order_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_event FOREIGN KEY (event_id) REFERENCES pub_events(event_id),
    UNIQUE KEY uq_event_order_num (event_id, order_number),
    INDEX idx_order_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- 6. ORDER LINE ITEMS
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    item_comment TEXT, 
    status ENUM('Pending', 'In Progress', 'Done', 'Delivered') DEFAULT 'Pending',
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_items_master FOREIGN KEY (item_id) REFERENCES menu_items(item_id),
    INDEX idx_line_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

INSERT INTO pub_events (event_name, is_active) 
VALUES ('Premiärpuben', 1);

INSERT INTO menu_items (slug, category, name, description, ingredients, color) VALUES
('oreo-supreme',   'milkshake', 'Oreo Supreme', 'Klassisk Oreo-dröm', 'Mjölk, Glass, Oreo', '#3d3d3d'),
('daim-licious',   'milkshake', 'Daim-Licious', 'Krispig Daim-milkshake', 'Mjölk, Glass, Daim', '#c1af28'),
('toast-standard', 'toast',     'Standarden', 'Skinka & Ost', 'Bröd, Skinka, Ost, Senap', '#F7C87D'),
('toast-chili',    'toast',     'Chilicheese', 'Stark och krämig', 'Bröd, Chilicheese-röra, Jalapenos', '#B8D98B');

-- Link all items to the first event
INSERT INTO event_menu_items (event_id, item_id, is_active)
SELECT 1, item_id, 1 FROM menu_items;

SET FOREIGN_KEY_CHECKS = 1;