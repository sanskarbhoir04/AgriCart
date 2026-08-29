<?php
// =====================================================================
// admin/inventory_action.php — Stock adjustments for the Inventory
// Management module. Mirrors the security pattern of product_action.php
// / equipment_action.php exactly (session check, csrf_require, prepared
// statements, requirePermission, logAdminActivity) and additionally
// writes every change to inventory_stock_history via inv_log() so it
// shows up on the Stock History tab.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/inventory_schema.php';
include __DIR__ . '/../includes/agri_connect_functions.php';
header('Content-Type: application/json');
inventory_bootstrap_schema($conn);

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}
csrf_require('json');

$action = $_POST['action'] ?? '';

// ---------------------------------------------------------------------
// PRODUCT INVENTORY
// ---------------------------------------------------------------------
if (in_array($action, ['add_stock', 'reduce_stock', 'set_stock'], true)) {
    requirePermission('inventory.edit_stock');
    $id  = (int)($_POST['id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    if ($id <= 0 || $qty < 0) { $response['error'] = 'Invalid product or quantity.'; echo json_encode($response); exit; }

    $stmt = $conn->prepare("SELECT name, stock FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    if (!$product) { $response['error'] = 'Product not found.'; echo json_encode($response); exit; }

    $prevStock = (int)$product['stock'];
    if ($action === 'add_stock')      { $newStock = $prevStock + $qty; $logAction = 'stock_added'; }
    elseif ($action === 'reduce_stock') { $newStock = max(0, $prevStock - $qty); $logAction = 'stock_reduced'; }
    else                               { $newStock = $qty; $logAction = $qty >= $prevStock ? 'stock_added' : 'stock_reduced'; }

    $upd = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $upd->bind_param('ii', $newStock, $id);
    $response['success'] = $upd->execute();
    if ($response['success']) {
        inv_log($conn, 'product', $id, $product['name'], $logAction, $prevStock, $newStock, $remarks ?: null);
        logAdminActivity('inventory_stock_updated', 'products', $id, $prevStock, $newStock, 'Stock for "' . $product['name'] . '" changed from ' . $prevStock . ' to ' . $newStock);
        $response['new_stock'] = $newStock;
    } else {
        $response['error'] = 'Stock update failed.';
    }
    echo json_encode($response);
    exit;
}

if ($action === 'bulk_update_stock') {
    requirePermission('inventory.edit_stock');
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    $mode = $_POST['mode'] ?? 'set'; // add | reduce | set
    $qty  = (int)($_POST['qty'] ?? 0);
    if (empty($ids) || !in_array($mode, ['add', 'reduce', 'set'], true) || $qty < 0) {
        $response['error'] = 'Invalid bulk update request.';
        echo json_encode($response);
        exit;
    }
    $updated = 0;
    foreach ($ids as $id) {
        $stmt = $conn->prepare("SELECT name, stock FROM products WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        if (!$product) { continue; }
        $prevStock = (int)$product['stock'];
        if ($mode === 'add')         { $newStock = $prevStock + $qty; $logAction = 'stock_added'; }
        elseif ($mode === 'reduce')  { $newStock = max(0, $prevStock - $qty); $logAction = 'stock_reduced'; }
        else                          { $newStock = $qty; $logAction = $qty >= $prevStock ? 'stock_added' : 'stock_reduced'; }
        $upd = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $upd->bind_param('ii', $newStock, $id);
        if ($upd->execute()) {
            $updated++;
            inv_log($conn, 'product', $id, $product['name'], $logAction, $prevStock, $newStock, 'Bulk update');
        }
    }
    logAdminActivity('inventory_bulk_stock_updated', 'products', null, null, null, "Bulk stock update ($mode $qty) applied to $updated product(s)");
    $response['success'] = true;
    $response['updated'] = $updated;
    echo json_encode($response);
    exit;
}

if ($action === 'update_sku_threshold') {
    requirePermission('inventory.edit_stock');
    $id  = (int)($_POST['id'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $threshold = (int)($_POST['low_stock_threshold'] ?? 10);
    if ($id <= 0) { $response['error'] = 'Invalid product.'; echo json_encode($response); exit; }
    $upd = $conn->prepare("UPDATE products SET sku = ?, low_stock_threshold = ? WHERE id = ?");
    $sku = $sku !== '' ? $sku : null;
    $upd->bind_param('sii', $sku, $threshold, $id);
    $response['success'] = $upd->execute();
    if ($response['success']) {
        logAdminActivity('inventory_product_settings_updated', 'products', $id, null, ['sku' => $sku, 'low_stock_threshold' => $threshold], 'Updated SKU / low-stock threshold');
    } else {
        $response['error'] = 'Update failed.';
    }
    echo json_encode($response);
    exit;
}

// ---------------------------------------------------------------------
// EQUIPMENT INVENTORY
// ---------------------------------------------------------------------
if ($action === 'equipment_set_maintenance') {
    requirePermission('inventory.edit_stock');
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id <= 0 || !in_array($status, ['available', 'maintenance', 'out_of_service'], true)) {
        $response['error'] = 'Invalid equipment or status.';
        echo json_encode($response);
        exit;
    }
    $stmt = $conn->prepare("SELECT name, maintenance_status FROM equipment WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $eq = $stmt->get_result()->fetch_assoc();
    if (!$eq) { $response['error'] = 'Equipment not found.'; echo json_encode($response); exit; }

    $upd = $conn->prepare("UPDATE equipment SET maintenance_status = ? WHERE id = ?");
    $upd->bind_param('si', $status, $id);
    $response['success'] = $upd->execute();
    if ($response['success']) {
        $logAction = $status === 'maintenance' ? 'maintenance_started' : ($eq['maintenance_status'] === 'maintenance' ? 'maintenance_completed' : 'status_changed');
        inv_log($conn, 'equipment', $id, $eq['name'], $logAction, null, null, 'Status set to ' . $status);
        logAdminActivity('inventory_equipment_status_updated', 'equipment', $id, $eq['maintenance_status'], $status, 'Equipment "' . $eq['name'] . '" status changed to ' . $status);
    } else {
        $response['error'] = 'Update failed.';
    }
    echo json_encode($response);
    exit;
}

if ($action === 'equipment_set_value') {
    requirePermission('inventory.edit_stock');
    $id    = (int)($_POST['id'] ?? 0);
    $value = $_POST['equipment_value'] !== '' ? (float)($_POST['equipment_value'] ?? 0) : null;
    if ($id <= 0) { $response['error'] = 'Invalid equipment.'; echo json_encode($response); exit; }
    $upd = $conn->prepare("UPDATE equipment SET equipment_value = ? WHERE id = ?");
    $upd->bind_param('di', $value, $id);
    $response['success'] = $upd->execute();
    if ($response['success']) {
        logAdminActivity('inventory_equipment_value_updated', 'equipment', $id, null, $value, 'Updated equipment asset value');
    } else {
        $response['error'] = 'Update failed.';
    }
    echo json_encode($response);
    exit;
}

if ($action === 'equipment_set_availability') {
    requirePermission('inventory.edit_stock');
    $id  = (int)($_POST['id'] ?? 0);
    $avail = !empty($_POST['availability']) ? 1 : 0;
    if ($id <= 0) { $response['error'] = 'Invalid equipment.'; echo json_encode($response); exit; }
    $stmt = $conn->prepare("SELECT name FROM equipment WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $eq = $stmt->get_result()->fetch_assoc();
    if (!$eq) { $response['error'] = 'Equipment not found.'; echo json_encode($response); exit; }
    $upd = $conn->prepare("UPDATE equipment SET availability = ? WHERE id = ?");
    $upd->bind_param('ii', $avail, $id);
    $response['success'] = $upd->execute();
    if ($response['success']) {
        inv_log($conn, 'equipment', $id, $eq['name'], $avail ? 'status_changed' : 'status_changed', null, null, $avail ? 'Marked available' : 'Marked unavailable');
        logAdminActivity('inventory_equipment_availability_updated', 'equipment', $id, null, $avail, 'Equipment "' . $eq['name'] . '" availability set to ' . $avail);
    } else {
        $response['error'] = 'Update failed.';
    }
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
