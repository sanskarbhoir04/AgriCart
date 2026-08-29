<?php
// =====================================================================
// admin/includes/cod_payment_sync.php
//
// Single source of truth for one rule: a Cash on Delivery order's
// `orders.payment_status` becomes 'paid' automatically the moment its
// `orders.order_status` becomes 'delivered' (the delivery agent has
// physically collected the cash at that point). Online/prepaid
// methods (UPI, card, netbanking, online, ...) are never touched —
// their existing payment_status logic is left completely alone.
//
// Two entry points, both backend-only (no frontend text-swapping):
//
//   agri_order_sync_cod_payment_status($conn, $orderId)
//     Real-time sync for ONE order. Call this right after an admin
//     (or any other flow) moves an order to 'delivered' — see
//     order_action.php. Writes only that single orders row.
//
//   agri_order_backfill_cod_payment_status($conn)
//     Safety-net bulk correction for pre-existing rows that are
//     already Delivered + COD but were never flipped to Paid (e.g.
//     orders delivered before this fix existed). Idempotent — safe
//     to run on every Admin Orders page load. Touches only rows that
//     match the criteria; every other order is left exactly as-is.
//
// Both only ever write orders.payment_status. Neither touches
// order_items, other orders, or non-COD orders in any way.
// =====================================================================

// ---- Normalize a raw payment-method value and decide whether it's COD.
// Handles the casings/spacings called out in the spec: COD, cod,
// "Cash on Delivery", "cash on delivery", plus underscore/hyphen
// variants (cash_on_delivery, cash-on-delivery) for safety. ----
if (!function_exists('agri_order_is_cod_method')) {
    function agri_order_is_cod_method($rawMethod): bool
    {
        $m = strtolower(trim((string)$rawMethod));
        $m = str_replace(['-', '_'], ' ', $m);
        $m = preg_replace('/\s+/', ' ', $m);
        return in_array($m, ['cod', 'cash on delivery', 'cash', 'cash delivery'], true);
    }
}

// ---- Is a stored payment_status value still "not actually paid yet"?
// Deliberately an allow-list (empty/unpaid/pending only) rather than
// "anything != paid", so this never overwrites a payment_status that
// already carries other meaning (e.g. 'failed', 'refunded'). ----
if (!function_exists('agri_order_payment_status_is_unsettled')) {
    function agri_order_payment_status_is_unsettled($rawStatus): bool
    {
        $s = strtolower(trim((string)$rawStatus));
        return in_array($s, ['', 'unpaid', 'pending', 'not paid', 'due'], true);
    }
}

// ---- Real-time sync for a single order, called right after its
// order_status is written as 'delivered'. Safe no-op for everything
// else (non-COD, not delivered, already paid, missing columns). ----
if (!function_exists('agri_order_sync_cod_payment_status')) {
    function agri_order_sync_cod_payment_status($conn, int $orderId): bool
    {
        try {
            $stmt = $conn->prepare("SELECT payment_mode, payment_status, order_status FROM orders WHERE id = ? LIMIT 1");
            if (!$stmt) { return false; } // payment_mode/payment_status columns don't exist on this schema — no-op

            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) { return false; }

            if (strtolower((string)$row['order_status']) !== 'delivered') { return false; }
            if (!agri_order_is_cod_method($row['payment_mode'])) { return false; }
            if (!agri_order_payment_status_is_unsettled($row['payment_status'])) { return false; }

            $upd = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ? AND order_status = 'delivered'");
            if (!$upd) { return false; }
            $upd->bind_param('i', $orderId);
            $ok = $upd->execute();

            if ($ok && function_exists('logAdminActivity')) {
                logAdminActivity(
                    'order_payment_status_changed',
                    'orders',
                    $orderId,
                    $row['payment_status'],
                    'paid',
                    'Order #' . $orderId . ' payment status auto-set to Paid (Cash on Delivery, order Delivered)'
                );
            }
            return (bool)$ok;
        } catch (\Throwable $e) {
            return false; // never let this block the order status update it rides along with
        }
    }
}

// ---- Bulk safety-net: correct any existing Delivered + COD orders
// that are still sitting at an unsettled payment_status. Call once
// per Orders page load, before reading the orders list. ----
if (!function_exists('agri_order_backfill_cod_payment_status')) {
    function agri_order_backfill_cod_payment_status($conn): int
    {
        try {
            $upd = $conn->prepare(
                "UPDATE orders
                 SET payment_status = 'paid'
                 WHERE order_status = 'delivered'
                   AND (payment_status IS NULL OR TRIM(LOWER(payment_status)) IN ('', 'unpaid', 'pending', 'not paid', 'due'))
                   AND TRIM(LOWER(REPLACE(REPLACE(payment_mode, '-', ' '), '_', ' '))) IN ('cod', 'cash on delivery', 'cash', 'cash delivery')"
            );
            if (!$upd) { return 0; } // columns don't exist on this schema — no-op
            $upd->execute();
            return (int)($upd->affected_rows ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
