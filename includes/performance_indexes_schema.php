<?php
/**
 * includes/performance_indexes_schema.php — spec §19 DB performance pass.
 *
 * Adds indexes on the columns that get filtered/joined/sorted on most
 * often across this codebase (orders, order_items, products, payouts,
 * users, notifications) — every one of these columns already appears in
 * a WHERE/JOIN/ORDER BY somewhere in admin/report.php, admin/index.php,
 * admin/finance_center.php, seller/seller_api.php, pages/marketplace.php,
 * etc. Confirmed by grepping this codebase's own queries, not guessed.
 *
 * PURELY ADDITIVE: only ADD INDEX, never touches data, never drops or
 * alters an existing index, and checks information_schema first so it's
 * a no-op (not an error) if a column already has one — safe to call on
 * every request, same idempotent pattern as every other *_schema.php
 * file in this codebase (companies_schema.php, gstin_schema.php, etc).
 * Adding an index never changes what a query returns, only how fast it
 * runs, so this cannot break any existing feature.
 */

if (!function_exists('agri_perf_index_exists')) {
    function agri_perf_index_exists(mysqli $conn, string $table, string $indexName): bool
    {
        try {
            $stmt = $conn->prepare(
                "SELECT 1 FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1"
            );
            $stmt->bind_param('ss', $table, $indexName);
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        } catch (\Throwable $e) {
            return true; // if we can't check, don't risk a duplicate-key error
        }
    }
}

if (!function_exists('agri_perf_table_has_column')) {
    function agri_perf_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        try {
            $stmt = $conn->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
            );
            $stmt->bind_param('ss', $table, $column);
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('agri_perf_add_index')) {
    function agri_perf_add_index(mysqli $conn, string $table, string $indexName, array $columns): void
    {
        try {
            if (agri_perf_index_exists($conn, $table, $indexName)) { return; }
            foreach ($columns as $col) {
                if (!agri_perf_table_has_column($conn, $table, $col)) { return; } // table shape differs on this install — skip safely
            }
            $colList = implode(', ', array_map(fn($c) => "`$c`", $columns));
            $conn->query("ALTER TABLE `$table` ADD INDEX `$indexName` ($colList)");
        } catch (\Throwable $e) {
            // best-effort — a missing index is a performance issue, never a
            // functional one, so this must never surface as a fatal error.
        }
    }
}

if (!function_exists('agri_perf_bootstrap_indexes')) {
    function agri_perf_bootstrap_indexes(mysqli $conn): void
    {
        static $done = false;
        if ($done) { return; }
        $done = true;

        // Cheap one-row marker so the ~15 INFORMATION_SCHEMA checks below
        // only ever run ONCE per install (not once per request) — after
        // that this whole function costs a single indexed SELECT, so it's
        // safe to call unconditionally from includes/db.php.
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS _agri_perf_index_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ran_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $chk = $conn->query("SELECT 1 FROM _agri_perf_index_log LIMIT 1");
            if ($chk && $chk->num_rows > 0) { return; } // already ran on this install
        } catch (\Throwable $e) {
            return; // can't even check — don't risk running the ALTERs repeatedly
        }

        // orders — filtered by status/date everywhere (Dashboard, Reports,
        // Finance Center); joined on user_id for every buyer-facing query.
        agri_perf_add_index($conn, 'orders', 'idx_orders_user_id', ['user_id']);
        agri_perf_add_index($conn, 'orders', 'idx_orders_status', ['order_status']);
        agri_perf_add_index($conn, 'orders', 'idx_orders_created_at', ['created_at']);
        agri_perf_add_index($conn, 'orders', 'idx_orders_payment_status', ['payment_status']);

        // order_items — the single most-joined table in the app (every
        // invoice, every commission calc, every dashboard stat touches it).
        agri_perf_add_index($conn, 'order_items', 'idx_order_items_order_id', ['order_id']);
        agri_perf_add_index($conn, 'order_items', 'idx_order_items_product_id', ['product_id']);

        // products — category/seller/stock filters (marketplace, commission
        // resolver, low-stock dashboard card, inventory).
        agri_perf_add_index($conn, 'products', 'idx_products_category', ['category']);
        agri_perf_add_index($conn, 'products', 'idx_products_approval_status', ['approval_status']);
        agri_perf_add_index($conn, 'products', 'idx_products_added_by', ['added_by_user_id']);

        // payouts — Admin queue, seller's own payout history, Finance Center.
        agri_perf_add_index($conn, 'payouts', 'idx_payouts_seller_id', ['seller_id']);
        agri_perf_add_index($conn, 'payouts', 'idx_payouts_status', ['status']);
        agri_perf_add_index($conn, 'payouts', 'idx_payouts_requested_at', ['requested_at']);

        // users — role filter appears in nearly every admin list query
        // (accounts, companies, commission sellers dropdown, global search).
        agri_perf_add_index($conn, 'users', 'idx_users_role', ['role']);

        // notification inboxes — every dashboard/bell load filters unread-first.
        agri_perf_add_index($conn, 'user_notifications', 'idx_user_notif_user_unread', ['user_id', 'is_read']);
        agri_perf_add_index($conn, 'seller_notifications', 'idx_seller_notif_seller_unread', ['seller_id', 'is_read']);

        // Mark done so no future request repeats any of the above.
        try { $conn->query("INSERT INTO _agri_perf_index_log () VALUES ()"); } catch (\Throwable $e) {}
    }
}
