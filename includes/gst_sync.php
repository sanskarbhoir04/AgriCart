<?php
// =====================================================================
// includes/gst_sync.php — Single source of truth bridge between the
// Admin "Companies" directory record (`sellers` table, one row per
// company) and the actual seller LOGIN account's own GST/business
// profile (`seller_payout_profiles`, keyed by `users.id`).
//
// WHY THIS FILE EXISTS
//   These are historically two separate, unlinked concepts in this
//   schema (see admin/company_profile.php's own comment on this). When
//   Admin verifies a company's GST on the Companies page, that write
//   only ever touched `sellers.gst_verified` — the Seller Dashboard
//   reads `seller_payout_profiles.gst_verified_status`, a completely
//   different column on a completely different table, which nothing
//   ever set to 'verified'. That mismatch is exactly why Admin could
//   show "Verified" while the Seller Dashboard kept showing
//   "Not Verified" after a refresh/login.
//
//   This file makes the two records point at each other with a real
//   foreign key (`sellers.linked_user_id` -> `users.id`) instead of
//   re-matching by name/email on every single lookup, and provides the
//   two sync functions every GST-verification entry point should call:
//     - gst_sync_push_company_verified_to_seller() : Admin verified/
//       unverified a company -> push the same status onto the linked
//       seller's own GST profile.
//     - gst_sync_reset_company_gst_on_seller_edit() : Seller edited
//       their GSTIN (which already resets their own verified_status to
//       'not_verified') -> also drop the stale "Verified" flag on the
//       Admin-side company record so Admin and Seller can never show
//       two different answers to "is this GST verified?".
//
//   Both directions are best-effort / non-fatal by design (same
//   pattern as every other *_schema.php file in this codebase) so a
//   missing table/column on an older install degrades quietly instead
//   of breaking the underlying verify/save action.
// =====================================================================

