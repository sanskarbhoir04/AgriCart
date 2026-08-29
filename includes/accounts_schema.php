<?php
// =====================================================================
// includes/accounts_schema.php — Bootstrap + shared helpers for the
// Accounts Management module (admin/accounts.php, account_action.php,
// account_details.php, account_export.php).
//
// IMPORTANT — no new "accounts" table is created. AgriCart already has
// everything an "account" is:
//   - Buyers / Sellers / Farmers / Experts -> the `users` table (role
//     column already distinguishes them; real registered sellers are
//     `users` with role='seller', see includes/seller_functions.php).
//   - Companies (the business/seller directory shown on the public
//     storefront) -> the `sellers` table (see admin/includes/
//     companies_schema.php for why that table, despite its name, is
//     really the Company record).
//   - Employees (internal admin/staff accounts) -> `admin_team_members`
//     joined to `users` (role='admin') + `admin_roles`.
// This file only ADDS the columns Accounts Management needs on top of
// those three tables (status reason/trail, verification flags, last
// login) — additive only, every ALTER wrapped in its own try/catch so
// one failing statement never blocks the rest or breaks the page.
//
// Follows the exact defensive pattern already used across this codebase
// (see admin/includes/companies_schema.php / inventory_schema.php).
// Safe to include from BOTH admin pages and public pages (login/
// register) since acc_stamp_login() needs the `users.last_login_at`
// column to exist outside the admin panel too.
// =====================================================================

