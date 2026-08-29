-- =====================================================================
-- setup/order_history_delete_upgrade.sql — run this ONCE
--
-- Adds a soft-delete column so a user can remove an order from their
-- own "My Orders" view without deleting the actual order record (the
-- order stays intact for the seller/admin side — accounting, sales
-- reports, dispute history, etc. all still need it).
--
-- Safe to re-run (information_schema-guarded, same pattern as the
-- project's other migrations).
-- =====================================================================

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'hidden_by_user_at');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE orders ADD COLUMN hidden_by_user_at DATETIME NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
