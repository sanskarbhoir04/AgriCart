<?php
// =====================================================================
// includes/seller_functions.php
//
// Shared helpers for the Seller Dashboard (seller/dashboard.php,
// seller/seller_api.php, seller/export_statement.php).
//
// A "seller" is simply any logged-in user who has listed at least one
// product (products.added_by_user_id = users.id) — there is no separate
// seller login. Every function below that touches products/orders takes
// the seller's user_id and filters by it; nothing here ever returns
// another seller's data.
//
// Because this file is designed to sit on top of an `orders` /
// `order_items` schema that already exists in your install (used by
// marketplace.php + place_order.php) and whose exact column names we
// cannot see, agri_seller_columns() detects the right column names once
// per request via information_schema and caches them. Every query in
// seller_api.php builds its SQL from those detected names instead of
// hard-coding a guess.
// =====================================================================

require_once __DIR__ . '/gstin_lib.php';
require_once __DIR__ . '/gstin_schema.php';

if (!function_exists('agri_seller_col_exists')) {
    function agri_seller_col_exists($conn, $table, $col) {
        static $cache = [];
        $key = $table . '.' . $col;
        if (isset($cache[$key])) return $cache[$key];
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeCol   = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        $exists = false;
        try {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS c FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
            );
            $stmt->bind_param("ss", $safeTable, $safeCol);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $exists = ((int)($row['c'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            $exists = false;
        }
        return $cache[$key] = $exists;
    }
}

if (!function_exists('agri_seller_table_exists')) {
    function agri_seller_table_exists($conn, $table) {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $exists = false;
        try {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS c FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->bind_param("s", $safeTable);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $exists = ((int)($row['c'] ?? 0)) > 0;
        } catch (\Throwable $e) {
            $exists = false;
        }
        return $cache[$table] = $exists;
    }
}

/**
 * Pick the first column (from a hard-coded candidate list — never from
 * user input, so this is safe to interpolate directly into SQL) that
 * actually exists on $table. Returns $default if none match.
 */
if (!function_exists('agri_pick_col')) {
    function agri_pick_col($conn, $table, array $candidates, $default = null) {
        foreach ($candidates as $c) {
            if (agri_seller_col_exists($conn, $table, $c)) return $c;
        }
        return $default;
    }
}

/**
 * Detects (once) the real column names this install's `orders`,
 * `order_items`, and `users` tables use for the fields the seller
 * dashboard needs, so the rest of the code never has to guess.
 */
if (!function_exists('agri_seller_columns')) {
    function agri_seller_columns($conn) {
        static $cols = null;
        if ($cols !== null) return $cols;

        $cols = [
            'orders_buyer'   => agri_pick_col($conn, 'orders', ['buyer_id', 'user_id', 'customer_id'], 'user_id'),
            'orders_amount'  => agri_pick_col($conn, 'orders', ['total_amount', 'grand_total', 'total', 'amount'], 'total_amount'),
            'orders_payment' => agri_pick_col($conn, 'orders', ['payment_status', 'payment_state'], null),
            'orders_method'  => agri_pick_col($conn, 'orders', ['payment_method', 'method'], null),
            'orders_created' => agri_pick_col($conn, 'orders', ['created_at', 'order_date', 'placed_at'], 'created_at'),
            'orders_number'  => agri_pick_col($conn, 'orders', ['order_number', 'order_code'], null),
            'orders_address' => agri_pick_col($conn, 'orders', ['delivery_address', 'address', 'shipping_address'], null),
            'orders_village'  => agri_pick_col($conn, 'orders', ['delivery_village', 'village', 'city'], null),
            'orders_district' => agri_pick_col($conn, 'orders', ['delivery_district', 'district'], null),
            'orders_pincode'  => agri_pick_col($conn, 'orders', ['delivery_pincode', 'pincode', 'pin_code'], null),
            'orders_name'     => agri_pick_col($conn, 'orders', ['delivery_name', 'customer_name', 'name'], null),
            'orders_phone'    => agri_pick_col($conn, 'orders', ['delivery_mobile', 'delivery_phone', 'customer_phone', 'mobile', 'phone'], null),

            'items_qty'       => agri_pick_col($conn, 'order_items', ['quantity', 'qty'], 'quantity'),
            'items_price'     => agri_pick_col($conn, 'order_items', ['price', 'unit_price', 'product_price'], 'price'),
            'items_created'   => agri_pick_col($conn, 'order_items', ['created_at'], null),

            'users_name'      => agri_pick_col($conn, 'users', ['name', 'full_name', 'saved_name', 'username'], 'name'),
            'users_mobile'    => agri_pick_col($conn, 'users', ['mobile', 'saved_mobile', 'phone'], 'mobile'),
            'users_email'     => agri_pick_col($conn, 'users', ['email'], null),
            'users_avatar'    => agri_pick_col($conn, 'users', ['profile_image', 'avatar', 'photo'], null),
        ];
        return $cols;
    }
}

/**
 * Binds a variable-length params array to a prepared statement.
 * mysqli::bind_param requires its arguments by reference, so
 * `$stmt->bind_param($types, ...$params)` is not reliable for a params
 * array built dynamically — this builds real references first.
 */
if (!function_exists('agri_bind_params')) {
    function agri_bind_params($stmt, $types, array $params) {
        $refs = [];
        foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
        array_unshift($refs, $types);
        return call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

/** Requires a logged-in user; sends a JSON 401 and exits if not. Returns the user_id (int). */
if (!function_exists('agri_seller_require_login')) {
    function agri_seller_require_login() {
        require_once __DIR__ . '/security.php';
        agri_session_start();
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'login_required']);
            exit;
        }
        return (int)$_SESSION['user_id'];
    }
}

/** Ensures a `seller_payout_profiles` profile row exists for this user; returns it as an assoc array. */
if (!function_exists('agri_seller_ensure_profile')) {
    function agri_seller_ensure_profile($conn, $sellerId) {
        $stmt = $conn->prepare("SELECT * FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) return $row;

        $ins = $conn->prepare("INSERT INTO seller_payout_profiles (user_id) VALUES (?)");
        $ins->bind_param("i", $sellerId);
        $ins->execute();

        $stmt2 = $conn->prepare("SELECT * FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
        $stmt2->bind_param("i", $sellerId);
        $stmt2->execute();
        return $stmt2->get_result()->fetch_assoc() ?: [
            'user_id' => $sellerId, 'available_balance' => 0, 'pending_balance' => 0,
            'total_earnings' => 0, 'total_platform_charges' => 0, 'total_paid' => 0,
        ];
    }
}

/**
 * Returns the seller's chosen listing type: 'product' | 'rental' | 'both' | null.
 * null means the user hasn't been through the "Become a Seller" form yet.
 */
if (!function_exists('agri_seller_get_type')) {
    function agri_seller_get_type($conn, $sellerId) {
        $stmt = $conn->prepare("SELECT seller_type FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row['seller_type'] ?? null;
    }
}

/** Saves the seller's chosen listing type (creates the payout profile row first if needed). */
if (!function_exists('agri_seller_set_type')) {
    function agri_seller_set_type($conn, $sellerId, $type) {
        if (!in_array($type, ['product', 'rental', 'both'], true)) return false;
        agri_seller_ensure_profile($conn, $sellerId);
        $stmt = $conn->prepare("UPDATE seller_payout_profiles SET seller_type = ? WHERE user_id = ?");
        $stmt->bind_param("si", $type, $sellerId);
        return $stmt->execute();
    }
}

/** Confirms $productId belongs to $sellerId. Returns the product row or null. */
if (!function_exists('agri_seller_owns_product')) {
    function agri_seller_owns_product($conn, $sellerId, $productId) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND added_by_user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $productId, $sellerId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }
}

/** Inserts a seller_notifications row. Best-effort — never throws. */
if (!function_exists('agri_seller_notify')) {
    function agri_seller_notify($conn, $sellerId, $type, $title, $message, $link = null) {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO seller_notifications (seller_id, type, title, message, link) VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param("issss", $sellerId, $type, $title, $message, $link);
            $stmt->execute();
        } catch (\Throwable $e) { /* notifications are best-effort */ }
    }
}

/**
 * Marks an order item delivered → creates its seller_earnings +
 * platform_charges ledger rows (idempotent: a UNIQUE key on
 * order_item_id means calling this twice is harmless) and rolls the
 * amount into the seller's pending_balance.
 */
if (!function_exists('agri_seller_recognize_earning')) {
    function agri_seller_recognize_earning($conn, $orderItemId, $sellerId, $grossAmount, $chargePercent) {
        $chargeAmount = round($grossAmount * ($chargePercent / 100), 2);
        $netAmount = round($grossAmount - $chargeAmount, 2);

        try {
            $stmt = $conn->prepare(
                "INSERT IGNORE INTO seller_earnings (seller_id, order_item_id, gross_amount, platform_charge, net_amount, status)
                 VALUES (?,?,?,?,?, 'pending')"
            );
            $stmt->bind_param("iiddd", $sellerId, $orderItemId, $grossAmount, $chargeAmount, $netAmount);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $pc = $conn->prepare(
                    "INSERT IGNORE INTO platform_charges (order_item_id, seller_id, charge_percent, charge_amount) VALUES (?,?,?,?)"
                );
                $pc->bind_param("iidd", $orderItemId, $sellerId, $chargePercent, $chargeAmount);
                $pc->execute();

                agri_seller_ensure_profile($conn, $sellerId);
                $upd = $conn->prepare(
                    "UPDATE seller_payout_profiles SET pending_balance = pending_balance + ?, total_earnings = total_earnings + ?, total_platform_charges = total_platform_charges + ? WHERE user_id = ?"
                );
                $upd->bind_param("dddi", $netAmount, $netAmount, $chargeAmount, $sellerId);
                $upd->execute();
            }
        } catch (\Throwable $e) { /* best-effort */ }

        return ['charge_amount' => $chargeAmount, 'net_amount' => $netAmount];
    }
}

/** Moves an item's pending earning to "available" once delivery is confirmed final (7 days is typical; here we do it on 'delivered'). */
if (!function_exists('agri_seller_make_earning_available')) {
    function agri_seller_make_earning_available($conn, $orderItemId) {
        try {
            $stmt = $conn->prepare("SELECT seller_id, net_amount, status FROM seller_earnings WHERE order_item_id = ? LIMIT 1");
            $stmt->bind_param("i", $orderItemId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && $row['status'] === 'pending') {
                $upd = $conn->prepare("UPDATE seller_earnings SET status = 'available' WHERE order_item_id = ?");
                $upd->bind_param("i", $orderItemId);
                $upd->execute();

                $bal = $conn->prepare(
                    "UPDATE seller_payout_profiles SET pending_balance = GREATEST(pending_balance - ?, 0), available_balance = available_balance + ? WHERE user_id = ?"
                );
                $bal->bind_param("ddi", $row['net_amount'], $row['net_amount'], $row['seller_id']);
                $bal->execute();
            }
        } catch (\Throwable $e) { /* best-effort */ }
    }
}

/**
 * Lazily "registers" a newly-placed order item the first time the seller
 * dashboard sees it: decrements stock / increments sold_quantity on the
 * product, snapshots the platform-charge %, and opens a 'pending'
 * earnings ledger row — then fires a "New Order Received" notification
 * (and a low/out-of-stock one if that update crossed the threshold).
 *
 * Idempotent: guarded by the UNIQUE(order_item_id) key on
 * seller_earnings, so calling this repeatedly (e.g. every dashboard
 * load) is always safe and only ever runs the side-effects once.
 *
 * For instant (rather than "next dashboard load") notifications and
 * stock updates, call this same function directly from place_order.php
 * right after each order_items row is inserted — see
 * seller/README_SELLER_DASHBOARD.md.
 */
if (!function_exists('agri_seller_register_sale')) {
    function agri_seller_register_sale($conn, $orderItemId, $productId, $sellerId, $qty, $unitPrice) {
        try {
            $chk = $conn->prepare("SELECT id FROM seller_earnings WHERE order_item_id = ? LIMIT 1");
            $chk->bind_param("i", $orderItemId);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) return; // already registered

            $pStmt = $conn->prepare("SELECT stock, commission_percent, name FROM products WHERE id = ? LIMIT 1");
            $pStmt->bind_param("i", $productId);
            $pStmt->execute();
            $product = $pStmt->get_result()->fetch_assoc();
            if (!$product) return;

            $chargePercent = (float)($product['commission_percent'] ?? 5.00);
            $gross = round($qty * $unitPrice, 2);

            $newStock = max(0, (int)$product['stock'] - (int)$qty);
            $upd = $conn->prepare("UPDATE products SET stock = ?, sold_quantity = sold_quantity + ? WHERE id = ?");
            $upd->bind_param("iii", $newStock, $qty, $productId);
            $upd->execute();

            $res = agri_seller_recognize_earning($conn, $orderItemId, $sellerId, $gross, $chargePercent);

            try {
                $oiUpd = $conn->prepare(
                    "UPDATE order_items SET seller_id = ?, platform_charge_percent = ?, platform_charge_amount = ?, seller_net_amount = ? WHERE id = ?"
                );
                $oiUpd->bind_param("idddi", $sellerId, $chargePercent, $res['charge_amount'], $res['net_amount'], $orderItemId);
                $oiUpd->execute();
            } catch (\Throwable $e) { /* best-effort */ }

            // Auto-generate (or refresh) this seller's Seller Invoice for the
            // order this item belongs to — see "Automatic Invoice Generation"
            // in the Seller Invoice spec. Never creates a duplicate order or
            // payment record; it only writes to seller_invoices.
            try {
                $oiRow = $conn->prepare("SELECT order_id FROM order_items WHERE id = ? LIMIT 1");
                $oiRow->bind_param("i", $orderItemId);
                $oiRow->execute();
                $oi = $oiRow->get_result()->fetch_assoc();
                if ($oi) {
                    agri_seller_ensure_invoice($conn, $sellerId, (int)$oi['order_id']);
                }
            } catch (\Throwable $e) { /* best-effort */ }

            agri_seller_notify(
                $conn, $sellerId, 'new_order', 'New Order Received',
                'You received a new order for "' . $product['name'] . '" (Qty: ' . $qty . ').',
                'seller/dashboard.php#orders'
            );
            if ($newStock <= 0) {
                agri_seller_notify($conn, $sellerId, 'out_of_stock', 'Product Out of Stock',
                    '"' . $product['name'] . '" is now out of stock.', 'seller/dashboard.php#products');
            } elseif ($newStock < 5) {
                agri_seller_notify($conn, $sellerId, 'low_stock', 'Stock Running Low',
                    '"' . $product['name'] . '" has only ' . $newStock . ' units left.', 'seller/dashboard.php#products');
            }
        } catch (\Throwable $e) { /* registration is best-effort, never blocks the dashboard */ }
    }
}

