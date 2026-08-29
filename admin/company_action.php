<?php
// =====================================================================
// admin/company_action.php — Verify / activate-deactivate / edit a
// company, and fetch the product list for a company's admin detail
// view. Operates on the existing `sellers` table (see
// includes/companies_schema.php for why there is no separate
// "companies" table). Every query is a prepared statement. Requires an
// active admin session + the relevant companies.* permission.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/companies_schema.php';
require_once __DIR__ . '/../includes/gstin_schema.php';
require_once __DIR__ . '/../includes/gst_sync.php';
require_once __DIR__ . '/../includes/secure_upload.php';
companies_bootstrap_schema($conn);
gstin_bootstrap_schema($conn);
gst_sync_bootstrap_schema($conn);

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_require')) {
    csrf_require('json');
}

// ---- View the products belonging to one company (used by the "Products
// by this Company" panel in the admin detail modal). Read-only, so any
// admin who can see the Companies list at all can open it. ----
if ($action === 'products') {
    requirePermission('companies.view');
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid company id.'; echo json_encode($response); exit; }

    $nStmt = $conn->prepare("SELECT name FROM sellers WHERE id = ?");
    $nStmt->bind_param('i', $id);
    $nStmt->execute();
    $company = $nStmt->get_result()->fetch_assoc();
    if (!$company) { $response['error'] = 'Company not found.'; echo json_encode($response); exit; }

    $products = [];
    $match = cmp_company_match($conn, $id, $company['name']);
    $pStmt = $conn->prepare(
        "SELECT id, name, category, price, discount_price, stock, image, is_active, approval_status
           FROM products WHERE {$match['sql']} ORDER BY id DESC"
    );
    $pStmt->bind_param($match['types'], ...$match['params']);
    $pStmt->execute();
    $res = $pStmt->get_result();
    while ($row = $res->fetch_assoc()) { $products[] = $row; }

    $response['success']  = true;
    $response['products'] = $products;
    echo json_encode($response);
    exit;
}

if ($action === 'toggle_verified') {
    requirePermission('companies.approve');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid company id.'; echo json_encode($response); exit; }

    $nStmt = $conn->prepare("SELECT name, verified FROM sellers WHERE id = ?");
    $nStmt->bind_param('i', $id);
    $nStmt->execute();
    $company = $nStmt->get_result()->fetch_assoc();
    if (!$company) { $response['error'] = 'Company not found.'; echo json_encode($response); exit; }

    $newVerified = $company['verified'] ? 0 : 1;
    $adminUserId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;
    $sets = ["verified = ?"];
    if (gstsync_col_exists($conn, 'sellers', 'gst_verified')) { $sets[] = "gst_verified = " . (int)$newVerified; }
    if (gstsync_col_exists($conn, 'sellers', 'business_verified')) { $sets[] = "business_verified = " . (int)$newVerified; }
    if (gstsync_col_exists($conn, 'sellers', 'gst_verified_status')) { $sets[] = "gst_verified_status = '" . ($newVerified ? 'verified' : 'not_verified') . "'"; }
    if (gstsync_col_exists($conn, 'sellers', 'gst_verified_at')) { $sets[] = "gst_verified_at = " . ($newVerified ? 'NOW()' : 'NULL'); }
    if (gstsync_col_exists($conn, 'sellers', 'gst_verified_by')) { $sets[] = "gst_verified_by = " . ($newVerified ? (int)$adminUserId : 'NULL'); }
    $stmt = $conn->prepare("UPDATE sellers SET " . implode(', ', $sets) . " WHERE id = ?");
    $stmt->bind_param('ii', $newVerified, $id);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Update failed.';
    } else {
        $response['verified'] = $newVerified;
        logAdminActivity($newVerified ? 'company_verified' : 'company_unverified', 'sellers', $id, null, null,
            ($newVerified ? 'Verified' : 'Unverified') . ' company "' . $company['name'] . '"');
        // Keep the linked seller LOGIN account's own GST profile
        // (what the Seller Dashboard actually reads) in sync with this
        // company-level verify/unverify toggle.
        gst_sync_push_company_verified_to_seller($conn, $id, (bool)$newVerified, $adminUserId);
    }
    echo json_encode($response);
    exit;
}

