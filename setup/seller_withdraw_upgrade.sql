-- =====================================================================
-- setup/seller_withdraw_upgrade.sql
-- AgriCart — Seller "Withdraw Funds" upgrade.
--
-- WHAT THIS DOES
--   The `payouts` table (created by setup/seller_dashboard_upgrade.sql)
--   already stores every payout/withdrawal request a seller makes, but
--   its `status` column only allowed pending / processing / completed.
--   This migration adds a `rejected` status so Admin can turn down a
--   withdrawal request (and the held amount is refunded back to the
--   seller's available balance by admin/payout_action.php).
--
-- HOW TO RUN
--   mysql -u root agricart < setup/seller_withdraw_upgrade.sql
--   -- or paste into phpMyAdmin's SQL tab on the `agricart` database.
--
-- SAFE TO RE-RUN — uses MODIFY COLUMN, which is idempotent.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE payouts
    MODIFY COLUMN status ENUM('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending';

-- Belt-and-braces: make sure the columns admin/payout_action.php and
-- seller/seller_api.php rely on exist (no-ops if already present from
-- setup/seller_dashboard_upgrade.sql).
ALTER TABLE payouts ADD COLUMN IF NOT EXISTS account_details VARCHAR(255) NULL AFTER method;
ALTER TABLE payouts ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL AFTER status;
ALTER TABLE payouts ADD COLUMN IF NOT EXISTS completed_by_admin_id INT NULL AFTER completed_at;
ALTER TABLE payouts ADD COLUMN IF NOT EXISTS notes VARCHAR(255) NULL AFTER completed_by_admin_id;
