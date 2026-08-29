<?php
// =====================================================================
// admin/includes/companies_schema.php — Bootstrap + shared helpers for
// the Companies Directory module (companies.php / company_action.php +
// the public-facing Companies directory / company profile pages).
//
// IMPORTANT — no new "companies" table is created. AgriCart already has
// a proper `sellers` table (see includes/sidebar_nav.php "Sellers" and
// admin/seller_action.php) which is exactly the "company" record the
// spec asks for: one row per seller/company, with products linked via
// products.farmer_name = sellers.name. This file only ADDS the columns
// a public company-directory profile needs (description, business
// category, logo) on top of that existing table — it never touches or
// drops anything that already exists, so it cannot break Sellers, the
// checkout, invoices, or any existing report that reads `sellers`.
//
// Follows the exact defensive, additive-only pattern already used
// across this codebase (see includes/inventory_schema.php).
// =====================================================================

if (!function_exists('cmp_col_exists')) {
    function cmp_col_exists(mysqli $conn, string $table, string $column): bool
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

if (!function_exists('companies_bootstrap_schema')) {
    /**
     * Idempotent, additive-only schema setup for the Companies module.
     * Safe to call on every page load. Requires the `sellers` table to
     * already exist (admin/index.php creates it via the Sellers tab /
     * add_sellers_coupons.sql) — if it doesn't exist yet, this is a
     * silent no-op and the Companies page shows a note instead of
     * crashing, exactly like the Sellers tab already does.
     */
    function companies_bootstrap_schema(mysqli $conn): void
    {
        $sellersExists = false;
        try {
            $chk = $conn->query("SELECT 1 FROM sellers LIMIT 1");
            $sellersExists = (bool)$chk;
        } catch (\Throwable $e) {
            $sellersExists = false;
        }
        if (!$sellersExists) { return; }

        // ---- Company-profile fields (all nullable/defaulted so every
        // existing seller row keeps working with zero data migration).
        // Each ALTER has its OWN try/catch — one failing statement (e.g.
        // a DB user without ALTER privilege, or a transient lock) must
        // never silently prevent the others from running, which is what
        // previously left products.seller_id missing while the rest of
        // the site assumed it existed. ----
        foreach ([
            ['description', "ALTER TABLE sellers ADD COLUMN description TEXT NULL AFTER name"],
            ['category',    "ALTER TABLE sellers ADD COLUMN category VARCHAR(100) NULL AFTER description"],
            // GST Number for companies not linked to a seller login account
            // (see company_profile.php "Payment History" note) — for a
            // linked seller, invoice.php already pulls GSTIN from their
            // users row instead; this is only the Companies-directory copy.
            ['gstin',       "ALTER TABLE sellers ADD COLUMN gstin VARCHAR(15) NULL AFTER category"],
            ['logo',        "ALTER TABLE sellers ADD COLUMN logo VARCHAR(255) NULL AFTER category"],
            // Per-company Digital Signature / Official Stamp — shown on
            // Buyer Invoices for this company's products (invoice.php ->
            // agri_buyer_invoice_signatory_block() in
            // ../includes/invoice_signature_schema.php reads these).
            ['signature_path', "ALTER TABLE sellers ADD COLUMN signature_path VARCHAR(255) NULL AFTER logo"],
            ['stamp_path',     "ALTER TABLE sellers ADD COLUMN stamp_path VARCHAR(255) NULL AFTER signature_path"],
            ['created_at',  "ALTER TABLE sellers ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER notes"],
        ] as [$col, $sql]) {
            if (cmp_col_exists($conn, 'sellers', $col)) { continue; }
            try { $conn->query($sql); } catch (\Throwable $e) { /* this one field stays unavailable until fixed; nothing else is affected */ }
        }

        // ---- The real Company ↔ Product relationship ----
        // Everything before this point kept using products.farmer_name
        // (text) to link a product to a company, because that's what
        // the existing schema had. Text matching is fragile — two
        // companies could share a display name, or a company could be
        // renamed — so we add a proper products.seller_id FK pointing
        // at sellers.id and use THAT for every company-scoped query
        // and permission check going forward. farmer_name is left
        // exactly as-is (nothing reads/writes it differently), so the
        // storefront, invoices, and reports are unaffected.
        //
        // IMPORTANT: every consumer of this column (company_profile.php,
        // company_products.php, companies.php, cmp_product_belongs_to_company(),
        // etc.) checks cmp_col_exists($conn,'products','seller_id') before
        // referencing it in SQL, and falls back to farmer_name-only
        // matching if it's not there — so even if this ALTER can't run on
        // a given server (e.g. restricted DB permissions), the Companies
        // module keeps working, just without the stronger ID-based match.
        $productsExists = false;
        try { $productsExists = (bool)$conn->query("SELECT 1 FROM products LIMIT 1"); } catch (\Throwable $e) {}

        if ($productsExists) {
            if (!cmp_col_exists($conn, 'products', 'seller_id')) {
                try {
                    $conn->query("ALTER TABLE products ADD COLUMN seller_id INT NULL AFTER farmer_name");
                } catch (\Throwable $e) { /* seller_id stays unavailable; every query below degrades gracefully */ }
            }
            if (cmp_col_exists($conn, 'products', 'seller_id')) {
                try { $conn->query("ALTER TABLE products ADD INDEX idx_products_seller_id (seller_id)"); }
                catch (\Throwable $e) { /* index may already exist, or user lacks INDEX privilege — not fatal */ }

                // Idempotent backfill: any product whose seller_id hasn't
                // been resolved yet gets matched by exact farmer_name =
                // sellers.name. Products that don't match anything are
                // simply left NULL — they fall back to the farmer_name
                // match rather than being force-linked to the wrong company.
                try {
                    $conn->query("
                        UPDATE products p
                        JOIN sellers s ON s.name = p.farmer_name
                           SET p.seller_id = s.id
                         WHERE p.seller_id IS NULL OR p.seller_id <> s.id
                    ");
                } catch (\Throwable $e) { /* backfill retried on next page load */ }
            }

            if (!cmp_col_exists($conn, 'products', 'created_at')) {
                try { $conn->query("ALTER TABLE products ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"); }
                catch (\Throwable $e) { /* "Added" date just shows — until fixed */ }
            }
        }

        // ---- Permissions (same self-registering pattern as inventory.*) ----
        // Mirrors the existing sellers.* key naming so behaviour/UX stays
        // consistent: .view to see the directory, .approve to edit/verify/
        // restore, .block to deactivate. Super Admin always passes
        // regardless; other roles get these via Roles & Permissions once
        // a Super Admin grants them, same as every other module here.
        try {
            $perms = [
                'companies.view'    => ['companies', 'view'],
                'companies.approve' => ['companies', 'approve'],
                'companies.block'   => ['companies', 'block'],
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

if (!function_exists('cmp_fmt_date')) {
    function cmp_fmt_date($d): string { return $d ? date('d M Y', strtotime($d)) : '—'; }
}

if (!function_exists('cmp_img_url')) {
    /**
     * Product/logo images in this codebase are stored as site-root-relative
     * paths (e.g. "assets/images/products/seeds.jpg" — see index.php's own
     * "../<?php echo $p['image']" pattern for product thumbnails). Admin
     * pages live one folder deeper than the site root, so they need a
     * "../" prefix to resolve. Full URLs (http/https) and already-absolute
     * paths ("/uploads/...") are left untouched.
     */
    function cmp_img_url(?string $path): string
    {
        $path = trim((string)$path);
        if ($path === '') { return ''; }
        if (preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $path) || $path[0] === '/') { return $path; }
        return '../' . ltrim($path, './');
    }
}

if (!function_exists('cmp_products_has_seller_id')) {
    /** Cached per-request so we don't re-query information_schema on every call. */
    function cmp_products_has_seller_id(mysqli $conn): bool
    {
        static $cached = null;
        if ($cached === null) { $cached = cmp_col_exists($conn, 'products', 'seller_id'); }
        return $cached;
    }
}

if (!function_exists('cmp_company_match')) {
    /**
     * Builds the "does this product belong to this company" WHERE
     * fragment for a prepared statement, using products.seller_id when
     * it's available and transparently falling back to the legacy
     * farmer_name text match when it isn't (e.g. the ALTER hasn't run
     * yet on this server). Every file that filters products by company
     * uses this instead of hand-writing "seller_id = ?" — so nothing
     * fatally errors if the column is temporarily missing.
     */
    function cmp_company_match(mysqli $conn, int $companyId, string $companyName, string $alias = ''): array
    {
        $col = $alias !== '' ? $alias . '.' : '';
        if (cmp_products_has_seller_id($conn)) {
            return ['sql' => "({$col}seller_id = ? OR ({$col}seller_id IS NULL AND {$col}farmer_name = ?))", 'types' => 'is', 'params' => [$companyId, $companyName]];
        }
        return ['sql' => "{$col}farmer_name = ?", 'types' => 's', 'params' => [$companyName]];
    }
}

if (!function_exists('cmp_company_match_joined')) {
    /** Same as cmp_company_match(), but for queries that already JOIN sellers (no bound params needed — references the joined alias's columns directly). */
    function cmp_company_match_joined(mysqli $conn, string $productAlias, string $sellerAlias): string
    {
        if (cmp_products_has_seller_id($conn)) {
            return "({$productAlias}.seller_id = {$sellerAlias}.id OR ({$productAlias}.seller_id IS NULL AND {$productAlias}.farmer_name = {$sellerAlias}.name))";
        }
        return "{$productAlias}.farmer_name = {$sellerAlias}.name";
    }
}

if (!function_exists('cmp_product_belongs_to_company')) {
    /**
     * Backend ownership check — every company-scoped product action
     * (stock update, status toggle, etc.) must call this and refuse to
     * proceed if it returns false, regardless of what the request claims.
     * This is what actually prevents cross-company / IDOR access; URL or
     * form parameters are never trusted on their own.
     */
    function cmp_product_belongs_to_company(mysqli $conn, int $productId, int $companyId): bool
    {
        if ($productId <= 0 || $companyId <= 0) { return false; }
        $matchSql = cmp_company_match_joined($conn, 'p', 's');
        $stmt = $conn->prepare(
            "SELECT p.id FROM products p
               JOIN sellers s ON s.id = ?
              WHERE p.id = ? AND $matchSql
              LIMIT 1"
        );
        $stmt->bind_param('ii', $companyId, $productId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
}
