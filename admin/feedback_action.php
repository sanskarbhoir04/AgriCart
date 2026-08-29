<?php
// =====================================================================
// admin/feedback_action.php — Manage footer feedback entries.
// Actions: status (update status), add (create new entry), delete.
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

$action = trim($_POST['action'] ?? 'status');

if ($action === 'delete') {
    requirePermission('feedback.manage');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $response['error'] = 'Invalid id.';
        echo json_encode($response);
        exit;
    }
    $response['success'] = agri_soft_delete($conn, 'feedback', $id);
    if (!$response['success']) {
        $response['error'] = 'Delete failed.';
    } else {
        logAdminActivity('feedback_deleted', 'feedback', $id, null, null, 'Deleted feedback #' . $id);
    }
    echo json_encode($response);
    exit;
}

if ($action === 'restore') {
    requirePermission('feedback.manage');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid id.'; echo json_encode($response); exit; }
    $response['success'] = agri_restore($conn, 'feedback', $id);
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('feedback_restored', 'feedback', $id, null, null, 'Restored feedback #' . $id);
    }
    echo json_encode($response);
    exit;
}

if ($action === 'add') {
    requirePermission('feedback.manage');
    $message = trim($_POST['message'] ?? '');
    $rating  = ($_POST['rating'] ?? '') !== '' ? (int)$_POST['rating'] : null;
    $page    = trim($_POST['page'] ?? '');

    if ($message === '') {
        $response['error'] = 'Message is required.';
        echo json_encode($response);
        exit;
    }
    if ($rating !== null && ($rating < 1 || $rating > 5)) { $rating = null; }
    if (mb_strlen($message) > 2000) { $message = mb_substr($message, 0, 2000); }
    if ($page !== '' && strlen($page) > 255) { $page = substr($page, 0, 255); }

    $adminUserId = $_SESSION['user_id'] ?? null;
    $stmt = $conn->prepare("INSERT INTO feedback (message, rating, page, user_id, status, created_at) VALUES (?, ?, ?, ?, 'new', NOW())");
    $stmt->bind_param("sisi", $message, $rating, $page, $adminUserId);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Add failed.';
    } else {
        logAdminActivity('feedback_added', 'feedback', $conn->insert_id, null, null, 'Logged a new feedback entry');
    }
    echo json_encode($response);
    exit;
}

// ---- default: status update ----
$id     = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowedStatuses = ['new','read','resolved'];

if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
    $response['error'] = 'Invalid id or status.';
    echo json_encode($response);
    exit;
}

requirePermission('feedback.manage');

$stmt = $conn->prepare("UPDATE feedback SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);
$response['success'] = $stmt->execute();
if (!$response['success']) {
    $response['error'] = 'Update failed.';
} else {
    logAdminActivity('feedback_status_changed', 'feedback', $id, null, $status, 'Feedback #' . $id . ' status changed to "' . $status . '"');
}

echo json_encode($response);
