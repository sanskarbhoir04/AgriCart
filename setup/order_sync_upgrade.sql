-- =====================================================================
-- setup/order_sync_upgrade.sql
-- AgriCart — Synchronized Order Management System.
--
-- Adds the single source of truth for order-status change tracking used
-- by admin/order_action.php, seller/seller_api.php, includes/order_sync.php
-- and pages/get_my_orders.php:
--
--   1) order_status_history — one row per status change, at either the
--      whole-order level (order_item_id NULL, e.g. an admin action or the
--      computed/aggregate order-level status) or a single seller's item
--      level (order_item_id set, e.g. a seller action). This is the audit
--      trail shown on both the Seller and Admin order-details pages.
--   2) user_notifications — buyer-facing notifications (mirrors the
--      existing seller_notifications table) so a buyer sees "Your order
--      was Confirmed / Shipped / Delivered" style alerts.
--   3) orders.updated_at — bumped on every status change so the buyer/
--      seller/admin polling endpoints can cheaply detect "did anything
--      change" without re-diffing full rows.
--
-- Everything below is ADDITIVE and GUARDED — safe to re-run, nothing
-- existing is renamed, dropped, or overwritten (same style as the
-- project's other setup/*.sql migrations).
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. order_status_history
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`          INT NOT NULL,
    `order_item_id`     INT NULL DEFAULT NULL COMMENT 'NULL = whole-order / aggregate entry, set = one sellers item',
    `previous_status`   VARCHAR(20) NULL DEFAULT NULL,
    `new_status`        VARCHAR(20) NOT NULL,
    `changed_by_user_id` INT NULL DEFAULT NULL,
    `changed_by_role`   ENUM('buyer','seller','admin','system') NOT NULL DEFAULT 'system',
    `changed_by_name`   VARCHAR(150) NULL DEFAULT NULL,
    `reason`            VARCHAR(255) NULL DEFAULT NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_osh_order` (`order_id`, `created_at`),
    KEY `idx_osh_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. user_notifications (buyer-facing; mirrors seller_notifications)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_notifications` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT NOT NULL,
    `type`       VARCHAR(60) NOT NULL DEFAULT 'order_status',
    `title`      VARCHAR(200) NOT NULL,
    `message`    VARCHAR(500) NOT NULL,
    `link`       VARCHAR(255) NULL DEFAULT NULL,
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user_notif_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. orders.updated_at — bumped automatically on every UPDATE so the
--    polling endpoints can order by "most recently changed".
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Helps "what changed recently" polling / sorting.
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_updated');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE orders ADD INDEX idx_orders_updated (updated_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 4. One-time backfill: give every existing order a single history row
--    reflecting its current status, so the timeline UI never shows a
--    blank history for orders placed before this upgrade. Guarded so
--    re-running this script never creates duplicate backfill rows.
-- ---------------------------------------------------------------------
INSERT INTO order_status_history (order_id, order_item_id, previous_status, new_status, changed_by_role, reason, created_at)
SELECT o.id, NULL, NULL, o.order_status, 'system', 'Order history tracking enabled', o.created_at
FROM orders o
WHERE NOT EXISTS (SELECT 1 FROM order_status_history h WHERE h.order_id = o.id);

-- Done. Next: no further manual steps — includes/order_sync.php uses
-- these tables automatically from admin/order_action.php and
-- seller/seller_api.php.
