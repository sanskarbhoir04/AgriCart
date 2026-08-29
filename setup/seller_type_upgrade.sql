-- =====================================================================
-- AgriCart — "Become a Seller" preference (Product / Rental / Both)
-- Adds seller_type to seller_payout_profiles so the seller dashboard
-- knows which sections (product listings vs. equipment rental) to show
-- for each seller. Safe to run multiple times.
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
DELIMITER ;

-- NULL = user hasn't chosen yet (or is a legacy seller from before this
-- feature existed — dashboard.php treats NULL as "both" for anyone who
-- already has product/equipment listings, so nothing breaks for them).
CALL agri_add_column_if_missing('seller_payout_profiles', 'seller_type',
    "ENUM('product','rental','both') NULL DEFAULT NULL AFTER user_id");

-- Timestamp of when the seller accepted the Seller Terms & Conditions
-- on the "Become a Seller" form.
CALL agri_add_column_if_missing('seller_payout_profiles', 'terms_accepted_at',
    "TIMESTAMP NULL DEFAULT NULL AFTER seller_type");

DROP PROCEDURE IF EXISTS agri_add_column_if_missing;