/** Reverses a registered sale when an item is cancelled/returned: restocks the product and removes its ledger rows. */
if (!function_exists('agri_seller_reverse_sale')) {
    function agri_seller_reverse_sale($conn, $orderItemId, $productId, $qty) {
        try {
            $e = $conn->prepare("SELECT seller_id, gross_amount, platform_charge, net_amount, status FROM seller_earnings WHERE order_item_id = ? LIMIT 1");
            $e->bind_param("i", $orderItemId);
            $e->execute();
            $row = $e->get_result()->fetch_assoc();
            if (!$row) return;

            $upd = $conn->prepare("UPDATE products SET stock = stock + ?, sold_quantity = GREATEST(sold_quantity - ?, 0) WHERE id = ?");
            $upd->bind_param("iii", $qty, $qty, $productId);
            $upd->execute();

            $col = $row['status'] === 'available' ? 'available_balance' : 'pending_balance';
            $bal = $conn->prepare("UPDATE seller_payout_profiles SET $col = GREATEST($col - ?, 0), total_earnings = GREATEST(total_earnings - ?, 0), total_platform_charges = GREATEST(total_platform_charges - ?, 0) WHERE user_id = ?");
            $bal->bind_param("dddi", $row['net_amount'], $row['net_amount'], $row['platform_charge'], $row['seller_id']);
            $bal->execute();

            $conn->query("DELETE FROM seller_earnings WHERE order_item_id = " . (int)$orderItemId);
            $conn->query("DELETE FROM platform_charges WHERE order_item_id = " . (int)$orderItemId);
        } catch (\Throwable $ex) { /* best-effort */ }
    }
}

