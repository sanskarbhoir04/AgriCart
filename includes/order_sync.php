<?php
// =====================================================================
// includes/order_sync.php
//
// Single source of truth for order status. Both the Seller Dashboard
// (seller/seller_api.php) and the Admin Panel (admin/order_action.php)
// include this file and call agri_order_recalculate() after *any*
// order_items.item_status change — that function is the only place
// orders.order_status is ever written from, so Seller, Admin and Buyer
// always end up reading the same value.
//
// Status vocabulary
// ------------------
// order_items.item_status: new_order, confirmed, packed, shipped,
//   delivered, cancelled, returned, refunded  (see setup/seller_dashboard_upgrade.sql
//   and setup/seller_dashboard_phase_a_upgrade.sql)
// orders.order_status: placed, confirmed, packed, shipped, delivered,
//   cancelled, returned, refunded             (see setup/orders_setup.sql)
// The only difference is the label for the initial stage ("placed" at
// the order level vs "new_order" at the item level) — agri_order_norm()
// below treats them as the same rank so both tables can be compared and
// translated into each other without any other special-casing.
// =====================================================================

if (!function_exists('agri_order_norm')) {
    /** Canonicalizes an order-level OR item-level status string for ranking/comparison. */
    function agri_order_norm(string $status): string {
        return $status === 'placed' ? 'new_order' : $status;
    }
}

if (!function_exists('agri_order_to_order_level')) {
    /** Converts an item-level status into the equivalent order-level label. */
    function agri_order_to_order_level(string $itemStatus): string {
        return $itemStatus === 'new_order' ? 'placed' : $itemStatus;
    }
}

if (!function_exists('agri_order_to_item_level')) {
    /** Converts an order-level status into the equivalent item-level label. */
    function agri_order_to_item_level(string $orderStatus): string {
        return $orderStatus === 'placed' ? 'new_order' : $orderStatus;
    }
}

if (!function_exists('agri_order_status_rank')) {
    /** Progress rank for the "forward" lifecycle. Terminal/negative statuses return -1 (handled separately). */
    function agri_order_status_rank(string $status): int {
        static $rank = ['new_order' => 0, 'confirmed' => 1, 'packed' => 2, 'shipped' => 3, 'delivered' => 4];
        $n = agri_order_norm($status);
        return $rank[$n] ?? -1;
    }
}

if (!function_exists('agri_order_is_terminal_negative')) {
    /** cancelled / returned / refunded — statuses that stop the forward lifecycle. */
    function agri_order_is_terminal_negative(string $status): bool {
        return in_array($status, ['cancelled', 'returned', 'refunded'], true);
    }
}

if (!function_exists('agri_order_status_label')) {
    /** Human-readable label shared by every UI (Buyer / Seller / Admin) so wording never drifts apart. */
    function agri_order_status_label(string $status): string {
        $labels = [
            'new_order' => 'Order Placed', 'placed' => 'Order Placed',
            'confirmed' => 'Confirmed', 'packed' => 'Packed', 'shipped' => 'Shipped',
            'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
            'returned' => 'Return Requested/Returned', 'refunded' => 'Refunded',
        ];
        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}

// -----------------------------------------------------------------
// Transition maps
// -----------------------------------------------------------------
if (!function_exists('agri_seller_order_status_transitions')) {
    // Kept here too (in addition to includes/seller_functions.php) so this
    // file has no hard dependency load-order on seller_functions.php.
    function agri_seller_order_status_transitions() {
        return [
            'new_order' => ['confirmed', 'cancelled'],
            'confirmed' => ['packed', 'cancelled'],
            'packed'    => ['shipped', 'cancelled'],
            'shipped'   => ['delivered'],
            'delivered' => ['returned'],
            'returned'  => ['refunded'],
            'cancelled' => [],
            'refunded'  => [],
        ];
    }
}

if (!function_exists('agri_admin_order_status_transitions')) {
    /**
     * Admin has broader authority than a seller (can jump stages, handle
     * disputes, cancel/override, process returns & refunds) but still
     * cannot move a terminal order backwards or resurrect it.
     */
    function agri_admin_order_status_transitions() {
        return [
            'new_order' => ['confirmed', 'packed', 'shipped', 'delivered', 'cancelled'],
            'confirmed' => ['packed', 'shipped', 'delivered', 'cancelled'],
            'packed'    => ['shipped', 'delivered', 'cancelled'],
            'shipped'   => ['delivered', 'cancelled'],
            'delivered' => ['returned', 'refunded'],
            'returned'  => ['refunded'],
            'cancelled' => [],
            'refunded'  => [],
        ];
    }
}

if (!function_exists('agri_admin_can_transition_status')) {
    function agri_admin_can_transition_status(string $current, string $new): bool {
        $current = agri_order_norm($current);
        $new = agri_order_norm($new);
        if ($current === $new) return true; // no-op re-save
        $map = agri_admin_order_status_transitions();
        return isset($map[$current]) && in_array($new, $map[$current], true);
    }
}

if (!function_exists('agri_order_permission_for_status')) {
    /** Which admin RBAC permission a given target status requires. */
    function agri_order_permission_for_status(string $status): string {
        if ($status === 'cancelled') return 'orders.cancel';
        if ($status === 'returned') return 'orders.return';
        if ($status === 'refunded') return 'orders.refund';
        return 'orders.update_status';
    }
}

// -----------------------------------------------------------------
// History logging
// -----------------------------------------------------------------
if (!function_exists('agri_order_log_history')) {
    /** Best-effort; never throws — a logging failure must never block the actual status update. */
    function agri_order_log_history($conn, int $orderId, ?int $orderItemId, ?string $previousStatus, string $newStatus, ?int $userId, string $role, ?string $changedByName = null, ?string $reason = null): void {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO order_status_history (order_id, order_item_id, previous_status, new_status, changed_by_user_id, changed_by_role, changed_by_name, reason)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            $types = 'iississs'; // order_id(i) order_item_id(i) previous(s) new(s) user_id(i) role(s) name(s) reason(s)
            $stmt->bind_param($types, $orderId, $orderItemId, $previousStatus, $newStatus, $userId, $role, $changedByName, $reason);
            $stmt->execute();
        } catch (\Throwable $e) { /* best-effort — never break the caller */ }
    }
}

