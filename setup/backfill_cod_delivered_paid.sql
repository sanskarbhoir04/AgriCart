-- =====================================================================
-- setup/backfill_cod_delivered_paid.sql — run this ONCE
--
-- BUG: orders.payment_status was never updated anywhere for marketplace
-- orders, so every COD order stayed "Payment: Pending" forever, even
-- after being marked Delivered (cash was actually collected at
-- delivery). admin/order_action.php now fixes this going forward for
-- any order marked delivered from now on — this script is a one-time
-- fix for orders that were ALREADY delivered before that fix existed
-- (e.g. AGC26073037BE9B in the screenshot).
--
-- Safe to re-run — it only ever touches rows that are delivered, COD,
-- and not already 'paid'.
-- =====================================================================

UPDATE orders
   SET payment_status = 'paid'
 WHERE order_status = 'delivered'
   AND payment_mode = 'cod'
   AND payment_status != 'paid';

-- Check how many rows this will affect before running the UPDATE above,
-- if you'd like to review first:
-- SELECT id, order_number, order_status, payment_mode, payment_status
--   FROM orders
--  WHERE order_status = 'delivered' AND payment_mode = 'cod' AND payment_status != 'paid';