/** Recomputes products.rating_avg / rating_count for one product from the reviews table. */
if (!function_exists('agri_seller_refresh_product_rating')) {
    function agri_seller_refresh_product_rating($conn, $productId) {
        try {
            $stmt = $conn->prepare("SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE product_id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $avg = round((float)($row['avg_r'] ?? 0), 2);
            $cnt = (int)($row['cnt'] ?? 0);
            $upd = $conn->prepare("UPDATE products SET rating_avg = ?, rating_count = ? WHERE id = ?");
            $upd->bind_param("dii", $avg, $cnt, $productId);
            $upd->execute();
        } catch (\Throwable $e) { /* best-effort */ }
    }
}

/** Human labels for order/stock statuses in English + Marathi, used by CSV export & any server-rendered text. */
if (!function_exists('agri_seller_status_label')) {
    function agri_seller_status_label($status, $lang = 'en') {
        $labels = [
            'new_order' => ['en' => 'New Order',  'mr' => 'नवीन ऑर्डर'],
            'confirmed' => ['en' => 'Confirmed',  'mr' => 'निश्चित'],
            'packed'    => ['en' => 'Packed',     'mr' => 'पॅक केले'],
            'shipped'   => ['en' => 'Shipped',    'mr' => 'पाठवले'],
            'delivered' => ['en' => 'Delivered',  'mr' => 'वितरित'],
            'cancelled' => ['en' => 'Cancelled',  'mr' => 'रद्द केले'],
            'returned'  => ['en' => 'Returned',   'mr' => 'परत केले'],
            'refunded'  => ['en' => 'Refunded',   'mr' => 'परतावा दिला'],
        ];
        return $labels[$status][$lang] ?? ($labels[$status]['en'] ?? ucfirst($status));
    }
}

/**
 * The single source of truth for "does this order item's amount count as
 * real revenue". Cancelled, returned, and refunded items are excluded
 * everywhere — gross sales, platform charges, net revenue, top-selling
 * products, analytics graphs, and exported statements — by building every
 * one of those queries against this same list instead of each hand-rolling
 * its own exclusion.
 */
if (!function_exists('agri_seller_valid_revenue_statuses')) {
    function agri_seller_valid_revenue_statuses() {
        return ['new_order', 'confirmed', 'packed', 'shipped', 'delivered'];
    }
}

/** Returns the valid-revenue statuses above as a ready-to-use SQL IN(...) list (values are hard-coded, never user input — safe to inline). */
if (!function_exists('agri_seller_valid_revenue_sql_list')) {
    function agri_seller_valid_revenue_sql_list() {
        return "'" . implode("','", agri_seller_valid_revenue_statuses()) . "'";
    }
}

/**
 * Allowed forward-only order-item status transitions. Enforced on the
 * server in seller_api.php's order_update_status action — the UI only
 * offers valid next statuses, but the server never trusts that alone.
 */
if (!function_exists('agri_seller_order_status_transitions')) {
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

/** True if $current -> $new is an allowed transition (or a same-status no-op). */
if (!function_exists('agri_seller_can_transition_status')) {
    function agri_seller_can_transition_status($current, $new) {
        if ($current === $new) return true; // no-op re-save, harmless
        $map = agri_seller_order_status_transitions();
        return isset($map[$current]) && in_array($new, $map[$current], true);
    }
}

// =====================================================================
// SELLER INVOICE — seller/invoice.php, seller/seller_api.php (invoices_*)
//
// A "Seller Invoice" is one seller's slice of one order: every product
// that seller sold within that order, the platform/commission charges
// on it, and the resulting net settlement amount. It is generated
// automatically (see the hook in agri_seller_register_sale() above) and
// is completely separate from the Buyer Invoice at pages/invoice.php.
// =====================================================================

/**
 * Atomically returns the next running number (1, 2, 3, ...) for a given
 * invoice year via a dedicated counter table, so two requests racing to
 * generate the first invoice of the year can never collide.
 */
if (!function_exists('agri_seller_next_invoice_seq')) {
    function agri_seller_next_invoice_seq($conn, $year) {
        $ins = $conn->prepare(
            "INSERT INTO seller_invoice_sequences (invoice_year, last_number) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE last_number = last_number + 1"
        );
        $ins->bind_param("i", $year);
        $ins->execute();

        $stmt = $conn->prepare("SELECT last_number FROM seller_invoice_sequences WHERE invoice_year = ? LIMIT 1");
        $stmt->bind_param("i", $year);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['last_number'] ?? 1);
    }
}

/** Formats a fresh, unique invoice number: AGR-SELL-2026-000001 */
if (!function_exists('agri_seller_generate_invoice_number')) {
    function agri_seller_generate_invoice_number($conn, $year) {
        $seq = agri_seller_next_invoice_seq($conn, $year);
        return 'AGR-SELL-' . $year . '-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
    }
}

/**
 * Sums this seller's own order_items within $orderId into the figures a
 * Seller Invoice needs. Deliberately reuses the exact same stored
 * per-item columns (platform_charge_amount, seller_net_amount) that
 * agri_seller_register_sale()/agri_seller_recognize_earning() already
 * write — the same numbers the Earnings & Payouts dashboard is built
 * from — so the invoice's Net Earnings figure can never drift from what
 * the seller sees in their wallet/balance.
 *
 * Cancelled / returned / refunded items are excluded, matching
 * agri_seller_valid_revenue_statuses() used everywhere else.
 */
if (!function_exists('agri_seller_invoice_financials')) {
    function agri_seller_invoice_financials($conn, $sellerId, $orderId) {
        $statusList = agri_seller_valid_revenue_sql_list();

        // Active (still-counted) items — real revenue this seller keeps.
        $sql = "SELECT
                    COALESCE(SUM(oi.quantity * oi.price), 0) AS gross_amount,
                    COALESCE(SUM(oi.gst_amount), 0) AS tax_amount,
                    COALESCE(SUM(oi.platform_charge_amount), 0) AS platform_charge_amount,
                    COALESCE(SUM(oi.seller_net_amount), 0) AS net_amount,
                    COUNT(*) AS item_count
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                WHERE p.added_by_user_id = ? AND oi.order_id = ?
                  AND oi.item_status IN ($statusList)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $sellerId, $orderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];

        // Reversed items (cancelled/returned/refunded) — shown as a
        // transparent "Refund / Adjustment" deduction rather than being
        // silently dropped from the invoice, per the return/refund
        // handling requirement.
        $revSql = "SELECT COALESCE(SUM(oi.seller_net_amount), 0) AS reversed_net, COUNT(*) AS reversed_count
                   FROM order_items oi
                   JOIN products p ON p.id = oi.product_id
                   WHERE p.added_by_user_id = ? AND oi.order_id = ?
                     AND oi.item_status IN ('cancelled','returned','refunded')";
        $revStmt = $conn->prepare($revSql);
        $revStmt->bind_param("ii", $sellerId, $orderId);
        $revStmt->execute();
        $revRow = $revStmt->get_result()->fetch_assoc() ?: [];

        $totalItemsStmt = $conn->prepare(
            "SELECT COUNT(*) c FROM order_items oi JOIN products p ON p.id = oi.product_id
             WHERE p.added_by_user_id = ? AND oi.order_id = ?"
        );
        $totalItemsStmt->bind_param("ii", $sellerId, $orderId);
        $totalItemsStmt->execute();
        $totalItems = (int)($totalItemsStmt->get_result()->fetch_assoc()['c'] ?? 0);

        $gross = round((float)($row['gross_amount'] ?? 0), 2);
        $tax = round((float)($row['tax_amount'] ?? 0), 2);
        $charge = round((float)($row['platform_charge_amount'] ?? 0), 2);
        $net = round((float)($row['net_amount'] ?? 0), 2);
        // seller_net_amount is only populated once agri_seller_register_sale
        // has run for an item — if it hasn't yet (e.g. invoice viewed the
        // instant before the sync sweep), fall back to gross - charge so the
        // figures on screen still always add up correctly.
        if ($net <= 0 && $gross > 0) {
            $net = round($gross - $charge, 2);
        }
        $percent = $gross > 0 ? round(($charge / $gross) * 100, 2) : 0.00;
        $reversedNet = round((float)($revRow['reversed_net'] ?? 0), 2);

        return [
            'gross_amount' => $gross,
            'tax_amount' => $tax,
            'platform_charge_percent' => $percent,
            'platform_charge_amount' => $charge,
            'other_charges' => 0.00,
            'adjustment_amount' => $reversedNet > 0 ? -$reversedNet : 0.00,
            'net_amount' => $net,
            'item_count' => $totalItems, // includes reversed items, so a fully-cancelled order still gets an invoice
        ];
    }
}

