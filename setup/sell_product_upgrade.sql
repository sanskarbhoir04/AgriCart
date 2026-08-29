-- =====================================================================
-- setup/sell_product_upgrade.sql
-- Run this ONCE in phpMyAdmin / MySQL CLI on the AgriCart database
-- before using the updated "Sell Your Product" feature.
--
-- What this adds (all additive — nothing existing is renamed, dropped,
-- or overwritten):
--
--   products.name              -> already exists, keeps being used as the
--                                  ENGLISH display name (product_name_en)
--   products.name_mr           -> already exists, keeps being used as the
--                                  MARATHI display name (product_name_mr)
--   products.name_hi           -> NEW: HINDI display name (product_name_hi)
--   products.name_original     -> NEW: exactly what the seller typed,
--                                  never overwritten by translation
--   products.original_language -> NEW: 'en' | 'mr' | 'hi' — the detected
--                                  or user-selected input language
--   products.brand              -> NEW: Brand / Company Name
--   products.product_condition  -> NEW: 'new' | 'used'
--   products.seller_email       -> NEW
--   products.seller_village     -> NEW: Village or City
--   products.seller_district    -> NEW
--   products.seller_address     -> NEW: Full address
--   products.delivery_available -> NEW: 1 = Yes, 0 = No
--
--   product_images  -> NEW table for multi-image listings. `products.image`
--                       is still kept in sync with the first uploaded image
--                       so every existing page that reads `products.image`
--                       (marketplace thumbnails, admin table, etc.) keeps
--                       working unchanged.
--
-- Safe to re-run: each ALTER TABLE is guarded so it won't error out if the
-- column already exists (MySQL 8.0.29+ / MariaDB 10.5+ syntax). If your
-- server is older and a statement below errors with "Duplicate column
-- name", that just means it was already applied — skip it and continue.
-- =====================================================================

ALTER TABLE products ADD COLUMN IF NOT EXISTS name_hi VARCHAR(255) NULL AFTER name_mr;
ALTER TABLE products ADD COLUMN IF NOT EXISTS name_original VARCHAR(255) NULL AFTER name_hi;
ALTER TABLE products ADD COLUMN IF NOT EXISTS original_language VARCHAR(10) NULL DEFAULT 'en' AFTER name_original;

ALTER TABLE products ADD COLUMN IF NOT EXISTS brand VARCHAR(150) NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS product_condition ENUM('new','used') NOT NULL DEFAULT 'new';

ALTER TABLE products ADD COLUMN IF NOT EXISTS seller_email VARCHAR(150) NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS seller_village VARCHAR(150) NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS seller_district VARCHAR(150) NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS seller_address VARCHAR(255) NULL;

ALTER TABLE products ADD COLUMN IF NOT EXISTS delivery_available TINYINT(1) NOT NULL DEFAULT 0;

-- Make sure text columns can store Marathi / Hindi correctly.
ALTER TABLE products
    MODIFY name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    MODIFY name_mr VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    MODIFY name_hi VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    MODIFY name_original VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_images_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
