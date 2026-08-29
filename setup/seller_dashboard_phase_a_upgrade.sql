-- =====================================================================
-- setup/seller_dashboard_phase_a_upgrade.sql
-- Run ONCE in phpMyAdmin / MySQL CLI on the AgriCart database.
-- Additive + guarded only (IF NOT EXISTS everywhere it's supported) —
-- nothing existing is renamed, dropped, or overwritten. Safe to re-run.
--
-- This is "Phase A" of the Seller Dashboard fixes:
--   1) Deleted-product visibility fix (is_active = 1 everywhere active)
--   2) Equipment deactivate/activate (uses the existing `availability`
--      column — no schema change needed for that one)
--   3) Unique order counting (COUNT(DISTINCT order_id) — query-only,
--      no schema change needed)
--   4) Revenue exclusion for cancelled/returned/refunded orders
--   10) Seller-defined low-stock limit per product
--   21) Order status transition validation (adds the 'refunded' status
--       so returns can be followed all the way to a refund)
--
-- Requires MySQL 8.0.29+ / MariaDB 10.5+ for the `ADD COLUMN IF NOT
-- EXISTS` / `ADD INDEX IF NOT EXISTS` syntax (same requirement as the
-- other setup/*.sql files already in this project). If your server is
-- older and a line errors with "Duplicate column/key", that just means
-- it was already applied — skip that one line and continue.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. products.low_stock_limit — every product can have its own
--    low-stock threshold instead of one fixed number for the whole
--    catalog. Existing products default to 5 (the old hard-coded value)
--    so behaviour doesn't change until a seller edits it.
-- ---------------------------------------------------------------------
ALTER TABLE products ADD COLUMN IF NOT EXISTS low_stock_limit INT NOT NULL DEFAULT 5 AFTER stock;

-- ---------------------------------------------------------------------
-- 2. order_items.item_status — add 'refunded' as the terminal step
--    after 'returned', so a return can be tracked all the way through
--    to a completed refund without being confused with a fresh order.
-- ---------------------------------------------------------------------
ALTER TABLE order_items MODIFY COLUMN item_status ENUM(
    'new_order','confirmed','packed','shipped','delivered','cancelled','returned','refunded'
) NOT NULL DEFAULT 'new_order';

-- ---------------------------------------------------------------------
-- 3. Indexes for the columns every seller-dashboard query filters on.
--    (orders/order_items' buyer/amount/date columns are still detected
--    dynamically per-install by includes/seller_functions.php, so we
--    only index the columns whose names are fixed across every install.)
-- ---------------------------------------------------------------------
ALTER TABLE products      ADD INDEX IF NOT EXISTS idx_products_seller_active (added_by_user_id, is_active);
ALTER TABLE products      ADD INDEX IF NOT EXISTS idx_products_stock (stock);
ALTER TABLE order_items   ADD INDEX IF NOT EXISTS idx_order_items_seller_status (seller_id, item_status);
ALTER TABLE order_items   ADD INDEX IF NOT EXISTS idx_order_items_product (product_id);
ALTER TABLE equipment     ADD INDEX IF NOT EXISTS idx_equipment_owner_availability (owner_user_id, availability);
ALTER TABLE equipment_bookings ADD INDEX IF NOT EXISTS idx_equipment_bookings_equipment_status (equipment_id, status);

-- ---------------------------------------------------------------------
-- Done. No existing data is changed — every currently-active product
-- keeps is_active = 1, every currently-active equipment listing keeps
-- availability = 1, and low_stock_limit starts at 5 (the previous
-- hard-coded threshold) for every existing product.
-- ---------------------------------------------------------------------
