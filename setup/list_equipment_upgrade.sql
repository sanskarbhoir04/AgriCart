-- =====================================================================
-- setup/list_equipment_upgrade.sql
-- Run this ONCE in phpMyAdmin / MySQL CLI on the AgriCart database
-- before using the updated "List Your Equipment for Rent" feature.
--
-- Additive only — nothing existing is renamed, dropped, or overwritten.
-- Existing columns kept exactly as-is and reused:
--   equipment.name       -> stays the ENGLISH display name (equipment_name_en)
--   equipment.name_mr    -> stays the MARATHI display name (equipment_name_mr)
--   equipment.rent_per_day / rent_per_hour -> unchanged, still used when
--                            rent_type = 'day' / 'hour'
--   equipment.type        -> unchanged (category); new dropdown values are
--                            just new possible strings for this same column
--                            (power_tiller, rotavator, cultivator, seed_drill,
--                            sprayer, thresher — plus existing tractor,
--                            harvester, drone, other)
--
-- New columns / tables added below:
--   equipment.name_hi              -> HINDI display name (equipment_name_hi)
--   equipment.name_original        -> exactly what the owner typed, never
--                                      overwritten by translation
--   equipment.original_language    -> 'en' | 'mr' | 'hi'
--   equipment.brand, model, manufacturing_year, hp already exists (reused)
--   equipment.equipment_condition  -> 'excellent' | 'good' | 'average'
--   equipment.rent_type            -> 'hour' | 'day' | 'acre'
--   equipment.rent_per_acre        -> new (rent_per_day/rent_per_hour exist)
--   equipment.security_deposit
--   equipment.min_rental_duration
--   equipment.operator_available, fuel_included, transport_available
--   equipment.transport_charge
--   equipment.available_from, available_to, available_days,
--     booking_notice_period
--   equipment.owner_email, owner_village, owner_district, owner_address
--   equipment.rental_rules
--
--   equipment_images    -> multi-photo support (equipment.image stays in
--                           sync with the first uploaded photo)
--   equipment_documents -> RC book / insurance / etc. uploads
--
-- Safe to re-run (MySQL 8.0.29+ / MariaDB 10.5+ "IF NOT EXISTS" syntax).
-- If your server is older and a line errors with "Duplicate column name",
-- that just means it was already applied — skip it and continue.
-- =====================================================================

ALTER TABLE equipment ADD COLUMN IF NOT EXISTS name_hi VARCHAR(255) NULL AFTER name_mr;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS name_original VARCHAR(255) NULL AFTER name_hi;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS original_language VARCHAR(10) NULL DEFAULT 'en' AFTER name_original;

ALTER TABLE equipment ADD COLUMN IF NOT EXISTS brand VARCHAR(150) NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS model VARCHAR(150) NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS manufacturing_year SMALLINT NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS equipment_condition ENUM('excellent','good','average') NOT NULL DEFAULT 'good';

ALTER TABLE equipment ADD COLUMN IF NOT EXISTS rent_type ENUM('hour','day','acre') NOT NULL DEFAULT 'day';
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS rent_per_acre DECIMAL(10,2) NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS security_deposit DECIMAL(10,2) NULL DEFAULT 0;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS min_rental_duration VARCHAR(50) NULL;

ALTER TABLE equipment ADD COLUMN IF NOT EXISTS operator_available TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS fuel_included TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS transport_available TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS transport_charge DECIMAL(10,2) NULL DEFAULT 0;

ALTER TABLE equipment ADD COLUMN IF NOT EXISTS available_from DATE NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS available_to DATE NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS available_days VARCHAR(255) NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS booking_notice_period VARCHAR(50) NULL;

ALTER TABLE equipment ADD COLUMN IF NOT EXISTS owner_email VARCHAR(150) NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS owner_village VARCHAR(150) NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS owner_district VARCHAR(150) NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS owner_address VARCHAR(255) NULL;
ALTER TABLE equipment ADD COLUMN IF NOT EXISTS rental_rules TEXT NULL;

-- Make sure text columns can store Marathi / Hindi correctly.
ALTER TABLE equipment
    MODIFY name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    MODIFY name_mr VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    MODIFY name_hi VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    MODIFY name_original VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS equipment_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_equipment_images_equipment_id (equipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS equipment_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    doc_path VARCHAR(255) NOT NULL,
    doc_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_equipment_documents_equipment_id (equipment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
