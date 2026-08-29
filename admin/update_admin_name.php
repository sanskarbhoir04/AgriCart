<?php
// =====================================================================
// admin/update_admin_name.php — Lets the CURRENTLY logged-in admin fix
// their own display name (the users.full_name column), for cases where
// the account was originally created with a placeholder like "Admin".
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/permissions.php';

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_require')) {
    csrf_require('json');
}

$fullName = trim($_POST['full_name'] ?? '');
if ($fullName === '' || mb_strlen($fullName) > 100) {
    $response['error'] = 'Please enter a valid name.';
    echo json_encode($response);
    exit;
}

$adminId = (int)$_SESSION['admin_id'];

$stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ? AND role = 'admin'");
$stmt->bind_param("si", $fullName, $adminId);

if ($stmt->execute()) {
    $_SESSION['admin_name'] = $fullName; // keep the session in sync immediately
    $response['success'] = true;
    logAdminActivity('admin_name_updated', 'profile', $adminId, null, $fullName, 'Updated their own display name to "' . $fullName . '"');
} else {
    $response['error'] = 'Update failed.';
}

echo json_encode($response);
