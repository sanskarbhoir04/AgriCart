<?php
// =====================================================================
// admin_guard.php — include this at the very top of every admin page
// (except login.php / auth.php themselves) to require a valid admin
// session. Anyone without $_SESSION['is_admin'] === true is bounced
// back to the login page.
//
// RBAC UPGRADE: this file now also loads the permission helper library
// (hasPermission, requirePermission, isSuperAdmin, canViewModule, ...)
// and re-validates that the logged-in admin's account is still active
// and not expired on every single page load — so disabling someone or
// letting their access expire takes effect immediately, not just at
// their next login.
// =====================================================================
require_once __DIR__ . '/../../includes/security.php';
agri_session_start(1800); // 30-minute inactivity timeout for admin sessions

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ' . (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'actions' ? '../login.php' : 'login.php'));
    exit;
}

require_once __DIR__ . '/permissions.php';
include_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/team_roles_schema.php';
team_roles_bootstrap_schema($conn);

// Re-check the admin_team_members row on every page load (not just at
// login) so that Deactivate / Suspend / Expiry actions taken by a Super
// Admin take effect for that user's very next request.
if (!empty($_SESSION['admin_member_id'])) {
    $stmt = $conn->prepare("SELECT status, access_expiry_date, role_id FROM admin_team_members WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['admin_member_id']);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();

    $blocked = false;
    $reason  = '';

    if (!$member) {
        $blocked = true;
        $reason  = 'account_removed';
    } elseif ($member['status'] === 'suspended') {
        $blocked = true;
        $reason  = 'suspended';
    } elseif ($member['status'] === 'inactive') {
        $blocked = true;
        $reason  = 'inactive';
    } elseif (!empty($member['access_expiry_date']) && $member['access_expiry_date'] < date('Y-m-d')) {
        $blocked = true;
        $reason  = 'expired';
        // Reflect the expiry in the database too.
        $upd = $conn->prepare("UPDATE admin_team_members SET status = 'expired' WHERE id = ? AND status <> 'expired'");
        $upd->bind_param('i', $_SESSION['admin_member_id']);
        $upd->execute();
    }

    if ($blocked) {
        $adminUserId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;
        session_unset();
        session_destroy();
        header('Location: login.php?error=' . $reason);
        exit;
    }
}