if (!function_exists('acc_col_exists')) {
    function acc_col_exists(mysqli $conn, string $table, string $column): bool
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

if (!function_exists('acc_table_exists')) {
    function acc_table_exists(mysqli $conn, string $table): bool
    {
        try {
            $stmt = $conn->prepare(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
            );
            $stmt->bind_param('s', $table);
            $stmt->execute();
            return $stmt->get_result()->num_rows > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('accounts_bootstrap_schema')) {
    /**
     * Idempotent, additive-only schema setup for Accounts Management.
     * Safe to call on every admin page load.
     */
    function accounts_bootstrap_schema(mysqli $conn): void
    {
        // ---- users (Buyers / Sellers / Farmers / Experts) ----
        if (acc_table_exists($conn, 'users')) {
            // Widen the status enum additively (existing values kept as-is,
            // 'banned' stays supported and is just treated as a legacy
            // alias for 'blocked' everywhere it's displayed).
            try {
                $conn->query(
                    "ALTER TABLE users MODIFY COLUMN status
                     ENUM('active','inactive','banned','suspended','blocked','pending_verification') DEFAULT 'active'"
                );
            } catch (\Throwable $e) { /* status filter just won't show the new values until fixed */ }

            foreach ([
                ['last_login_at',      "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER updated_at"],
                ['login_method',       "ALTER TABLE users ADD COLUMN login_method VARCHAR(20) NULL AFTER last_login_at"],
                ['status_reason',      "ALTER TABLE users ADD COLUMN status_reason VARCHAR(255) NULL AFTER status"],
                ['status_changed_at',  "ALTER TABLE users ADD COLUMN status_changed_at DATETIME NULL AFTER status_reason"],
                ['status_changed_by',  "ALTER TABLE users ADD COLUMN status_changed_by INT NULL AFTER status_changed_at"],
                ['kyc_verified',       "ALTER TABLE users ADD COLUMN kyc_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER mobile_verified"],
                ['deleted_at',         "ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL"],
            ] as [$col, $sql]) {
                if (acc_col_exists($conn, 'users', $col)) { continue; }
                try { $conn->query($sql); } catch (\Throwable $e) { /* this field stays unavailable until fixed */ }
            }
        }

        // ---- sellers (= Companies directory, see companies_schema.php) ----
        if (acc_table_exists($conn, 'sellers')) {
            foreach ([
                ['gstin',              "ALTER TABLE sellers ADD COLUMN gstin VARCHAR(20) NULL"],
                ['gst_verified',       "ALTER TABLE sellers ADD COLUMN gst_verified TINYINT(1) NOT NULL DEFAULT 0"],
                ['business_verified',  "ALTER TABLE sellers ADD COLUMN business_verified TINYINT(1) NOT NULL DEFAULT 0"],
                ['bank_verified',      "ALTER TABLE sellers ADD COLUMN bank_verified TINYINT(1) NOT NULL DEFAULT 0"],
                ['kyc_verified',       "ALTER TABLE sellers ADD COLUMN kyc_verified TINYINT(1) NOT NULL DEFAULT 0"],
                ['account_status',     "ALTER TABLE sellers ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT 'active'"],
                ['status_reason',      "ALTER TABLE sellers ADD COLUMN status_reason VARCHAR(255) NULL"],
                ['business_type',      "ALTER TABLE sellers ADD COLUMN business_type VARCHAR(100) NULL"],
                ['district',           "ALTER TABLE sellers ADD COLUMN district VARCHAR(100) NULL"],
                ['state',              "ALTER TABLE sellers ADD COLUMN state VARCHAR(100) NULL"],
                ['pincode',            "ALTER TABLE sellers ADD COLUMN pincode VARCHAR(10) NULL"],
            ] as [$col, $sql]) {
                if (acc_col_exists($conn, 'sellers', $col)) { continue; }
                try { $conn->query($sql); } catch (\Throwable $e) { /* non-fatal */ }
            }
        }

        // ---- admin_team_members (Employees) ----
        if (acc_table_exists($conn, 'admin_team_members')) {
            try {
                $conn->query(
                    "ALTER TABLE admin_team_members MODIFY COLUMN status
                     ENUM('active','inactive','suspended','expired','blocked') NOT NULL DEFAULT 'active'"
                );
            } catch (\Throwable $e) { /* non-fatal */ }
            if (!acc_col_exists($conn, 'admin_team_members', 'status_reason')) {
                try { $conn->query("ALTER TABLE admin_team_members ADD COLUMN status_reason VARCHAR(255) NULL"); }
                catch (\Throwable $e) { /* non-fatal */ }
            }
        }

        // ---- Document uploads for KYC/business/GST verification ----
        if (!acc_table_exists($conn, 'account_documents')) {
            try {
                $conn->query(
                    "CREATE TABLE account_documents (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        account_type ENUM('buyer','seller','company','employee') NOT NULL,
                        account_id INT NOT NULL,
                        doc_type VARCHAR(60) NOT NULL,
                        file_path VARCHAR(255) NOT NULL,
                        uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        KEY idx_account (account_type, account_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } catch (\Throwable $e) { /* non-fatal — Documents tab just shows empty state */ }
        }

        // ---- Permissions (same self-registering pattern as companies.*) ----
        try {
            $perms = [
                'accounts.view'    => ['accounts', 'view'],
                'accounts.manage'  => ['accounts', 'manage'],
                'accounts.verify'  => ['accounts', 'verify'],
                'accounts.export'  => ['accounts', 'export'],
            ];
            foreach ($perms as $key => [$module, $act]) {
                $exists = $conn->prepare("SELECT id FROM admin_permissions WHERE permission_key = ? LIMIT 1");
                $exists->bind_param('s', $key);
                $exists->execute();
                if ($exists->get_result()->num_rows > 0) { continue; }
                $ins = $conn->prepare("INSERT INTO admin_permissions (permission_key, module_name, action_name) VALUES (?,?,?)");
                $ins->bind_param('sss', $key, $module, $act);
                $ins->execute();
            }
        } catch (\Throwable $e) { /* permissions retried on next page load */ }
    }
}

if (!function_exists('acc_stamp_login')) {
    /**
     * Called right after a storefront login/registration sets
     * $_SESSION['user_id'], to keep users.last_login_at accurate for the
     * Accounts dashboard. Silently no-ops if the column isn't there yet
     * (it will be, next time an admin page bootstraps the schema) —
     * never allowed to break the actual login flow.
     */
    function acc_stamp_login(mysqli $conn, int $userId, string $method = 'password'): void
    {
        try {
            $stmt = $conn->prepare("UPDATE users SET last_login_at = NOW(), login_method = ? WHERE id = ?");
            $stmt->bind_param('si', $method, $userId);
            $stmt->execute();
        } catch (\Throwable $e) { /* column not migrated yet — ignore */ }
    }
}

if (!function_exists('acc_fmt_date')) {
    function acc_fmt_date($d, string $fmt = 'd M Y'): string { return $d ? date($fmt, strtotime($d)) : '—'; }
}

if (!function_exists('acc_fmt_datetime')) {
    function acc_fmt_datetime($d): string { return $d ? date('d M Y, h:i A', strtotime($d)) : '—'; }
}

if (!function_exists('acc_img_url')) {
    /** Same site-root-relative -> admin-relative path fix as cmp_img_url(). */
    function acc_img_url(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') { return ''; }
        if (preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $path) || $path[0] === '/') { return $path; }
        return '../' . ltrim($path, './');
    }
}

if (!function_exists('acc_status_label')) {
    /** Normalizes the various raw status strings across users/sellers/admin_team_members into one label + badge class. */
    function acc_status_label(?string $raw, ?string $deletedAt = null): array
    {
        if (!empty($deletedAt)) { return ['Deactivated', 'inactive']; }
        $raw = strtolower((string)$raw);
        switch ($raw) {
            case 'active':                return ['Active', 'active'];
            case 'inactive':               return ['Inactive', 'inactive'];
            case 'expired':                return ['Expired', 'expired'];
            case 'suspended':              return ['Suspended', 'suspended'];
            case 'blocked':
            case 'banned':                 return ['Blocked', 'suspended'];
            case 'pending_verification':   return ['Pending Verification', 'pending'];
            default:                       return [ucfirst($raw ?: 'Active'), 'active'];
        }
    }
}

if (!function_exists('acc_verify_label')) {
    function acc_verify_label($flag): array
    {
        return $flag ? ['Verified', 'active'] : ['Pending', 'pending'];
    }
}

if (!function_exists('acc_role_type')) {
    /** Maps a users.role value to the Accounts module's account-type bucket. */
    function acc_role_type(?string $role): string
    {
        switch ($role) {
            case 'seller': return 'seller';
            case 'admin':  return 'employee';
            default:       return 'buyer'; // farmer, customer, buyer, expert
        }
    }
}

if (!function_exists('acc_type_label')) {
    function acc_type_label(string $type): string
    {
        return ['buyer' => 'Buyer', 'seller' => 'Seller', 'company' => 'Company', 'employee' => 'Employee'][$type] ?? ucfirst($type);
    }
}

if (!function_exists('acc_buyer_stats')) {
    /** Order/wishlist/review counters for one buyer (users.id). */
    function acc_buyer_stats(mysqli $conn, int $userId): array
    {
        $stats = ['total_orders' => 0, 'completed_orders' => 0, 'pending_orders' => 0, 'cancelled_orders' => 0,
                  'total_value' => 0.0, 'total_refunds' => 0.0, 'wishlist' => 0, 'cart' => 0, 'reviews' => 0];
        $stmt = $conn->prepare(
            "SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN order_status = 'delivered' OR status = 'delivered' THEN 1 ELSE 0 END) AS completed_orders,
                SUM(CASE WHEN status IN ('pending','confirmed','processing','shipped') THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
                SUM(final_amount) AS total_value,
                SUM(CASE WHEN payment_status = 'refunded' THEN final_amount ELSE 0 END) AS total_refunds
             FROM orders WHERE user_id = ?"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $stats['total_orders']     = (int)$row['total_orders'];
            $stats['completed_orders'] = (int)$row['completed_orders'];
            $stats['pending_orders']   = (int)$row['pending_orders'];
            $stats['cancelled_orders'] = (int)$row['cancelled_orders'];
            $stats['total_value']      = (float)$row['total_value'];
            $stats['total_refunds']    = (float)$row['total_refunds'];
        }
        if (acc_table_exists($conn, 'wishlist')) {
            $s = $conn->prepare("SELECT COUNT(*) c FROM wishlist WHERE user_id = ?");
            $s->bind_param('i', $userId); $s->execute();
            $stats['wishlist'] = (int)($s->get_result()->fetch_assoc()['c'] ?? 0);
        }
        if (acc_table_exists($conn, 'cart')) {
            $s = $conn->prepare("SELECT COUNT(*) c FROM cart WHERE user_id = ?");
            $s->bind_param('i', $userId); $s->execute();
            $stats['cart'] = (int)($s->get_result()->fetch_assoc()['c'] ?? 0);
        }
        $s = $conn->prepare("SELECT COUNT(*) c FROM reviews WHERE user_id = ?");
        $s->bind_param('i', $userId); $s->execute();
        $stats['reviews'] = (int)($s->get_result()->fetch_assoc()['c'] ?? 0);
        return $stats;
    }
}

if (!function_exists('acc_seller_stats')) {
    /** Product/order/earnings counters for one registered seller (users.id). Mirrors seller/dashboard.php's own numbers. */
    function acc_seller_stats(mysqli $conn, int $userId): array
    {
        $stats = ['total_products' => 0, 'active_products' => 0, 'out_of_stock' => 0,
                  'total_orders' => 0, 'completed_orders' => 0, 'cancelled_orders' => 0,
                  'total_sales' => 0.0, 'total_earnings' => 0.0, 'pending_earnings' => 0.0,
                  'withdrawn' => 0.0, 'pending_withdrawal' => 0.0, 'rating' => 0.0, 'reviews' => 0];

        $s = $conn->prepare("SELECT COUNT(*) total, SUM(is_active=1) active, SUM(stock<=0) oos FROM products WHERE added_by_user_id = ?");
        $s->bind_param('i', $userId); $s->execute();
        if ($row = $s->get_result()->fetch_assoc()) {
            $stats['total_products']  = (int)$row['total'];
            $stats['active_products'] = (int)$row['active'];
            $stats['out_of_stock']    = (int)$row['oos'];
        }

        $s = $conn->prepare(
            "SELECT COUNT(*) total,
                    SUM(item_status IN ('delivered')) completed,
                    SUM(item_status IN ('cancelled','returned')) cancelled,
                    SUM(subtotal) sales
             FROM order_items WHERE seller_id = ?"
        );
        $s->bind_param('i', $userId); $s->execute();
        if ($row = $s->get_result()->fetch_assoc()) {
            $stats['total_orders']     = (int)$row['total'];
            $stats['completed_orders'] = (int)$row['completed'];
            $stats['cancelled_orders'] = (int)$row['cancelled'];
            $stats['total_sales']      = (float)$row['sales'];
        }

        if (acc_table_exists($conn, 'seller_payout_profiles')) {
            $s = $conn->prepare("SELECT total_earnings, pending_balance, available_balance, total_paid FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
            $s->bind_param('i', $userId); $s->execute();
            if ($row = $s->get_result()->fetch_assoc()) {
                $stats['total_earnings']      = (float)$row['total_earnings'];
                $stats['pending_earnings']    = (float)$row['pending_balance'];
                $stats['pending_withdrawal']  = (float)$row['available_balance'];
                $stats['withdrawn']           = (float)$row['total_paid'];
            }
        }

        $s = $conn->prepare(
            "SELECT AVG(r.rating) avg_r, COUNT(*) c FROM reviews r
             JOIN products p ON p.id = r.item_id AND r.item_type = 'product'
             WHERE p.added_by_user_id = ?"
        );
        $s->bind_param('i', $userId); $s->execute();
        if ($row = $s->get_result()->fetch_assoc()) {
            $stats['rating']  = round((float)$row['avg_r'], 1);
            $stats['reviews'] = (int)$row['c'];
        }
        return $stats;
    }
}

if (!function_exists('acc_company_match_where')) {
    /** Reuses the same farmer_name / seller_id matching logic as companies_schema.php's cmp_company_match_joined(). */
    function acc_company_match_where(mysqli $conn, string $prodAlias, int $companyId, string $companyName): string
    {
        if (function_exists('cmp_products_has_seller_id') && cmp_products_has_seller_id($conn)) {
            return "($prodAlias.seller_id = $companyId OR ($prodAlias.seller_id IS NULL AND $prodAlias.farmer_name = '" . $conn->real_escape_string($companyName) . "'))";
        }
        return "$prodAlias.farmer_name = '" . $conn->real_escape_string($companyName) . "'";
    }
}

if (!function_exists('acc_company_stats')) {
    /** Product/order counters for one Company (sellers.id) — same relationship product listings already use. */
    function acc_company_stats(mysqli $conn, int $companyId, string $companyName): array
    {
        $stats = ['total_products' => 0, 'active_products' => 0, 'total_orders' => 0, 'total_sales' => 0.0, 'rating' => 0.0, 'reviews' => 0];
        $match = acc_company_match_where($conn, 'p', $companyId, $companyName);
        $res = $conn->query("SELECT COUNT(*) total, SUM(is_active=1) active FROM products p WHERE $match");
        if ($res && ($row = $res->fetch_assoc())) {
            $stats['total_products']  = (int)$row['total'];
            $stats['active_products'] = (int)$row['active'];
        }
        $matchOi = acc_company_match_where($conn, 'p', $companyId, $companyName);
        $res = $conn->query(
            "SELECT COUNT(*) total, SUM(oi.subtotal) sales FROM order_items oi
             JOIN products p ON p.id = oi.product_id WHERE $matchOi"
        );
        if ($res && ($row = $res->fetch_assoc())) {
            $stats['total_orders'] = (int)$row['total'];
            $stats['total_sales']  = (float)$row['sales'];
        }
        $res = $conn->query(
            "SELECT AVG(r.rating) avg_r, COUNT(*) c FROM reviews r
             JOIN products p ON p.id = r.item_id AND r.item_type='product' WHERE $match"
        );
        if ($res && ($row = $res->fetch_assoc())) {
            $stats['rating']  = round((float)$row['avg_r'], 1);
            $stats['reviews'] = (int)$row['c'];
        }
        return $stats;
    }
}

if (!function_exists('acc_employee_activity_count')) {
    function acc_employee_activity_count(mysqli $conn, int $adminUserId): int
    {
        $s = $conn->prepare("SELECT COUNT(*) c FROM admin_activity_logs WHERE admin_user_id = ?");
        $s->bind_param('i', $adminUserId); $s->execute();
        return (int)($s->get_result()->fetch_assoc()['c'] ?? 0);
    }
}
