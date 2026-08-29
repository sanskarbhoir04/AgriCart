<?php
// =====================================================================
// admin/company_product_action.php — Company-scoped product actions.
//
// Security model (see spec section 10): never trust company_id/product_id
// from the request on their own. Every action here:
//   1. Requires an authenticated admin session (admin_guard.php)
//   2. Requires the companies.approve permission
//   3. Verifies the company row exists
//   4. Verifies the product row exists
//   5. Verifies the product actually belongs to that company via
//      cmp_product_belongs_to_company() (checks products.seller_id,
//      the real FK — not just a name string) BEFORE any UPDATE runs
// If step 5 fails, the request is rejected — no partial trust, no
// "trust the ID because the button only shows same-company products in
// the UI". A crafted request with a mismatched company_id/product_id
// pair is refused server-side regardless of what the UI would allow.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/companies_schema.php';
companies_bootstrap_schema($conn);

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

requirePermission('companies.approve');

$action    = $_POST['action'] ?? '';
$companyId = (int)($_POST['company_id'] ?? 0);
$productId = (int)($_POST['product_id'] ?? 0);

// ---- Steps 3 & 4: both rows must actually exist ----
$cStmt = $conn->prepare("SELECT id, name FROM sellers WHERE id = ?");
$cStmt->bind_param('i', $companyId);
$cStmt->execute();
$company = $cStmt->get_result()->fetch_assoc();
if (!$company) { $response['error'] = 'Company not found.'; echo json_encode($response); exit; }

$pStmt = $conn->prepare("SELECT id, name, stock, is_active FROM products WHERE id = ?");
$pStmt->bind_param('i', $productId);
$pStmt->execute();
$product = $pStmt->get_result()->fetch_assoc();
if (!$product) { $response['error'] = 'Product not found.'; echo json_encode($response); exit; }

// ---- Step 5: the ownership check that actually matters ----
if (!cmp_product_belongs_to_company($conn, $productId, $companyId)) {
    http_response_code(403);
    $response['error'] = 'This product does not belong to the selected company.';
    echo json_encode($response);
    exit;
}

if ($action === 'update_stock') {
    $stock = (int)($_POST['stock'] ?? -1);
    if ($stock < 0) { $response['error'] = 'Invalid stock value.'; echo json_encode($response); exit; }

    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmt->bind_param('ii', $stock, $productId);
    $response['success'] = $stmt->execute();
    if ($response['success']) {
        logAdminActivity('company_product_stock_updated', 'products', $productId,
            ['stock' => (int)$product['stock']], ['stock' => $stock],
            'Updated stock for "' . $product['name'] . '" (company: ' . $company['name'] . ')');
        if (function_exists('inv_log')) {
            inv_log($conn, 'product', $productId, $product['name'], 'manual_update', (int)$product['stock'], $stock, 'Updated via Companies → Manage Products');
        }
        $response['stock'] = $stock;
    } else {
        $response['error'] = 'Update failed.';
    }
    echo json_encode($response);
    exit;
}

if ($action === 'toggle_status') {
    $newStatus = $product['is_active'] ? 0 : 1;
    $stmt = $conn->prepare("UPDATE products SET is_active = ? WHERE id = ?");
    $stmt->bind_param('ii', $newStatus, $productId);
    $response['success'] = $stmt->execute();
    if ($response['success']) {
        logAdminActivity($newStatus ? 'company_product_activated' : 'company_product_deactivated', 'products', $productId, null, null,
            ($newStatus ? 'Activated' : 'Deactivated') . ' "' . $product['name'] . '" (company: ' . $company['name'] . ')');
        $response['is_active'] = $newStatus;
    } else {
        $response['error'] = 'Update failed.';
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