/**
 * Ensures a seller_invoices row exists for this (seller, order) pair —
 * generating a unique invoice number the first time — and keeps its
 * financial columns in sync with the live order_items figures on every
 * subsequent call (idempotent; safe to call on every dashboard load,
 * order-status change, or invoice page view). Returns the row as an
 * associative array, or null if this seller has no items in that order.
 */
/**
 * Captures the seller's identity (name, business/company name, address,
 * GSTIN, contact) exactly as it stands right now, for freezing onto a
 * seller_invoices row at the moment it's first generated. Reuses the
 * same column-detection + seller_payout_profiles-takes-priority
 * convention as pages/seller-invoice.php's agis_fetch_seller_profile(),
 * so the snapshot always matches what the invoice would have shown live
 * at that instant. Called once, on INSERT only — never on the
 * money-sync UPDATE path — so later edits to the seller's profile can
 * never retroactively change an already-issued invoice.
 */
if (!function_exists('agri_seller_identity_snapshot')) {
    function agri_seller_identity_snapshot($conn, $sellerId) {
        $nameCol = agri_pick_col_generic($conn, 'users', ['full_name', 'name'], 'full_name');
        $mobileCol = agri_pick_col_generic($conn, 'users', ['mobile', 'phone']);
        $gstinCol = agri_pick_col_generic($conn, 'users', ['gstin', 'gst_number', 'gst_no']);
        $bizNameCol = agri_pick_col_generic($conn, 'users', ['business_name', 'shop_name', 'store_name']);
        $bizAddrCol = agri_pick_col_generic($conn, 'users', ['business_address', 'shop_address', 'address']);
        $emailCol = agri_pick_col_generic($conn, 'users', ['email']);

        $select = "u.$nameCol seller_name";
        $select .= $mobileCol ? ", u.$mobileCol seller_mobile" : ", NULL seller_mobile";
        $select .= $gstinCol ? ", u.$gstinCol seller_gstin" : ", NULL seller_gstin";
        $select .= $bizNameCol ? ", u.$bizNameCol business_name" : ", NULL business_name";
        $select .= $bizAddrCol ? ", u.$bizAddrCol business_address" : ", NULL business_address";
        $select .= $emailCol ? ", u.$emailCol seller_email" : ", NULL seller_email";

        $stmt = $conn->prepare("SELECT $select FROM users u WHERE u.id = ? LIMIT 1");
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc() ?: [];

        // GST-specific fields, added onto seller_payout_profiles by
        // includes/gstin_schema.php — these have no legacy home on
        // `users`, so they're read only from here.
        $gst = [
            'legal_business_name' => null, 'gst_status' => 'not_applicable', 'pan' => null,
            'business_type' => null, 'registered_address' => null, 'gst_state' => null,
            'gst_state_code' => null, 'gst_city' => null, 'gst_pincode' => null,
        ];

        // seller_payout_profiles, when present, is the more authoritative
        // source for business identity — same as the live invoice pages.
        if (agri_table_exists_generic($conn, 'seller_payout_profiles')) {
            $ppCols = [];
            foreach (['business_name', 'business_address', 'gstin', 'legal_business_name', 'gst_status', 'pan',
                       'business_type', 'registered_address', 'gst_state', 'gst_state_code', 'gst_city', 'gst_pincode'] as $c) {
                if (agri_col_exists_generic($conn, 'seller_payout_profiles', $c)) $ppCols[] = $c;
            }
            if ($ppCols) {
                $stmt = $conn->prepare("SELECT " . implode(',', $ppCols) . " FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
                $stmt->bind_param("i", $sellerId);
                $stmt->execute();
                $pp = $stmt->get_result()->fetch_assoc();
                if ($pp) {
                    if (!empty($pp['business_name'])) $u['business_name'] = $pp['business_name'];
                    if (!empty($pp['business_address'])) $u['business_address'] = $pp['business_address'];
                    if (!empty($pp['gstin'])) $u['seller_gstin'] = $pp['gstin'];
                    foreach (array_keys($gst) as $gk) {
                        if (array_key_exists($gk, $pp) && $pp[$gk] !== null && $pp[$gk] !== '') $gst[$gk] = $pp[$gk];
                    }
                }
            }
        }

        // Rule: never show a fake GSTIN — an unregistered seller's
        // gstin_snapshot stays NULL no matter what's in `users`/legacy
        // columns, and templates must fall back to "GST Status:
        // Unregistered" instead of printing anything in its place.
        $gstinForInvoice = ($gst['gst_status'] === 'registered' || $gst['gst_status'] === 'composition')
            ? ($u['seller_gstin'] ?? null)
            : null;

        return [
            'seller_name_snapshot' => $u['seller_name'] ?? null,
            'business_name_snapshot' => $u['business_name'] ?? null,
            'business_address_snapshot' => $u['business_address'] ?? null,
            'gstin_snapshot' => $gstinForInvoice,
            'seller_mobile_snapshot' => $u['seller_mobile'] ?? null,
            'seller_email_snapshot' => $u['seller_email'] ?? null,
            // GST-specific extras (used by the extended INSERT branch of
            // agri_seller_ensure_invoice() when the columns exist).
            'seller_legal_name_snapshot' => $gst['legal_business_name'] ?: ($u['business_name'] ?? null),
            'seller_pan_snapshot' => $gst['pan'] ?: gstin_extract_pan($gstinForInvoice),
            'seller_gst_status_snapshot' => $gst['gst_status'],
            'seller_state_snapshot' => $gst['gst_state'],
            'seller_state_code_snapshot' => $gst['gst_state_code'],
        ];
    }
}

if (!function_exists('agri_pick_col_generic')) {
    function agri_pick_col_generic($conn, $table, $candidates, $default = null) {
        foreach ($candidates as $c) { if (agri_col_exists_generic($conn, $table, $c)) return $c; }
        return $default;
    }
}
if (!function_exists('agri_col_exists_generic')) {
    function agri_col_exists_generic($conn, $table, $col) {
        static $cache = [];
        $key = $table . '.' . $col;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($col);
        $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $cache[$key] = ($res && $res->num_rows > 0);
    }
}
if (!function_exists('agri_table_exists_generic')) {
    function agri_table_exists_generic($conn, $table) {
        static $cache = [];
        if (array_key_exists($table, $cache)) return $cache[$table];
        $t = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$t'");
        return $cache[$table] = ($res && $res->num_rows > 0);
    }
}

if (!function_exists('agri_seller_ensure_invoice')) {
    function agri_seller_ensure_invoice($conn, $sellerId, $orderId) {
        // Make sure the GST snapshot columns exist before we check for
        // them below — cheap/idempotent, and the only place in the
        // invoice-generation path that needs it (gstin_bootstrap_schema()
        // itself no-ops instantly once every column is already present).
        if (function_exists('gstin_bootstrap_schema')) {
            static $gstSchemaReady = false;
            if (!$gstSchemaReady) { gstin_bootstrap_schema($conn); $gstSchemaReady = true; }
        }

        $fin = agri_seller_invoice_financials($conn, $sellerId, $orderId);
        if ($fin['item_count'] <= 0) return null;

        $existing = $conn->prepare("SELECT * FROM seller_invoices WHERE seller_id = ? AND order_id = ? LIMIT 1");
        $existing->bind_param("ii", $sellerId, $orderId);
        $existing->execute();
        $row = $existing->get_result()->fetch_assoc();

        if ($row) {
            $upd = $conn->prepare(
                "UPDATE seller_invoices SET gross_amount = ?, tax_amount = ?, platform_charge_percent = ?,
                    platform_charge_amount = ?, net_amount = ? WHERE id = ?"
            );
            $upd->bind_param("dddddi", $fin['gross_amount'], $fin['tax_amount'], $fin['platform_charge_percent'],
                $fin['platform_charge_amount'], $fin['net_amount'], $row['id']);
            $upd->execute();
            return array_merge($row, $fin);
        }

        // Invoice year is pinned to the order's own placed date, not
        // "today" — so an invoice generated late (e.g. via the sync sweep)
        // still lands in the year the sale actually happened.
        $yearStmt = $conn->prepare("SELECT YEAR(COALESCE(ordered_at, created_at)) y FROM orders WHERE id = ? LIMIT 1");
        $yearStmt->bind_param("i", $orderId);
        $yearStmt->execute();
        $yearRow = $yearStmt->get_result()->fetch_assoc();
        $year = (int)($yearRow['y'] ?? date('Y'));

        // Retry a couple of times in the (very unlikely) case two requests
        // generate a number in the same instant and collide on the UNIQUE
        // invoice_number key.
        // Freeze the seller's identity as of right now — this INSERT is
        // the only moment a seller_invoices row's identity columns are
        // ever written; see agri_seller_identity_snapshot() above.
        $snap = agri_seller_identity_snapshot($conn, $sellerId);
        $hasSnapshotCols = agri_col_exists_generic($conn, 'seller_invoices', 'gstin_snapshot');
        $hasGstCols = agri_col_exists_generic($conn, 'seller_invoices', 'tax_type');

        $gstExtra = [];
        if ($hasGstCols) {
            $platform = agri_gst_platform_snapshot($conn);
            $buyer = agri_gst_buyer_snapshot($conn, $orderId);
            $tax = agri_gst_compute_tax($snap['seller_state_snapshot'] ?? null, $buyer['state'] ?? null, $fin['tax_amount']);
            $gstExtra = [
                'seller_legal_name_snapshot' => $snap['seller_legal_name_snapshot'],
                'seller_pan_snapshot' => $snap['seller_pan_snapshot'],
                'seller_gst_status_snapshot' => $snap['seller_gst_status_snapshot'],
                'seller_state_snapshot' => $snap['seller_state_snapshot'],
                'seller_state_code_snapshot' => $snap['seller_state_code_snapshot'],
                'platform_legal_name_snapshot' => $platform['legal_name'],
                'platform_gstin_snapshot' => $platform['gstin'],
                'platform_pan_snapshot' => $platform['pan'],
                'platform_address_snapshot' => $platform['address'],
                'platform_state_snapshot' => $platform['state'],
                'platform_state_code_snapshot' => $platform['state_code'],
                'buyer_name_snapshot' => $buyer['name'],
                'buyer_gstin_snapshot' => $buyer['gstin'],
                'buyer_pan_snapshot' => $buyer['pan'],
                'buyer_address_snapshot' => $buyer['address'],
                'buyer_state_snapshot' => $buyer['state'],
                'buyer_gst_status_snapshot' => $buyer['gst_status'],
                'tax_type' => $tax['tax_type'],
                'cgst_amount' => $tax['cgst'],
                'sgst_amount' => $tax['sgst'],
                'igst_amount' => $tax['igst'],
            ];
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $invoiceNumber = agri_seller_generate_invoice_number($conn, $year);
            try {
                if ($hasGstCols) {
                    $ins = $conn->prepare(
                        "INSERT IGNORE INTO seller_invoices
                            (invoice_number, seller_id, order_id, invoice_year, gross_amount, tax_amount,
                             platform_charge_percent, platform_charge_amount, other_charges, adjustment_amount, net_amount,
                             seller_name_snapshot, business_name_snapshot, business_address_snapshot, gstin_snapshot,
                             seller_mobile_snapshot, seller_email_snapshot,
                             seller_legal_name_snapshot, seller_pan_snapshot, seller_gst_status_snapshot,
                             seller_state_snapshot, seller_state_code_snapshot,
                             platform_legal_name_snapshot, platform_gstin_snapshot, platform_pan_snapshot,
                             platform_address_snapshot, platform_state_snapshot, platform_state_code_snapshot,
                             buyer_name_snapshot, buyer_gstin_snapshot, buyer_pan_snapshot, buyer_address_snapshot,
                             buyer_state_snapshot, buyer_gst_status_snapshot,
                             tax_type, cgst_amount, sgst_amount, igst_amount)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?)"
                    );
                    $ins->bind_param(
                        "siiidddddddssssss" . "sssss" . "ssssss" . "ssssss" . "sddd",
                        $invoiceNumber, $sellerId, $orderId, $year, $fin['gross_amount'], $fin['tax_amount'],
                        $fin['platform_charge_percent'], $fin['platform_charge_amount'], $fin['other_charges'],
                        $fin['adjustment_amount'], $fin['net_amount'],
                        $snap['seller_name_snapshot'], $snap['business_name_snapshot'], $snap['business_address_snapshot'],
                        $snap['gstin_snapshot'], $snap['seller_mobile_snapshot'], $snap['seller_email_snapshot'],
                        $gstExtra['seller_legal_name_snapshot'], $gstExtra['seller_pan_snapshot'], $gstExtra['seller_gst_status_snapshot'],
                        $gstExtra['seller_state_snapshot'], $gstExtra['seller_state_code_snapshot'],
                        $gstExtra['platform_legal_name_snapshot'], $gstExtra['platform_gstin_snapshot'], $gstExtra['platform_pan_snapshot'],
                        $gstExtra['platform_address_snapshot'], $gstExtra['platform_state_snapshot'], $gstExtra['platform_state_code_snapshot'],
                        $gstExtra['buyer_name_snapshot'], $gstExtra['buyer_gstin_snapshot'], $gstExtra['buyer_pan_snapshot'], $gstExtra['buyer_address_snapshot'],
                        $gstExtra['buyer_state_snapshot'], $gstExtra['buyer_gst_status_snapshot'],
                        $gstExtra['tax_type'], $gstExtra['cgst_amount'], $gstExtra['sgst_amount'], $gstExtra['igst_amount']
                    );
                } elseif ($hasSnapshotCols) {
                    $ins = $conn->prepare(
                        "INSERT IGNORE INTO seller_invoices
                            (invoice_number, seller_id, order_id, invoice_year, gross_amount, tax_amount,
                             platform_charge_percent, platform_charge_amount, other_charges, adjustment_amount, net_amount,
                             seller_name_snapshot, business_name_snapshot, business_address_snapshot, gstin_snapshot,
                             seller_mobile_snapshot, seller_email_snapshot)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $ins->bind_param(
                        "siiidddddddssssss",
                        $invoiceNumber, $sellerId, $orderId, $year, $fin['gross_amount'], $fin['tax_amount'],
                        $fin['platform_charge_percent'], $fin['platform_charge_amount'], $fin['other_charges'],
                        $fin['adjustment_amount'], $fin['net_amount'],
                        $snap['seller_name_snapshot'], $snap['business_name_snapshot'], $snap['business_address_snapshot'],
                        $snap['gstin_snapshot'], $snap['seller_mobile_snapshot'], $snap['seller_email_snapshot']
                    );
                } else {
                    // Pre-upgrade schema (setup/seller_invoice_snapshot_upgrade.sql
                    // not yet run) — degrade to the original columns only.
                    $ins = $conn->prepare(
                        "INSERT IGNORE INTO seller_invoices
                            (invoice_number, seller_id, order_id, invoice_year, gross_amount, tax_amount,
                             platform_charge_percent, platform_charge_amount, other_charges, adjustment_amount, net_amount)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $ins->bind_param(
                        "siiidddddd",
                        $invoiceNumber, $sellerId, $orderId, $year, $fin['gross_amount'], $fin['tax_amount'],
                        $fin['platform_charge_percent'], $fin['platform_charge_amount'], $fin['other_charges'],
                        $fin['adjustment_amount'], $fin['net_amount']
                    );
                }
                $ins->execute();
                if ($ins->affected_rows > 0) break;
            } catch (\Throwable $e) { /* try again with a fresh number */ }
        }

        $final = $conn->prepare("SELECT * FROM seller_invoices WHERE seller_id = ? AND order_id = ? LIMIT 1");
        $final->bind_param("ii", $sellerId, $orderId);
        $final->execute();
        $row = $final->get_result()->fetch_assoc();
        if (!$row) return null;

        // Freeze AgriCart's current signature/stamp onto this row the
        // first time it exists (no-ops silently if already frozen, or
        // if setup/signature_stamp_upgrade.sql hasn't been run yet) —
        // see agri_seller_invoice_freeze_agricart_snapshot() below.
        if (function_exists('agri_seller_invoice_freeze_agricart_snapshot')) {
            agri_seller_invoice_freeze_agricart_snapshot($conn, (int)$row['id']);
            // Re-read so the caller sees the just-frozen columns.
            $final->execute();
            $row = $final->get_result()->fetch_assoc();
        }

        return array_merge($row, $fin);
    }
}

/**
 * Human-readable settlement status for a seller's invoice, derived
 * entirely from the underlying order_items / seller_earnings rows
 * (never stored separately, so it can never go stale or show "Settled"
 * before a payout has actually happened):
 *
 *   'refunded'            — every item in this seller's invoice was
 *                            cancelled/returned/refunded
 *   'partially_refunded'  — some (not all) items were
 *   'pending'             — earnings recognised but not yet available
 *                            for payout (order not yet delivered)
 *   'available'           — delivered; earnings sit in the seller's
 *                            available balance awaiting a withdrawal
 *                            (shown to the seller as "Processing")
 *   'paid'                — this earning has actually been marked paid
 *                            (only true once real per-earning payout
 *                            tracking sets seller_earnings.status =
 *                            'paid'; shown as "Settled")
 *
 * NOTE: the current payouts table records a *lump* withdrawal amount
 * against a seller's balance, not which specific earnings it covered —
 * so "Settled" is only ever reported when a future payout-reconciliation
 * step explicitly marks the row 'paid'. Until then, honestly-earned
 * money that's ready to withdraw is reported as "available"/Processing,
 * never invented as "Settled".
 */
if (!function_exists('agri_seller_invoice_settlement_status')) {
    function agri_seller_invoice_settlement_status($conn, $sellerId, $orderId) {
        // Every item of this seller's, in this order, cancelled/returned/refunded?
        $itemStmt = $conn->prepare(
            "SELECT oi.item_status FROM order_items oi JOIN products p ON p.id = oi.product_id
             WHERE p.added_by_user_id = ? AND oi.order_id = ?"
        );
        $itemStmt->bind_param("ii", $sellerId, $orderId);
        $itemStmt->execute();
        $terminal = ['cancelled', 'returned', 'refunded'];
        $total = 0; $refundedCount = 0;
        $res = $itemStmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $total++;
            if (in_array($r['item_status'], $terminal, true)) $refundedCount++;
        }
        if ($total > 0 && $refundedCount === $total) return 'refunded';
        if ($refundedCount > 0) return 'partially_refunded';

        $stmt = $conn->prepare(
            "SELECT se.status FROM seller_earnings se
             JOIN order_items oi ON oi.id = se.order_item_id
             WHERE se.seller_id = ? AND oi.order_id = ?"
        );
        $stmt->bind_param("ii", $sellerId, $orderId);
        $stmt->execute();
        $statuses = [];
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) { $statuses[$r['status']] = true; }

        if (!$statuses) return 'pending';
        if (isset($statuses['pending'])) return 'pending';
        if (isset($statuses['available'])) return 'available';
        if (isset($statuses['paid'])) return 'paid';
        return 'pending';
    }
}

