-- =====================================================================
-- orders_setup.sql — run this ONCE in phpMyAdmin (or `mysql < orders_setup.sql`)
-- Creates the `orders` and `order_items` tables used by place_order.php,
-- get_my_orders.php and the admin orders panel.
--
-- This is very likely why checkout/payment was failing: place_order.php
-- was trying to INSERT into these two tables, but they didn't exist yet
-- in the `agricart` database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_number VARCHAR(40) NOT NULL UNIQUE,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_mode VARCHAR(20) NOT NULL DEFAULT 'cod',      -- 'cod' or 'online'
    payment_status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending / paid / failed
    order_status VARCHAR(20) NOT NULL DEFAULT 'placed',    -- placed / packed / shipped / delivered / cancelled
    delivery_name VARCHAR(150) NOT NULL,
    delivery_mobile VARCHAR(15) NOT NULL,
    delivery_address VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,2) NOT NULL,
    INDEX idx_order (order_id)
);

-- After running this, checkout / "Confirm Order" should work and
-- placed orders will show up under "My Orders".
