<?php
// =====================================================================
// admin/contact_action.php — Manage contact_messages entries.
// Actions: status (update status), add (create new message), delete.
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
    requirePermission('support.resolve');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $response['error'] = 'Invalid id.';
        echo json_encode($response);
        exit;
    }
    $response['success'] = agri_soft_delete($conn, 'contact_messages', $id);
    if (!$response['success']) {
        $response['error'] = 'Delete failed.';
    } else {
        logAdminActivity('contact_message_deleted', 'support', $id, null, null, 'Deleted contact message #' . $id);
    }
    echo json_encode($response);
    exit;
}

if ($action === 'restore') {
    requirePermission('support.resolve');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid id.'; echo json_encode($response); exit; }
    $response['success'] = agri_restore($conn, 'contact_messages', $id);
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('contact_message_restored', 'support', $id, null, null, 'Restored contact message #' . $id);
    }
    echo json_encode($response);
    exit;
}

if ($action === 'add') {
    requirePermission('support.reply');
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $phone === '' || $subject === '' || $message === '') {
        $response['error'] = 'Name, phone, subject and message are required.';
        echo json_encode($response);
        exit;
    }
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $response['error'] = 'Phone must be a valid 10-digit number.';
        echo json_encode($response);
        exit;
    }

    $ticketNumber = 'AGC-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $emailToSave  = $email !== '' ? $email : 'not-provided@agricart.local';
    $userId       = $_SESSION['user_id'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO contact_messages (ticket_number, user_id, name, email, phone, subject, message, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'new')"
    );
    $stmt->bind_param("sisssss", $ticketNumber, $userId, $name, $emailToSave, $phone, $subject, $message);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Add failed.';
    } else {
        logAdminActivity('contact_message_added', 'support', $conn->insert_id, null, ['subject' => $subject], 'Logged a new support message (ticket ' . $ticketNumber . ')');
    }
    echo json_encode($response);
    exit;
}

// ---- default: status update ----
$id     = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowedStatuses = ['new','read','replied','closed'];

if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
    $response['error'] = 'Invalid id or status.';
    echo json_encode($response);
    exit;
}

requirePermission($status === 'replied' ? 'support.reply' : 'support.resolve');

$stmt = $conn->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);
$response['success'] = $stmt->execute();
if (!$response['success']) {
    $response['error'] = 'Update failed.';
} else {
    logAdminActivity('contact_status_changed', 'support', $id, null, $status, 'Contact message #' . $id . ' status changed to "' . $status . '"');
}

echo json_encode($response);
