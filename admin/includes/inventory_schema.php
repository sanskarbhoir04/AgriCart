<?php
// =====================================================================
// admin/includes/inventory_schema.php — Bootstrap + shared helpers for
// the Inventory Management module (inventory.php / inventory_action.php
// / inventory_export.php).
//
// Follows the exact defensive pattern already used across this codebase
// (see includes/reports_schema.php / admin/index.php's
// agri_connect_bootstrap_schema): every helper degrades to a safe
// default instead of a fatal error when a table/column isn't there yet,
// and inventory_bootstrap_schema() only ever ADDS columns/tables — it
// never touches or drops anything that already exists, so it cannot
// break existing Product / Equipment / Rental functionality.
//
// Include this AFTER admin_guard.php (so $conn + permissions.php are
// already available). reports_schema.php is also expected to be loaded
// alongside this file wherever rpt_*() helpers are used, so we don't
// duplicate that generic query-helper logic here.
// =====================================================================

if (!function_exists('inv_col_exists')) {
    function inv_col_exists(mysqli $conn, string $table, string $column): bool
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

if (!function_exists('inv_table_exists')) {
    function inv_table_exists(mysqli $conn, string $table): bool
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

if (!function_exists('inventory_bootstrap_schema')) {
    /**
     * Idempotent, additive-only schema setup for the Inventory module.
     * Safe to call on every page load (each check is a no-op once the
     * column/table already exists), exactly like agri_connect_bootstrap_schema()
     * and reports_bootstrap_permission() elsewhere in this project.
     */
    function inventory_bootstrap_schema(mysqli $conn): void
    {
        try {
            // ---- products: SKU + low-stock threshold (both nullable/defaulted
            // so every existing row keeps working with no data migration). ----
            if (!inv_col_exists($conn, 'products', 'sku')) {
                $conn->query("ALTER TABLE products ADD COLUMN sku VARCHAR(60) NULL AFTER name");
            }
            if (!inv_col_exists($conn, 'products', 'low_stock_threshold')) {
                $conn->query("ALTER TABLE products ADD COLUMN low_stock_threshold INT NOT NULL DEFAULT 10");
            }
            if (!inv_col_exists($conn, 'products', 'updated_at')) {
                $conn->query("ALTER TABLE products ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }

            // ---- equipment: maintenance tracking + asset value. Equipment in
            // this schema is one row per physical unit (booked by date range
            // via equipment_bookings), so "available units" for a listing is
            // derived at query time from `availability` + active bookings +
            // maintenance_status rather than stored as a separate counter. ----
            if (!inv_col_exists($conn, 'equipment', 'maintenance_status')) {
                $conn->query("ALTER TABLE equipment ADD COLUMN maintenance_status ENUM('available','maintenance','out_of_service') NOT NULL DEFAULT 'available'");
            }
            if (!inv_col_exists($conn, 'equipment', 'equipment_value')) {
                $conn->query("ALTER TABLE equipment ADD COLUMN equipment_value DECIMAL(12,2) NULL");
            }
            if (!inv_col_exists($conn, 'equipment', 'updated_at')) {
                $conn->query("ALTER TABLE equipment ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }

            // ---- unified stock/audit history for both Product and Equipment
            // inventory (section 6 of the spec: Stock History). ----
            if (!inv_table_exists($conn, 'inventory_stock_history')) {
                $conn->query("CREATE TABLE inventory_stock_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    item_type ENUM('product','equipment') NOT NULL,
                    item_id INT NOT NULL,
                    item_name VARCHAR(255) NOT NULL,
                    action VARCHAR(60) NOT NULL,
                    previous_qty INT NULL,
                    updated_qty INT NULL,
                    updated_by VARCHAR(150) NULL,
                    remarks VARCHAR(500) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_item (item_type, item_id),
                    KEY idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }

            // ---- permissions (same self-registering pattern as reports.view) ----
            $perms = [
                'inventory.view'       => ['inventory', 'view'],
                'inventory.edit_stock' => ['inventory', 'edit_stock'],
                'inventory.export'     => ['inventory', 'export'],
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
        } catch (\Throwable $e) {
            // Never let a schema hiccup (e.g. no ALTER privilege on this DB
            // user) break the Inventory page — it just falls back to fewer
            // features (no SKU / thresholds / history) until fixed.
        }
    }
}

if (!function_exists('inv_log')) {
    /** Writes one row to inventory_stock_history. Never throws. */
    function inv_log(mysqli $conn, string $itemType, int $itemId, string $itemName, string $action, ?int $prevQty, ?int $newQty, ?string $remarks = null): void
    {
        try {
            $updatedBy = $_SESSION['admin_name'] ?? 'Admin';
            $stmt = $conn->prepare(
                "INSERT INTO inventory_stock_history (item_type, item_id, item_name, action, previous_qty, updated_qty, updated_by, remarks)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param('sisssiss', $itemType, $itemId, $itemName, $action, $prevQty, $newQty, $updatedBy, $remarks);
            $stmt->execute();
        } catch (\Throwable $e) {
            // inventory_stock_history not created yet (bootstrap hasn't run
            // or lacks privileges) — silently skip, matches logAdminActivity().
        }
    }
}

if (!function_exists('inv_product_status')) {
    /** Returns ['label' => ..., 'class' => ...] for a product's stock status. */
    function inv_product_status(int $stock, int $threshold): array
    {
        if ($stock <= 0) { return ['label' => 'Out of Stock', 'class' => 'suspended']; }
        if ($stock <= max(1, $threshold)) { return ['label' => 'Low Stock', 'class' => 'pending']; }
        return ['label' => 'In Stock', 'class' => 'active'];
    }
}

if (!function_exists('inv_equipment_status')) {
    /**
     * Derives an equipment listing's effective inventory status from
     * availability + maintenance_status + whether it has an active
     * booking covering today. $activeBookingIds is a lookup set (equipment_id => true)
     * built once per page load, not queried per row.
     */
    function inv_equipment_status(array $eq, array $activeBookingIds): array
    {
        if (!empty($eq['maintenance_status']) && $eq['maintenance_status'] === 'out_of_service') {
            return ['label' => 'Out of Service', 'class' => 'suspended'];
        }
        if ((int)($eq['availability'] ?? 0) === 0) {
            return ['label' => 'Out of Service', 'class' => 'suspended'];
        }
        if (!empty($eq['maintenance_status']) && $eq['maintenance_status'] === 'maintenance') {
            return ['label' => 'Maintenance', 'class' => 'expired'];
        }
        if (!empty($activeBookingIds[(int)$eq['id']])) {
            return ['label' => 'Rented', 'class' => 'pending'];
        }
        return ['label' => 'Available', 'class' => 'active'];
    }
}

if (!function_exists('inv_money')) {
    function inv_money($v): string
    {
        return '₹' . number_format((float)$v, 2);
    }
}
