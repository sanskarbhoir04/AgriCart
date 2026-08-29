<?php
// =====================================================================
// admin/krishi_bazaar_action.php — Add / Update / Delete Krishi Bazaar
// (mandi/market crop price) entries. Requires an active admin session.
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
    requirePermission('bazaar.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid entry id.'; echo json_encode($response); exit; }
    $response['success'] = agri_soft_delete($conn, 'krishi_bazaar', $id);
    if (!$response['success']) {
        $response['error'] = 'Delete failed.';
    } else {
        logAdminActivity('bazaar_entry_deleted', 'bazaar', $id, null, null, 'Deleted Krishi Bazaar entry #' . $id);
    }
    echo json_encode($response);
    exit;
}

if ($action === 'restore') {
    requirePermission('bazaar.edit');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid id.'; echo json_encode($response); exit; }
    $response['success'] = agri_restore($conn, 'krishi_bazaar', $id);
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('bazaar_entry_restored', 'bazaar', $id, null, null, 'Restored Krishi Bazaar entry #' . $id);
    }
    echo json_encode($response);
    exit;
}

if ($action === 'save') {
    $id          = (int)($_POST['id'] ?? 0);
    requirePermission($id > 0 ? 'bazaar.edit' : 'bazaar.create');
    $cropName    = trim($_POST['crop_name'] ?? '');
    $cropNameMr  = trim($_POST['crop_name_mr'] ?? '');
    $marketName  = trim($_POST['market_name'] ?? '');
    $district    = trim($_POST['district'] ?? '');
    $minPrice    = (float)($_POST['min_price'] ?? 0);
    $maxPrice    = (float)($_POST['max_price'] ?? 0);
    $modalPrice  = $_POST['modal_price'] !== '' ? (float)$_POST['modal_price'] : (($minPrice + $maxPrice) / 2);
    $unit        = trim($_POST['unit'] ?? 'quintal');
    $priceDate   = trim($_POST['price_date'] ?? date('Y-m-d'));

    if ($cropName === '' || $marketName === '' || $minPrice <= 0 || $maxPrice <= 0) {
        $response['error'] = 'Crop name, market name, and valid min/max prices are required.';
        echo json_encode($response);
        exit;
    }

    if ($id > 0) {
        $stmt = $conn->prepare(
            "UPDATE krishi_bazaar SET crop_name=?, crop_name_mr=?, market_name=?, district=?, min_price=?, max_price=?, modal_price=?, unit=?, price_date=? WHERE id=?"
        );
        $stmt->bind_param(
            "ssssdddssi",
            $cropName, $cropNameMr, $marketName, $district, $minPrice, $maxPrice, $modalPrice, $unit, $priceDate, $id
        );
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO krishi_bazaar (crop_name, crop_name_mr, market_name, district, min_price, max_price, modal_price, unit, price_date) VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            "ssssdddss",
            $cropName, $cropNameMr, $marketName, $district, $minPrice, $maxPrice, $modalPrice, $unit, $priceDate
        );
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $newId = $id > 0 ? $id : $conn->insert_id;
        $response['id'] = $newId;
        $summary = ['crop' => $cropName, 'market' => $marketName, 'modal_price' => $modalPrice];
        if ($id > 0) {
            logAdminActivity('bazaar_entry_updated', 'bazaar', $newId, null, $summary, 'Updated Krishi Bazaar price for "' . $cropName . '" at ' . $marketName);
        } else {
            logAdminActivity('bazaar_entry_created', 'bazaar', $newId, null, $summary, 'Added Krishi Bazaar price for "' . $cropName . '" at ' . $marketName);
        }
    } else {
        error_log('admin/krishi_bazaar_action.php: save failed: ' . $conn->error);
        $response['error'] = 'Save failed. Please try again.';
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
