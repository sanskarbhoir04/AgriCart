<?php
// =====================================================================
// admin/newsletter_action.php — Remove a newsletter subscriber.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_require')) {
    csrf_require('json');
}

requirePermission('newsletter.manage');

$action = $_POST['action'] ?? 'delete';
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $response['error'] = 'Invalid id.';
    echo json_encode($response);
    exit;
}

if ($action === 'restore') {
    $response['success'] = agri_restore($conn, 'newsletter_subscribers', $id);
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('newsletter_subscriber_restored', 'newsletter', $id, null, null, 'Restored newsletter subscriber #' . $id);
    }
    echo json_encode($response);
    exit;
}

$response['success'] = agri_soft_delete($conn, 'newsletter_subscribers', $id);
if (!$response['success']) {
    $response['error'] = 'Delete failed.';
} else {
    logAdminActivity('newsletter_subscriber_removed', 'newsletter', $id, null, null, 'Removed newsletter subscriber #' . $id);
}

echo json_encode($response);
