<?php
// =====================================================================
// admin/includes/team_roles_schema.php — Bootstrap + shared helpers for
// MULTI-ROLE team members (a team member can now hold more than one
// role at once, e.g. "Support" + "Inventory Manager").
//
// Follows the exact defensive pattern already used across this codebase
// (see includes/inventory_schema.php / includes/reports_schema.php):
// team_roles_bootstrap_schema() only ever ADDS a table — it never
// touches or drops admin_team_members.role_id or anything else that
// already exists, so it cannot break existing functionality.
//
// admin_team_members.role_id is kept as-is and now means "primary
// role" (used anywhere in the app that still expects exactly one role,
// e.g. the Role column filter). The full set of roles for permission
// checks lives in admin_team_member_roles.
//
// Include this AFTER admin_guard.php (so $conn is already available).
// =====================================================================

if (!function_exists('team_roles_table_exists')) {
    function team_roles_table_exists(mysqli $conn): bool
    {
        try {
            $res = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_team_member_roles' LIMIT 1");
            return $res && $res->num_rows > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('team_roles_bootstrap_schema')) {
    /**
     * Idempotent, additive-only schema setup for multi-role team
     * members. Safe to call on every page load.
     */
    function team_roles_bootstrap_schema(mysqli $conn): void
    {
        try {
            if (!team_roles_table_exists($conn)) {
                $conn->query(
                    "CREATE TABLE admin_team_member_roles (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        member_id INT NOT NULL,
                        role_id INT NOT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uniq_member_role (member_id, role_id),
                        KEY idx_member (member_id),
                        KEY idx_role (role_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            }
            // Backfill: every existing team member's current primary
            // role_id becomes their (only) row here if they don't have
            // any rows yet. INSERT IGNORE so this is a safe no-op once
            // it's already been run once.
            $conn->query(
                "INSERT IGNORE INTO admin_team_member_roles (member_id, role_id)
                 SELECT id, role_id FROM admin_team_members"
            );
        } catch (\Throwable $e) {
            // Never let schema bootstrap break the page.
        }
    }
}

if (!function_exists('team_roles_get_for_member')) {
    /** Returns role ids currently assigned to a team member, e.g. [3, 7]. */
    function team_roles_get_for_member(mysqli $conn, int $memberId): array
    {
        $ids = [];
        $stmt = $conn->prepare("SELECT role_id FROM admin_team_member_roles WHERE member_id = ?");
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $ids[] = (int)$row['role_id']; }
        return $ids;
    }
}

if (!function_exists('team_roles_sync_for_member')) {
    /**
     * Replaces a team member's full role set with $roleIds (array of
     * ints, deduped, at least one required) and keeps
     * admin_team_members.role_id pointing at the first one as the
     * "primary" role for display/back-compat purposes.
     */
    function team_roles_sync_for_member(mysqli $conn, int $memberId, array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if (empty($roleIds)) return;

        $del = $conn->prepare("DELETE FROM admin_team_member_roles WHERE member_id = ?");
        $del->bind_param('i', $memberId);
        $del->execute();

        $ins = $conn->prepare("INSERT IGNORE INTO admin_team_member_roles (member_id, role_id) VALUES (?, ?)");
        foreach ($roleIds as $rid) {
            $ins->bind_param('ii', $memberId, $rid);
            $ins->execute();
        }

        $primary = $roleIds[0];
        $updPrimary = $conn->prepare("UPDATE admin_team_members SET role_id = ? WHERE id = ?");
        $updPrimary->bind_param('ii', $primary, $memberId);
        $updPrimary->execute();
    }
}
