-- 1. DATABASE INITIALIZATION
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 2. SALES EVENTS (The "MSP" sessions)
-- We track each night/event separately so history is never lost.
CREATE TABLE IF NOT EXISTS sales_events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(120) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1 -- Only one should be 1 at a time
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- 3. THE MASTER MENU
-- Instead of two tables, we use one 'category' column. This makes the code much cleaner.
CREATE TABLE IF NOT EXISTS menu_items (
    item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('milkshake', 'toast', 'other') NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    ingredients TEXT,
    color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    is_archived TINYINT(1) NOT NULL DEFAULT 0, -- Set to 1 to "delete" without breaking old orders
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- 4. EVENT-ITEM MAPPING (The "Lager")
-- This table determines which items from the Master Menu are available for a SPECIFIC event.
CREATE TABLE IF NOT EXISTS event_menu_items (
    event_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1, -- Toggle availability for just this night
    PRIMARY KEY (event_id, item_id),
    CONSTRAINT fk_emi_event FOREIGN KEY (event_id) REFERENCES sales_events(event_id) ON DELETE CASCADE,
    CONSTRAINT fk_emi_item FOREIGN KEY (item_id) REFERENCES menu_items(item_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. ORDERS
-- The master record for a customer's purchase.
CREATE TABLE IF NOT EXISTS orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(50) NOT NULL,      -- Unique string (e.g. UUID or formatted)
    pub_order_number INT UNSIGNED NULL,     -- The "Daily" number (1, 2, 3...)
    customer_name VARCHAR(100) NOT NULL,
    status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
    order_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_event FOREIGN KEY (event_id) REFERENCES sales_events(event_id),
    UNIQUE KEY uq_event_pub_num (event_id, pub_order_number), -- Prevent duplicate order numbers in one night
    INDEX idx_order_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- 6. ORDER LINE ITEMS
-- Tracks the specific items within an order.
CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    item_comment TEXT, 
    status ENUM('Pending', 'Ready', 'Served') DEFAULT 'Pending',
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_items_master FOREIGN KEY (item_id) REFERENCES menu_items(item_id),
    INDEX idx_line_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- 7. SEED DATA (Initial Setup)
-- Insert the first event
INSERT INTO sales_events (event_name, is_active) 
VALUES ('Premiärpuben', 1);

-- Insert starting inventory
INSERT INTO menu_items (category, name, description, ingredients, color) VALUES
('milkshake', 'Oreo Supreme', 'Klassisk Oreo-dröm', 'Mjölk, Glass, Oreo', '#3d3d3d'),
('milkshake', 'Daim-Licious', 'Krispig Daim-milkshake', 'Mjölk, Glass, Daim', '#c1af28'),
('toast', 'Standarden', 'Skinka & Ost', 'Bröd, Skinka, Ost, Senap', '#F7C87D'),
('toast', 'Chilicheese', 'Stark och krämig', 'Bröd, Chilicheese-röra, Jalapenos', '#B8D98B');

-- Link all items to the first event automatically
INSERT INTO event_menu_items (event_id, item_id, is_active)
SELECT 1, item_id, 1 FROM menu_items;

SET FOREIGN_KEY_CHECKS = 1;