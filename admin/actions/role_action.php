<?php
// =====================================================================
// admin/actions/role_action.php — Create / Edit / Duplicate / Toggle
// custom Admin Panel roles, and assign their permissions.
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

function agri_slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
}

if ($action === 'create') {
    requirePermission('team.change_permissions');

    $roleName    = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $permissionIds = $_POST['permissions'] ?? [];

    if ($roleName === '') { $response['error'] = 'Role name is required.'; echo json_encode($response); exit; }

    $slug = agri_slugify($roleName);
    if ($slug === '') { $response['error'] = 'Please enter a valid role name.'; echo json_encode($response); exit; }

    $dup = $conn->prepare("SELECT id FROM admin_roles WHERE role_slug = ?");
    $dup->bind_param('s', $slug);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $slug .= '_' . substr(md5(uniqid('', true)), 0, 5);
    }

    $createdBy = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;
    $ins = $conn->prepare("INSERT INTO admin_roles (role_name, role_slug, description, is_system_role, is_active, created_by) VALUES (?,?,?,0,1,?)");
    $ins->bind_param('sssi', $roleName, $slug, $description, $createdBy);
    if (!$ins->execute()) {
        error_log('admin/actions/role_action.php: role create failed: ' . $conn->error);
        $response['error'] = 'Could not create role. Please try again.';
        echo json_encode($response); exit;
    }
    $roleId = $conn->insert_id;

    if (is_array($permissionIds)) {
        $stmt = $conn->prepare("INSERT INTO admin_role_permissions (role_id, permission_id, allowed) VALUES (?,?,1)");
        foreach ($permissionIds as $pid) {
            $pid = (int)$pid;
            if ($pid > 0) { $stmt->bind_param('ii', $roleId, $pid); $stmt->execute(); }
        }
    }

    logAdminActivity('role_created', 'roles', $roleId, null, ['name' => $roleName, 'permissions' => count($permissionIds)], 'Created custom role "' . $roleName . '"');
    $response['success'] = true;
    $response['role_id'] = $roleId;
    echo json_encode($response);
    exit;
}

if ($action === 'update') {
    requirePermission('team.change_permissions');

    $roleId      = (int)($_POST['role_id'] ?? 0);
    $roleName    = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $permissionIds = $_POST['permissions'] ?? [];

    if ($roleId <= 0 || $roleName === '') { $response['error'] = 'Invalid request.'; echo json_encode($response); exit; }

    $find = $conn->prepare("SELECT * FROM admin_roles WHERE id = ?");
    $find->bind_param('i', $roleId);
    $find->execute();
    $role = $find->get_result()->fetch_assoc();
    if (!$role) { $response['error'] = 'Role not found.'; echo json_encode($response); exit; }

    if ($role['is_system_role'] && !isSuperAdmin()) {
        requirePermission('__super_admin_only__');
    }

    // System-role slugs never change (permission mapping keys off them
    // elsewhere); custom roles keep their name in sync with their slug
    // only at creation time, so editing a custom role's name does not
    // change its slug (avoids breaking existing team-member assignments).
    $upd = $conn->prepare("UPDATE admin_roles SET role_name = ?, description = ? WHERE id = ?");
    $upd->bind_param('ssi', $roleName, $description, $roleId);
    $upd->execute();

    $old = [];
    $oldStmt = $conn->prepare("SELECT permission_id FROM admin_role_permissions WHERE role_id = ? AND allowed = 1");
    $oldStmt->bind_param('i', $roleId);
    $oldStmt->execute();
    $oldRes = $oldStmt->get_result();
    while ($r = $oldRes->fetch_assoc()) { $old[] = (int)$r['permission_id']; }

    $del = $conn->prepare("DELETE FROM admin_role_permissions WHERE role_id = ?");
    $del->bind_param('i', $roleId);
    $del->execute();

    $new = [];
    if (is_array($permissionIds)) {
        $stmt = $conn->prepare("INSERT INTO admin_role_permissions (role_id, permission_id, allowed) VALUES (?,?,1)");
        foreach ($permissionIds as $pid) {
            $pid = (int)$pid;
            if ($pid > 0) { $stmt->bind_param('ii', $roleId, $pid); $stmt->execute(); $new[] = $pid; }
        }
    }

    logAdminActivity('role_permissions_changed', 'roles', $roleId, count($old) . ' permissions', count($new) . ' permissions', 'Updated permissions for role "' . $roleName . '"');
    $response['success'] = true;
    echo json_encode($response);
    exit;
}

if ($action === 'duplicate') {
    requirePermission('team.change_permissions');

    $roleId = (int)($_POST['role_id'] ?? 0);
    $find = $conn->prepare("SELECT * FROM admin_roles WHERE id = ?");
    $find->bind_param('i', $roleId);
    $find->execute();
    $role = $find->get_result()->fetch_assoc();
    if (!$role) { $response['error'] = 'Role not found.'; echo json_encode($response); exit; }

    $newName = $role['role_name'] . ' (Copy)';
    $slug = agri_slugify($newName) . '_' . substr(md5(uniqid('', true)), 0, 5);
    $createdBy = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;

    $ins = $conn->prepare("INSERT INTO admin_roles (role_name, role_slug, description, is_system_role, is_active, created_by) VALUES (?,?,?,0,1,?)");
    $ins->bind_param('sssi', $newName, $slug, $role['description'], $createdBy);
    $ins->execute();
    $newRoleId = $conn->insert_id;

    $conn->query("INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
                   SELECT {$newRoleId}, permission_id, allowed FROM admin_role_permissions WHERE role_id = {$roleId}");

    logAdminActivity('role_duplicated', 'roles', $newRoleId, null, ['from' => $role['role_name']], 'Duplicated role "' . $role['role_name'] . '"');
    $response['success'] = true;
    $response['role_id'] = $newRoleId;
    echo json_encode($response);
    exit;
}

if ($action === 'toggle_active') {
    requirePermission('team.change_permissions');

    $roleId = (int)($_POST['role_id'] ?? 0);
    $find = $conn->prepare("SELECT * FROM admin_roles WHERE id = ?");
    $find->bind_param('i', $roleId);
    $find->execute();
    $role = $find->get_result()->fetch_assoc();
    if (!$role) { $response['error'] = 'Role not found.'; echo json_encode($response); exit; }
    if ($role['role_slug'] === 'super_admin') {
        $response['error'] = 'The Super Admin role cannot be deactivated.';
        echo json_encode($response); exit;
    }

    $newState = $role['is_active'] ? 0 : 1;
    $upd = $conn->prepare("UPDATE admin_roles SET is_active = ? WHERE id = ?");
    $upd->bind_param('ii', $newState, $roleId);
    $upd->execute();

    logAdminActivity('role_status_changed', 'roles', $roleId, $role['is_active'], $newState, ($newState ? 'Activated' : 'Deactivated') . ' role "' . $role['role_name'] . '"');
    $response['success'] = true;
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
