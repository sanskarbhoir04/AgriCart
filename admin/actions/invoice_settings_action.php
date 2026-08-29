<?php
// =====================================================================
// admin/actions/invoice_settings_action.php — Save AgriCart's official
// invoice Digital Signature / Stamp / Authorized Signatory Name /
// Designation (Admin Panel -> Settings -> Invoice Settings). Gated on
// 'settings.invoice_manage' so only an authorized Admin/Super Admin can
// change these assets — every Seller Invoice on the marketplace uses
// whatever is configured here.
//
// Uploaded images never overwrite an already-issued invoice: see
// agri_seller_invoice_freeze_agricart_snapshot() in
// includes/seller_functions.php — only NEWLY generated Seller Invoices
// pick up a change made here.
// =====================================================================
require_once __DIR__ . '/../../includes/db.php';
agri_session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../../includes/invoice_signature_schema.php';
require_once __DIR__ . '/../../includes/secure_upload.php';
agri_sig_bootstrap_schema($conn);

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}
csrf_require('json');
requirePermission('settings.invoice_manage');

$action = $_POST['action'] ?? '';
$destDir = __DIR__ . '/../../assets/uploads/invoice_signatures';
$webPrefix = 'assets/uploads/invoice_signatures';

if ($action === 'save_agricart_assets') {
    $signatoryName = trim($_POST['signatory_name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');

    $sets = [];
    $types = '';
    $params = [];

    if (!empty($_FILES['signature']) && ($_FILES['signature']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $res = agri_secure_upload_image($_FILES['signature'], $destDir, $webPrefix, 2 * 1024 * 1024);
        if (!$res['ok']) { echo json_encode(['success' => false, 'error' => $res['error']]); exit; }
        $sets[] = 'signature_path = ?'; $types .= 's'; $params[] = $res['path'];
    }
    if (!empty($_FILES['stamp']) && ($_FILES['stamp']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $res = agri_secure_upload_image($_FILES['stamp'], $destDir, $webPrefix, 2 * 1024 * 1024);
        if (!$res['ok']) { echo json_encode(['success' => false, 'error' => $res['error']]); exit; }
        $sets[] = 'stamp_path = ?'; $types .= 's'; $params[] = $res['path'];
    }

    $sets[] = 'signatory_name = ?'; $types .= 's'; $params[] = $signatoryName;
    $sets[] = 'designation = ?'; $types .= 's'; $params[] = $designation;
    $sets[] = 'updated_by_admin_id = ?'; $types .= 'i'; $params[] = (int)($_SESSION['admin_member_id'] ?? 0);

    $sql = "UPDATE agricart_invoice_assets SET " . implode(', ', $sets) . " WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();

    if ($ok && function_exists('logAdminActivity')) {
        logAdminActivity('invoice_settings_update', 'settings', 1, null, null);
    }

    echo json_encode(['success' => $ok]);
    exit;
}

if ($action === 'remove_asset') {
    $which = $_POST['which'] ?? '';
    if (!in_array($which, ['signature', 'stamp'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid asset.']);
        exit;
    }
    $col = $which . '_path';
    $cur = $conn->query("SELECT $col FROM agricart_invoice_assets WHERE id = 1")->fetch_assoc();
    if ($cur && !empty($cur[$col]) && function_exists('agri_delete_uploaded_file')) {
        agri_delete_uploaded_file($cur[$col]);
    }
    $stmt = $conn->prepare("UPDATE agricart_invoice_assets SET $col = NULL WHERE id = 1");
    $ok = $stmt->execute();
    echo json_encode(['success' => $ok]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action.']);