if (!function_exists('agri_order_get_status_history')) {
    /**
     * Returns the full timeline for an order, newest last (ascending by
     * time) — ready to render directly. When $orderItemIdFilter is given,
     * only that item's rows PLUS whole-order rows (order_item_id IS NULL)
     * are returned, so a seller viewing their own item never sees another
     * seller's item-level actions on a shared multi-seller order.
     */
    function agri_order_get_status_history($conn, int $orderId, ?int $orderItemIdFilter = null): array {
        $sql = "SELECT * FROM order_status_history WHERE order_id = ?";
        $types = 'i'; $params = [$orderId];
        if ($orderItemIdFilter !== null) {
            $sql .= " AND (order_item_id IS NULL OR order_item_id = ?)";
            $types .= 'i'; $params[] = $orderItemIdFilter;
        }
        $sql .= " ORDER BY created_at ASC, id ASC";
        try {
            $stmt = $conn->prepare($sql);
            $refs = [];
            foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
            array_unshift($refs, $types);
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }
}

// -----------------------------------------------------------------
// Notifications
// -----------------------------------------------------------------
if (!function_exists('agri_order_notify_buyer')) {
    /** Best-effort; never throws. */
    function agri_order_notify_buyer($conn, int $userId, string $title, string $message, ?string $link = null, string $type = 'order_status'): void {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO user_notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
            $stmt->execute();
        } catch (\Throwable $e) { /* best-effort */ }
    }
}

