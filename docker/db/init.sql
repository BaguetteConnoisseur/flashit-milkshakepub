CREATE TABLE IF NOT EXISTS orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    order_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS milkshakes (
    milkshake_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    ingredients TEXT,
    color VARCHAR(50) NOT NULL DEFAULT '#FFFFFF'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS toasts (
    toast_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    ingredients TEXT,
    color VARCHAR(50) NOT NULL DEFAULT '#FFFFFF'
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
