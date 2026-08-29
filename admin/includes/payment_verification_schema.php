<?php
// =====================================================================
// admin/includes/payment_verification_schema.php — Bootstrap for the
// online payment-verification workflow (pages/confirm_payment.php +
// admin/payment_verification.php + booking_action.php's 'verify_payment'
// action).
//
// Root cause of the fatal error on payment_verification.php: the table
// `equipment_bookings` never actually got the payment-proof columns this
// workflow depends on (transaction_id, payment_screenshot, etc.) — the
// code had a comment pointing at "setup/equipment_bookings_upgrade.sql"
// but that migration file was never committed. booking_action.php's
// plain payment_status UPDATE already guarded against this with a
// `prepare()` false-check, but the WHERE eb.transaction_id clause in
// payment_verification.php had no such guard, so a missing column there
// raised an uncaught mysqli_sql_exception instead of failing softly.
//
// Follows the same defensive, additive-only pattern as
// includes/inventory_schema.php: every check is idempotent, nothing is
// ever dropped or altered destructively, and a schema hiccup (e.g. no
// ALTER privilege) never turns into a fatal error — the page just falls
// back to fewer features until it's fixed.
// =====================================================================

if (!function_exists('pv_col_exists')) {
    function pv_col_exists(mysqli $conn, string $table, string $column): bool
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

if (!function_exists('payment_verification_bootstrap_schema')) {
    /**
     * Idempotent, additive-only schema setup for the payment-verification
     * workflow. Safe to call on every page load.
     */
    function payment_verification_bootstrap_schema(mysqli $conn): void
    {
        try {
            if (!pv_col_exists($conn, 'equipment_bookings', 'payment_status')) {
                $conn->query("ALTER TABLE equipment_bookings ADD COLUMN payment_status VARCHAR(30) NOT NULL DEFAULT 'pending'");
            }
            if (!pv_col_exists($conn, 'equipment_bookings', 'payment_method')) {
                $conn->query("ALTER TABLE equipment_bookings ADD COLUMN payment_method VARCHAR(40) NULL");
            }
            if (!pv_col_exists($conn, 'equipment_bookings', 'transaction_id')) {
                $conn->query("ALTER TABLE equipment_bookings ADD COLUMN transaction_id VARCHAR(120) NULL");
            }
            if (!pv_col_exists($conn, 'equipment_bookings', 'payment_screenshot')) {
                $conn->query("ALTER TABLE equipment_bookings ADD COLUMN payment_screenshot VARCHAR(255) NULL");
            }
            if (!pv_col_exists($conn, 'equipment_bookings', 'payment_submitted_at')) {
                $conn->query("ALTER TABLE equipment_bookings ADD COLUMN payment_submitted_at DATETIME NULL");
            }
            if (!pv_col_exists($conn, 'equipment_bookings', 'admin_verification_note')) {
                $conn->query("ALTER TABLE equipment_bookings ADD COLUMN admin_verification_note VARCHAR(500) NULL");
            }
            if (!pv_col_exists($conn, 'equipment_bookings', 'payment_verified_by')) {
                $conn->query("ALTER TABLE equipment_bookings ADD COLUMN payment_verified_by INT NULL");
            }
            if (!pv_col_exists($conn, 'equipment_bookings', 'payment_verified_at')) {
                $conn->query("ALTER TABLE equipment_bookings ADD COLUMN payment_verified_at DATETIME NULL");
            }

            // Self-registers, same pattern as inventory.view/inventory.edit_stock.
            if (!pv_col_exists($conn, 'admin_permissions', 'permission_key')) {
                return; // permissions table itself isn't in the shape we expect — skip silently
            }
            $exists = $conn->prepare("SELECT id FROM admin_permissions WHERE permission_key = ? LIMIT 1");
            $key = 'rental_bookings.verify_payment';
            $exists->bind_param('s', $key);
            $exists->execute();
            if ($exists->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO admin_permissions (permission_key, module_name, action_name) VALUES (?,?,?)");
                $module = 'rental_bookings'; $act = 'verify_payment';
                $ins->bind_param('sss', $key, $module, $act);
                $ins->execute();
            }
        } catch (\Throwable $e) {
            // Never let a schema hiccup (e.g. no ALTER privilege on this DB
            // user) break this page — see file header.
        }
    }
}
