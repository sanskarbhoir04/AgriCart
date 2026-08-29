<?php
// =====================================================================
// admin/user_action.php — Change a user's role (farmer / buyer / seller /
// expert / admin). When promoting to 'expert', optional qualification
// and expertise fields can be set at the same time.
// =====================================================================
include __DIR__ . '/../includes/db.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/agri_connect_schema.php';
agri_connect_bootstrap_schema($conn);
require_once __DIR__ . '/includes/permissions.php';

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}
csrf_require('json');

requirePermission('users.change_role');

$id   = (int)($_POST['id'] ?? 0);
$role = trim($_POST['role'] ?? '');
$allowedRoles = ['farmer','customer','buyer','seller','expert','admin'];

if ($id <= 0 || !in_array($role, $allowedRoles, true)) {
    $response['error'] = 'Invalid id or role.';
    echo json_encode($response);
    exit;
}

// Safety: don't let an admin accidentally demote themselves via this endpoint.
if ($id === (int)($_SESSION['admin_id'] ?? 0)) {
    $response['error'] = "You can't change your own role here.";
    echo json_encode($response);
    exit;
}

// Promoting a website user straight to 'admin' from here would bypass the
// Team Management flow entirely (no admin_team_members row, so no RBAC
// role/permissions would ever be assigned to them). Route that through
// Team Management > Add Team Member instead, which creates both records
// together.
if ($role === 'admin') {
    $response['error'] = "To give someone Admin Panel access, use Team Management → Add Team Member instead (it assigns a proper role and permissions).";
    echo json_encode($response);
    exit;
}

$oldRoleStmt = $conn->prepare("SELECT role, full_name FROM users WHERE id = ?");
$oldRoleStmt->bind_param("i", $id);
$oldRoleStmt->execute();
$userRow = $oldRoleStmt->get_result()->fetch_assoc();
$oldRole = $userRow['role'] ?? null;
$userName = $userRow['full_name'] ?? ('#' . $id);

if ($role === 'expert') {
    $qualification = trim($_POST['qualification'] ?? '');
    $expertise     = trim($_POST['expertise'] ?? '');
    $stmt = $conn->prepare("UPDATE users SET role = ?, qualification = ?, expertise = ? WHERE id = ?");
    $stmt->bind_param("sssi", $role, $qualification, $expertise, $id);
} else {
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $role, $id);
}
$response['success'] = $stmt->execute();
if (!$response['success']) {
    $response['error'] = 'Update failed.';
} else {
    logAdminActivity('user_role_changed', 'users', $id, $oldRole, $role, 'Changed role for "' . $userName . '" from "' . $oldRole . '" to "' . $role . '"');
}

echo json_encode($response);
