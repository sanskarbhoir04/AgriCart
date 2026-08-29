-- =====================================================================
-- setup/seller_dashboard_upgrade.sql
-- Run ONCE in phpMyAdmin / MySQL CLI on the AgriCart database before
-- using the new Seller Dashboard (seller/dashboard.php).
--
-- Everything below is ADDITIVE and GUARDED (IF NOT EXISTS / CREATE TABLE
-- IF NOT EXISTS) — nothing existing is renamed, dropped or overwritten,
-- same defensive style as setup/sell_product_upgrade.sql. Safe to re-run.
--
-- IMPORTANT — table naming:
-- This project already has its own unrelated `sellers` table (an
-- admin-managed seller directory — see admin/seller_action.php). To
-- avoid any collision, the table this script creates for seller
-- bank/UPI + running balances is called `seller_payout_profiles`, not
-- `sellers`.
--
-- IMPORTANT — orders / order_items:
-- Your `orders` and `order_items` tables already exist (used by
-- marketplace.php + place_order.php). This script does NOT know your
-- exact column names for the buyer, amount, price and quantity columns,
-- so it only ADDS the new seller-facing columns below. The dashboard's
-- PHP (includes/seller_functions.php) auto-detects which of the common
-- column names your tables actually use (buyer_id/user_id, total_amount/
-- grand_total, price/unit_price, qty/quantity, etc.) at runtime — so you
-- do not need to rename anything. If your schema uses very different
-- names, open includes/seller_functions.php and extend the candidate
-- lists in agri_seller_columns().
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. products — a few extra tracked metrics (safe defaults, all additive)
-- ---------------------------------------------------------------------
ALTER TABLE products ADD COLUMN IF NOT EXISTS sold_quantity INT NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN IF NOT EXISTS views_count INT NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN IF NOT EXISTS rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN IF NOT EXISTS rating_count INT NOT NULL DEFAULT 0;
-- commission_percent already exists (setup/sell_product_upgrade.sql /
-- insert_product.php) and is reused everywhere below as the seller's
-- "Platform Charge Percentage" for that product.

-- ---------------------------------------------------------------------
-- 2. order_items — seller attribution + per-seller order lifecycle.
--    Every item a buyer orders belongs to exactly one seller (the
--    product's owner at the time of purchase) and is tracked through
--    its own status independent of other sellers' items in the same
--    order.
-- ---------------------------------------------------------------------
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS seller_id INT NULL;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS product_name_snapshot VARCHAR(255) NULL;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS platform_charge_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS platform_charge_amount DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS seller_net_amount DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS item_status ENUM(
    'new_order','confirmed','packed','shipped','delivered','cancelled','returned'
) NOT NULL DEFAULT 'new_order';
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE order_items ADD INDEX IF NOT EXISTS idx_order_items_seller (seller_id);

-- Backfill seller_id on existing rows from the product's owner, so
-- historical orders show up in the dashboard immediately.
UPDATE order_items oi
    JOIN products p ON p.id = oi.product_id
SET oi.seller_id = p.added_by_user_id
WHERE oi.seller_id IS NULL AND p.added_by_user_id IS NOT NULL;

-- ---------------------------------------------------------------------
-- 3. seller_payout_profiles — extended seller profile (bank/UPI + running balances)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seller_payout_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_name VARCHAR(150) NULL,
    bank_account_name VARCHAR(150) NULL,
    bank_account_number VARCHAR(40) NULL,
    bank_ifsc VARCHAR(20) NULL,
    upi_id VARCHAR(100) NULL,
    payout_day TINYINT NOT NULL DEFAULT 1,
    available_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    pending_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_earnings DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_platform_charges DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_seller_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. seller_earnings — one ledger row per delivered order item
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seller_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    order_item_id INT NOT NULL,
    gross_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    platform_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pending','available','paid') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_earning_item (order_item_id),
    INDEX idx_seller_earnings_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. platform_charges — ledger of AgriCart's commission per item
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS platform_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    seller_id INT NOT NULL,
    charge_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    charge_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_charge_item (order_item_id),
    INDEX idx_platform_charges_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. payouts — seller payout requests / admin-completed payouts
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method ENUM('bank','upi') NOT NULL DEFAULT 'bank',
    account_details VARCHAR(255) NULL,
    status ENUM('pending','processing','completed') NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    completed_by_admin_id INT NULL,
    notes VARCHAR(255) NULL,
    INDEX idx_payouts_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. reviews + review_replies
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    seller_id INT NOT NULL,
    buyer_id INT NOT NULL,
    order_item_id INT NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    review_text TEXT NULL,
    review_images TEXT NULL COMMENT 'JSON array of image paths',
    verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reviews_seller (seller_id),
    INDEX idx_reviews_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    seller_id INT NOT NULL,
    reply_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reply_review (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. seller_notifications
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seller_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    type ENUM(
        'new_order','low_stock','out_of_stock','new_review',
        'payment_received','order_cancelled','product_approved','product_rejected'
    ) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message VARCHAR(500) NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seller_notifications_seller (seller_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. product_views — one row per storefront view (for analytics)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    viewer_id INT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_views_product (product_id, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Done. Next: open seller/dashboard.php while logged in as any user who
-- has listed at least one product — they become a "seller" automatically.
-- ---------------------------------------------------------------------
