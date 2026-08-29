-- =====================================================================
-- setup/seller_invoice_snapshot_upgrade.sql
--
-- Historical Invoice Protection.
--
-- Problem: seller-invoice.php / seller/invoice.php currently render the
-- seller's business name, GSTIN, address and logo with a LIVE lookup
-- against `users` / `seller_payout_profiles` every time the invoice is
-- opened. If a seller later updates their GSTIN, company name, or
-- address, every invoice they've ever issued silently changes to show
-- the new details — which is wrong for a GST document: an invoice must
-- keep showing the seller identity that was valid on the day it was
-- generated.
--
-- Fix: freeze the identity fields onto the `seller_invoices` row itself,
-- once, the first time that row is created (see
-- agri_seller_ensure_invoice() in includes/seller_functions.php, which
-- now populates these columns on INSERT only — they are never touched
-- on the idempotent UPDATE path that keeps the money columns in sync).
-- The invoice pages then prefer these snapshot columns and only fall
-- back to a live lookup for legacy rows generated before this upgrade
-- ran (NULL snapshot columns).
--
-- Run this once against your existing AgriCart database, e.g.:
--   mysql -u youruser -p your_agricart_db < setup/seller_invoice_snapshot_upgrade.sql
-- =====================================================================

ALTER TABLE `seller_invoices`
  ADD COLUMN IF NOT EXISTS `seller_name_snapshot` VARCHAR(191) NULL AFTER `net_amount`,
  ADD COLUMN IF NOT EXISTS `business_name_snapshot` VARCHAR(191) NULL AFTER `seller_name_snapshot`,
  ADD COLUMN IF NOT EXISTS `business_address_snapshot` VARCHAR(500) NULL AFTER `business_name_snapshot`,
  ADD COLUMN IF NOT EXISTS `gstin_snapshot` VARCHAR(30) NULL AFTER `business_address_snapshot`,
  ADD COLUMN IF NOT EXISTS `seller_mobile_snapshot` VARCHAR(30) NULL AFTER `gstin_snapshot`,
  ADD COLUMN IF NOT EXISTS `seller_email_snapshot` VARCHAR(191) NULL AFTER `seller_mobile_snapshot`;

-- Existing rows generated before this upgrade are left with NULL
-- snapshot columns on purpose — the invoice pages treat a NULL snapshot
-- as "pre-upgrade, fall back to live lookup" rather than backfilling
-- them with today's (possibly already-changed) seller details, which
-- would just recreate the same bug retroactively.
