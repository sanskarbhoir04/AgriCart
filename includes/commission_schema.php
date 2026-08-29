<?php
/**
 * Commission Management schema + resolver (spec §10).
 *
 * Replaces the two previously-hardcoded `AGRI_PRODUCT_COMMISSION_PERCENT = 5.00`
 * constants (pages/sell_product.php, pages/insert_product.php) with a real,
 * database-driven, admin-editable commission system:
 *
 *   commission_settings   — one row: the platform-wide default (type,
 *                            percent/fixed amount, min/max cap, effective
 *                            date, active/inactive).
 *   category_commission   — optional per-category override.
 *   seller_commission     — optional per-seller override (highest priority).
 *
 * Resolution order for any given sale: seller override -> category override
 * -> global default. Nothing here removes or changes commission_percent
 * already stored on existing products/equipment rows — this only changes
 * what value gets used for *new* listings going forward, so historical
 * invoices are untouched.
 *
 * Self-registering + idempotent, same pattern as companies_schema.php /
 * inventory_schema.php: safe to require on every request.
 */

if (!function_exists('commission_bootstrap_schema')) {
    function commission_bootstrap_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) { return; }
        $done = true;

        try {
            $conn->query("
                CREATE TABLE IF NOT EXISTS commission_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    commission_type ENUM('percentage','fixed','percentage_plus_fixed') NOT NULL DEFAULT 'percentage',
                    default_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00,
                    default_fixed_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    min_commission DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    max_commission DECIMAL(10,2) NULL,
                    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    effective_from DATE NOT NULL,
                    updated_by INT NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->query("
                CREATE TABLE IF NOT EXISTS category_commission (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    category VARCHAR(100) NOT NULL,
                    commission_percent DECIMAL(5,2) NOT NULL,
                    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_category (category)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $conn->query("
                CREATE TABLE IF NOT EXISTS seller_commission (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    commission_percent DECIMAL(5,2) NOT NULL,
                    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                    notes VARCHAR(255) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Seed exactly one default row, matching the value the two
            // hardcoded constants used, so behaviour is identical on first
            // run — admins can then change it from the new UI.
            $chk = $conn->query("SELECT COUNT(*) c FROM commission_settings");
            $count = $chk ? (int)($chk->fetch_assoc()['c'] ?? 0) : 0;
            if ($count === 0) {
                $conn->query("
                    INSERT INTO commission_settings
                        (commission_type, default_percent, default_fixed_amount, min_commission, max_commission, status, effective_from)
                    VALUES ('percentage', 5.00, 0.00, 0.00, NULL, 'active', CURDATE())
                ");
            }

            // Preserve the equipment-rental commission (previously a separate
            // hardcoded 10% constant) as a category override, so upgrading to
            // this system doesn't silently change what owners already earn.
            $chkEq = $conn->query("SELECT id FROM category_commission WHERE category = 'equipment_rental' LIMIT 1");
            if ($chkEq && $chkEq->num_rows === 0) {
                $conn->query("INSERT INTO category_commission (category, commission_percent, status) VALUES ('equipment_rental', 10.00, 'active')");
            }

            // ---- Permission (same self-registering pattern as companies.*) ----
            $exists = $conn->prepare("SELECT id FROM admin_permissions WHERE permission_key = 'finance.commission' LIMIT 1");
            $exists->execute();
            if ($exists->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO admin_permissions (permission_key, module_name, action_name) VALUES ('finance.commission', 'finance', 'commission')");
                $ins->execute();
            }
        } catch (\Throwable $e) {
            $done = false; // retry on next request if the DB user lacks CREATE TABLE rights etc.
        }
    }
}

if (!function_exists('agri_resolve_commission_percent')) {
    /**
     * Returns the commission percent that should be stamped onto a NEW
     * product/equipment listing right now, checking (in priority order):
     *   1. an active seller-specific override for $userId
     *   2. an active category-specific override for $category
     *   3. the active global default
     * Falls back to 5.00 only if the table is missing/unreachable, so a
     * broken DB never blocks a farmer from listing a product.
     */
    function agri_resolve_commission_percent(mysqli $conn, ?string $category = null, ?int $userId = null): float
    {
        try {
            commission_bootstrap_schema($conn);

            if ($userId) {
                $s = $conn->prepare("SELECT commission_percent FROM seller_commission WHERE user_id = ? AND status = 'active' LIMIT 1");
                $s->bind_param('i', $userId);
                $s->execute();
                $row = $s->get_result()->fetch_assoc();
                if ($row) { return (float)$row['commission_percent']; }
            }

            if ($category) {
                $c = $conn->prepare("SELECT commission_percent FROM category_commission WHERE category = ? AND status = 'active' LIMIT 1");
                $c->bind_param('s', $category);
                $c->execute();
                $row = $c->get_result()->fetch_assoc();
                if ($row) { return (float)$row['commission_percent']; }
            }

            $g = $conn->query("SELECT default_percent, min_commission, max_commission FROM commission_settings WHERE status = 'active' ORDER BY effective_from DESC, id DESC LIMIT 1");
            $row = $g ? $g->fetch_assoc() : null;
            if ($row) {
                $pct = (float)$row['default_percent'];
                if ($row['min_commission'] !== null && $pct < (float)$row['min_commission']) { $pct = (float)$row['min_commission']; }
                if ($row['max_commission'] !== null && $pct > (float)$row['max_commission']) { $pct = (float)$row['max_commission']; }
                return $pct;
            }
        } catch (\Throwable $e) {
            // fall through to the safe default below
        }
        return 5.00;
    }
}
