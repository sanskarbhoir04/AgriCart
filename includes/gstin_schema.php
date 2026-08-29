<?php
// =====================================================================
// includes/gstin_schema.php — Bootstrap for the centralized GSTIN
// Management System (AgriCart Company GST, Company/Seller GST, Buyer
// GST-on-order, and the Invoice GST snapshot).
//
// Follows the exact defensive, additive-only, idempotent pattern used
// throughout this codebase (admin/includes/companies_schema.php,
// includes/accounts_schema.php, includes/invoice_signature_schema.php):
// every call only ADDS a table/column if it is missing, never touches
// or drops anything that already exists. Safe to call on every page
// load. Include this from any page that reads/writes GST data.
//
// Table ownership (no duplicate "GST tables" are created — GST fields
// are added directly onto the existing record each belongs to):
//   - AgriCart's own company/GST info -> agricart_invoice_assets (id=1)
//     (already created by invoice_signature_schema.php for the
//     signature/stamp; this file adds the company/GSTIN columns to the
//     SAME single-row table, since it is already the "AgriCart Company
//     Settings" record and is already wired into invoice generation).
//   - Company GST info (Admin -> Companies)  -> sellers table
//   - Seller GST info (Seller Dashboard)     -> seller_payout_profiles
//   - Buyer GST info (per order, optional)   -> orders table
//   - Invoice GST snapshot (frozen at generation time) -> seller_invoices
//   - GSTIN change audit trail               -> gstin_change_logs
// =====================================================================

