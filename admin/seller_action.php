<?php
// =====================================================================
// admin/seller_action.php — Add / Update / Delete sellers.
// Every query uses prepared statements. Requires an active admin session.
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
    requirePermission('sellers.block');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid seller id.'; echo json_encode($response); exit; }
    $nStmt = $conn->prepare("SELECT name FROM sellers WHERE id = ?");
    $nStmt->bind_param("i", $id);
    $nStmt->execute();
    $sName = $nStmt->get_result()->fetch_assoc()['name'] ?? ('#' . $id);
    $response['success'] = agri_soft_delete($conn, 'sellers', $id);
    if (!$response['success']) {
        $response['error'] = 'Delete failed.';
    } else {
        logAdminActivity('seller_blocked', 'sellers', $id, null, null, 'Blocked/removed seller "' . $sName . '"');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'restore') {
    requirePermission('sellers.approve');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid id.'; echo json_encode($response); exit; }
    $nStmt = $conn->prepare("SELECT name FROM sellers WHERE id = ?");
    $nStmt->bind_param("i", $id);
    $nStmt->execute();
    $sName = $nStmt->get_result()->fetch_assoc()['name'] ?? ('#' . $id);
    $response['success'] = agri_restore($conn, 'sellers', $id);
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('seller_restored', 'sellers', $id, null, null, 'Restored seller "' . $sName . '"');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'save') {
    requirePermission('sellers.approve');
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $mobile   = trim($_POST['mobile'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $village  = trim($_POST['village'] ?? '');
    $city     = trim($_POST['city'] ?? '');
    $verified = (int)($_POST['verified'] ?? 0);
    $notes    = trim($_POST['notes'] ?? '');

    if ($name === '') {
        $response['error'] = 'Seller name is required.';
        echo json_encode($response);
        exit;
    }

    $oldSummary = null;
    if ($id > 0) {
        $oStmt = $conn->prepare("SELECT name, verified FROM sellers WHERE id = ?");
        $oStmt->bind_param("i", $id);
        $oStmt->execute();
        $oldRow = $oStmt->get_result()->fetch_assoc();
        if ($oldRow) { $oldSummary = ['name' => $oldRow['name'], 'verified' => (bool)$oldRow['verified']]; }

        $stmt = $conn->prepare("UPDATE sellers SET name=?, mobile=?, email=?, village=?, city=?, verified=?, notes=? WHERE id=?");
        $stmt->bind_param("sssssisi", $name, $mobile, $email, $village, $city, $verified, $notes, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO sellers (name, mobile, email, village, city, verified, notes) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssis", $name, $mobile, $email, $village, $city, $verified, $notes);
    }

    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        if ($conn->errno === 1146) {
            $response['error'] = "The 'sellers' table doesn't exist yet. Run add_sellers_coupons.sql in phpMyAdmin first.";
        } else {
            error_log('admin/seller_action.php: save failed: ' . $conn->error);
            $response['error'] = 'Save failed. Please try again.';
        }
    } else {
        $newId = $id > 0 ? $id : $conn->insert_id;
        $response['id'] = $newId;
        $newSummary = ['name' => $name, 'verified' => (bool)$verified];
        if ($id > 0) {
            logAdminActivity('seller_updated', 'sellers', $newId, $oldSummary, $newSummary, 'Updated seller "' . $name . '"');
        } else {
            logAdminActivity('seller_created', 'sellers', $newId, null, $newSummary, 'Added seller "' . $name . '"');
        }
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
