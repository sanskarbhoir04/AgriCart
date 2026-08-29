<?php
// =====================================================================
// admin/advisory_request_action.php — Reply to / update status of a
// farmer's advisory request (from admin's "Advisory → Farmer Requests").
// Requires an active admin session.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
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

requirePermission('advisory.approve');

$action = $_POST['action'] ?? '';

if ($action === 'reply') {
    $id         = (int)($_POST['id'] ?? 0);
    $status     = trim($_POST['status'] ?? 'pending');
    $adminReply = trim($_POST['admin_reply'] ?? '');
    $allowedStatuses = ['pending', 'answered', 'closed'];

    if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
        $response['error'] = 'Invalid request id or status.';
        echo json_encode($response);
        exit;
    }

    $answeredAtSql = ($status === 'answered') ? ', answered_at = NOW()' : '';
    $stmt = $conn->prepare(
        "UPDATE advisory_requests SET status = ?, admin_reply = ?" . $answeredAtSql . " WHERE id = ?"
    );
    if (!$stmt) {
        $response['error'] = 'advisory_requests table not found. Run setup/advisory_requests_setup.sql.';
        echo json_encode($response);
        exit;
    }
    $stmt->bind_param("ssi", $status, $adminReply, $id);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Update failed.';
    } else {
        logAdminActivity('advisory_request_replied', 'advisory', $id, null, $status, 'Replied to advisory request #' . $id . ', status set to "' . $status . '"');
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
