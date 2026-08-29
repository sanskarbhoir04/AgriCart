<?php
// =====================================================================
// admin/actions/permission_action.php — Per-team-member custom
// permission overrides (allow/deny), layered on top of their role.
// A user-level "deny" always overrides the role's "allow".
// =====================================================================
require_once __DIR__ . '/../../includes/db.php';
agri_session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/permissions.php';

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}
csrf_require('json');

$action = $_POST['action'] ?? '';

if ($action === 'save_overrides') {
    requirePermission('team.change_permissions');

    $memberId = (int)($_POST['member_id'] ?? 0);
    // overrides[permission_id] = 'allow' | 'deny' | 'inherit'
    $overrides = $_POST['overrides'] ?? [];

    if ($memberId <= 0) { $response['error'] = 'Invalid team member.'; echo json_encode($response); exit; }

    $find = $conn->prepare("SELECT m.*, r.role_slug FROM admin_team_members m JOIN admin_roles r ON r.id = m.role_id WHERE m.id = ?");
    $find->bind_param('i', $memberId);
    $find->execute();
    $member = $find->get_result()->fetch_assoc();
    if (!$member) { $response['error'] = 'Team member not found.'; echo json_encode($response); exit; }

    if ($member['role_slug'] === 'super_admin' && !isSuperAdmin()) {
        requirePermission('__super_admin_only__');
    }

    $del = $conn->prepare("DELETE FROM admin_user_permissions WHERE admin_member_id = ?");
    $del->bind_param('i', $memberId);
    $del->execute();

    $insertCount = 0;
    if (is_array($overrides)) {
        $ins = $conn->prepare("INSERT INTO admin_user_permissions (admin_member_id, permission_id, permission_type) VALUES (?,?,?)");
        foreach ($overrides as $permissionId => $type) {
            $permissionId = (int)$permissionId;
            if ($permissionId <= 0) { continue; }
            if (!in_array($type, ['allow', 'deny'], true)) { continue; } // 'inherit' = no row
            $ins->bind_param('iis', $memberId, $permissionId, $type);
            $ins->execute();
            $insertCount++;
        }
    }

    logAdminActivity('permission_override_changed', 'team', $member['user_id'], null,
        $insertCount . ' overrides', 'Set ' . $insertCount . ' custom permission override(s) for team member #' . $memberId);
    notifySuperAdmin('permission_changed', 'Custom permissions changed', 'A team member\'s custom permission overrides were updated.');

    $response['success'] = true;
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
