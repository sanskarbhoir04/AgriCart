<?php
// =====================================================================
// includes/invoice_signature_schema.php — Bootstrap for the dynamic
// Authorized Signatory system (Seller Invoice = AgriCart's own signature
// & stamp; Buyer Invoice = the order's seller's signature & stamp).
//
// Follows the exact defensive, additive-only, idempotent pattern already
// used across this codebase (admin/includes/inventory_schema.php,
// admin/includes/companies_schema.php): every call only ADDS a
// table/column if it's missing, never touches or drops anything that
// already exists, so it cannot break existing invoice generation, PDF,
// print, download, GST, or order functionality. Safe to call on every
// page load — see setup/signature_stamp_upgrade.sql for the same
// changes as a one-time script, for installs that prefer to run
// migrations manually instead.
//
// Include this from any page that reads/writes signature/stamp data
// (admin/invoice.php, pages/invoice.php, pages/seller-invoice.php,
// seller/invoice.php, seller/seller_api.php, admin/invoice_settings.php)
// and call agri_sig_bootstrap_schema($conn) once near the top.
// =====================================================================

if (!function_exists('agri_sig_col_exists')) {
    function agri_sig_col_exists(mysqli $conn, string $table, string $column): bool
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

if (!function_exists('agri_sig_table_exists')) {
    function agri_sig_table_exists(mysqli $conn, string $table): bool
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

if (!function_exists('agri_sig_bootstrap_schema')) {
    function agri_sig_bootstrap_schema(mysqli $conn): void
    {
        try {
            // ---- 1. AgriCart's own official signature/stamp (single row, id=1). ----
            if (!agri_sig_table_exists($conn, 'agricart_invoice_assets')) {
                $conn->query(
                    "CREATE TABLE agricart_invoice_assets (
                        id TINYINT NOT NULL,
                        signature_path VARCHAR(255) NULL,
                        stamp_path VARCHAR(255) NULL,
                        signatory_name VARCHAR(150) NULL,
                        designation VARCHAR(150) NULL,
                        updated_by_admin_id INT NULL,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            }
            $conn->query("INSERT IGNORE INTO agricart_invoice_assets (id) VALUES (1)");

            // ---- 2. Per-seller signature/stamp fields on the Business Profile. ----
            if (agri_sig_table_exists($conn, 'seller_payout_profiles')) {
                if (!agri_sig_col_exists($conn, 'seller_payout_profiles', 'signature_path')) {
                    $conn->query("ALTER TABLE seller_payout_profiles ADD COLUMN signature_path VARCHAR(255) NULL AFTER business_name");
                }
                if (!agri_sig_col_exists($conn, 'seller_payout_profiles', 'stamp_path')) {
                    $conn->query("ALTER TABLE seller_payout_profiles ADD COLUMN stamp_path VARCHAR(255) NULL AFTER signature_path");
                }
                if (!agri_sig_col_exists($conn, 'seller_payout_profiles', 'authorized_signatory_name')) {
                    $conn->query("ALTER TABLE seller_payout_profiles ADD COLUMN authorized_signatory_name VARCHAR(150) NULL AFTER stamp_path");
                }
                if (!agri_sig_col_exists($conn, 'seller_payout_profiles', 'signatory_designation')) {
                    $conn->query("ALTER TABLE seller_payout_profiles ADD COLUMN signatory_designation VARCHAR(150) NULL AFTER authorized_signatory_name");
                }
                if (!agri_sig_col_exists($conn, 'seller_payout_profiles', 'signature_status')) {
                    $conn->query("ALTER TABLE seller_payout_profiles ADD COLUMN signature_status ENUM('missing','uploaded') NOT NULL DEFAULT 'missing' AFTER signatory_designation");
                }
                if (!agri_sig_col_exists($conn, 'seller_payout_profiles', 'stamp_status')) {
                    $conn->query("ALTER TABLE seller_payout_profiles ADD COLUMN stamp_status ENUM('missing','uploaded') NOT NULL DEFAULT 'missing' AFTER signature_status");
                }
            }

            // ---- 3. Historical protection for Seller Invoices (AgriCart signatory). ----
            if (agri_sig_table_exists($conn, 'seller_invoices')) {
                if (!agri_sig_col_exists($conn, 'seller_invoices', 'agricart_signature_snapshot')) {
                    $conn->query("ALTER TABLE seller_invoices ADD COLUMN agricart_signature_snapshot VARCHAR(255) NULL");
                }
                if (!agri_sig_col_exists($conn, 'seller_invoices', 'agricart_stamp_snapshot')) {
                    $conn->query("ALTER TABLE seller_invoices ADD COLUMN agricart_stamp_snapshot VARCHAR(255) NULL");
                }
                if (!agri_sig_col_exists($conn, 'seller_invoices', 'agricart_signatory_name_snapshot')) {
                    $conn->query("ALTER TABLE seller_invoices ADD COLUMN agricart_signatory_name_snapshot VARCHAR(150) NULL");
                }
                if (!agri_sig_col_exists($conn, 'seller_invoices', 'agricart_designation_snapshot')) {
                    $conn->query("ALTER TABLE seller_invoices ADD COLUMN agricart_designation_snapshot VARCHAR(150) NULL");
                }
            }

            // ---- 4. Historical protection for Buyer Invoices (seller signatory). ----
            if (!agri_sig_table_exists($conn, 'buyer_invoice_signatory_snapshots')) {
                $conn->query(
                    "CREATE TABLE buyer_invoice_signatory_snapshots (
                        id INT NOT NULL AUTO_INCREMENT,
                        order_id BIGINT UNSIGNED NOT NULL,
                        seller_id INT NOT NULL,
                        business_name_snapshot VARCHAR(191) NULL,
                        signature_path_snapshot VARCHAR(255) NULL,
                        stamp_path_snapshot VARCHAR(255) NULL,
                        signatory_name_snapshot VARCHAR(150) NULL,
                        designation_snapshot VARCHAR(150) NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY uniq_order_seller (order_id, seller_id),
                        KEY idx_seller_id (seller_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            }

            // ---- 5. Admin permission gate for the settings screen. ----
            if (agri_sig_table_exists($conn, 'admin_permissions')) {
                $conn->query(
                    "INSERT IGNORE INTO admin_permissions (permission_key, module_name, action_name, description)
                     VALUES ('settings.invoice_manage', 'settings', 'invoice_manage', 'Manage AgriCart official invoice signature & stamp')"
                );
            }
        } catch (\Throwable $e) { /* degrade quietly, same as inventory_bootstrap_schema() */ }
    }
}