if ($action === 'toggle_status') {
    // Deactivate = soft delete (same mechanism the Sellers tab already
    // uses for "block"), Activate = restore. Nothing else reads
    // `sellers` differently based on this, so existing product/order
    // history for a deactivated company is untouched.
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid company id.'; echo json_encode($response); exit; }

    $nStmt = $conn->prepare("SELECT name, deleted_at FROM sellers WHERE id = ?");
    $nStmt->bind_param('i', $id);
    $nStmt->execute();
    $company = $nStmt->get_result()->fetch_assoc();
    if (!$company) { $response['error'] = 'Company not found.'; echo json_encode($response); exit; }

    if (empty($company['deleted_at'])) {
        requirePermission('companies.block');
        $response['success'] = agri_soft_delete($conn, 'sellers', $id);
        $newStatus = 'inactive';
        $logKey = 'company_deactivated';
    } else {
        requirePermission('companies.approve');
        $response['success'] = agri_restore($conn, 'sellers', $id);
        $newStatus = 'active';
        $logKey = 'company_activated';
    }

    if (!$response['success']) {
        $response['error'] = 'Status update failed.';
    } else {
        $response['status'] = $newStatus;
        logAdminActivity($logKey, 'sellers', $id, null, null, ucfirst($newStatus) . ' company "' . $company['name'] . '"');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    requirePermission('companies.approve');

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $gstin       = strtoupper(trim($_POST['gstin'] ?? ''));
    $logo        = trim($_POST['logo'] ?? '');
    $mobile      = trim($_POST['mobile'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $village     = trim($_POST['village'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $verified    = (int)($_POST['verified'] ?? 0);
    $notes       = trim($_POST['notes'] ?? '');

    if ($name === '') {
        $response['error'] = 'Company name is required.';
        echo json_encode($response);
        exit;
    }
    if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/', $gstin)) {
        $response['error'] = 'GST Number should be 15 characters (letters & numbers).';
        echo json_encode($response);
        exit;
    }

    // ---- Digital Signature / Official Stamp for this company ----
    // Only meaningful once the company row already exists (id > 0):
    // uploads for a brand-new "Add Company" submit are ignored so we
    // never have to juggle a variable-length INSERT — save the company
    // first, then re-open Edit to attach its signature/stamp.
    $assetSets = [];
    if ($id > 0 && cmp_col_exists($conn, 'sellers', 'signature_path')) {
        $aStmt = $conn->prepare("SELECT signature_path, stamp_path FROM sellers WHERE id = ?");
        $aStmt->bind_param('i', $id);
        $aStmt->execute();
        $oldAssetRow = $aStmt->get_result()->fetch_assoc();

        $sigDestDir   = __DIR__ . '/../assets/uploads/company_signatures';
        $sigWebPrefix = 'assets/uploads/company_signatures';

        foreach (['signature' => 'signature_path', 'stamp' => 'stamp_path'] as $field => $col) {
            if (!empty($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $res = agri_secure_upload_image($_FILES[$field], $sigDestDir, $sigWebPrefix, 2 * 1024 * 1024);
                if (!$res['ok']) { $response['error'] = $res['error']; echo json_encode($response); exit; }
                if (!empty($oldAssetRow[$col]) && function_exists('agri_delete_uploaded_file')) {
                    agri_delete_uploaded_file($oldAssetRow[$col]);
                }
                $assetSets[] = "$col = '" . $conn->real_escape_string($res['path']) . "'";
            } elseif (($_POST['remove_' . $field] ?? '') === '1') {
                if (!empty($oldAssetRow[$col]) && function_exists('agri_delete_uploaded_file')) {
                    agri_delete_uploaded_file($oldAssetRow[$col]);
                }
                $assetSets[] = "$col = NULL";
            }
        }
    }

    $oldSummary = null;
    if ($id > 0) {
        $oStmt = $conn->prepare("SELECT name, verified FROM sellers WHERE id = ?");
        $oStmt->bind_param('i', $id);
        $oStmt->execute();
        $oldRow = $oStmt->get_result()->fetch_assoc();
        if ($oldRow) { $oldSummary = ['name' => $oldRow['name'], 'verified' => (bool)$oldRow['verified']]; }

        $stmt = $conn->prepare(
            "UPDATE sellers SET name=?, description=?, category=?, gstin=?, logo=?, mobile=?, email=?, village=?, city=?, verified=?, notes=?"
            . ($assetSets ? (', ' . implode(', ', $assetSets)) : '')
            . " WHERE id=?"
        );
        $stmt->bind_param('sssssssssisi', $name, $description, $category, $gstin, $logo, $mobile, $email, $village, $city, $verified, $notes, $id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO sellers (name, description, category, gstin, logo, mobile, email, village, city, verified, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('sssssssssis', $name, $description, $category, $gstin, $logo, $mobile, $email, $village, $city, $verified, $notes);
    }

    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        error_log('admin/company_action.php: save failed: ' . $conn->error);
        $response['error'] = 'Save failed. Please try again.';
    } else {
        $newId = $id > 0 ? $id : $conn->insert_id;
        $response['id'] = $newId;
        $newSummary = ['name' => $name, 'verified' => (bool)$verified];
        if ($id > 0) {
            logAdminActivity('company_updated', 'sellers', $newId, $oldSummary, $newSummary, 'Updated company "' . $name . '"');
        } else {
            logAdminActivity('company_created', 'sellers', $newId, null, $newSummary, 'Added company "' . $name . '"');
        }
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