// =====================================================================
// Dynamic Authorized Signatory system
// ---------------------------------------------------------------------
//   Seller Invoice  (pages/seller-invoice.php, seller/invoice.php)
//     -> AgriCart is always the authorized signatory.
//   Buyer Invoice   (pages/invoice.php, admin/invoice.php)
//     -> the seller/company on that specific order is the authorized
//        signatory (never AgriCart, never another seller).
//
// Both sides are historically protected: once an invoice has been
// generated/first-rendered, later edits to AgriCart's or a seller's
// signature/stamp never change what that invoice already shows.
// =====================================================================

if (!function_exists('agri_gst_platform_snapshot')) {
    /**
     * AgriCart's own Company/GST identity, live from Admin -> Settings ->
     * Company Settings (agricart_invoice_assets). Only for freezing a
     * NEW invoice snapshot — see agri_gst_platform_snapshot_frozen()
     * below for reading an already-generated invoice's frozen copy.
     */
    function agri_gst_platform_snapshot($conn) {
        $empty = ['legal_name' => 'AgriCart', 'gstin' => null, 'pan' => null, 'address' => null, 'state' => null, 'state_code' => null];
        if (!agri_table_exists_generic($conn, 'agricart_invoice_assets') || !agri_col_exists_generic($conn, 'agricart_invoice_assets', 'gstin')) {
            return $empty;
        }
        $res = $conn->query(
            "SELECT COALESCE(NULLIF(legal_name,''), 'AgriCart') legal_name, gstin, pan, registered_address address, state, state_code, gst_status
               FROM agricart_invoice_assets WHERE id = 1 LIMIT 1"
        );
        $row = $res ? $res->fetch_assoc() : null;
        if (!$row) return $empty;
        // Never show a fake GSTIN for AgriCart either.
        if (!in_array($row['gst_status'] ?? '', ['registered', 'composition'], true)) { $row['gstin'] = null; }
        return $row;
    }
}

