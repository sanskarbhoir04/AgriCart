-- =====================================================================
-- setup/signature_stamp_upgrade.sql
--
-- Dynamic Authorized Signatory system.
--
--   Seller Invoice  (pages/seller-invoice.php, seller/invoice.php)
--     -> AgriCart is the authorized signatory (AgriCart is the one
--        issuing this settlement document to the seller).
--   Buyer Invoice   (pages/invoice.php, admin/invoice.php)
--     -> the seller/company that actually sold the product on that
--        order is the authorized signatory (never AgriCart).
--
-- Run this once against your existing AgriCart database, e.g.:
--   mysql -u youruser -p your_agricart_db < setup/signature_stamp_upgrade.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. AgriCart's own official signature/stamp — a single admin-managed
--    row (id = 1). Configured from
--    Admin Panel -> Settings -> Invoice Settings -> AgriCart Signature & Stamp.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `agricart_invoice_assets` (
  `id` tinyint(4) NOT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `stamp_path` varchar(255) DEFAULT NULL,
  `signatory_name` varchar(150) DEFAULT NULL,
  `designation` varchar(150) DEFAULT NULL,
  `updated_by_admin_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `agricart_invoice_assets` (`id`, `signatory_name`, `designation`)
VALUES (1, NULL, NULL);

-- ---------------------------------------------------------------------
-- 2. Per-seller signature/stamp, added to the Seller Business Profile
--    (seller_payout_profiles — the same table that already holds
--    business_name / GSTIN / bank details for each seller).
-- ---------------------------------------------------------------------
ALTER TABLE `seller_payout_profiles`
  ADD COLUMN IF NOT EXISTS `signature_path` varchar(255) DEFAULT NULL AFTER `business_name`,
  ADD COLUMN IF NOT EXISTS `stamp_path` varchar(255) DEFAULT NULL AFTER `signature_path`,
  ADD COLUMN IF NOT EXISTS `authorized_signatory_name` varchar(150) DEFAULT NULL AFTER `stamp_path`,
  ADD COLUMN IF NOT EXISTS `signatory_designation` varchar(150) DEFAULT NULL AFTER `authorized_signatory_name`,
  ADD COLUMN IF NOT EXISTS `signature_status` enum('missing','uploaded') NOT NULL DEFAULT 'missing' AFTER `signatory_designation`,
  ADD COLUMN IF NOT EXISTS `stamp_status` enum('missing','uploaded') NOT NULL DEFAULT 'missing' AFTER `signature_status`;

-- ---------------------------------------------------------------------
-- 3. Historical protection for Seller Invoices (AgriCart is the
--    signatory here) — freeze AgriCart's signature/stamp onto the
--    seller_invoices row the moment it's first generated, exactly like
--    the existing seller identity snapshot columns
--    (setup/seller_invoice_snapshot_upgrade.sql). If AgriCart later
--    updates its official signature/stamp, already-issued Seller
--    Invoices must keep showing what was valid when they were issued.
-- ---------------------------------------------------------------------
ALTER TABLE `seller_invoices`
  ADD COLUMN IF NOT EXISTS `agricart_signature_snapshot` varchar(255) DEFAULT NULL AFTER `seller_email_snapshot`,
  ADD COLUMN IF NOT EXISTS `agricart_stamp_snapshot` varchar(255) DEFAULT NULL AFTER `agricart_signature_snapshot`,
  ADD COLUMN IF NOT EXISTS `agricart_signatory_name_snapshot` varchar(150) DEFAULT NULL AFTER `agricart_stamp_snapshot`,
  ADD COLUMN IF NOT EXISTS `agricart_designation_snapshot` varchar(150) DEFAULT NULL AFTER `agricart_signatory_name_snapshot`;

-- ---------------------------------------------------------------------
-- 4. Historical protection for Buyer Invoices (the order's seller is
--    the signatory here). A Buyer Invoice isn't backed by its own DB
--    row today (pages/invoice.php renders live from orders/order_items),
--    so this table is the first-render snapshot: the first time a
--    Buyer Invoice is opened for a given (order, seller) pair, the
--    seller's current signature/stamp/name/designation are frozen here.
--    Every later render of that same invoice reads this frozen copy, so
--    a seller changing their signature afterwards never rewrites
--    invoices already shown to a buyer.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `buyer_invoice_signatory_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` int(11) NOT NULL,
  `business_name_snapshot` varchar(191) DEFAULT NULL,
  `signature_path_snapshot` varchar(255) DEFAULT NULL,
  `stamp_path_snapshot` varchar(255) DEFAULT NULL,
  `signatory_name_snapshot` varchar(150) DEFAULT NULL,
  `designation_snapshot` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_seller` (`order_id`, `seller_id`),
  KEY `idx_seller_id` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Admin permission gate for the new AgriCart Signature & Stamp
--    settings screen. Only Super Admin (who bypass all permission
--    checks) or an admin explicitly granted this key can change
--    AgriCart's official assets.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `admin_permissions` (`permission_key`, `module_name`, `action_name`, `description`)
VALUES ('settings.invoice_manage', 'settings', 'invoice_manage', 'Manage AgriCart official invoice signature & stamp');
