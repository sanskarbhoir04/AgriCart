-- =====================================================================
-- setup/payment_verification_upgrade.sql — run this ONCE
-- (mysql agricart < setup/payment_verification_upgrade.sql, or paste
-- into phpMyAdmin's SQL tab)
--
-- WHAT THIS FIXES
--   Previously, pages/confirm_payment.php let a logged-in user mark
--   their OWN booking as "paid" just by clicking a button — no proof
--   of payment was checked by anyone. This migration adds the columns
--   needed for a real verification flow:
--
--     pending  -> user hasn't submitted payment proof yet
--     verification_pending -> user submitted proof, admin hasn't reviewed
--     paid     -> admin verified and approved
--     failed   -> admin rejected the submitted proof
--     refunded -> admin processed a refund after a paid booking
--     cod      -> unchanged, Cash on Delivery (no online proof needed)
--
--   `payment_status` stays a VARCHAR(20) (as it already was), so no
--   existing rows or enum constraint break — these are just new
--   allowed string values the application now writes/reads.
--
-- SAFE TO RE-RUN: every ALTER is guarded by an information_schema
-- check, same pattern as setup/admin_rbac.sql.
-- =====================================================================

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_bookings' AND COLUMN_NAME = 'payment_method');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE equipment_bookings ADD COLUMN payment_method VARCHAR(20) NULL DEFAULT NULL AFTER payment_status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_bookings' AND COLUMN_NAME = 'transaction_id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE equipment_bookings ADD COLUMN transaction_id VARCHAR(100) NULL DEFAULT NULL AFTER payment_method', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Unique-when-present index: blocks the same UTR/transaction ID being
-- submitted for two different bookings, without breaking rows that
-- have NULL (MySQL treats multiple NULLs as distinct under a UNIQUE
-- index, so old/COD/unpaid bookings are unaffected).
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_bookings' AND INDEX_NAME = 'uq_transaction_id');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE equipment_bookings ADD UNIQUE KEY uq_transaction_id (transaction_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_bookings' AND COLUMN_NAME = 'payment_submitted_at');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE equipment_bookings ADD COLUMN payment_submitted_at DATETIME NULL DEFAULT NULL AFTER transaction_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_bookings' AND COLUMN_NAME = 'payment_screenshot');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE equipment_bookings ADD COLUMN payment_screenshot VARCHAR(255) NULL DEFAULT NULL AFTER payment_submitted_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_bookings' AND COLUMN_NAME = 'admin_verification_note');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE equipment_bookings ADD COLUMN admin_verification_note VARCHAR(500) NULL DEFAULT NULL AFTER payment_screenshot', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_bookings' AND COLUMN_NAME = 'payment_verified_by');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE equipment_bookings ADD COLUMN payment_verified_by INT NULL DEFAULT NULL AFTER admin_verification_note', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_bookings' AND COLUMN_NAME = 'payment_verified_at');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE equipment_bookings ADD COLUMN payment_verified_at DATETIME NULL DEFAULT NULL AFTER payment_verified_by', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Helps the admin verification queue page (WHERE payment_status = 'verification_pending')
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'equipment_bookings' AND INDEX_NAME = 'idx_payment_status');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE equipment_bookings ADD INDEX idx_payment_status (payment_status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- New RBAC permission for reviewing/approving submitted payment proof.
-- Kept separate from rental_bookings.confirm (booking accept/reject)
-- since verifying money and accepting a rental request are different
-- responsibilities. Safe to re-run (INSERT IGNORE / ON DUPLICATE).
-- ---------------------------------------------------------------------
INSERT IGNORE INTO admin_permissions (permission_key, module_name, action_name, description) VALUES
('rental_bookings.verify_payment', 'rental_bookings', 'verify_payment', 'Approve or reject submitted rental payment proof');

-- Grant to Super Admin (already gets everything via isSuperAdmin(),
-- but this keeps admin_role_permissions consistent for reporting) and
-- to Rental Manager, who already handles rental_bookings.confirm.
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'super_admin' AND p.permission_key = 'rental_bookings.verify_payment'
ON DUPLICATE KEY UPDATE allowed = 1;

INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'rental_manager' AND p.permission_key = 'rental_bookings.verify_payment'
ON DUPLICATE KEY UPDATE allowed = 1;

-- After running this, deploy the updated pages/payment.php,
-- pages/confirm_payment.php and the new admin/payment_verification.php.
