-- =====================================================================
-- setup/seller_invoice_upgrade.sql
--
-- Adds the "Seller Invoice" feature (separate from the existing Buyer
-- Invoice at pages/invoice.php, which is untouched by this upgrade).
--
-- One row in `seller_invoices` = one seller's slice of one order (an
-- order with 3 sellers gets 3 seller_invoices rows, one per seller —
-- matching "if an order contains multiple products from the same
-- seller, show all of them on the same seller invoice").
--
-- Run this once against your existing AgriCart database, e.g.:
--   mysql -u youruser -p your_agricart_db < setup/seller_invoice_upgrade.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS `seller_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(40) NOT NULL COMMENT 'e.g. AGR-SELL-2026-000001',
  `seller_id` int(11) NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `invoice_year` smallint(6) NOT NULL,
  `gross_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'sum of this seller''s product value in the order (pre-charges)',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `platform_charge_percent` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'blended/weighted commission % snapshot',
  `platform_charge_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `adjustment_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'refunds / manual adjustments, negative reduces net',
  `net_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'seller net earnings / settlement amount — must equal Earnings dashboard',
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_invoice_number` (`invoice_number`),
  UNIQUE KEY `uniq_seller_order` (`seller_id`, `order_id`),
  KEY `idx_seller_id` (`seller_id`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Atomic per-year running counter so invoice numbers are guaranteed
-- unique even under concurrent requests (two sellers' dashboards
-- registering a sale at the same instant, etc.) without needing a
-- table lock — see agri_seller_next_invoice_number() in
-- includes/seller_functions.php.
CREATE TABLE IF NOT EXISTS `seller_invoice_sequences` (
  `invoice_year` smallint(6) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`invoice_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
