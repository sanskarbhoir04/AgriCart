-- =====================================================================
-- AgriCart — Email OTP verification upgrade
--
-- Adds email_verified / mobile_verified flags + unique indexes to
-- `users`, and creates the otp_rate_limits table used for server-side
-- OTP send throttling (per email + per IP).
--
-- Safe to run multiple times: every statement checks for existing
-- columns/indexes first, matching the pattern used in migrations.sql.
-- Review against your actual schema before running in production.
-- =====================================================================

DELIMITER $$
DROP PROCEDURE IF EXISTS agri_otp_add_column_if_missing $$
CREATE PROCEDURE agri_otp_add_column_if_missing(
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

DROP PROCEDURE IF EXISTS agri_otp_add_unique_if_missing $$
CREATE PROCEDURE agri_otp_add_unique_if_missing(
    IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_definition VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD UNIQUE KEY `', p_index, '` ', p_definition);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

-- ---------------------------------------------------------------------
-- 1) Verification flags.
--    mobile_verified stays 0 — AgriCart currently verifies the EMAIL
--    address only (free, no paid SMS API). Never set this to 1 from
--    application code until a real SMS-based mobile OTP is added.
-- ---------------------------------------------------------------------
CALL agri_otp_add_column_if_missing('users', 'email_verified',  'TINYINT(1) NOT NULL DEFAULT 0');
CALL agri_otp_add_column_if_missing('users', 'mobile_verified', 'TINYINT(1) NOT NULL DEFAULT 0');

-- ---------------------------------------------------------------------
-- 2) Uniqueness at the database layer (defense in depth — the app
--    already checks for duplicates before INSERT, but a unique index
--    closes the race-condition window between check and insert).
--    NOTE: if your existing data already has duplicate/blank emails or
--    mobiles, these ALTERs will fail — clean up duplicates first.
-- ---------------------------------------------------------------------
CALL agri_otp_add_unique_if_missing('users', 'unique_email',  '(email)');
CALL agri_otp_add_unique_if_missing('users', 'unique_mobile', '(mobile)');

-- ---------------------------------------------------------------------
-- 3) Server-side OTP send rate limiting (per email address + per IP).
--    One row per OTP send attempt; old rows can be purged periodically
--    (e.g. a cron job deleting rows older than 1 day) but are cheap
--    enough to leave as-is for typical traffic.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS otp_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_rl_email_time (email, sent_at),
    KEY idx_otp_rl_ip_time (ip_address, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cleanup helper procedures (optional — comment out if you'd rather keep them)
DROP PROCEDURE IF EXISTS agri_otp_add_column_if_missing;
DROP PROCEDURE IF EXISTS agri_otp_add_unique_if_missing;