if (!function_exists('gstsync_col_exists')) {
    function gstsync_col_exists(mysqli $conn, string $table, string $column): bool
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

if (!function_exists('gstsync_table_exists')) {
    function gstsync_table_exists(mysqli $conn, string $table): bool
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

if (!function_exists('gst_sync_bootstrap_schema')) {
    /** Additive-only. Safe to call on every page load. */
    function gst_sync_bootstrap_schema(mysqli $conn): void
    {
        try {
            if (gstsync_table_exists($conn, 'sellers') && !gstsync_col_exists($conn, 'sellers', 'linked_user_id')) {
                try {
                    $conn->query("ALTER TABLE sellers ADD COLUMN linked_user_id INT NULL AFTER id");
                    $conn->query("ALTER TABLE sellers ADD INDEX idx_sellers_linked_user_id (linked_user_id)");
                } catch (\Throwable $e) { /* non-fatal — falls back to name/email matching every time */ }
            }
            if (gstsync_table_exists($conn, 'seller_payout_profiles') && !gstsync_col_exists($conn, 'seller_payout_profiles', 'gst_verified_by')) {
                try {
                    $conn->query("ALTER TABLE seller_payout_profiles ADD COLUMN gst_verified_by INT NULL AFTER gst_verified_at");
                } catch (\Throwable $e) { /* non-fatal */ }
            }
        } catch (\Throwable $eOuter) { /* degrade quietly */ }
    }
}

if (!function_exists('gst_sync_resolve_seller_user_id')) {
    /**
     * Company (`sellers` row) -> the seller LOGIN account (`users.id`)
     * it belongs to. Uses the stored `linked_user_id` FK when present;
     * otherwise falls back to the existing best-effort match (business
     * name, then seller full_name, then email — same order already
     * used by admin/company_profile.php) and PERSISTS the match once
     * found, so every call after the first is a real ID lookup, not a
     * name match.
     */
    function gst_sync_resolve_seller_user_id(mysqli $conn, int $companyId): ?int
    {
        if ($companyId <= 0) { return null; }
        try {
            $c = $conn->prepare("SELECT id, name, email, linked_user_id FROM sellers WHERE id = ? LIMIT 1");
            $c->bind_param('i', $companyId);
            $c->execute();
            $company = $c->get_result()->fetch_assoc();
            if (!$company) { return null; }

            if (!empty($company['linked_user_id'])) {
                // Confirm the linked account still exists.
                $chk = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
                $chk->bind_param('i', $company['linked_user_id']);
                $chk->execute();
                $row = $chk->get_result()->fetch_assoc();
                if ($row) { return (int)$row['id']; }
                // Stale link (account deleted) — fall through and re-match.
            }

            $userId = null;

            if (gstsync_table_exists($conn, 'seller_payout_profiles')) {
                $m1 = $conn->prepare(
                    "SELECT u.id FROM seller_payout_profiles spp JOIN users u ON u.id = spp.user_id
                      WHERE spp.business_name = ? LIMIT 1"
                );
                $m1->bind_param('s', $company['name']);
                $m1->execute();
                $r1 = $m1->get_result()->fetch_assoc();
                if ($r1) { $userId = (int)$r1['id']; }
            }

            if (!$userId) {
                $m2 = $conn->prepare("SELECT id FROM users WHERE role = 'seller' AND full_name = ? LIMIT 1");
                $m2->bind_param('s', $company['name']);
                $m2->execute();
                $r2 = $m2->get_result()->fetch_assoc();
                if ($r2) { $userId = (int)$r2['id']; }
            }

            if (!$userId && !empty($company['email'])) {
                $m3 = $conn->prepare("SELECT id FROM users WHERE role = 'seller' AND email = ? LIMIT 1");
                $m3->bind_param('s', $company['email']);
                $m3->execute();
                $r3 = $m3->get_result()->fetch_assoc();
                if ($r3) { $userId = (int)$r3['id']; }
            }

            if ($userId && gstsync_col_exists($conn, 'sellers', 'linked_user_id')) {
                try {
                    $upd = $conn->prepare("UPDATE sellers SET linked_user_id = ? WHERE id = ?");
                    $upd->bind_param('ii', $userId, $companyId);
                    $upd->execute();
                } catch (\Throwable $e) { /* link just won't be cached this time */ }
            }

            return $userId;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('gst_sync_resolve_company_id')) {
    /** Reverse of gst_sync_resolve_seller_user_id(): seller user_id -> sellers.id. */
    function gst_sync_resolve_company_id(mysqli $conn, int $userId): ?int
    {
        if ($userId <= 0 || !gstsync_table_exists($conn, 'sellers')) { return null; }
        try {
            if (gstsync_col_exists($conn, 'sellers', 'linked_user_id')) {
                $s = $conn->prepare("SELECT id FROM sellers WHERE linked_user_id = ? LIMIT 1");
                $s->bind_param('i', $userId);
                $s->execute();
                $row = $s->get_result()->fetch_assoc();
                if ($row) { return (int)$row['id']; }
            }

            $u = $conn->prepare("SELECT full_name, email FROM users WHERE id = ? LIMIT 1");
            $u->bind_param('i', $userId);
            $u->execute();
            $user = $u->get_result()->fetch_assoc();
            if (!$user) { return null; }

            $businessName = null;
            if (gstsync_table_exists($conn, 'seller_payout_profiles')) {
                $bn = $conn->prepare("SELECT business_name FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
                $bn->bind_param('i', $userId);
                $bn->execute();
                $bnRow = $bn->get_result()->fetch_assoc();
                $businessName = $bnRow['business_name'] ?? null;
            }

            $companyId = null;
            foreach (array_filter([$businessName, $user['full_name']]) as $name) {
                $m = $conn->prepare("SELECT id FROM sellers WHERE name = ? LIMIT 1");
                $m->bind_param('s', $name);
                $m->execute();
                $row = $m->get_result()->fetch_assoc();
                if ($row) { $companyId = (int)$row['id']; break; }
            }
            if (!$companyId && !empty($user['email'])) {
                $m = $conn->prepare("SELECT id FROM sellers WHERE email = ? LIMIT 1");
                $m->bind_param('s', $user['email']);
                $m->execute();
                $row = $m->get_result()->fetch_assoc();
                if ($row) { $companyId = (int)$row['id']; }
            }

            if ($companyId && gstsync_col_exists($conn, 'sellers', 'linked_user_id')) {
                try {
                    $upd = $conn->prepare("UPDATE sellers SET linked_user_id = ? WHERE id = ?");
                    $upd->bind_param('ii', $userId, $companyId);
                    $upd->execute();
                } catch (\Throwable $e) { /* non-fatal */ }
            }

            return $companyId;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('gst_sync_push_company_verified_to_seller')) {
    /**
     * Admin just verified/unverified a company's GST (`sellers` row) —
     * push the identical status onto the linked seller LOGIN account's
     * own GST profile (`seller_payout_profiles.gst_verified_status`),
     * which is what the Seller Dashboard actually reads. Best-effort:
     * if no seller account can be matched yet, the company record is
     * still updated (already done by the caller) and this becomes a
     * no-op until a matching seller account exists.
     */
    function gst_sync_push_company_verified_to_seller(mysqli $conn, int $companyId, bool $verified, ?int $adminId): bool
    {
        try {
            gst_sync_bootstrap_schema($conn);
            $userId = gst_sync_resolve_seller_user_id($conn, $companyId);
            if (!$userId || !gstsync_table_exists($conn, 'seller_payout_profiles')) { return false; }

            // Make sure the seller has a payout-profile row to update.
            $chk = $conn->prepare("SELECT user_id FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
            $chk->bind_param('i', $userId);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                $ins = $conn->prepare("INSERT INTO seller_payout_profiles (user_id) VALUES (?)");
                $ins->bind_param('i', $userId);
                $ins->execute();
            }

            $status = $verified ? 'verified' : 'not_verified';
            $hasVerifiedBy = gstsync_col_exists($conn, 'seller_payout_profiles', 'gst_verified_by');
            if ($hasVerifiedBy) {
                $upd = $conn->prepare(
                    "UPDATE seller_payout_profiles
                        SET gst_verified_status = ?, gst_verified_at = " . ($verified ? "NOW()" : "NULL") . ", gst_verified_by = ?
                      WHERE user_id = ?"
                );
                $upd->bind_param('sii', $status, $adminId, $userId);
            } else {
                $upd = $conn->prepare(
                    "UPDATE seller_payout_profiles
                        SET gst_verified_status = ?, gst_verified_at = " . ($verified ? "NOW()" : "NULL") . "
                      WHERE user_id = ?"
                );
                $upd->bind_param('si', $status, $userId);
            }
            $ok = $upd->execute();

            if ($ok && function_exists('agri_seller_notify')) {
                agri_seller_notify(
                    $conn, $userId,
                    $verified ? 'gst_verified' : 'gst_unverified',
                    $verified ? 'GST Verified' : 'GST Verification Removed',
                    $verified
                        ? 'Your GST details have been verified by AgriCart.'
                        : 'Your GST verification has been reset. Please check your Business/GST Details.',
                    'seller/dashboard.php#gst'
                );
            }

            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('gst_sync_reset_company_gst_on_seller_edit')) {
    /**
     * Seller changed their GSTIN (seller/seller_api.php -> gst_save
     * already reset their own gst_verified_status to 'not_verified').
     * Mirror that back onto the linked Admin-side company record so a
     * stale "Verified" badge never lingers on the Companies page after
     * the seller's underlying GST details actually changed.
     */
    function gst_sync_reset_company_gst_on_seller_edit(mysqli $conn, int $userId): void
    {
        try {
            if (!gstsync_table_exists($conn, 'sellers')) { return; }
            $companyId = gst_sync_resolve_company_id($conn, $userId);
            if (!$companyId) { return; }

            $sets = [];
            if (gstsync_col_exists($conn, 'sellers', 'gst_verified')) { $sets[] = "gst_verified = 0"; }
            if (gstsync_col_exists($conn, 'sellers', 'gst_verified_status')) { $sets[] = "gst_verified_status = 'not_verified'"; }
            if (!$sets) { return; }

            $conn->query("UPDATE sellers SET " . implode(', ', $sets) . " WHERE id = " . (int)$companyId);
        } catch (\Throwable $e) { /* best-effort */ }
    }
}
