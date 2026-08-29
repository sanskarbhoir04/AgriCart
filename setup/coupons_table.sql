-- =====================================================
-- AgriCart — coupons table (run this once in phpMyAdmin / MySQL)
-- =====================================================
CREATE TABLE IF NOT EXISTS coupons (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    code              VARCHAR(30) NOT NULL UNIQUE,
    discount_type     ENUM('percent','flat') NOT NULL DEFAULT 'percent',
    discount_value    DECIMAL(10,2) NOT NULL,
    min_order_amount  DECIMAL(10,2) DEFAULT 0,
    is_active         TINYINT(1) NOT NULL DEFAULT 1,
    start_date        DATETIME NULL,
    expiry_date       DATETIME NULL,
    usage_limit       INT NULL,
    used_count        INT NOT NULL DEFAULT 0,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample coupons so the offer banner has something to show immediately.
-- Delete/edit these once you add your own real offers.
INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, is_active, expiry_date)
VALUES
    ('AGRI15', 'percent', 15.00, 500,  1, DATE_ADD(NOW(), INTERVAL 60 DAY)),
    ('FLAT50', 'flat',    50.00, 300,  1, DATE_ADD(NOW(), INTERVAL 30 DAY))
ON DUPLICATE KEY UPDATE code = code;
