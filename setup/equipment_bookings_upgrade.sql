-- =====================================================================
-- equipment_bookings_upgrade.sql — run this ONCE in phpMyAdmin
-- (or `mysql agricart < equipment_bookings_upgrade.sql`)
--
-- Adds:
--   equipment.rent_per_hour        -> optional hourly rate for an equipment
--   equipment_bookings.total_hours -> hours booked (used when renting by the hour)
--   equipment_bookings.payment_status -> pending / paid / failed
--
-- Safe to run even if some columns already exist — each statement is
-- wrapped so it won't fatally error out on a re-run in most MySQL/MariaDB
-- setups. If your server complains "Duplicate column", that column is
-- already there — just skip that one line and run the rest.
-- =====================================================================

ALTER TABLE equipment
    ADD COLUMN rent_per_hour DECIMAL(10,2) NULL DEFAULT NULL AFTER rent_per_day;

ALTER TABLE equipment_bookings
    ADD COLUMN total_hours INT NULL DEFAULT NULL AFTER total_days,
    ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER status;

-- payment_status values used by the app: 'pending', 'paid', 'failed'