if (!function_exists('gstin_col_exists')) {
    function gstin_col_exists(mysqli $conn, string $table, string $column): bool
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

if (!function_exists('gstin_table_exists')) {
    function gstin_table_exists(mysqli $conn, string $table): bool
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

if (!function_exists('gstin_add_cols')) {
    /** $cols = [ [colName, fullAlterSql], ... ] — each ALTER isolated so one failure never blocks the rest. */
    function gstin_add_cols(mysqli $conn, string $table, array $cols): void
    {
        if (!gstin_table_exists($conn, $table)) { return; }
        foreach ($cols as [$col, $sql]) {
            if (gstin_col_exists($conn, $table, $col)) { continue; }
            try { $conn->query($sql); } catch (\Throwable $e) { /* this field stays unavailable until fixed; nothing else affected */ }
        }
    }
}

if (!function_exists('agri_company_contact')) {
    /**
     * Single source of truth for the "Customer / Seller Support" contact
     * block shown on every invoice (admin/invoice.php, pages/invoice.php,
     * seller/invoice.php, pages/seller-invoice.php). Reads company_phone /
     * company_email / website from agricart_invoice_assets (id=1) — the
     * same central settings record edited in Admin -> Company Settings —
     * and falls back to the previous static defaults only when a field
     * hasn't been set yet, so the page never shows a blank contact block.
     */
    function agri_company_contact(mysqli $conn): array
    {
        $defaults = ['phone' => '+91 1800-123-4567', 'email' => 'support@agricart.in', 'website' => 'www.agricart.in'];
        try {
            if (!gstin_table_exists($conn, 'agricart_invoice_assets')) { return $defaults; }
            $row = $conn->query("SELECT company_phone, company_email, website FROM agricart_invoice_assets WHERE id = 1")->fetch_assoc() ?: [];
            return [
                'phone'   => !empty($row['company_phone']) ? $row['company_phone'] : $defaults['phone'],
                'email'   => !empty($row['company_email']) ? $row['company_email'] : $defaults['email'],
                'website' => !empty($row['website']) ? $row['website'] : $defaults['website'],
            ];
        } catch (\Throwable $e) {
            return $defaults;
        }
    }
}

if (!function_exists('gstin_bootstrap_schema')) {
    function gstin_bootstrap_schema(mysqli $conn): void
    {
        try {
            // -----------------------------------------------------------
            // 1. AgriCart's own Company / Business Information
            //    (Admin Panel -> Settings -> Company / Business Info).
            //    Reuses agricart_invoice_assets (id=1 single row) which
            //    invoice_signature_schema.php already creates.
            // -----------------------------------------------------------
            if (!gstin_table_exists($conn, 'agricart_invoice_assets')) {
                // Signature schema hasn't bootstrapped yet on this request —
                // create the bare table so the ALTERs below have something
                // to attach to; invoice_signature_schema.php's own bootstrap
                // (INSERT IGNORE id=1 etc.) still runs independently.
                try {
                    $conn->query(
                        "CREATE TABLE agricart_invoice_assets (
                            id TINYINT NOT NULL,
                            PRIMARY KEY (id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                    );
                } catch (\Throwable $e) { /* non-fatal */ }
            }
            $conn->query("INSERT IGNORE INTO agricart_invoice_assets (id) VALUES (1)");

            gstin_add_cols($conn, 'agricart_invoice_assets', [
                ['legal_name',       "ALTER TABLE agricart_invoice_assets ADD COLUMN legal_name VARCHAR(191) NULL AFTER id"],
                ['trade_name',       "ALTER TABLE agricart_invoice_assets ADD COLUMN trade_name VARCHAR(191) NULL AFTER legal_name"],
                ['gstin',            "ALTER TABLE agricart_invoice_assets ADD COLUMN gstin VARCHAR(15) NULL AFTER trade_name"],
                ['gst_status',       "ALTER TABLE agricart_invoice_assets ADD COLUMN gst_status ENUM('registered','composition','unregistered','not_applicable') NOT NULL DEFAULT 'registered' AFTER gstin"],
                ['pan',              "ALTER TABLE agricart_invoice_assets ADD COLUMN pan VARCHAR(10) NULL AFTER gst_status"],
                ['business_type',    "ALTER TABLE agricart_invoice_assets ADD COLUMN business_type VARCHAR(100) NULL AFTER pan"],
                ['registered_address', "ALTER TABLE agricart_invoice_assets ADD COLUMN registered_address VARCHAR(255) NULL AFTER business_type"],
                ['state',            "ALTER TABLE agricart_invoice_assets ADD COLUMN state VARCHAR(100) NULL AFTER registered_address"],
                ['state_code',       "ALTER TABLE agricart_invoice_assets ADD COLUMN state_code VARCHAR(2) NULL AFTER state"],
                ['city',             "ALTER TABLE agricart_invoice_assets ADD COLUMN city VARCHAR(100) NULL AFTER state_code"],
                ['pincode',          "ALTER TABLE agricart_invoice_assets ADD COLUMN pincode VARCHAR(10) NULL AFTER city"],
                ['company_email',    "ALTER TABLE agricart_invoice_assets ADD COLUMN company_email VARCHAR(150) NULL AFTER pincode"],
                ['company_phone',    "ALTER TABLE agricart_invoice_assets ADD COLUMN company_phone VARCHAR(20) NULL AFTER company_email"],
                ['website',          "ALTER TABLE agricart_invoice_assets ADD COLUMN website VARCHAR(191) NULL AFTER company_phone"],
                ['logo_path',        "ALTER TABLE agricart_invoice_assets ADD COLUMN logo_path VARCHAR(255) NULL AFTER website"],
                ['gst_verified',     "ALTER TABLE agricart_invoice_assets ADD COLUMN gst_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER logo_path"],
            ]);

            // -----------------------------------------------------------
            // 2. Company GST info (Admin -> Companies), on the `sellers`
            //    table (see admin/includes/companies_schema.php — this
            //    IS the Company record). includes/accounts_schema.php
            //    already adds a plain `gstin` column; this only adds
            //    what's still missing.
            // -----------------------------------------------------------
            gstin_add_cols($conn, 'sellers', [
                ['legal_name',        "ALTER TABLE sellers ADD COLUMN legal_name VARCHAR(191) NULL AFTER name"],
                ['trade_name',        "ALTER TABLE sellers ADD COLUMN trade_name VARCHAR(191) NULL AFTER legal_name"],
                ['gstin',             "ALTER TABLE sellers ADD COLUMN gstin VARCHAR(15) NULL"], // safety net if accounts_schema hasn't run yet
                ['gst_status',        "ALTER TABLE sellers ADD COLUMN gst_status ENUM('registered','composition','unregistered','not_applicable') NOT NULL DEFAULT 'not_applicable'"],
                ['pan',               "ALTER TABLE sellers ADD COLUMN pan VARCHAR(10) NULL"],
                ['registered_address', "ALTER TABLE sellers ADD COLUMN registered_address VARCHAR(255) NULL"],
                ['state_code',        "ALTER TABLE sellers ADD COLUMN state_code VARCHAR(2) NULL"],
                ['gst_verified_status', "ALTER TABLE sellers ADD COLUMN gst_verified_status ENUM('verified','pending','not_verified') NOT NULL DEFAULT 'not_verified'"],
                ['gst_verified_at',   "ALTER TABLE sellers ADD COLUMN gst_verified_at DATETIME NULL"],
                ['gst_verified_by',   "ALTER TABLE sellers ADD COLUMN gst_verified_by INT NULL"],
            ]);

            // -----------------------------------------------------------
            // 3. Seller GST info (Seller Registration / Seller Dashboard
            //    -> Business Profile / GST Details), on
            //    `seller_payout_profiles` — the seller's own business
            //    profile record (see includes/seller_functions.php).
            // -----------------------------------------------------------
            gstin_add_cols($conn, 'seller_payout_profiles', [
                ['legal_business_name', "ALTER TABLE seller_payout_profiles ADD COLUMN legal_business_name VARCHAR(191) NULL AFTER business_name"],
                ['gst_status',        "ALTER TABLE seller_payout_profiles ADD COLUMN gst_status ENUM('registered','composition','unregistered','not_applicable') NOT NULL DEFAULT 'not_applicable' AFTER legal_business_name"],
                ['gstin',             "ALTER TABLE seller_payout_profiles ADD COLUMN gstin VARCHAR(15) NULL AFTER gst_status"],
                ['pan',               "ALTER TABLE seller_payout_profiles ADD COLUMN pan VARCHAR(10) NULL AFTER gstin"],
                ['business_type',     "ALTER TABLE seller_payout_profiles ADD COLUMN business_type VARCHAR(100) NULL AFTER pan"],
                ['registered_address', "ALTER TABLE seller_payout_profiles ADD COLUMN registered_address VARCHAR(255) NULL AFTER business_type"],
                ['gst_state',         "ALTER TABLE seller_payout_profiles ADD COLUMN gst_state VARCHAR(100) NULL AFTER registered_address"],
                ['gst_state_code',    "ALTER TABLE seller_payout_profiles ADD COLUMN gst_state_code VARCHAR(2) NULL AFTER gst_state"],
                ['gst_city',          "ALTER TABLE seller_payout_profiles ADD COLUMN gst_city VARCHAR(100) NULL AFTER gst_state_code"],
                ['gst_pincode',       "ALTER TABLE seller_payout_profiles ADD COLUMN gst_pincode VARCHAR(10) NULL AFTER gst_city"],
                ['gst_verified_status', "ALTER TABLE seller_payout_profiles ADD COLUMN gst_verified_status ENUM('verified','pending','not_verified') NOT NULL DEFAULT 'not_verified' AFTER gst_pincode"],
                ['gst_verified_at',   "ALTER TABLE seller_payout_profiles ADD COLUMN gst_verified_at DATETIME NULL AFTER gst_verified_status"],
            ]);

            // -----------------------------------------------------------
            // 4. Buyer GST info — optional, captured per order (a buyer
            //    may be GST-registered for one purchase and not another,
            //    e.g. a farmer buying for their own registered farm biz).
            // -----------------------------------------------------------
            gstin_add_cols($conn, 'orders', [
                ['buyer_gstin',        "ALTER TABLE orders ADD COLUMN buyer_gstin VARCHAR(15) NULL"],
                ['buyer_gst_business_name', "ALTER TABLE orders ADD COLUMN buyer_gst_business_name VARCHAR(191) NULL"],
                ['buyer_pan',          "ALTER TABLE orders ADD COLUMN buyer_pan VARCHAR(10) NULL"],
                ['buyer_gst_status',   "ALTER TABLE orders ADD COLUMN buyer_gst_status ENUM('registered','unregistered') NOT NULL DEFAULT 'unregistered'"],
                ['buyer_gst_state',    "ALTER TABLE orders ADD COLUMN buyer_gst_state VARCHAR(100) NULL"],
            ]);

            // -----------------------------------------------------------
            // 5. Invoice GST snapshot — frozen at the moment each
            //    seller_invoices row is first generated (see
            //    agri_seller_identity_snapshot() / agri_seller_ensure_invoice()
            //    in includes/seller_functions.php). This is what makes
            //    rule #17 ("never retroactively change an issued
            //    invoice") work for every GST field, not just the ones
            //    the pre-existing gstin_snapshot/business_name_snapshot
            //    columns already covered.
            // -----------------------------------------------------------
            gstin_add_cols($conn, 'seller_invoices', [
                // Seller side — the columns this file adds beyond the
                // pre-existing seller_name_snapshot/business_name_snapshot/
                // business_address_snapshot/gstin_snapshot.
                ['seller_legal_name_snapshot', "ALTER TABLE seller_invoices ADD COLUMN seller_legal_name_snapshot VARCHAR(191) NULL"],
                ['seller_pan_snapshot',        "ALTER TABLE seller_invoices ADD COLUMN seller_pan_snapshot VARCHAR(10) NULL"],
                ['seller_gst_status_snapshot', "ALTER TABLE seller_invoices ADD COLUMN seller_gst_status_snapshot VARCHAR(20) NULL"],
                ['seller_state_snapshot',      "ALTER TABLE seller_invoices ADD COLUMN seller_state_snapshot VARCHAR(100) NULL"],
                ['seller_state_code_snapshot', "ALTER TABLE seller_invoices ADD COLUMN seller_state_code_snapshot VARCHAR(2) NULL"],

                // AgriCart platform side.
                ['platform_legal_name_snapshot', "ALTER TABLE seller_invoices ADD COLUMN platform_legal_name_snapshot VARCHAR(191) NULL"],
                ['platform_gstin_snapshot',      "ALTER TABLE seller_invoices ADD COLUMN platform_gstin_snapshot VARCHAR(15) NULL"],
                ['platform_pan_snapshot',        "ALTER TABLE seller_invoices ADD COLUMN platform_pan_snapshot VARCHAR(10) NULL"],
                ['platform_address_snapshot',    "ALTER TABLE seller_invoices ADD COLUMN platform_address_snapshot VARCHAR(255) NULL"],
                ['platform_state_snapshot',      "ALTER TABLE seller_invoices ADD COLUMN platform_state_snapshot VARCHAR(100) NULL"],
                ['platform_state_code_snapshot', "ALTER TABLE seller_invoices ADD COLUMN platform_state_code_snapshot VARCHAR(2) NULL"],

                // Buyer side.
                ['buyer_name_snapshot',    "ALTER TABLE seller_invoices ADD COLUMN buyer_name_snapshot VARCHAR(191) NULL"],
                ['buyer_gstin_snapshot',   "ALTER TABLE seller_invoices ADD COLUMN buyer_gstin_snapshot VARCHAR(15) NULL"],
                ['buyer_pan_snapshot',     "ALTER TABLE seller_invoices ADD COLUMN buyer_pan_snapshot VARCHAR(10) NULL"],
                ['buyer_address_snapshot', "ALTER TABLE seller_invoices ADD COLUMN buyer_address_snapshot VARCHAR(255) NULL"],
                ['buyer_state_snapshot',   "ALTER TABLE seller_invoices ADD COLUMN buyer_state_snapshot VARCHAR(100) NULL"],
                ['buyer_gst_status_snapshot', "ALTER TABLE seller_invoices ADD COLUMN buyer_gst_status_snapshot VARCHAR(20) NOT NULL DEFAULT 'unregistered'"],

                // Tax breakdown — computed once at generation time and
                // frozen, exactly like every other snapshot column here.
                ['tax_type',          "ALTER TABLE seller_invoices ADD COLUMN tax_type ENUM('CGST_SGST','IGST') NOT NULL DEFAULT 'CGST_SGST'"],
                ['cgst_amount',       "ALTER TABLE seller_invoices ADD COLUMN cgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0"],
                ['sgst_amount',       "ALTER TABLE seller_invoices ADD COLUMN sgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0"],
                ['igst_amount',       "ALTER TABLE seller_invoices ADD COLUMN igst_amount DECIMAL(10,2) NOT NULL DEFAULT 0"],
            ]);

            // -----------------------------------------------------------
            // 6. GSTIN change audit trail (section 19 — Activity Logs).
            //    Admin-side GSTIN edits already flow through
            //    admin_activity_logs via logAdminActivity(); this table
            //    additionally captures SELLER self-service GST edits
            //    (which have no admin session to log against) and gives
            //    both a single place to query "every GSTIN change ever
            //    made", with old/new value + actor + timestamp.
            // -----------------------------------------------------------
            if (!gstin_table_exists($conn, 'gstin_change_logs')) {
                try {
                    $conn->query(
                        "CREATE TABLE gstin_change_logs (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            entity_type ENUM('agricart','company','seller') NOT NULL,
                            entity_id INT NOT NULL,
                            entity_label VARCHAR(191) NULL,
                            old_gstin VARCHAR(15) NULL,
                            new_gstin VARCHAR(15) NULL,
                            changed_by_type ENUM('admin','seller','system') NOT NULL DEFAULT 'system',
                            changed_by_id INT NULL,
                            changed_by_name VARCHAR(150) NULL,
                            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            KEY idx_entity (entity_type, entity_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                    );
                } catch (\Throwable $e) { /* non-fatal — change history just won't show until this runs */ }
            }

            // -----------------------------------------------------------
            // 7. Invoice display settings (Admin -> Invoice Settings ->
            //    GST & Tax Settings). Controls what's SHOWN on an
            //    invoice, never the underlying stored GST data.
            // -----------------------------------------------------------
            gstin_add_cols($conn, 'agricart_invoice_assets', [
                ['default_gst_mode',   "ALTER TABLE agricart_invoice_assets ADD COLUMN default_gst_mode ENUM('auto','cgst_sgst','igst') NOT NULL DEFAULT 'auto'"],
                ['default_cgst_rate',  "ALTER TABLE agricart_invoice_assets ADD COLUMN default_cgst_rate DECIMAL(5,2) NOT NULL DEFAULT 2.50"],
                ['default_sgst_rate',  "ALTER TABLE agricart_invoice_assets ADD COLUMN default_sgst_rate DECIMAL(5,2) NOT NULL DEFAULT 2.50"],
                ['default_igst_rate',  "ALTER TABLE agricart_invoice_assets ADD COLUMN default_igst_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00"],
                ['show_gstin',         "ALTER TABLE agricart_invoice_assets ADD COLUMN show_gstin TINYINT(1) NOT NULL DEFAULT 1"],
                ['show_pan',           "ALTER TABLE agricart_invoice_assets ADD COLUMN show_pan TINYINT(1) NOT NULL DEFAULT 1"],
                ['show_seller_gstin',  "ALTER TABLE agricart_invoice_assets ADD COLUMN show_seller_gstin TINYINT(1) NOT NULL DEFAULT 1"],
                ['show_buyer_gstin',   "ALTER TABLE agricart_invoice_assets ADD COLUMN show_buyer_gstin TINYINT(1) NOT NULL DEFAULT 1"],
            ]);

            // ---- Permissions (same self-registering pattern as every other module) ----
            if (gstin_table_exists($conn, 'admin_permissions')) {
                $perms = [
                    'settings.company_manage' => ['settings', 'company_manage', 'Manage AgriCart Company / GST information'],
                    'companies.gst_manage'    => ['companies', 'gst_manage', 'Add/Edit/Verify Company GSTIN'],
                ];
                foreach ($perms as $key => [$module, $act, $desc]) {
                    $exists = $conn->prepare("SELECT id FROM admin_permissions WHERE permission_key = ? LIMIT 1");
                    $exists->bind_param('s', $key);
                    $exists->execute();
                    if ($exists->get_result()->num_rows > 0) { continue; }
                    try {
                        $ins = $conn->prepare("INSERT INTO admin_permissions (permission_key, module_name, action_name, description) VALUES (?,?,?,?)");
                        $ins->bind_param('ssss', $key, $module, $act, $desc);
                        $ins->execute();
                    } catch (\Throwable $e) {
                        // description column may not exist on older installs — retry without it
                        try {
                            $ins = $conn->prepare("INSERT INTO admin_permissions (permission_key, module_name, action_name) VALUES (?,?,?)");
                            $ins->bind_param('sss', $key, $module, $act);
                            $ins->execute();
                        } catch (\Throwable $e2) { /* retried on next page load */ }
                    }
                }
            }
        } catch (\Throwable $eOuter) { /* degrade quietly, same as every other *_bootstrap_schema() in this codebase */ }
    }
}

if (!function_exists('gstin_log_change')) {
    /**
     * Records a GSTIN change to gstin_change_logs. Best-effort — never
     * throws, never blocks the calling save operation.
     */
    function gstin_log_change(mysqli $conn, string $entityType, int $entityId, ?string $entityLabel, ?string $oldGstin, ?string $newGstin, string $changedByType, ?int $changedById, ?string $changedByName): void
    {
        $oldGstin = $oldGstin !== null ? trim($oldGstin) : null;
        $newGstin = $newGstin !== null ? trim($newGstin) : null;
        if ($oldGstin === $newGstin) { return; } // no actual change
        try {
            $stmt = $conn->prepare(
                "INSERT INTO gstin_change_logs (entity_type, entity_id, entity_label, old_gstin, new_gstin, changed_by_type, changed_by_id, changed_by_name)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            // Types: entity_type(s) entity_id(i) entity_label(s) old_gstin(s) new_gstin(s) changed_by_type(s) changed_by_id(i) changed_by_name(s)
            $stmt->bind_param('sissssis', $entityType, $entityId, $entityLabel, $oldGstin, $newGstin, $changedByType, $changedById, $changedByName);
        } catch (\Throwable $e) { /* best-effort */ }
    }
}
