-- =====================================================================
-- setup/profile_edit_upgrade.sql
-- Run ONCE in phpMyAdmin / MySQL CLI on the AgriCart database.
-- Additive + guarded only (IF NOT EXISTS everywhere it's supported) —
-- nothing existing is renamed, dropped, or overwritten. Safe to re-run.
--
-- Backs the new "Edit Profile" flow (pages/update_profile.php +
-- includes/header.php profile modal). Most of the fields it edits
-- already exist on `users` (full_name, email, mobile, village,
-- district, primary_crop, profile_photo, saved_pincode, saved_address)
-- and are reused as-is. This only adds the handful of structured
-- address fields the profile form needs that don't already exist.
--
-- Requires MySQL 8.0.29+ / MariaDB 10.5+ for the `ADD COLUMN IF NOT
-- EXISTS` syntax (same requirement as the other setup/*.sql files
-- already in this project). If your server is older and a line errors
-- with "Duplicate column", that just means it was already applied —
-- skip that line and continue.
-- =====================================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS address_line1 VARCHAR(255) DEFAULT NULL AFTER saved_address,
    ADD COLUMN IF NOT EXISTS address_line2 VARCHAR(255) DEFAULT NULL AFTER address_line1,
    ADD COLUMN IF NOT EXISTS city           VARCHAR(100) DEFAULT NULL AFTER address_line2,
    ADD COLUMN IF NOT EXISTS state          VARCHAR(100) DEFAULT 'Maharashtra' AFTER city;
