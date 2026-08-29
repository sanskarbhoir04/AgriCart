<?php
// =====================================================================
// admin/advisory_action.php — Add / Update / Delete Advisory (farming
// tips / crop advisory) posts. Requires an active admin session.
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

$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    requirePermission('advisory.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid advisory id.'; echo json_encode($response); exit; }
    $response['success'] = agri_soft_delete($conn, 'advisory', $id);
    if (!$response['success']) {
        $response['error'] = 'Delete failed.';
    } else {
        logAdminActivity('advisory_deleted', 'advisory', $id, null, null, 'Deleted advisory post #' . $id);
    }
    echo json_encode($response);
    exit;
}

if ($action === 'restore') {
    requirePermission('advisory.edit');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid advisory id.'; echo json_encode($response); exit; }
    $response['success'] = agri_restore($conn, 'advisory', $id);
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('advisory_restored', 'advisory', $id, null, null, 'Restored advisory post #' . $id);
    }
    echo json_encode($response);
    exit;
}

if ($action === 'save') {
    $id         = (int)($_POST['id'] ?? 0);
    requirePermission($id > 0 ? 'advisory.edit' : 'advisory.create');
    $title      = trim($_POST['title'] ?? '');
    $titleMr    = trim($_POST['title_mr'] ?? '');
    $crop       = trim($_POST['crop'] ?? '');
    $image      = trim($_POST['image'] ?? '');
    $content    = trim($_POST['content'] ?? '');
    $contentMr  = trim($_POST['content_mr'] ?? '');
    $adminId    = (int)($_SESSION['user_id'] ?? 0);

    if ($title === '' || $content === '') {
        $response['error'] = 'Title and content are required.';
        echo json_encode($response);
        exit;
    }

    if ($id > 0) {
        $stmt = $conn->prepare(
            "UPDATE advisory SET title=?, title_mr=?, crop=?, image=?, content=?, content_mr=? WHERE id=?"
        );
        $stmt->bind_param(
            "ssssssi",
            $title, $titleMr, $crop, $image, $content, $contentMr, $id
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO advisory (title, title_mr, crop, image, content, content_mr, posted_by) VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            "ssssssi",
            $title, $titleMr, $crop, $image, $content, $contentMr, $adminId
        );
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $newId = $id > 0 ? $id : $conn->insert_id;
        $response['id'] = $newId;
        if ($id > 0) {
            logAdminActivity('advisory_updated', 'advisory', $newId, null, ['title' => $title, 'crop' => $crop], 'Updated advisory "' . $title . '"');
        } else {
            logAdminActivity('advisory_created', 'advisory', $newId, null, ['title' => $title, 'crop' => $crop], 'Published advisory "' . $title . '"');
        }
    } else {
        error_log('admin/advisory_action.php: save failed: ' . $conn->error);
        $response['error'] = 'Save failed. Please try again.';
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
