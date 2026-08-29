<?php
// =====================================================================
// admin/product_action.php — Add / Update / Delete products in MySQL.
// Every query uses prepared statements. Requires an active admin session.
// =====================================================================
include __DIR__ . '/../includes/db.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/agri_connect_functions.php';
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

if ($action === 'delete') {
    requirePermission('products.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid product id.'; echo json_encode($response); exit; }
    $nStmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
    $nStmt->bind_param("i", $id);
    $nStmt->execute();
    $pName = $nStmt->get_result()->fetch_assoc()['name'] ?? ('#' . $id);
    $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Delete failed.';
    } else {
        logAdminActivity('product_deleted', 'products', $id, null, null, 'Deleted product "' . $pName . '"');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'restore') {
    requirePermission('products.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid product id.'; echo json_encode($response); exit; }
    $nStmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
    $nStmt->bind_param("i", $id);
    $nStmt->execute();
    $pName = $nStmt->get_result()->fetch_assoc()['name'] ?? ('#' . $id);
    $stmt = $conn->prepare("UPDATE products SET is_active = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Restore failed.';
    } else {
        logAdminActivity('product_restored', 'products', $id, null, null, 'Restored product "' . $pName . '"');
    }
    echo json_encode($response);
    exit;
}

if ($action === 'permanent_delete') {
    requirePermission('products.delete');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid product id.'; echo json_encode($response); exit; }

    $nStmt = $conn->prepare("SELECT name, is_active FROM products WHERE id = ?");
    $nStmt->bind_param("i", $id);
    $nStmt->execute();
    $prod = $nStmt->get_result()->fetch_assoc();
    if (!$prod) { $response['error'] = 'Product not found.'; echo json_encode($response); exit; }

    // Only ever allow permanently erasing a product that's already been
    // soft-deleted — permanent delete is a cleanup step for the "Deleted"
    // list, not a shortcut around the normal Delete/Restore flow.
    if (!empty($prod['is_active'])) {
        $response['error'] = 'This product is still active — delete it first, then permanently delete it from the Deleted list.';
        echo json_encode($response);
        exit;
    }

    // Never erase a product that has order history: order_items.product_id
    // feeds invoices, GST reports, seller settlements, etc. Removing the
    // product row would break those joins and rewrite accounting history.
    $ordStmt = $conn->prepare("SELECT COUNT(*) AS c FROM order_items WHERE product_id = ?");
    $ordStmt->bind_param("i", $id);
    $ordStmt->execute();
    $hasOrders = (int)($ordStmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
    if ($hasOrders) {
        $response['error'] = 'Can\'t permanently delete — this product has past orders linked to it (needed for invoices/reports). It will stay in the Deleted list.';
        echo json_encode($response);
        exit;
    }

    $pName = $prod['name'] ?? ('#' . $id);
    try {
        $conn->begin_transaction();
        $imgStmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
        $imgStmt->bind_param("i", $id);
        $imgStmt->execute(); // product_images may not exist on every install — ignore failure below

        $delStmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $delStmt->bind_param("i", $id);
        $response['success'] = $delStmt->execute();
        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        $response['error'] = 'Could not permanently delete this product — it may still be referenced elsewhere (e.g. a wishlist or cart). Try again later.';
        echo json_encode($response);
        exit;
    }

    if ($response['success']) {
        logAdminActivity('product_permanently_deleted', 'products', $id, null, null, 'Permanently deleted product "' . $pName . '"');
    } else {
        $response['error'] = 'Permanent delete failed.';
    }
    echo json_encode($response);
    exit;
}

if ($action === 'approve' || $action === 'reject') {
    requirePermission('products.approve');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { $response['error'] = 'Invalid product id.'; echo json_encode($response); exit; }
    $status = $action === 'approve' ? 'approved' : 'rejected';
    try {
        $stmt = $conn->prepare("UPDATE products SET approval_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $response['success'] = $stmt->execute();
        if (!$response['success']) $response['error'] = 'Update failed.';

        // Let the farmer know their listing was reviewed.
        if ($response['success']) {
            $pStmt = $conn->prepare("SELECT name, added_by_user_id FROM products WHERE id = ?");
            $pStmt->bind_param("i", $id);
            $pStmt->execute();
            $prod = $pStmt->get_result()->fetch_assoc();
            logAdminActivity('product_' . $status, 'products', $id, null, null, ucfirst($status) . ' product "' . ($prod['name'] ?? ('#' . $id)) . '".');
            if ($prod && !empty($prod['added_by_user_id'])) {
                if ($status === 'approved') {
                    agri_notify_user(
                        $conn, (int)$prod['added_by_user_id'],
                        'Product listing approved',
                        'Your product "' . $prod['name'] . '" has been approved and is now live on the Marketplace.',
                        'marketplace.php', 'market'
                    );
                } else {
                    agri_notify_user(
                        $conn, (int)$prod['added_by_user_id'],
                        'Product listing rejected',
                        'Your product "' . $prod['name'] . '" was not approved by our team. Please review and resubmit.',
                        'sell_product.php', 'market'
                    );
                }
            }
        }
    } catch (\Throwable $e) {
        $response['error'] = "approval_status column missing — run setup/farmer_selling_upgrade.sql first.";
    }
    echo json_encode($response);
    exit;
}

if ($action === 'save') {
    $id            = (int)($_POST['id'] ?? 0);
    requirePermission($id > 0 ? 'products.edit' : 'products.create');
    $name          = trim($_POST['name'] ?? '');
    $name_mr       = trim($_POST['name_mr'] ?? '');
    $name_hi       = trim($_POST['name_hi'] ?? '');
    $category      = trim($_POST['category'] ?? 'seeds');
    $price         = (float)($_POST['price'] ?? 0);
    $discountPrice = $_POST['discount_price'] !== '' ? (float)$_POST['discount_price'] : null;
    $unit          = trim($_POST['unit'] ?? '1 pc');
    $stock         = (int)($_POST['stock'] ?? 0);
    $image         = trim($_POST['image'] ?? '');
    $farmerName    = trim($_POST['farmer_name'] ?? 'AgriCart Logistics');
    $deliveryEst   = trim($_POST['delivery_estimate'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $brand         = trim($_POST['brand'] ?? '');
    $condition     = (($_POST['product_condition'] ?? 'new') === 'used') ? 'used' : 'new';
    $deliveryAvail = !empty($_POST['delivery_available']) ? 1 : 0;
    $sellerEmail   = trim($_POST['seller_email'] ?? '');
    $sellerVillage = trim($_POST['seller_village'] ?? '');
    $sellerDistrict= trim($_POST['seller_district'] ?? '');
    $sellerAddress = trim($_POST['seller_address'] ?? '');

    if ($name === '' || $price <= 0) {
        $response['error'] = 'Product name and a valid price are required.';
        echo json_encode($response);
        exit;
    }

    // Try saving with the full farmer-listing/translation column set first;
    // if any of those columns don't exist yet on this database, fall back
    // progressively (same defensive pattern this file already used for
    // delivery_estimate) so admin editing never breaks. Each attempt
    // executes at most once — no statement is ever run twice.
    $ok = false;
    $extendedColsMissing = false;
    try {
        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE products SET name=?, name_mr=?, name_hi=?, category=?, price=?, discount_price=?, unit=?, stock=?, image=?, farmer_name=?, delivery_estimate=?, description=?, brand=?, product_condition=?, delivery_available=?, seller_email=?, seller_village=?, seller_district=?, seller_address=? WHERE id=?"
            );
            $stmt->bind_param(
                "sssdssissssssssssii",
                $name, $name_mr, $name_hi, $category, $price, $discountPrice, $unit, $stock, $image, $farmerName, $deliveryEst, $description, $brand, $condition, $deliveryAvail, $sellerEmail, $sellerVillage, $sellerDistrict, $sellerAddress, $id
            );
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO products (name, name_mr, name_hi, category, price, discount_price, unit, stock, image, farmer_name, delivery_estimate, description, brand, product_condition, delivery_available, seller_email, seller_village, seller_district, seller_address, source, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'store', 1)"
            );
            $stmt->bind_param(
                "sssdssissssssssssi",
                $name, $name_mr, $name_hi, $category, $price, $discountPrice, $unit, $stock, $image, $farmerName, $deliveryEst, $description, $brand, $condition, $deliveryAvail, $sellerEmail, $sellerVillage, $sellerDistrict, $sellerAddress
            );
        }
        $ok = $stmt->execute();
        if (!$ok) { $extendedColsMissing = true; }
    } catch (\Throwable $eCol) {
        $extendedColsMissing = true;
    }

    if ($extendedColsMissing) {
        // Fall back to the delivery_estimate-only column set.
        $deliveryColMissing = false;
        try {
            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE products SET name=?, name_mr=?, category=?, price=?, discount_price=?, unit=?, stock=?, image=?, farmer_name=?, delivery_estimate=?, description=? WHERE id=?"
                );
                $stmt->bind_param(
                    "sssdssissssi",
                    $name, $name_mr, $category, $price, $discountPrice, $unit, $stock, $image, $farmerName, $deliveryEst, $description, $id
                );
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO products (name, name_mr, category, price, discount_price, unit, stock, image, farmer_name, delivery_estimate, description, source, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'store', 1)"
                );
                $stmt->bind_param(
                    "sssdssissss",
                    $name, $name_mr, $category, $price, $discountPrice, $unit, $stock, $image, $farmerName, $deliveryEst, $description
                );
            }
            $ok = $stmt->execute();
            if (!$ok) { $deliveryColMissing = true; }
        } catch (\Throwable $eCol2) {
            $deliveryColMissing = true;
        }

        if ($deliveryColMissing) {
            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE products SET name=?, name_mr=?, category=?, price=?, discount_price=?, unit=?, stock=?, image=?, farmer_name=?, description=? WHERE id=?"
                );
                $stmt->bind_param(
                    "sssdssisssi",
                    $name, $name_mr, $category, $price, $discountPrice, $unit, $stock, $image, $farmerName, $description, $id
                );
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO products (name, name_mr, category, price, discount_price, unit, stock, image, farmer_name, description, source, is_active) VALUES (?,?,?,?,?,?,?,?,?,?, 'store', 1)"
                );
                $stmt->bind_param(
                    "sssdssisss",
                    $name, $name_mr, $category, $price, $discountPrice, $unit, $stock, $image, $farmerName, $description
                );
            }
            $ok = $stmt->execute();
        }
    }

    if ($ok) {
        $response['success'] = true;
        $newId = $id > 0 ? $id : $conn->insert_id;
        $response['id'] = $newId;
        $summary = ['name' => $name, 'price' => $price, 'stock' => $stock];
        if ($id > 0) {
            logAdminActivity('product_updated', 'products', $newId, null, $summary, 'Updated product "' . $name . '"');
        } else {
            logAdminActivity('product_created', 'products', $newId, null, $summary, 'Added product "' . $name . '"');
        }
        if ($extendedColsMissing) {
            $response['note'] = "Some fields (Hindi name, brand, condition, seller details) weren't saved — run setup/sell_product_upgrade.sql on this database, then save again.";
        }
    } else {
        error_log('admin/product_action.php: save failed: ' . $conn->error);
        $response['error'] = 'Save failed. Please try again, and contact support if the problem continues.';
    }
    echo json_encode($response);
    exit;
}

if ($action === 'get_images') {
    requirePermission('products.view');
    $id = (int)($_POST['id'] ?? 0);
    $images = [];
    if ($id > 0) {
        try {
            $imgStmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
            $imgStmt->bind_param("i", $id);
            $imgStmt->execute();
            $res = $imgStmt->get_result();
            while ($row = $res->fetch_assoc()) { $images[] = $row['image_path']; }
        } catch (\Throwable $e) {
            // product_images table not created yet — return empty list,
            // the main products.image column still has the cover photo.
        }
    }
    $response['success'] = true;
    $response['images'] = $images;
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