if (!function_exists('agri_gst_buyer_snapshot')) {
    /** Buyer's GST details for one order, if they provided any (section 8 — never fabricate a GSTIN for an unregistered buyer). */
    function agri_gst_buyer_snapshot($conn, $orderId) {
        $empty = ['name' => null, 'gstin' => null, 'pan' => null, 'address' => null, 'state' => null, 'gst_status' => 'unregistered'];
        $hasBuyerGst = agri_table_exists_generic($conn, 'orders') && agri_col_exists_generic($conn, 'orders', 'buyer_gstin');

        $nameCol = agri_pick_col_generic($conn, 'orders', ['buyer_name', 'customer_name', 'shipping_name'], null);
        $addrCol = agri_pick_col_generic($conn, 'orders', ['shipping_address', 'delivery_address', 'address'], null);

        $select = $nameCol ? "$nameCol AS buyer_order_name" : "NULL AS buyer_order_name";
        $select .= ", " . ($addrCol ? "$addrCol AS buyer_order_address" : "NULL AS buyer_order_address");
        if ($hasBuyerGst) {
            $select .= ", buyer_gstin, buyer_gst_business_name, buyer_pan, buyer_gst_status, buyer_gst_state";
        }

        $stmt = $conn->prepare("SELECT $select FROM orders WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return $empty;

        $gstStatus = $hasBuyerGst ? ($row['buyer_gst_status'] ?? 'unregistered') : 'unregistered';
        $gstin = ($gstStatus === 'registered' && !empty($row['buyer_gstin'])) ? $row['buyer_gstin'] : null; // never a fake GSTIN

        return [
            'name' => (!empty($row['buyer_gst_business_name']) ? $row['buyer_gst_business_name'] : ($row['buyer_order_name'] ?? null)),
            'gstin' => $gstin,
            'pan' => $hasBuyerGst ? ($row['buyer_pan'] ?? null) : null,
            'address' => $row['buyer_order_address'] ?? null,
            'state' => $hasBuyerGst ? ($row['buyer_gst_state'] ?? null) : null,
            'gst_status' => $gstStatus,
        ];
    }
}

if (!function_exists('agri_gst_compute_tax')) {
    /**
     * The single place invoice generation decides CGST+SGST vs IGST
     * (section 10) and splits a known tax amount accordingly. Both the
     * live preview and the frozen-snapshot INSERT go through this.
     */
    function agri_gst_compute_tax($sellerState, $buyerState, $taxAmount) {
        $taxType = gstin_determine_tax_type($sellerState, $buyerState);
        $split = gstin_split_tax((float)$taxAmount, $taxType);
        return array_merge(['tax_type' => $taxType], $split);
    }
}

if (!function_exists('agri_gst_invoice_blocks')) {
    /**
     * The one function every invoice-rendering page (admin/invoice.php,
     * seller/invoice.php, pages/invoice.php, pages/seller-invoice.php)
     * should call to get Seller / AgriCart / Buyer GST blocks + the
     * CGST/SGST/IGST breakdown for a given seller_invoices row.
     *
     * Prefers the row's own frozen *_snapshot columns (section 17 —
     * historically accurate, never changes after generation). Falls
     * back to a live lookup only for invoices generated before this
     * GST upgrade (their snapshot columns are simply NULL), so older
     * invoices still render something sensible instead of blank GST
     * fields. $displaySettings controls what's actually shown, never
     * what's stored (section 14).
     */
    function agri_gst_invoice_blocks($conn, $invoiceRow) {
        $sellerId = (int)($invoiceRow['seller_id'] ?? 0);
        $orderId = (int)($invoiceRow['order_id'] ?? 0);
        $hasGst = array_key_exists('tax_type', $invoiceRow) && $invoiceRow['tax_type'] !== null;

        if (!$hasGst) {
            // Pre-upgrade invoice — degrade gracefully to a live lookup
            // (still never retroactively FREEZES anything onto the row).
            $snap = $sellerId ? agri_seller_identity_snapshot($conn, $sellerId) : [];
            $platform = agri_gst_platform_snapshot($conn);
            $buyer = $orderId ? agri_gst_buyer_snapshot($conn, $orderId) : ['name' => null, 'gstin' => null, 'pan' => null, 'address' => null, 'state' => null, 'gst_status' => 'unregistered'];
            $tax = agri_gst_compute_tax($snap['seller_state_snapshot'] ?? null, $buyer['state'] ?? null, $invoiceRow['tax_amount'] ?? 0);
            return [
                'seller' => [
                    'name' => $snap['seller_legal_name_snapshot'] ?? $invoiceRow['business_name_snapshot'] ?? $invoiceRow['seller_name_snapshot'] ?? null,
                    'address' => $invoiceRow['business_address_snapshot'] ?? null,
                    'gstin' => $invoiceRow['gstin_snapshot'] ?? null,
                    'pan' => $snap['seller_pan_snapshot'] ?? null,
                    'gst_status' => $snap['seller_gst_status_snapshot'] ?? (!empty($invoiceRow['gstin_snapshot']) ? 'registered' : 'unregistered'),
                    'state' => $snap['seller_state_snapshot'] ?? null,
                    'state_code' => $snap['seller_state_code_snapshot'] ?? null,
                ],
                'platform' => $platform,
                'buyer' => $buyer,
                'tax_type' => $tax['tax_type'], 'cgst_amount' => $tax['cgst'], 'sgst_amount' => $tax['sgst'], 'igst_amount' => $tax['igst'],
            ];
        }

        return [
            'seller' => [
                'name' => $invoiceRow['seller_legal_name_snapshot'] ?: $invoiceRow['business_name_snapshot'] ?: $invoiceRow['seller_name_snapshot'] ?? null,
                'address' => $invoiceRow['business_address_snapshot'] ?? null,
                'gstin' => $invoiceRow['gstin_snapshot'] ?? null,
                'pan' => $invoiceRow['seller_pan_snapshot'] ?? null,
                'gst_status' => $invoiceRow['seller_gst_status_snapshot'] ?? 'not_applicable',
                'state' => $invoiceRow['seller_state_snapshot'] ?? null,
                'state_code' => $invoiceRow['seller_state_code_snapshot'] ?? null,
            ],
            'platform' => [
                'legal_name' => $invoiceRow['platform_legal_name_snapshot'] ?: 'AgriCart',
                'gstin' => $invoiceRow['platform_gstin_snapshot'] ?? null,
                'pan' => $invoiceRow['platform_pan_snapshot'] ?? null,
                'address' => $invoiceRow['platform_address_snapshot'] ?? null,
                'state' => $invoiceRow['platform_state_snapshot'] ?? null,
                'state_code' => $invoiceRow['platform_state_code_snapshot'] ?? null,
            ],
            'buyer' => [
                'name' => $invoiceRow['buyer_name_snapshot'] ?? null,
                'gstin' => $invoiceRow['buyer_gstin_snapshot'] ?? null,
                'pan' => $invoiceRow['buyer_pan_snapshot'] ?? null,
                'address' => $invoiceRow['buyer_address_snapshot'] ?? null,
                'state' => $invoiceRow['buyer_state_snapshot'] ?? null,
                'gst_status' => $invoiceRow['buyer_gst_status_snapshot'] ?? 'unregistered',
            ],
            'tax_type' => $invoiceRow['tax_type'] ?? 'CGST_SGST',
            'cgst_amount' => (float)($invoiceRow['cgst_amount'] ?? 0),
            'sgst_amount' => (float)($invoiceRow['sgst_amount'] ?? 0),
            'igst_amount' => (float)($invoiceRow['igst_amount'] ?? 0),
        ];
    }
}

if (!function_exists('agri_agricart_invoice_assets')) {
    /**
     * AgriCart's own official signature/stamp, live from the
     * Admin Panel -> Settings -> Invoice Settings screen.
     * Only for freezing a NEW snapshot — an already-generated Seller
     * Invoice must keep reading its own frozen snapshot columns, not
     * this live row.
     */
    function agri_agricart_invoice_assets($conn) {
        if (!agri_table_exists_generic($conn, 'agricart_invoice_assets')) {
            return ['signature_path' => null, 'stamp_path' => null, 'signatory_name' => null, 'designation' => null];
        }
        $res = $conn->query("SELECT signature_path, stamp_path, signatory_name, designation FROM agricart_invoice_assets WHERE id = 1 LIMIT 1");
        $row = $res ? $res->fetch_assoc() : null;
        return $row ?: ['signature_path' => null, 'stamp_path' => null, 'signatory_name' => null, 'designation' => null];
    }
}

if (!function_exists('agri_seller_signature_assets')) {
    /** A seller's own uploaded signature/stamp, live from their Business Profile. */
    function agri_seller_signature_assets($conn, $sellerId) {
        $empty = ['signature_path' => null, 'stamp_path' => null, 'authorized_signatory_name' => null, 'signatory_designation' => null];
        $live = $empty;
        if (agri_table_exists_generic($conn, 'seller_payout_profiles') && agri_col_exists_generic($conn, 'seller_payout_profiles', 'signature_path')) {
            $stmt = $conn->prepare(
                "SELECT signature_path, stamp_path, authorized_signatory_name, signatory_designation
                   FROM seller_payout_profiles WHERE user_id = ? LIMIT 1"
            );
            $stmt->bind_param("i", $sellerId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) { $live = $row; }
        }

        // ---- Fallback: if the seller hasn't uploaded their own signature
        // and/or stamp via the Seller Dashboard Business Profile above,
        // fall back to the Stamp & Signature set centrally by Admin on the
        // matching Companies (`sellers` table) record — see
        // admin/company_profile.php "Stamp & Signature" card. Matched the
        // same best-effort way company_profile.php already links a company
        // to a seller login: business_name -> full_name -> email. Never
        // overwrites a value the seller already has; only fills in
        // whichever of signature/stamp/name/designation is still empty. ----
        if ((empty($live['signature_path']) || empty($live['stamp_path']))
            && agri_table_exists_generic($conn, 'sellers')
            && agri_col_exists_generic($conn, 'sellers', 'signature_path')) {
            $userRow = null;
            $uStmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ? LIMIT 1");
            $uStmt->bind_param("i", $sellerId);
            $uStmt->execute();
            $userRow = $uStmt->get_result()->fetch_assoc();

            $company = null;
            if (agri_table_exists_generic($conn, 'seller_payout_profiles') && agri_col_exists_generic($conn, 'seller_payout_profiles', 'business_name')) {
                $bStmt = $conn->prepare(
                    "SELECT s.signature_path, s.stamp_path, s.authorized_signatory_name, s.signatory_designation
                       FROM seller_payout_profiles spp JOIN sellers s ON s.name = spp.business_name
                      WHERE spp.user_id = ? LIMIT 1"
                );
                $bStmt->bind_param("i", $sellerId);
                $bStmt->execute();
                $company = $bStmt->get_result()->fetch_assoc() ?: null;
            }
            if (!$company && $userRow && !empty($userRow['full_name'])) {
                $nStmt = $conn->prepare(
                    "SELECT signature_path, stamp_path, authorized_signatory_name, signatory_designation
                       FROM sellers WHERE name = ? LIMIT 1"
                );
                $nStmt->bind_param("s", $userRow['full_name']);
                $nStmt->execute();
                $company = $nStmt->get_result()->fetch_assoc() ?: null;
            }
            if (!$company && $userRow && !empty($userRow['email'])) {
                $eStmt = $conn->prepare(
                    "SELECT signature_path, stamp_path, authorized_signatory_name, signatory_designation
                       FROM sellers WHERE email = ? LIMIT 1"
                );
                $eStmt->bind_param("s", $userRow['email']);
                $eStmt->execute();
                $company = $eStmt->get_result()->fetch_assoc() ?: null;
            }

            if ($company) {
                if (empty($live['signature_path']) && !empty($company['signature_path'])) { $live['signature_path'] = $company['signature_path']; }
                if (empty($live['stamp_path']) && !empty($company['stamp_path'])) { $live['stamp_path'] = $company['stamp_path']; }
                if (empty($live['authorized_signatory_name']) && !empty($company['authorized_signatory_name'])) { $live['authorized_signatory_name'] = $company['authorized_signatory_name']; }
                if (empty($live['signatory_designation']) && !empty($company['signatory_designation'])) { $live['signatory_designation'] = $company['signatory_designation']; }
            }
        }

        return $live;
    }
}

if (!function_exists('agri_seller_invoice_signatory_block')) {
    /**
     * Signatory block for a SELLER INVOICE row (seller_invoices) —
     * always AgriCart. Prefers the frozen agricart_*_snapshot columns
     * on that row (present once agri_seller_ensure_invoice() has run
     * after this upgrade); falls back to a live lookup only for rows
     * generated before the upgrade (NULL snapshot columns) or when no
     * row exists yet (e.g. a preview before the first sale registers).
     */
    function agri_seller_invoice_signatory_block($conn, $invoiceRow) {
        $sig = $invoiceRow['agricart_signature_snapshot'] ?? null;
        $stamp = $invoiceRow['agricart_stamp_snapshot'] ?? null;
        $name = $invoiceRow['agricart_signatory_name_snapshot'] ?? null;
        $designation = $invoiceRow['agricart_designation_snapshot'] ?? null;

        if ($sig === null && $stamp === null && $name === null) {
            $live = agri_agricart_invoice_assets($conn);
            $sig = $live['signature_path']; $stamp = $live['stamp_path'];
            $name = $live['signatory_name']; $designation = $live['designation'];
        }

        return [
            'for_name' => 'AgriCart',
            'signature_path' => $sig,
            'stamp_path' => $stamp,
            'signatory_name' => $name,
            'designation' => $designation,
            'missing_signature' => empty($sig),
            'missing_stamp' => empty($stamp),
        ];
    }
}

if (!function_exists('agri_seller_invoice_freeze_agricart_snapshot')) {
    /**
     * Freezes AgriCart's CURRENT signature/stamp onto a seller_invoices
     * row, once — called right after agri_seller_ensure_invoice() creates
     * a new row. No-ops if the snapshot columns don't exist yet
     * (setup/signature_stamp_upgrade.sql not run) or the row already has
     * a snapshot (never overwrite — that would defeat historical
     * protection).
     */
    function agri_seller_invoice_freeze_agricart_snapshot($conn, $invoiceId) {
        if (!agri_col_exists_generic($conn, 'seller_invoices', 'agricart_signature_snapshot')) return;
        $check = $conn->prepare("SELECT agricart_signature_snapshot, agricart_stamp_snapshot, agricart_signatory_name_snapshot FROM seller_invoices WHERE id = ? LIMIT 1");
        $check->bind_param("i", $invoiceId);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        if (!$row || $row['agricart_signature_snapshot'] !== null || $row['agricart_stamp_snapshot'] !== null || $row['agricart_signatory_name_snapshot'] !== null) return;

        $live = agri_agricart_invoice_assets($conn);
        $upd = $conn->prepare(
            "UPDATE seller_invoices SET agricart_signature_snapshot = ?, agricart_stamp_snapshot = ?,
                agricart_signatory_name_snapshot = ?, agricart_designation_snapshot = ? WHERE id = ?"
        );
        $upd->bind_param("ssssi", $live['signature_path'], $live['stamp_path'], $live['signatory_name'], $live['designation'], $invoiceId);
        $upd->execute();
    }
}

if (!function_exists('agri_buyer_invoice_signatory_block')) {
    /**
     * Signatory block for a BUYER INVOICE (pages/invoice.php,
     * admin/invoice.php) for one seller on one order. Historically
     * protected via buyer_invoice_signatory_snapshots: the first time
     * this is called for a given (order, seller) it freezes that
     * seller's current signature/stamp/name/designation/business name;
     * every later call for the same pair reads the frozen copy back, so
     * a seller editing their profile afterwards can never change an
     * invoice a buyer has already seen.
     */
    function agri_buyer_invoice_signatory_block($conn, $orderId, $sellerId, $businessNameFallback = null) {
        $hasSnapshotTable = agri_table_exists_generic($conn, 'buyer_invoice_signatory_snapshots');

        if ($hasSnapshotTable) {
            $stmt = $conn->prepare("SELECT * FROM buyer_invoice_signatory_snapshots WHERE order_id = ? AND seller_id = ? LIMIT 1");
            $stmt->bind_param("ii", $orderId, $sellerId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if (!$row) {
                $live = agri_seller_signature_assets($conn, $sellerId);
                $bizName = $businessNameFallback;
                if (agri_table_exists_generic($conn, 'seller_payout_profiles') && agri_col_exists_generic($conn, 'seller_payout_profiles', 'business_name')) {
                    $bStmt = $conn->prepare("SELECT business_name FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
                    $bStmt->bind_param("i", $sellerId);
                    $bStmt->execute();
                    $b = $bStmt->get_result()->fetch_assoc();
                    if (!empty($b['business_name'])) $bizName = $b['business_name'];
                }

                $ins = $conn->prepare(
                    "INSERT IGNORE INTO buyer_invoice_signatory_snapshots
                        (order_id, seller_id, business_name_snapshot, signature_path_snapshot, stamp_path_snapshot, signatory_name_snapshot, designation_snapshot)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $ins->bind_param(
                    "iisssss", $orderId, $sellerId, $bizName,
                    $live['signature_path'], $live['stamp_path'], $live['authorized_signatory_name'], $live['signatory_designation']
                );
                $ins->execute();

                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
            } elseif (
                empty($row['signature_path_snapshot']) || empty($row['stamp_path_snapshot'])
                || empty($row['business_name_snapshot']) || empty($row['signatory_name_snapshot'])
            ) {
                // ---- Backfill-only repair: this row was frozen back when
                // the seller (very often a demo seller Admin is still
                // setting up) had no signature/stamp/name yet, so it
                // captured blanks. That is not "historical data" worth
                // protecting — nothing was ever shown. Once Admin fills
                // those fields in later, pick them up here, but ONLY for
                // whichever columns are still empty; any column that
                // already holds a real value from a genuine past state is
                // left untouched, so a real historical change is still
                // never overwritten. ----
                $live = agri_seller_signature_assets($conn, $sellerId);
                $bizName = null;
                if (agri_table_exists_generic($conn, 'seller_payout_profiles') && agri_col_exists_generic($conn, 'seller_payout_profiles', 'business_name')) {
                    $bStmt = $conn->prepare("SELECT business_name FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
                    $bStmt->bind_param("i", $sellerId);
                    $bStmt->execute();
                    $b = $bStmt->get_result()->fetch_assoc();
                    if (!empty($b['business_name'])) $bizName = $b['business_name'];
                }

                $sets = []; $types = ''; $params = [];
                if (empty($row['signature_path_snapshot']) && !empty($live['signature_path'])) {
                    $sets[] = 'signature_path_snapshot = ?'; $types .= 's'; $params[] = $live['signature_path'];
                }
                if (empty($row['stamp_path_snapshot']) && !empty($live['stamp_path'])) {
                    $sets[] = 'stamp_path_snapshot = ?'; $types .= 's'; $params[] = $live['stamp_path'];
                }
                if (empty($row['signatory_name_snapshot']) && !empty($live['authorized_signatory_name'])) {
                    $sets[] = 'signatory_name_snapshot = ?'; $types .= 's'; $params[] = $live['authorized_signatory_name'];
                }
                if (empty($row['designation_snapshot']) && !empty($live['signatory_designation'])) {
                    $sets[] = 'designation_snapshot = ?'; $types .= 's'; $params[] = $live['signatory_designation'];
                }
                if (empty($row['business_name_snapshot']) && !empty($bizName)) {
                    $sets[] = 'business_name_snapshot = ?'; $types .= 's'; $params[] = $bizName;
                }

                if ($sets) {
                    $types .= 'ii'; $params[] = $orderId; $params[] = $sellerId;
                    $upd = $conn->prepare("UPDATE buyer_invoice_signatory_snapshots SET " . implode(', ', $sets) . " WHERE order_id = ? AND seller_id = ?");
                    $upd->bind_param($types, ...$params);
                    $upd->execute();

                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                }
            }

            if ($row) {
                return [
                    'for_name' => $row['business_name_snapshot'] ?: ($businessNameFallback ?: 'Seller'),
                    'signature_path' => $row['signature_path_snapshot'],
                    'stamp_path' => $row['stamp_path_snapshot'],
                    'signatory_name' => $row['signatory_name_snapshot'],
                    'designation' => $row['designation_snapshot'],
                    'missing_signature' => empty($row['signature_path_snapshot']),
                    'missing_stamp' => empty($row['stamp_path_snapshot']),
                ];
            }
        }

        // Pre-upgrade schema or insert race fallback — live lookup only.
        $live = agri_seller_signature_assets($conn, $sellerId);
        return [
            'for_name' => $businessNameFallback ?: 'Seller',
            'signature_path' => $live['signature_path'],
            'stamp_path' => $live['stamp_path'],
            'signatory_name' => $live['authorized_signatory_name'],
            'designation' => $live['signatory_designation'],
            'missing_signature' => empty($live['signature_path']),
            'missing_stamp' => empty($live['stamp_path']),
        ];
    }
}
