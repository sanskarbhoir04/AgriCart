<?php
// =====================================================================
// admin/coupon_action.php — Add / Update / Delete / Generate coupons.
// Every query uses prepared statements. Requires an active admin session.
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

$action = $_POST['action'] ?? '';

if ($action === 'generate') {
    requirePermission('coupons.create');
    // Produce a short, random, human-friendly code that doesn't already exist.
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I to avoid confusion
    for ($attempt = 0; $attempt < 15; $attempt++) {
        $code = 'AGRI' . substr(str_shuffle($chars), 0, 5);
        $stmt = $conn->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $response['success'] = true;
            $response['code'] = $code;
            echo json_encode($response);
            exit;
        }
    }
    $response['error'] = 'Could not generate a unique code, try again.';
    echo json_encode($response);
    exit;
}

if ($action === 'delete') {
    requirePermission('coupons.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid coupon id.'; echo json_encode($response); exit; }
    $codeStmt = $conn->prepare("SELECT code FROM coupons WHERE id = ?");
    $codeStmt->bind_param("i", $id);
    $codeStmt->execute();
    $couponCode = $codeStmt->get_result()->fetch_assoc()['code'] ?? ('#' . $id);
    $response['success'] = agri_soft_delete($conn, 'coupons', $id);
    if (!$response['success']) {
        $response['error'] = 'Delete failed.';
    } else {
        logAdminActivity('coupon_deleted', 'coupons', $id, ['code' => $couponCode], null, 'Deleted coupon "' . $couponCode . '"');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'restore') {
    requirePermission('coupons.edit');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid id.'; echo json_encode($response); exit; }
    $codeStmt = $conn->prepare("SELECT code FROM coupons WHERE id = ?");
    $codeStmt->bind_param("i", $id);
    $codeStmt->execute();
    $couponCode = $codeStmt->get_result()->fetch_assoc()['code'] ?? ('#' . $id);
    $response['success'] = agri_restore($conn, 'coupons', $id);
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('coupon_restored', 'coupons', $id, null, ['code' => $couponCode], 'Restored coupon "' . $couponCode . '"');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'save') {
    $id              = (int)($_POST['id'] ?? 0);
    requirePermission($id > 0 ? 'coupons.edit' : 'coupons.create');
    $code            = strtoupper(trim($_POST['code'] ?? ''));
    $discountType    = ($_POST['discount_type'] ?? 'percent') === 'flat' ? 'flat' : 'percent';
    $discountValue   = (float)($_POST['discount_value'] ?? 0);
    $minOrderAmount  = (float)($_POST['min_order_amount'] ?? 0);
    $maxDiscountRaw  = trim($_POST['max_discount_amount'] ?? '');
    $maxDiscount     = $maxDiscountRaw === '' ? null : (float)$maxDiscountRaw;
    $usageLimitRaw   = trim($_POST['usage_limit'] ?? '');
    $usageLimit      = $usageLimitRaw === '' ? null : (int)$usageLimitRaw;
    $expiryRaw       = trim($_POST['expiry_date'] ?? '');
    $expiryDate      = $expiryRaw === '' ? null : $expiryRaw;
    $active          = (int)($_POST['active'] ?? 1);

    if ($code === '' || $discountValue <= 0) {
        $response['error'] = 'Coupon code and a valid discount value are required.';
        echo json_encode($response);
        exit;
    }
    if ($discountType === 'percent' && $discountValue > 100) {
        $response['error'] = 'Percent discount cannot be more than 100.';
        echo json_encode($response);
        exit;
    }

    $newSummary = [
        'code' => $code,
        'discount' => $discountType === 'percent' ? ($discountValue . '%') : ('₹' . $discountValue),
        'active' => (bool)$active,
    ];

    if ($id > 0) {
        // Grab the previous values first so the activity log can show a
        // real old -> new diff instead of just "coupon updated".
        $oldStmt = $conn->prepare("SELECT code, discount_type, discount_value, active FROM coupons WHERE id = ?");
        $oldStmt->bind_param("i", $id);
        $oldStmt->execute();
        $oldRow = $oldStmt->get_result()->fetch_assoc();
        $oldSummary = $oldRow ? [
            'code' => $oldRow['code'],
            'discount' => $oldRow['discount_type'] === 'percent' ? ($oldRow['discount_value'] . '%') : ('₹' . $oldRow['discount_value']),
            'active' => (bool)$oldRow['active'],
        ] : null;

        $stmt = $conn->prepare("UPDATE coupons SET code=?, discount_type=?, discount_value=?, min_order_amount=?, max_discount_amount=?, usage_limit=?, expiry_date=?, active=? WHERE id=?");
        $stmt->bind_param("ssdddisii", $code, $discountType, $discountValue, $minOrderAmount, $maxDiscount, $usageLimit, $expiryDate, $active, $id);
    } else {
        $oldSummary = null;
        $stmt = $conn->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_discount_amount, usage_limit, expiry_date, active) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssdddisi", $code, $discountType, $discountValue, $minOrderAmount, $maxDiscount, $usageLimit, $expiryDate, $active);
    }

    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        if ($conn->errno === 1146) {
            $response['error'] = "The 'coupons' table doesn't exist yet. Run add_sellers_coupons.sql in phpMyAdmin first.";
        } elseif ($conn->errno === 1062) {
            $response['error'] = 'That coupon code already exists — try a different one.';
        } else {
            error_log('admin/coupon_action.php: save failed: ' . $conn->error);
            $response['error'] = 'Save failed. Please try again.';
        }
    } else {
        $newId = $id > 0 ? $id : $conn->insert_id;
        $response['id'] = $newId;
        if ($id > 0) {
            logAdminActivity('coupon_updated', 'coupons', $newId, $oldSummary, $newSummary, 'Updated coupon "' . $code . '"');
        } else {
            logAdminActivity('coupon_created', 'coupons', $newId, null, $newSummary, 'Created coupon "' . $code . '"');
        }
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
