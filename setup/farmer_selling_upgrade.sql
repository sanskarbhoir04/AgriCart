-- =====================================================================
-- farmer_selling_upgrade.sql — safe to run in phpMyAdmin any number of
-- times, on any AgriCart database, no matter which columns already
-- exist from a previous partial run.
-- (or `mysql agricart < farmer_selling_upgrade.sql`)
--
-- Adds the ability for a FARMER (logged-in user) to:
--   1) List their own produce for sale on the Marketplace, and
--   2) List their own equipment for rent on the Rental Hub,
-- with every self-listed item going through an Admin approval step,
-- and a platform commission percentage recorded on the item so the
-- farmer knows AgriCart's cut before they publish it (Amazon-style
-- "seller fee" model).
--
-- WHY THIS VERSION IS DIFFERENT: a plain `ALTER TABLE ... ADD COLUMN a,
-- ADD COLUMN b, ADD COLUMN c` aborts the WHOLE statement — including
-- columns b and c — the moment MySQL hits one column (a) that already
-- exists ("#1060 - Duplicate column"). That's exactly what happened if
-- you saw that error. This version checks INFORMATION_SCHEMA before
-- adding each column individually, so a column that already exists is
-- just skipped instead of blocking the rest.
-- =====================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS agri_add_column_if_missing$$
CREATE PROCEDURE agri_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS agri_add_unique_key_if_missing$$
CREATE PROCEDURE agri_add_unique_key_if_missing(
    IN p_table VARCHAR(64),
    IN p_key_name VARCHAR(64),
    IN p_columns VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_key_name
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD UNIQUE KEY `', p_key_name, '` (', p_columns, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- ---- products: who submitted it, is it approved yet, what's AgriCart's cut ----
CALL agri_add_column_if_missing('products', 'added_by_user_id', 'INT NULL DEFAULT NULL AFTER farmer_name');
CALL agri_add_column_if_missing('products', 'farmer_phone', 'VARCHAR(20) NULL DEFAULT NULL AFTER farmer_name');
CALL agri_add_column_if_missing('products', 'approval_status', "VARCHAR(20) NOT NULL DEFAULT 'approved' AFTER added_by_user_id");
CALL agri_add_column_if_missing('products', 'commission_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 5.00 AFTER approval_status');

-- ---- equipment: same idea — owner's own user account + approval + cut ----
CALL agri_add_column_if_missing('equipment', 'owner_user_id', 'INT NULL DEFAULT NULL AFTER owner_phone');
CALL agri_add_column_if_missing('equipment', 'approval_status', "VARCHAR(20) NOT NULL DEFAULT 'approved' AFTER owner_user_id");
CALL agri_add_column_if_missing('equipment', 'commission_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 10.00 AFTER approval_status');

-- ---- order_items / equipment_bookings: record the platform charge that
--      applied at the time of the sale/booking, so it doesn't change
--      retroactively if the seller's commission % is edited later ----
CALL agri_add_column_if_missing('order_items', 'commission_percent', 'DECIMAL(5,2) NULL DEFAULT NULL');
CALL agri_add_column_if_missing('order_items', 'commission_amount', 'DECIMAL(10,2) NULL DEFAULT NULL');

CALL agri_add_column_if_missing('equipment_bookings', 'commission_percent', 'DECIMAL(5,2) NULL DEFAULT NULL');
CALL agri_add_column_if_missing('equipment_bookings', 'commission_amount', 'DECIMAL(10,2) NULL DEFAULT NULL');

-- approval_status values used by the app: 'pending', 'approved', 'rejected'
-- existing rows default to 'approved' so nothing already live gets hidden.

-- ---- reviews: one review per user per item (re-submitting updates it
--      instead of piling up duplicates) ----
CALL agri_add_unique_key_if_missing('reviews', 'uniq_user_item', 'item_type, item_id, user_id');

-- ---- cleanup: drop the helper procedures, job's done ----
DROP PROCEDURE IF EXISTS agri_add_column_if_missing;
DROP PROCEDURE IF EXISTS agri_add_unique_key_if_missing;

-- ---- sanity check: confirm the columns this app depends on now exist ----
SELECT
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'approval_status') AS products_approval_status_ok,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'farmer_phone') AS products_farmer_phone_ok,
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment' AND COLUMN_NAME = 'approval_status') AS equipment_approval_status_ok;
-- Each column above should show 1 (present) — if any shows 0, that ALTER
-- failed and the error above it will say why (e.g. table itself missing).
