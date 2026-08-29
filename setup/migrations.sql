-- =====================================================================
-- AgriCart — Migration script for the marketplace.php security/feature update
-- Safe to run multiple times: every statement checks for existing
-- columns/indexes first (via a small procedure) so re-running won't error
-- out on a database that already has some of these.
-- Review each block against your actual schema before running in production.
-- =====================================================================

DELIMITER $$
DROP PROCEDURE IF EXISTS agri_add_column_if_missing $$
CREATE PROCEDURE agri_add_column_if_missing(
    IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS agri_add_index_if_missing $$
CREATE PROCEDURE agri_add_index_if_missing(
    IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_definition VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` ', p_definition);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 1) Product approval workflow (item 2) — required for the fail-safe
--    pending-product filter in marketplace.php to have something to filter on.
-- ---------------------------------------------------------------------
CALL agri_add_column_if_missing('products', 'approval_status',
    "ENUM('pending','approved','rejected') NULL DEFAULT NULL");
CALL agri_add_index_if_missing('products', 'idx_product_visibility', '(is_active, approval_status)');

-- ---------------------------------------------------------------------
-- 2) AI suggestion crop tags (item 11)
-- ---------------------------------------------------------------------
CALL agri_add_column_if_missing('products', 'crop_tags', "VARCHAR(500) NULL");

-- ---------------------------------------------------------------------
-- 3) Idempotency support for place_order.php (items 7, 23) — stops a
--    double-click / retried request from creating two orders.
-- ---------------------------------------------------------------------
CALL agri_add_column_if_missing('orders', 'idempotency_key', "VARCHAR(64) NULL");
CALL agri_add_index_if_missing('orders', 'idx_orders_idempotency', '(user_id, idempotency_key)');

-- ---------------------------------------------------------------------
-- 4) Payment status (item 15) — an order is never marked "paid" just
--    because Confirm Order was clicked; only a verified gateway callback
--    (future work) should ever set this to 'paid'.
-- ---------------------------------------------------------------------
CALL agri_add_column_if_missing('orders', 'payment_status',
    "ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending'");

-- ---------------------------------------------------------------------
-- 5) Per-user coupon usage tracking (item 14 — per-user usage limit)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupon_usages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT NULL,
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_coupon_user (coupon_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6) General performance indexes (item 27)
-- ---------------------------------------------------------------------
CALL agri_add_index_if_missing('products', 'idx_products_active_approval', '(is_active, approval_status)');
CALL agri_add_index_if_missing('products', 'idx_products_category', '(category)');
CALL agri_add_index_if_missing('products', 'idx_products_stock', '(stock)');

CALL agri_add_index_if_missing('reviews', 'idx_reviews_item', '(item_type, item_id)');
CALL agri_add_index_if_missing('reviews', 'idx_reviews_user_item', '(user_id, item_type, item_id)');

CALL agri_add_index_if_missing('orders', 'idx_orders_user_created', '(user_id, created_at)');
CALL agri_add_index_if_missing('orders', 'idx_orders_status', '(order_status)');

CALL agri_add_index_if_missing('coupons', 'idx_coupons_code_active', '(code, is_active)');

-- Cleanup helper procedures (optional — comment out if you'd rather keep them)
DROP PROCEDURE IF EXISTS agri_add_column_if_missing;
DROP PROCEDURE IF EXISTS agri_add_index_if_missing;