// -----------------------------------------------------------------
// THE core sync function
// -----------------------------------------------------------------
if (!function_exists('agri_order_recalculate')) {
    /**
     * Recomputes orders.order_status from the current order_items.item_status
     * values for this order and — if it changed — writes it, logs a
     * whole-order history row, and notifies the buyer. This is the ONLY
     * function anywhere in the codebase that should write orders.order_status
     * as a *result* of an item-level change, so Seller/Admin/Buyer never
     * see divergent values.
     *
     * Aggregation rule (mirrors how Amazon/Flipkart-style marketplaces
     * summarize a multi-seller order for the buyer):
     *   - If every item is in a terminal-negative state (cancelled/returned/
     *     refunded): order status = refunded if any item is refunded,
     *     else returned if any item is returned, else cancelled.
     *   - Otherwise (at least one item still "active"): order status =
     *     the LEAST advanced active item's stage — the buyer's order isn't
     *     "Delivered" until every seller's items are delivered, and isn't
     *     "Shipped" until the slowest seller has shipped, etc. Items that
     *     are individually cancelled/returned/refunded are excluded from
     *     this — they don't hold the rest of the order back.
     *
     * @param string $actorRole 'seller'|'admin'|'system'
     */
    function agri_order_recalculate($conn, int $orderId, string $actorRole = 'system', ?int $actorId = null, ?string $actorName = null, ?string $reason = null): ?string {
        try {
            $stmt = $conn->prepare("SELECT item_status FROM order_items WHERE order_id = ?");
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $statuses = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'item_status');
            if (empty($statuses)) return null;

            $active = array_filter($statuses, static function ($s) { return !agri_order_is_terminal_negative($s); });

            if (empty($active)) {
                if (in_array('refunded', $statuses, true)) { $aggregate = 'refunded'; }
                elseif (in_array('returned', $statuses, true)) { $aggregate = 'returned'; }
                else { $aggregate = 'cancelled'; }
            } else {
                $minRank = null; $minStatus = null;
                foreach ($active as $s) {
                    $r = agri_order_status_rank($s);
                    if ($minRank === null || $r < $minRank) { $minRank = $r; $minStatus = $s; }
                }
                $aggregate = agri_order_to_order_level($minStatus ?? 'new_order');
            }

            $ordStmt = $conn->prepare("SELECT order_status, user_id, order_number FROM orders WHERE id = ?");
            $ordStmt->bind_param('i', $orderId);
            $ordStmt->execute();
            $order = $ordStmt->get_result()->fetch_assoc();
            if (!$order) return null;

            $previous = $order['order_status'];
            if ($previous === $aggregate) {
                return $aggregate; // nothing changed — no duplicate history/notification
            }

            $upd = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
            $upd->bind_param('si', $aggregate, $orderId);
            if (!$upd->execute()) return $previous;

            agri_order_log_history($conn, $orderId, null, $previous, $aggregate, $actorId, $actorRole, $actorName, $reason);

            if (!empty($order['user_id'])) {
                $label = agri_order_status_label($aggregate);
                agri_order_notify_buyer(
                    $conn,
                    (int)$order['user_id'],
                    'Order ' . $label,
                    'Your order ' . ($order['order_number'] ?? ('#' . $orderId)) . ' is now "' . $label . '".',
                    'pages/marketplace.php'
                );
            }

            // Best-effort admin-visible alert so Admin sees seller-driven changes too.
            if ($actorRole === 'seller' && function_exists('notifySuperAdmin')) {
                notifySuperAdmin(
                    'order_status_changed',
                    'Order #' . ($order['order_number'] ?? $orderId) . ' → ' . agri_order_status_label($aggregate),
                    'Changed by seller' . ($actorName ? ' (' . $actorName . ')' : '') . '.'
                );
            }

            return $aggregate;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('agri_order_cascade_admin_status')) {
    /**
     * Applies an admin-driven order-level status change down to every
     * order_item under this order — but only to items for which that
     * exact transition is legal (agri_admin_can_transition_status). Items
     * already in a locked/terminal state, or that the target status
     * doesn't apply to, are silently left untouched, so a multi-seller
     * order's other sellers' items are never corrupted by one admin click.
     * Returns the number of items actually changed.
     */
    function agri_order_cascade_admin_status($conn, int $orderId, string $targetOrderStatus, ?int $adminId, ?string $adminName, ?string $reason = null): int {
        $targetItemStatus = agri_order_to_item_level($targetOrderStatus);
        $changed = 0;
        try {
            $stmt = $conn->prepare(
                "SELECT oi.id, oi.item_status, oi.product_id, oi.quantity, oi.price,
                        COALESCE(oi.seller_id, p.added_by_user_id) AS seller_id
                 FROM order_items oi
                 JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id = ?"
            );
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($items as $item) {
                $current = $item['item_status'];
                if ($current === $targetItemStatus) continue;
                if (!agri_admin_can_transition_status($current, $targetItemStatus)) continue;

                $upd = $conn->prepare("UPDATE order_items SET item_status = ? WHERE id = ?");
                $upd->bind_param('si', $targetItemStatus, $item['id']);
                if ($upd->execute()) {
                    agri_order_log_history($conn, $orderId, (int)$item['id'], $current, $targetItemStatus, $adminId, 'admin', $adminName, $reason);
                    $changed++;

                    // Keep stock / seller-earnings ledgers consistent with the
                    // same rules the Seller Dashboard uses for these exact
                    // transitions (see seller/seller_api.php order_update_status).
                    // A sale may not have been "registered" yet if the seller
                    // hasn't opened their dashboard since the order was placed
                    // (registration normally happens lazily on their first
                    // visit) — register it first so the reversal below always
                    // has a real ledger row to reverse and stock is restored
                    // exactly once, the same way it would be if the seller
                    // had actioned this themselves.
                    if (in_array($targetItemStatus, ['cancelled', 'returned'], true) && !empty($item['seller_id'])) {
                        if (function_exists('agri_seller_register_sale')) {
                            agri_seller_register_sale($conn, (int)$item['id'], (int)$item['product_id'], (int)$item['seller_id'], (int)$item['quantity'], (float)$item['price']);
                        }
                        if (function_exists('agri_seller_reverse_sale')) {
                            agri_seller_reverse_sale($conn, (int)$item['id'], (int)$item['product_id'], (int)$item['quantity']);
                        }
                    }
                    if ($targetItemStatus === 'delivered' && function_exists('agri_seller_make_earning_available')) {
                        agri_seller_make_earning_available($conn, (int)$item['id']);
                    }
                    if (!empty($item['seller_id']) && function_exists('agri_seller_notify')) {
                        $msg = 'Admin changed order item #' . $item['id'] . ' to "' . agri_order_status_label($targetItemStatus) . '"' . ($reason ? (': ' . $reason) : '.');
                        agri_seller_notify($conn, (int)$item['seller_id'], 'order_cancelled', 'Order Updated by Admin', $msg, 'seller/dashboard.php#orders');
                    }
                }
            }
        } catch (\Throwable $e) { /* best-effort */ }
        return $changed;
    }
}
