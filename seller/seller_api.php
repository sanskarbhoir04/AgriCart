<?php
// =====================================================================
// seller/seller_api.php
// JSON API powering seller/dashboard.php. Every action is scoped to the
// logged-in user (= the seller) via $_SESSION['user_id']; no action
// ever accepts a seller_id from the client. Prepared statements only.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json; charset=utf-8');
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_translate.php';
include __DIR__ . '/../includes/seller_functions.php';
include __DIR__ . '/../includes/order_sync.php';
include __DIR__ . '/../includes/invoice_signature_schema.php';
include __DIR__ . '/../includes/secure_upload.php';
require_once __DIR__ . '/../includes/gstin_schema.php';
require_once __DIR__ . '/../includes/gstin_lib.php';
require_once __DIR__ . '/../includes/gst_sync.php';
require_once __DIR__ . '/../includes/gst_verify_requests.php';

$sellerId = agri_seller_require_login();
$C = agri_seller_columns($conn);
$action = $_REQUEST['action'] ?? '';
agri_sig_bootstrap_schema($conn);
gstin_bootstrap_schema($conn);
gst_sync_bootstrap_schema($conn);
gst_verify_requests_bootstrap_schema($conn);

function agri_json($data) { echo json_encode($data); exit; }
function agri_csrf_ok() {
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
const AGRI_WRITE_ACTIONS = [
    'product_update_stock','product_delete','product_restore','product_edit_save','order_update_status',
    'review_reply_save','notification_mark_read','profile_save','payout_request',
    'equipment_save','equipment_delete','equipment_activate','booking_update_status',
    'signature_save','gst_save','gst_verify_request',
];
if (in_array($action, AGRI_WRITE_ACTIONS, true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    agri_json(['success' => false, 'error' => 'This action requires POST.']);
}
if (in_array($action, AGRI_WRITE_ACTIONS, true) && !agri_csrf_ok()) {
    agri_json(['success' => false, 'error' => 'Session expired. Please reload the page.']);
}

/**
 * Sweeps every order_item belonging to this seller that hasn't been
 * "registered" yet (see agri_seller_register_sale) and registers it —
 * this is what makes stock/earnings/notifications correct even when
 * place_order.php hasn't been wired to call the hook directly.
 * Cheap: only touches rows missing a seller_earnings entry.
 */
function agri_sync_seller_orders($conn, $sellerId, $C) {
    try {
        $qtyCol = $C['items_qty']; $priceCol = $C['items_price'];
        $sql = "SELECT oi.id, oi.product_id, oi.$qtyCol AS qty, oi.$priceCol AS price
                FROM order_items oi
                LEFT JOIN seller_earnings se ON se.order_item_id = oi.id
                JOIN products p ON p.id = oi.product_id
                WHERE p.added_by_user_id = ? AND se.id IS NULL
                  AND (oi.item_status IS NULL OR oi.item_status NOT IN ('cancelled','returned','refunded'))
                LIMIT 200";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            agri_seller_register_sale($conn, (int)$row['id'], (int)$row['product_id'], $sellerId, (int)$row['qty'], (float)$row['price']);
        }
    } catch (\Throwable $e) { /* best-effort sync; dashboard still works off whatever is already registered */ }
}
agri_sync_seller_orders($conn, $sellerId, $C);

try {
switch ($action) {

// -----------------------------------------------------------------
case 'summary': {
    $out = [];
    $validList = agri_seller_valid_revenue_sql_list(); // 'new_order','confirmed','packed','shipped','delivered'

    // Active products only (is_active = 1) — a deactivated/deleted product
    // never counts toward total products, stock, low-stock, or out-of-stock.
    // low_stock_limit is per-product (falls back to 5 if the column isn't
    // there yet, i.e. before setup/seller_dashboard_phase_a_upgrade.sql runs).
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) c, COALESCE(SUM(stock),0) stock_sum,
                SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) out_of_stock,
                SUM(CASE WHEN stock > 0 AND stock <= low_stock_limit THEN 1 ELSE 0 END) low_stock,
                COALESCE(SUM(sold_quantity),0) units_sold,
                COALESCE(AVG(NULLIF(rating_avg,0)),0) avg_rating
            FROM products WHERE added_by_user_id = ? AND is_active = 1");
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
    } catch (\Throwable $e) {
        // low_stock_limit column not present yet on this install — fall back to the old fixed threshold of 5.
        $stmt = $conn->prepare("SELECT COUNT(*) c, COALESCE(SUM(stock),0) stock_sum,
                SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) out_of_stock,
                SUM(CASE WHEN stock > 0 AND stock < 5 THEN 1 ELSE 0 END) low_stock,
                COALESCE(SUM(sold_quantity),0) units_sold,
                COALESCE(AVG(NULLIF(rating_avg,0)),0) avg_rating
            FROM products WHERE added_by_user_id = ? AND is_active = 1");
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
    }

    $out['total_products']    = (int)$p['c'];
    $out['total_stock']       = (int)$p['stock_sum'];
    $out['out_of_stock']      = (int)$p['out_of_stock'];
    $out['low_stock_products'] = (int)$p['low_stock'];
    $out['units_sold']        = (int)$p['units_sold'];
    $out['avg_rating']        = round((float)$p['avg_rating'], 2);

    // Deactivated/deleted products — shown under "Inactive Listings", never in any active count above.
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM products WHERE added_by_user_id = ? AND is_active = 0");
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $out['inactive_products'] = (int)$stmt->get_result()->fetch_assoc()['c'];

    // Orders — COUNT(DISTINCT order_id) everywhere, so an order that
    // contains several of this seller's products is still counted once,
    // for total/pending/completed/cancelled/returned/refunded alike.
    $stmt = $conn->prepare("SELECT
            COUNT(DISTINCT oi.order_id) total_orders,
            COUNT(DISTINCT CASE WHEN oi.item_status IN ('new_order','confirmed','packed','shipped') THEN oi.order_id END) pending_orders,
            COUNT(DISTINCT CASE WHEN oi.item_status = 'delivered' THEN oi.order_id END) completed_orders,
            COUNT(DISTINCT CASE WHEN oi.item_status = 'cancelled' THEN oi.order_id END) cancelled_orders,
            COUNT(DISTINCT CASE WHEN oi.item_status = 'returned' THEN oi.order_id END) returned_orders,
            COUNT(DISTINCT CASE WHEN oi.item_status = 'refunded' THEN oi.order_id END) refunded_orders
        FROM order_items oi JOIN products p ON p.id = oi.product_id
        WHERE p.added_by_user_id = ?");
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();
    $out['total_orders']     = (int)($o['total_orders'] ?? 0);
    $out['pending_orders']   = (int)($o['pending_orders'] ?? 0);
    $out['completed_orders'] = (int)($o['completed_orders'] ?? 0);
    $out['cancelled_orders'] = (int)($o['cancelled_orders'] ?? 0);
    $out['returned_orders']  = (int)($o['returned_orders'] ?? 0);
    $out['refunded_orders']  = (int)($o['refunded_orders'] ?? 0);

    // Revenue — gross sales / platform charges / net revenue only ever
    // include valid (non cancelled/returned/refunded) order items.
    // Returned and refunded amounts are reported as their own totals,
    // never blended into revenue.
    $qtyCol = $C['items_qty']; $priceCol = $C['items_price'];
    $stmt = $conn->prepare("SELECT
            COALESCE(SUM(CASE WHEN oi.item_status IN ($validList) THEN oi.$qtyCol * oi.$priceCol ELSE 0 END),0) gross,
            COALESCE(SUM(CASE WHEN oi.item_status IN ($validList) THEN oi.platform_charge_amount ELSE 0 END),0) charges,
            COALESCE(SUM(CASE WHEN oi.item_status IN ($validList) THEN oi.seller_net_amount ELSE 0 END),0) net,
            COALESCE(SUM(CASE WHEN oi.item_status = 'returned' THEN oi.$qtyCol * oi.$priceCol ELSE 0 END),0) returned_value,
            COALESCE(SUM(CASE WHEN oi.item_status = 'refunded' THEN oi.$qtyCol * oi.$priceCol ELSE 0 END),0) refund_value
        FROM order_items oi JOIN products p ON p.id = oi.product_id
        WHERE p.added_by_user_id = ?");
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $rev = $stmt->get_result()->fetch_assoc();
    $out['gross_sales']      = round((float)$rev['gross'], 2);
    $out['platform_charges'] = round((float)$rev['charges'], 2);
    $out['net_revenue']      = round((float)$rev['net'], 2);
    $out['returned_value']   = round((float)$rev['returned_value'], 2);
    $out['refund_value']     = round((float)$rev['refund_value'], 2);

    agri_json(['success' => true, 'data' => $out]);
}

// -----------------------------------------------------------------
case 'products_list': {
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Active listings only — a deactivated/deleted product (is_active = 0)
    // never appears here, in stock totals, or anywhere else "active" is implied.
    $where = "WHERE added_by_user_id = ? AND is_active = 1";
    $types = "i"; $params = [$sellerId];
    if ($search !== '') {
        $where .= " AND (name LIKE ? OR name_mr LIKE ? OR category LIKE ?)";
        $like = "%$search%";
        $types .= "sss"; $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) c FROM products $where");
    agri_bind_params($countStmt, $types, $params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['c'];

    try {
        $sql = "SELECT id, name, name_mr, name_hi, category, price, stock, low_stock_limit, sold_quantity, image, approval_status, commission_percent, rating_avg, rating_count, views_count
                FROM products $where ORDER BY id DESC LIMIT $perPage OFFSET $offset";
        $stmt = $conn->prepare($sql);
        agri_bind_params($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
    } catch (\Throwable $e) {
        // name_hi / low_stock_limit column not present on this install yet — fall back without them.
        $sql = "SELECT id, name, name_mr, category, price, stock, sold_quantity, image, approval_status, commission_percent, rating_avg, rating_count, views_count
                FROM products $where ORDER BY id DESC LIMIT $perPage OFFSET $offset";
        $stmt = $conn->prepare($sql);
        agri_bind_params($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
    }
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $remaining = (int)$r['stock'];
        $sold = (int)$r['sold_quantity'];
        $limit = isset($r['low_stock_limit']) ? (int)$r['low_stock_limit'] : 5;
        $r['low_stock_limit'] = $limit;
        $r['original_stock'] = $remaining + $sold;
        $r['remaining_stock'] = $remaining;
        $r['stock_status'] = $remaining <= 0 ? 'out_of_stock' : ($remaining <= $limit ? 'low_stock' : 'in_stock');
        $rows[] = $r;
    }
    agri_json(['success' => true, 'data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

// -----------------------------------------------------------------
// Inactive Listings — deactivated/deleted products (is_active = 0).
// Nothing here counts toward any active total; "Restore" is the only
// way back, via product_restore below.
case 'products_list_inactive': {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    $countStmt = $conn->prepare("SELECT COUNT(*) c FROM products WHERE added_by_user_id = ? AND is_active = 0");
    $countStmt->bind_param("i", $sellerId);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['c'];

    $stmt = $conn->prepare("SELECT id, name, name_mr, category, price, stock, sold_quantity, image, approval_status
            FROM products WHERE added_by_user_id = ? AND is_active = 0
            ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    agri_json(['success' => true, 'data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

// -----------------------------------------------------------------
case 'product_restore': {
    $productId = (int)($_POST['product_id'] ?? 0);
    $product = agri_seller_owns_product($conn, $sellerId, $productId); // ownership check works regardless of is_active
    if (!$product) agri_json(['success' => false, 'error' => 'Product not found.']);

    $stmt = $conn->prepare("UPDATE products SET is_active = 1 WHERE id = ? AND added_by_user_id = ?");
    $stmt->bind_param("ii", $productId, $sellerId);
    $ok = $stmt->execute();
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
case 'product_update_stock': {
    $productId = (int)($_POST['product_id'] ?? 0);
    $mode = ($_POST['mode'] ?? 'set') === 'add' ? 'add' : 'set';
    $value = (int)($_POST['value'] ?? 0);

    $product = agri_seller_owns_product($conn, $sellerId, $productId);
    if (!$product) agri_json(['success' => false, 'error' => 'Product not found.']);

    $newStock = $mode === 'add' ? max(0, (int)$product['stock'] + $value) : max(0, $value);
    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ? AND added_by_user_id = ?");
    $stmt->bind_param("iii", $newStock, $productId, $sellerId);
    $ok = $stmt->execute();

    $lowLimit = isset($product['low_stock_limit']) ? (int)$product['low_stock_limit'] : 5;
    if ($ok && (int)$product['stock'] > $lowLimit && $newStock <= $lowLimit && $newStock > 0) {
        agri_seller_notify($conn, $sellerId, 'low_stock', 'Stock Running Low', '"' . $product['name'] . '" has only ' . $newStock . ' units left (alert level: ' . $lowLimit . ').', 'seller/dashboard.php#products');
    }
    if ($ok && (int)$product['stock'] > 0 && $newStock <= 0) {
        agri_seller_notify($conn, $sellerId, 'out_of_stock', 'Product Out of Stock', '"' . $product['name'] . '" is now out of stock.', 'seller/dashboard.php#products');
    }
    agri_json(['success' => $ok, 'new_stock' => $newStock]);
}

// -----------------------------------------------------------------
case 'product_delete': {
    // Always a soft delete (is_active = 0) — the product keeps existing so
    // past orders/invoices/reviews still resolve correctly. It moves to
    // "Inactive Listings" and can be brought back with product_restore.
    // Never permanently removed from the database from here.
    $productId = (int)($_POST['product_id'] ?? 0);
    $product = agri_seller_owns_product($conn, $sellerId, $productId);
    if (!$product) agri_json(['success' => false, 'error' => 'Product not found.']);

    $stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ? AND added_by_user_id = ?");
    $stmt->bind_param("ii", $productId, $sellerId);
    $ok = $stmt->execute();
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
case 'product_get': {
    $productId = (int)($_GET['product_id'] ?? 0);
    $product = agri_seller_owns_product($conn, $sellerId, $productId);
    if (!$product) agri_json(['success' => false, 'error' => 'Product not found.']);
    agri_json(['success' => true, 'data' => $product]);
}

// -----------------------------------------------------------------
case 'product_edit_save': {
    $productId = (int)($_POST['product_id'] ?? 0);
    $product = agri_seller_owns_product($conn, $sellerId, $productId);
    if (!$product) agri_json(['success' => false, 'error' => 'Product not found.']);

    $rawName = trim($_POST['name'] ?? $product['name']);
    $price = (float)($_POST['price'] ?? $product['price']);
    $category = trim($_POST['category'] ?? $product['category']);
    $description = trim($_POST['description'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $condition = (($_POST['product_condition'] ?? 'new') === 'used') ? 'used' : 'new';
    $delivery = !empty($_POST['delivery_available']) ? 1 : 0;
    $lowStockLimit = isset($_POST['low_stock_limit']) ? max(0, (int)$_POST['low_stock_limit']) : null;

    if ($rawName === '' || $price <= 0) agri_json(['success' => false, 'error' => 'Name and a valid price are required.']);

    $nameMr = $product['name_mr']; $nameHi = $product['name_hi'] ?? '';
    if ($rawName !== $product['name']) {
        try {
            $lang = agri_detect_language($rawName);
            $t = agri_translate_product_name($rawName, $lang);
            $nameMr = $t['mr']; $nameHi = $t['hi'];
        } catch (\Throwable $e) { $nameMr = $rawName; $nameHi = $rawName; }
    }

    $ok = false;
    try {
        if ($lowStockLimit !== null) {
            $stmt = $conn->prepare(
                "UPDATE products SET name=?, name_mr=?, name_hi=?, price=?, category=?, description=?, brand=?, product_condition=?, delivery_available=?, low_stock_limit=? WHERE id=? AND added_by_user_id=?"
            );
            $stmt->bind_param("sssdssssiiii", $rawName, $nameMr, $nameHi, $price, $category, $description, $brand, $condition, $delivery, $lowStockLimit, $productId, $sellerId);
        } else {
            $stmt = $conn->prepare(
                "UPDATE products SET name=?, name_mr=?, name_hi=?, price=?, category=?, description=?, brand=?, product_condition=?, delivery_available=? WHERE id=? AND added_by_user_id=?"
            );
            $stmt->bind_param("sssdssssiii", $rawName, $nameMr, $nameHi, $price, $category, $description, $brand, $condition, $delivery, $productId, $sellerId);
        }
        $ok = $stmt->execute();
    } catch (\Throwable $e) {
        $stmt = $conn->prepare("UPDATE products SET name=?, name_mr=?, price=?, category=?, description=? WHERE id=? AND added_by_user_id=?");
        $stmt->bind_param("ssdssii", $rawName, $nameMr, $price, $category, $description, $productId, $sellerId);
        $ok = $stmt->execute();
    }
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
// EQUIPMENT RENTAL — a seller can list their own machinery/equipment
// for rent (tractors, harvesters, etc). Every row is scoped to
// owner_user_id = the logged-in seller, mirroring how products are
// scoped to added_by_user_id. New listings go in as approval_status
// 'pending' — same admin review flow as farmer-submitted products.
// -----------------------------------------------------------------
case 'equipment_list': {
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    // view=active (default): only availability=1 listings, in the main
    // "My Equipment" list. view=inactive: only deactivated listings, shown
    // under "Inactive Listings" with an "Activate" action.
    $view = (($_GET['view'] ?? 'active') === 'inactive') ? 'inactive' : 'active';

    $where = "WHERE e.owner_user_id = ? AND e.availability = " . ($view === 'inactive' ? '0' : '1');
    $types = "i"; $params = [$sellerId];
    if ($search !== '') {
        $where .= " AND (e.name LIKE ? OR e.type LIKE ?)";
        $like = "%$search%"; $types .= "ss"; $params[] = $like; $params[] = $like;
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) c FROM equipment e $where");
    agri_bind_params($countStmt, $types, $params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['c'];

    $sql = "SELECT e.id, e.name, e.name_mr, e.name_hi, e.type, e.image, e.rent_per_day, e.hp, e.brand, e.model,
                e.equipment_condition, e.security_deposit, e.operator_available, e.fuel_included,
                e.availability, e.approval_status, c.name AS city_name,
                (SELECT COUNT(*) FROM equipment_bookings b WHERE b.equipment_id = e.id AND b.status IN ('pending','confirmed','on_the_way')) AS active_bookings
            FROM equipment e LEFT JOIN cities c ON c.id = e.city_id
            $where ORDER BY e.id DESC LIMIT $perPage OFFSET $offset";
    $stmt = $conn->prepare($sql);
    agri_bind_params($stmt, $types, $params);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        // Display status: Deactivated > Booked > Unavailable (pending/rejected approval) > Available.
        // Deactivated equipment is never bookable, regardless of active_bookings history, which stays visible.
        if (!(int)$r['availability']) {
            $r['status'] = 'deactivated';
        } elseif ((int)$r['active_bookings'] > 0) {
            $r['status'] = 'booked';
        } elseif (($r['approval_status'] ?? 'approved') !== 'approved') {
            $r['status'] = 'unavailable';
        } else {
            $r['status'] = 'available';
        }
        $rows[] = $r;
    }
    agri_json(['success' => true, 'data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

// -----------------------------------------------------------------
case 'equipment_get': {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM equipment WHERE id = ? AND owner_user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $id, $sellerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) agri_json(['success' => false, 'error' => 'Equipment not found.']);
    agri_json(['success' => true, 'data' => $row]);
}

// -----------------------------------------------------------------
case 'equipment_save': {
    $id            = (int)($_POST['id'] ?? 0);
    $name          = trim($_POST['name'] ?? '');
    $type          = trim($_POST['type'] ?? 'other');
    $rentPerDay    = (float)($_POST['rent_per_day'] ?? 0);
    $hp            = trim($_POST['hp'] ?? '');
    $brand         = trim($_POST['brand'] ?? '');
    $model         = trim($_POST['model'] ?? '');
    $condition     = in_array(($_POST['equipment_condition'] ?? 'good'), ['excellent','good','average'], true) ? $_POST['equipment_condition'] : 'good';
    $securityDeposit    = (float)($_POST['security_deposit'] ?? 0);
    $operatorAvailable  = !empty($_POST['operator_available']) ? 1 : 0;
    $fuelIncluded       = !empty($_POST['fuel_included']) ? 1 : 0;
    $description   = trim($_POST['description'] ?? '');
    $cityName      = trim($_POST['city'] ?? '');
    $availability  = !empty($_POST['availability']) ? 1 : 0;

    if ($id > 0) {
        // Owner can only be editing their own listing.
        $existing = $conn->prepare("SELECT id FROM equipment WHERE id = ? AND owner_user_id = ? LIMIT 1");
        $existing->bind_param("ii", $id, $sellerId);
        $existing->execute();
        if (!$existing->get_result()->fetch_assoc()) agri_json(['success' => false, 'error' => 'Equipment not found.']);
    }

    if ($name === '' || $rentPerDay <= 0) {
        agri_json(['success' => false, 'error' => 'Equipment name and a valid rent/day are required.']);
    }

    // Translate the name the same way products do; fall back to the
    // original text if the translation helper/API isn't available.
    $nameMr = $name; $nameHi = $name;
    try {
        $lang = agri_detect_language($name);
        $t = agri_translate_product_name($name, $lang);
        $nameMr = $t['mr']; $nameHi = $t['hi'];
    } catch (\Throwable $e) { /* keep plain name as fallback */ }

    // Resolve city name to a cities.id, creating the city if needed.
    $cityId = null;
    if ($cityName !== '') {
        $cs = $conn->prepare("SELECT id FROM cities WHERE name = ? LIMIT 1");
        $cs->bind_param("s", $cityName);
        $cs->execute();
        $cityRow = $cs->get_result()->fetch_assoc();
        if ($cityRow) {
            $cityId = (int)$cityRow['id'];
        } else {
            $ci = $conn->prepare("INSERT INTO cities (name) VALUES (?)");
            $ci->bind_param("s", $cityName);
            if ($ci->execute()) { $cityId = $conn->insert_id; }
        }
    }

    // Owner name/phone always come from the seller's own account —
    // never client-supplied, so a seller can't list equipment under
    // someone else's name.
    $ownerName = ''; $ownerPhone = '';
    $uStmt = $conn->prepare("SELECT {$C['users_name']} nm, {$C['users_mobile']} mb FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param("i", $sellerId);
    $uStmt->execute();
    if ($uRow = $uStmt->get_result()->fetch_assoc()) { $ownerName = $uRow['nm'] ?? ''; $ownerPhone = $uRow['mb'] ?? ''; }

    $ok = false;
    try {
        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE equipment SET name=?, name_mr=?, name_hi=?, type=?, rent_per_day=?, hp=?, brand=?, model=?,
                    equipment_condition=?, security_deposit=?, operator_available=?, fuel_included=?, description=?,
                    owner_name=?, owner_phone=?, city_id=?, availability=? WHERE id=? AND owner_user_id=?"
            );
            $stmt->bind_param(
                "ssssdssssdiisssiiii",
                $name, $nameMr, $nameHi, $type, $rentPerDay, $hp, $brand, $model,
                $condition, $securityDeposit, $operatorAvailable, $fuelIncluded, $description,
                $ownerName, $ownerPhone, $cityId, $availability, $id, $sellerId
            );
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO equipment (name, name_mr, name_hi, type, rent_per_day, hp, brand, model,
                    equipment_condition, security_deposit, operator_available, fuel_included, description,
                    owner_name, owner_phone, city_id, availability, owner_user_id, approval_status, owner_verified)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending', 0)"
            );
            $stmt->bind_param(
                "ssssdssssdiisssiii",
                $name, $nameMr, $nameHi, $type, $rentPerDay, $hp, $brand, $model,
                $condition, $securityDeposit, $operatorAvailable, $fuelIncluded, $description,
                $ownerName, $ownerPhone, $cityId, $availability, $sellerId
            );
        }
        $ok = $stmt->execute();
    } catch (\Throwable $e) {
        agri_json(['success' => false, 'error' => 'Save failed — this database may be missing the equipment rental columns (run setup/list_equipment_upgrade.sql).']);
    }

    if ($ok) {
        agri_json(['success' => true, 'id' => $id > 0 ? $id : $conn->insert_id]);
    }
    agri_json(['success' => false, 'error' => 'Save failed.']);
}

// -----------------------------------------------------------------
case 'equipment_delete': {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT id FROM equipment WHERE id = ? AND owner_user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $id, $sellerId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) agri_json(['success' => false, 'error' => 'Equipment not found.']);

    // Deactivate only (availability = 0) — never a permanent delete. Keeps
    // booking history intact instead of orphaning past bookings, and the
    // listing simply moves to "Inactive Equipment" until activated again.
    $upd = $conn->prepare("UPDATE equipment SET availability = 0 WHERE id = ? AND owner_user_id = ?");
    $upd->bind_param("ii", $id, $sellerId);
    $ok = $upd->execute();
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
case 'equipment_activate': {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT id FROM equipment WHERE id = ? AND owner_user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $id, $sellerId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) agri_json(['success' => false, 'error' => 'Equipment not found.']);

    $upd = $conn->prepare("UPDATE equipment SET availability = 1 WHERE id = ? AND owner_user_id = ?");
    $upd->bind_param("ii", $id, $sellerId);
    $ok = $upd->execute();
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
case 'rental_bookings_list': {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10; $offset = ($page - 1) * $perPage;

    $where = "WHERE e.owner_user_id = ?"; $types = "i"; $params = [$sellerId];
    if ($status !== '') { $where .= " AND b.status = ?"; $types .= "s"; $params[] = $status; }
    if ($dateFrom !== '') { $where .= " AND b.from_date >= ?"; $types .= "s"; $params[] = $dateFrom; }
    if ($dateTo !== '') { $where .= " AND b.to_date <= ?"; $types .= "s"; $params[] = $dateTo; }
    if ($search !== '') {
        $where .= " AND (b.booking_number LIKE ? OR e.name LIKE ? OR b.contact_name LIKE ?)";
        $like = "%$search%"; $types .= "sss"; $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $baseFrom = "FROM equipment_bookings b JOIN equipment e ON e.id = b.equipment_id $where";

    $countStmt = $conn->prepare("SELECT COUNT(*) c $baseFrom");
    agri_bind_params($countStmt, $types, $params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['c'];

    $sql = "SELECT b.id, b.booking_number, b.status, b.payment_status, b.from_date, b.to_date, b.total_hours,
                b.total_amount, b.contact_name, b.contact_mobile, e.name AS equipment_name, e.name_mr AS equipment_name_mr,
                e.name_hi AS equipment_name_hi, e.image AS equipment_image
            $baseFrom ORDER BY b.id DESC LIMIT $perPage OFFSET $offset";
    $stmt = $conn->prepare($sql);
    agri_bind_params($stmt, $types, $params);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    agri_json(['success' => true, 'data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

// -----------------------------------------------------------------
case 'booking_update_status': {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $allowed = ['pending','confirmed','on_the_way','completed','cancelled'];
    if (!in_array($status, $allowed, true)) agri_json(['success' => false, 'error' => 'Invalid status.']);

    // Ownership check: the booking's equipment must belong to this seller.
    $chk = $conn->prepare("SELECT b.id FROM equipment_bookings b JOIN equipment e ON e.id = b.equipment_id WHERE b.id = ? AND e.owner_user_id = ? LIMIT 1");
    $chk->bind_param("ii", $bookingId, $sellerId);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) agri_json(['success' => false, 'error' => 'Booking not found.']);

    $upd = $conn->prepare("UPDATE equipment_bookings SET status = ? WHERE id = ?");
    $upd->bind_param("si", $status, $bookingId);
    $ok = $upd->execute();

    if ($ok && in_array($status, ['completed'], true)) {
        try {
            $payUpd = $conn->prepare("UPDATE equipment_bookings SET payment_status = 'paid' WHERE id = ? AND payment_status = 'cod'");
            $payUpd->bind_param("i", $bookingId);
            $payUpd->execute();
        } catch (\Throwable $e) {}
    }
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
case 'analytics': {
    $range = $_GET['range'] ?? '7d';
    $tz = new DateTimeZone('Asia/Kolkata');
    $now = new DateTime('now', $tz);
    $start = clone $now; $end = clone $now;

    switch ($range) {
        case 'today': break;
        case '30d': $start->modify('-29 days'); break;
        case 'month': $start->modify('first day of this month'); break;
        case 'custom':
            $start = DateTime::createFromFormat('Y-m-d', $_GET['start'] ?? '', $tz) ?: $start->modify('-6 days');
            $end   = DateTime::createFromFormat('Y-m-d', $_GET['end'] ?? '', $tz) ?: $end;
            if ($start > $end) { $tmp = $start; $start = $end; $end = $tmp; } // never let start be after end
            $maxEnd = clone $start; $maxEnd->modify('+366 days');
            if ($end > $maxEnd) { $end = $maxEnd; } // cap the range so the day-fill loop below can't run away
            break;
        case '7d':
        default: $start->modify('-6 days'); break;
    }
    $startStr = $start->format('Y-m-d'); $endStr = $end->format('Y-m-d');

    $itemsDate = $C['items_created'] ?: $C['orders_created'];
    $joinOrders = $C['items_created'] ? "" : "JOIN orders o ON o.id = oi.order_id";
    $dateExpr = $C['items_created'] ? "oi.{$C['items_created']}" : "o.{$C['orders_created']}";

    $qtyCol = $C['items_qty']; $priceCol = $C['items_price'];

    $validList = agri_seller_valid_revenue_sql_list();

    // Chart series across the selected range — cancelled/returned/refunded excluded, same rule everywhere else.
    $sql = "SELECT DATE($dateExpr) d, COALESCE(SUM(oi.$qtyCol),0) units,
                COALESCE(SUM(oi.$qtyCol * oi.$priceCol),0) revenue
            FROM order_items oi JOIN products p ON p.id = oi.product_id $joinOrders
            WHERE p.added_by_user_id = ? AND oi.item_status IN ($validList)
              AND DATE($dateExpr) BETWEEN ? AND ?
            GROUP BY DATE($dateExpr) ORDER BY d ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $sellerId, $startStr, $endStr);
    $stmt->execute();
    $byDate = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $byDate[$r['d']] = $r; }

    // Fill every day in the range (even ones with zero sales) so the chart
    // always shows the full selected timeline instead of a single sparse
    // day stretching to fill the whole plot width.
    $series = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $d = $cursor->format('Y-m-d');
        $series[] = ['d' => $d, 'units' => (int)($byDate[$d]['units'] ?? 0), 'revenue' => (float)($byDate[$d]['revenue'] ?? 0)];
        $cursor->modify('+1 day');
    }

    // Fixed-period rollup tiles (always today / 7d / 30d, independent of the filter above).
    function agri_period_units_revenue($conn, $sellerId, $days, $dateExpr, $joinOrders, $qtyCol, $priceCol) {
        $validList = agri_seller_valid_revenue_sql_list();
        $sql = "SELECT COALESCE(SUM(oi.$qtyCol),0) units, COALESCE(SUM(oi.$qtyCol*oi.$priceCol),0) revenue
                FROM order_items oi JOIN products p ON p.id = oi.product_id $joinOrders
                WHERE p.added_by_user_id = ? AND oi.item_status IN ($validList)
                  AND $dateExpr >= (NOW() - INTERVAL ? DAY)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $sellerId, $days);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    $today = agri_period_units_revenue($conn, $sellerId, 0, $dateExpr, $joinOrders, $qtyCol, $priceCol);
    $week  = agri_period_units_revenue($conn, $sellerId, 6, $dateExpr, $joinOrders, $qtyCol, $priceCol);
    $month = agri_period_units_revenue($conn, $sellerId, 29, $dateExpr, $joinOrders, $qtyCol, $priceCol);

    $stmt = $conn->prepare("SELECT COALESCE(SUM(platform_charge),0) charges, COALESCE(SUM(net_amount),0) net
        FROM seller_earnings se JOIN order_items oi ON oi.id = se.order_item_id $joinOrders
        WHERE se.seller_id = ? AND DATE($dateExpr) BETWEEN ? AND ?");
    $stmt->bind_param("iss", $sellerId, $startStr, $endStr);
    $stmt->execute();
    $charges = $stmt->get_result()->fetch_assoc();

    try {
        $stmt = $conn->prepare("SELECT p.id, p.name, p.name_mr, p.name_hi, SUM(oi.$qtyCol) qty
            FROM order_items oi JOIN products p ON p.id = oi.product_id
            WHERE p.added_by_user_id = ? AND p.is_active = 1 AND oi.item_status IN ($validList)
            GROUP BY p.id ORDER BY qty DESC LIMIT 1");
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $bestSelling = $stmt->get_result()->fetch_assoc();
    } catch (\Throwable $e) {
        $stmt = $conn->prepare("SELECT p.id, p.name, p.name_mr, SUM(oi.$qtyCol) qty
            FROM order_items oi JOIN products p ON p.id = oi.product_id
            WHERE p.added_by_user_id = ? AND p.is_active = 1 AND oi.item_status IN ($validList)
            GROUP BY p.id ORDER BY qty DESC LIMIT 1");
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $bestSelling = $stmt->get_result()->fetch_assoc();
    }

    try {
        $stmt = $conn->prepare("SELECT id, name, name_mr, name_hi, views_count FROM products WHERE added_by_user_id = ? AND is_active = 1 ORDER BY views_count DESC LIMIT 1");
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $mostViewed = $stmt->get_result()->fetch_assoc();
    } catch (\Throwable $e) {
        $stmt = $conn->prepare("SELECT id, name, name_mr, views_count FROM products WHERE added_by_user_id = ? AND is_active = 1 ORDER BY views_count DESC LIMIT 1");
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $mostViewed = $stmt->get_result()->fetch_assoc();
    }

    agri_json(['success' => true, 'data' => [
        'series' => $series,
        'daily_sales' => (int)$today['units'], 'weekly_sales' => (int)$week['units'], 'monthly_sales' => (int)$month['units'],
        'monthly_revenue' => round((float)$month['revenue'], 2),
        'platform_charges' => round((float)$charges['charges'], 2), 'net_earnings' => round((float)$charges['net'], 2),
        'best_selling_product' => $bestSelling, 'most_viewed_product' => $mostViewed,
        'range' => ['start' => $startStr, 'end' => $endStr],
    ]]);
}

// -----------------------------------------------------------------
case 'orders_list': {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10; $offset = ($page - 1) * $perPage;

    $buyerCol = $C['orders_buyer']; $createdCol = $C['orders_created'];
    $qtyCol = $C['items_qty']; $priceCol = $C['items_price'];
    $nameCol = $C['users_name']; $mobileCol = $C['users_mobile'];

    $where = "WHERE p.added_by_user_id = ?"; $types = "i"; $params = [$sellerId];
    if ($status !== '') { $where .= " AND oi.item_status = ?"; $types .= "s"; $params[] = $status; }
    if ($dateFrom !== '') { $where .= " AND DATE(o.$createdCol) >= ?"; $types .= "s"; $params[] = $dateFrom; }
    if ($dateTo !== '') { $where .= " AND DATE(o.$createdCol) <= ?"; $types .= "s"; $params[] = $dateTo; }
    if ($search !== '') {
        $where .= " AND (oi.id = ? OR o.id = ? OR p.name LIKE ? OR u.$nameCol LIKE ?)";
        $types .= "iiss"; $params[] = (int)$search; $params[] = (int)$search; $params[] = "%$search%"; $params[] = "%$search%";
    }

    $baseFrom = "FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        LEFT JOIN users u ON u.id = o.$buyerCol
        $where";

    $countStmt = $conn->prepare("SELECT COUNT(*) c $baseFrom");
    agri_bind_params($countStmt, $types, $params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['c'];

    $paymentSelect = $C['orders_payment'] ? "o.{$C['orders_payment']} payment_status" : "'paid' payment_status";
    $sqlBase = "SELECT oi.id item_id, o.id order_id, oi.item_status, oi.$qtyCol qty, oi.$priceCol price,
                oi.platform_charge_amount, oi.seller_net_amount, o.$createdCol order_date,
                p.name product_name, p.name_mr product_name_mr, %s p.image product_image,
                u.$nameCol buyer_name" . ($C['users_avatar'] ? ", u.{$C['users_avatar']} buyer_avatar" : ", NULL buyer_avatar") . ",
                $paymentSelect
            $baseFrom
            ORDER BY o.$createdCol DESC LIMIT $perPage OFFSET $offset";
    try {
        $stmt = $conn->prepare(sprintf($sqlBase, "p.name_hi product_name_hi,"));
        agri_bind_params($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
    } catch (\Throwable $e) {
        // name_hi column not present on this install — fall back without it.
        $stmt = $conn->prepare(sprintf($sqlBase, ""));
        agri_bind_params($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
    }
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['total_amount'] = round((float)$r['qty'] * (float)$r['price'], 2);
        $rows[] = $r;
    }
    agri_json(['success' => true, 'data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

// -----------------------------------------------------------------
case 'order_update_status': {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $reason = trim($_POST['reason'] ?? '') ?: null;
    $allowed = ['new_order','confirmed','packed','shipped','delivered','cancelled','returned','refunded'];
    if (!in_array($newStatus, $allowed, true)) agri_json(['success' => false, 'error' => 'Invalid status.']);

    // Ownership check happens right inside the query (JOIN products ON
    // added_by_user_id = $sellerId) — a request for another seller's
    // order item simply finds no row, so it fails identically to an
    // invalid item id (never leaks whether the item exists at all).
    $stmt = $conn->prepare("SELECT oi.id, oi.order_id, oi.product_id, oi.item_status, oi.$C[items_qty] qty
        FROM order_items oi JOIN products p ON p.id = oi.product_id
        WHERE oi.id = ? AND p.added_by_user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $itemId, $sellerId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    if (!$item) agri_json(['success' => false, 'error' => 'Order item not found or you do not have permission to manage it.']);

    // Server-side transition validation — the same map the UI uses to
    // decide which options to show, checked again here so a request built
    // by hand (or a stale page) can never force an invalid jump, e.g.
    // Delivered -> Packed, Shipped -> Accepted, or Cancelled -> Delivered.
    if (!agri_seller_can_transition_status($item['item_status'], $newStatus)) {
        agri_json(['success' => false, 'error' => 'Cannot change status from "' . $item['item_status'] . '" to "' . $newStatus . '".']);
    }
    if ($item['item_status'] === $newStatus) {
        agri_json(['success' => true]); // no-op re-save, nothing to do
    }

    $previousStatus = $item['item_status'];
    $upd = $conn->prepare("UPDATE order_items SET item_status = ? WHERE id = ?");
    $upd->bind_param("si", $newStatus, $itemId);
    $ok = $upd->execute();

    if ($ok) {
        // ---- Single source of truth: log this item's history, then let
        // the shared recalculation function derive/write the one order-level
        // status that Admin and the Buyer both read — never write
        // orders.order_status directly from here. ----
        $sellerName = null;
        try {
            $nameCol = $C['users_name'];
            $nStmt = $conn->prepare("SELECT $nameCol AS n FROM users WHERE id = ? LIMIT 1");
            $nStmt->bind_param('i', $sellerId);
            $nStmt->execute();
            $sellerName = $nStmt->get_result()->fetch_assoc()['n'] ?? null;
        } catch (\Throwable $e) { /* best-effort */ }

        agri_order_log_history($conn, (int)$item['order_id'], $itemId, $previousStatus, $newStatus, $sellerId, 'seller', $sellerName, $reason);
        $aggregateStatus = agri_order_recalculate($conn, (int)$item['order_id'], 'seller', $sellerId, $sellerName, $reason);
    }

    if ($ok && $newStatus === 'delivered') {
        agri_seller_make_earning_available($conn, $itemId);
        agri_seller_notify($conn, $sellerId, 'payment_received', 'Payment Received', 'Your earnings for order item #' . $itemId . ' are now available for payout.', 'seller/dashboard.php#earnings');
    }
    // Reverse stock/earnings exactly once — only on the cancelled/returned
    // transition itself. 'refunded' always follows an already-reversed
    // 'returned' item, so it must never trigger a second reversal.
    if ($ok && in_array($newStatus, ['cancelled', 'returned'], true)) {
        agri_seller_reverse_sale($conn, $itemId, (int)$item['product_id'], (int)$item['qty']);
        agri_seller_notify($conn, $sellerId, 'order_cancelled', 'Order Cancelled', 'Order item #' . $itemId . ' was ' . $newStatus . '.', 'seller/dashboard.php#orders');
    }
    if ($ok && $newStatus === 'refunded') {
        agri_seller_notify($conn, $sellerId, 'order_cancelled', 'Order Refunded', 'Order item #' . $itemId . ' was refunded.', 'seller/dashboard.php#orders');
    }
    agri_json(['success' => $ok, 'order_status' => $ok ? ($aggregateStatus ?? agri_order_to_order_level($newStatus)) : null]);
}

// -----------------------------------------------------------------
// order_get_history — status timeline for the Order Details view.
// Scoped so a seller only ever sees their own item-level entries plus
// whole-order (admin/system) entries — never another seller's actions.
case 'order_get_history': {
    $itemId = (int)($_GET['item_id'] ?? 0);
    $stmt = $conn->prepare("SELECT oi.id, oi.order_id FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.id = ? AND p.added_by_user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $itemId, $sellerId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    if (!$item) agri_json(['success' => false, 'error' => 'Order item not found or you do not have permission to view it.']);

    $history = agri_order_get_status_history($conn, (int)$item['order_id'], $itemId);
    $history = array_map(static function ($h) {
        $h['new_status_label'] = agri_order_status_label($h['new_status']);
        return $h;
    }, $history);
    agri_json(['success' => true, 'data' => $history]);
}

// -----------------------------------------------------------------
case 'order_invoice': {
    $itemId = (int)($_GET['item_id'] ?? 0);
    $buyerCol = $C['orders_buyer']; $createdCol = $C['orders_created'];
    $nameCol = $C['users_name']; $mobileCol = $C['users_mobile'];
    $qtyCol = $C['items_qty']; $priceCol = $C['items_price'];
    $addrCol = $C['orders_address'];

    $sql = "SELECT oi.id item_id, o.id order_id, oi.item_status, oi.$qtyCol qty, oi.$priceCol price,
                oi.platform_charge_amount, oi.seller_net_amount, o.$createdCol order_date,
                p.name product_name,
                u.$nameCol buyer_name, u.$mobileCol buyer_mobile" .
                ($addrCol ? ", o.$addrCol delivery_address" : ", NULL delivery_address") . "
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            JOIN products p ON p.id = oi.product_id
            LEFT JOIN users u ON u.id = o.$buyerCol
            WHERE oi.id = ? AND p.added_by_user_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $itemId, $sellerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) agri_json(['success' => false, 'error' => 'Not found.']);
    $row['total_amount'] = round((float)$row['qty'] * (float)$row['price'], 2);
    agri_json(['success' => true, 'data' => $row]);
}

// -----------------------------------------------------------------
// Seller Invoices — list / search / filter for the "Invoices" section
// of the Seller Dashboard (separate from the buyer-facing invoice).
case 'seller_invoices_list': {
    // Lazy backfill: any (seller, order) combo that already has
    // registered order items but was created before this feature (or
    // slipped past the sync-on-load hook) gets its invoice generated now.
    try {
        $backfill = $conn->prepare(
            "SELECT DISTINCT oi.order_id FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             LEFT JOIN seller_invoices si ON si.seller_id = ? AND si.order_id = oi.order_id
             WHERE p.added_by_user_id = ? AND si.id IS NULL
               AND oi.item_status IN (" . agri_seller_valid_revenue_sql_list() . ")
             LIMIT 200"
        );
        $backfill->bind_param("ii", $sellerId, $sellerId);
        $backfill->execute();
        $res = $backfill->get_result();
        while ($row = $res->fetch_assoc()) {
            agri_seller_ensure_invoice($conn, $sellerId, (int)$row['order_id']);
        }
    } catch (\Throwable $e) { /* best-effort */ }

    $search = trim($_GET['search'] ?? '');
    $filterPayment = trim($_GET['payment_status'] ?? '');
    $filterSettlement = trim($_GET['settlement_status'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10;

    $payStatusCol = $C['orders_payment'];

    $where = "WHERE si.seller_id = ?"; $types = "i"; $params = [$sellerId];
    if ($search !== '') {
        $where .= " AND (si.invoice_number LIKE ? OR si.order_id = ?)";
        $types .= "si"; $params[] = "%$search%"; $params[] = (int)$search;
    }
    if ($dateFrom !== '') { $where .= " AND DATE(si.generated_at) >= ?"; $types .= "s"; $params[] = $dateFrom; }
    if ($dateTo !== '') { $where .= " AND DATE(si.generated_at) <= ?"; $types .= "s"; $params[] = $dateTo; }

    $paymentSelect = $payStatusCol ? "o.$payStatusCol payment_status_raw" : "'paid' payment_status_raw";
    $orderStatusCol = agri_pick_col($conn, 'orders', ['order_status', 'status']);
    $orderStatusSelect = $orderStatusCol ? "o.$orderStatusCol order_status" : "NULL order_status";

    $sql = "SELECT si.id, si.invoice_number, si.order_id, si.gross_amount, si.tax_amount,
                si.platform_charge_percent, si.platform_charge_amount, si.net_amount, si.generated_at,
                $paymentSelect, $orderStatusSelect
            FROM seller_invoices si
            JOIN orders o ON o.id = si.order_id
            $where
            ORDER BY si.generated_at DESC";
    $stmt = $conn->prepare($sql);
    agri_bind_params($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rawStatus = strtolower(trim((string)($r['payment_status_raw'] ?? '')));
        $isDelivered = strtolower((string)($r['order_status'] ?? '')) === 'delivered';
        $paidWords = ['paid', 'success', 'successful', 'completed', 'complete', 'captured', 'settled', 'confirmed', 'done', 'received'];
        if ($rawStatus === '') {
            $resolved = $isDelivered ? 'paid' : 'pending';
        } else {
            $resolved = in_array($rawStatus, $paidWords, true) ? 'paid' : $rawStatus;
        }
        $r['payment_status'] = ucwords(str_replace('_', ' ', $resolved));
        unset($r['payment_status_raw']);

        $r['settlement_status'] = agri_seller_invoice_settlement_status($conn, $sellerId, (int)$r['order_id']);

        if ($filterPayment !== '' && strtolower($resolved) !== strtolower($filterPayment)) continue;
        if ($filterSettlement !== '' && $r['settlement_status'] !== $filterSettlement) continue;
        $rows[] = $r;
    }

    $total = count($rows);
    $offset = ($page - 1) * $perPage;
    $pageRows = array_slice($rows, $offset, $perPage);

    agri_json(['success' => true, 'data' => $pageRows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
}

// -----------------------------------------------------------------
case 'customers_list': {
    $buyerCol = $C['orders_buyer']; $createdCol = $C['orders_created'];
    $nameCol = $C['users_name']; $mobileCol = $C['users_mobile'];
    $qtyCol = $C['items_qty']; $priceCol = $C['items_price'];
    $locationCol = $C['orders_village'] ?: $C['orders_address'];

    $sql = "SELECT o.$buyerCol buyer_id, u.$nameCol buyer_name, u.$mobileCol buyer_mobile,
                COUNT(DISTINCT o.id) order_count, SUM(oi.$qtyCol) total_qty,
                SUM(oi.$qtyCol * oi.$priceCol) total_amount, MAX(o.$createdCol) last_purchase,
                GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') products_bought" .
                ($locationCol ? ", MAX(o.$locationCol) delivery_location" : ", NULL delivery_location") . "
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            JOIN products p ON p.id = oi.product_id
            LEFT JOIN users u ON u.id = o.$buyerCol
            WHERE p.added_by_user_id = ? AND oi.item_status IN (" . agri_seller_valid_revenue_sql_list() . ")
            GROUP BY o.$buyerCol, u.$nameCol, u.$mobileCol
            ORDER BY last_purchase DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        // Privacy: mask all but the last 4 digits of the phone number.
        $m = (string)($r['buyer_mobile'] ?? '');
        $r['buyer_mobile_masked'] = strlen($m) >= 4 ? str_repeat('X', max(0, strlen($m) - 4)) . substr($m, -4) : $m;
        unset($r['buyer_mobile']);
        $rows[] = $r;
    }
    agri_json(['success' => true, 'data' => $rows]);
}

// -----------------------------------------------------------------
case 'reviews_list': {
    $ratingFilter = (int)($_GET['rating'] ?? 0);
    $nameCol = $C['users_name'];

    $where = "WHERE r.seller_id = ?"; $types = "i"; $params = [$sellerId];
    if ($ratingFilter >= 1 && $ratingFilter <= 5) { $where .= " AND r.rating = ?"; $types .= "i"; $params[] = $ratingFilter; }

    $sql = "SELECT r.*, p.name product_name, u.$nameCol buyer_name" .
        ($C['users_avatar'] ? ", u.{$C['users_avatar']} buyer_avatar" : ", NULL buyer_avatar") . ",
            rr.reply_text, rr.created_at reply_date
        FROM reviews r
        JOIN products p ON p.id = r.product_id
        LEFT JOIN users u ON u.id = r.buyer_id
        LEFT JOIN review_replies rr ON rr.review_id = r.id
        $where ORDER BY r.created_at DESC";
    $stmt = $conn->prepare($sql);
    agri_bind_params($stmt, $types, $params);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $r['review_images'] = $r['review_images'] ? json_decode($r['review_images'], true) : [];
        $rows[] = $r;
    }

    $sumStmt = $conn->prepare("SELECT rating, COUNT(*) c FROM reviews WHERE seller_id = ? GROUP BY rating");
    $sumStmt->bind_param("i", $sellerId);
    $sumStmt->execute();
    $counts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0];
    $resS = $sumStmt->get_result();
    $total = 0; $sum = 0;
    while ($s = $resS->fetch_assoc()) { $counts[(string)$s['rating']] = (int)$s['c']; $total += $s['c']; $sum += $s['rating'] * $s['c']; }

    agri_json(['success' => true, 'data' => $rows, 'summary' => [
        'average' => $total > 0 ? round($sum / $total, 2) : 0, 'total' => $total, 'counts' => $counts,
    ]]);
}

// -----------------------------------------------------------------
case 'review_reply_save': {
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $replyText = trim($_POST['reply_text'] ?? '');
    if ($replyText === '') agri_json(['success' => false, 'error' => 'Reply cannot be empty.']);

    $stmt = $conn->prepare("SELECT id FROM reviews WHERE id = ? AND seller_id = ? LIMIT 1");
    $stmt->bind_param("ii", $reviewId, $sellerId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) agri_json(['success' => false, 'error' => 'Review not found.']);

    $stmt = $conn->prepare(
        "INSERT INTO review_replies (review_id, seller_id, reply_text) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE reply_text = VALUES(reply_text), updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->bind_param("iis", $reviewId, $sellerId, $replyText);
    $ok = $stmt->execute();
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
case 'product_performance': {
    $validList = agri_seller_valid_revenue_sql_list();
    $sqlPerfBase = "SELECT p.id, p.name, p.name_mr, %s p.image, p.views_count, p.sold_quantity, p.stock, p.rating_avg, p.rating_count,
                COUNT(DISTINCT oi.order_id) order_count,
                COALESCE(SUM(oi.$C[items_qty] * oi.$C[items_price]),0) revenue
            FROM products p
            LEFT JOIN order_items oi ON oi.product_id = p.id AND oi.item_status IN ($validList)
            WHERE p.added_by_user_id = ? AND p.is_active = 1
            GROUP BY p.id ORDER BY revenue DESC";
    try {
        $stmt = $conn->prepare(sprintf($sqlPerfBase, "p.name_hi,"));
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $res = $stmt->get_result();
    } catch (\Throwable $e) {
        $stmt = $conn->prepare(sprintf($sqlPerfBase, ""));
        $stmt->bind_param("i", $sellerId);
        $stmt->execute();
        $res = $stmt->get_result();
    }
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['conversion_rate'] = ((int)$r['views_count'] > 0) ? round(((int)$r['order_count'] / (int)$r['views_count']) * 100, 1) : 0;
        $rows[] = $r;
    }
    agri_json(['success' => true, 'data' => $rows]);
}

// -----------------------------------------------------------------
case 'notifications_list': {
    $stmt = $conn->prepare("SELECT * FROM seller_notifications WHERE seller_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $rows = []; $unread = 0;
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { if (!$r['is_read']) $unread++; $rows[] = $r; }
    agri_json(['success' => true, 'data' => $rows, 'unread' => $unread]);
}

// -----------------------------------------------------------------
case 'notification_mark_read': {
    $id = trim($_POST['id'] ?? '');
    if ($id === 'all') {
        $stmt = $conn->prepare("UPDATE seller_notifications SET is_read = 1 WHERE seller_id = ?");
        $stmt->bind_param("i", $sellerId);
    } else {
        $stmt = $conn->prepare("UPDATE seller_notifications SET is_read = 1 WHERE id = ? AND seller_id = ?");
        $stmt->bind_param("ii", $id, $sellerId);
    }
    $ok = $stmt->execute();
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
case 'earnings_summary': {
    $seller = agri_seller_ensure_profile($conn, $sellerId);

    $stmt = $conn->prepare("SELECT * FROM payouts WHERE seller_id = ? ORDER BY requested_at DESC LIMIT 20");
    $stmt->bind_param("i", $sellerId);
    $stmt->execute();
    $payouts = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $payouts[] = $r; }

    $payoutDay = max(1, min(28, (int)($seller['payout_day'] ?: 1)));
    $tz = new DateTimeZone('Asia/Kolkata');
    $today = new DateTime('now', $tz);
    $next = new DateTime($today->format('Y-m-') . sprintf('%02d', $payoutDay), $tz);
    if ($next <= $today) { $next->modify('+1 month'); }

    // Total value of this seller's own withdrawal requests still awaiting
    // Admin action (spec §11 "Processing Amount") — distinct from
    // pending_balance, which is uncleared order earnings not yet even
    // eligible to be requested.
    $processingAmount = 0.0;
    foreach ($payouts as $po) {
        if (in_array($po['status'], ['pending', 'processing'], true)) {
            $processingAmount += (float)$po['amount'];
        }
    }

    agri_json(['success' => true, 'data' => [
        'available_balance' => round((float)$seller['available_balance'], 2),
        'pending_balance' => round((float)$seller['pending_balance'], 2),
        'processing_amount' => round($processingAmount, 2),
        'total_earnings' => round((float)$seller['total_earnings'], 2),
        'total_platform_charges' => round((float)$seller['total_platform_charges'], 2),
        'total_paid' => round((float)$seller['total_paid'], 2),
        'next_payout_date' => $next->format('Y-m-d'),
        'bank_account_name' => $seller['bank_account_name'], 'bank_account_number' => $seller['bank_account_number'],
        'bank_ifsc' => $seller['bank_ifsc'], 'upi_id' => $seller['upi_id'], 'business_name' => $seller['business_name'],
        'payouts' => $payouts,
        'signature_path' => $seller['signature_path'] ?? null,
        'stamp_path' => $seller['stamp_path'] ?? null,
        'authorized_signatory_name' => $seller['authorized_signatory_name'] ?? null,
        'signatory_designation' => $seller['signatory_designation'] ?? null,
        'signature_status' => $seller['signature_status'] ?? 'missing',
        'stamp_status' => $seller['stamp_status'] ?? 'missing',
        'gst' => [
            'legal_business_name' => $seller['legal_business_name'] ?? '',
            'gst_status' => $seller['gst_status'] ?? 'not_applicable',
            'gstin' => $seller['gstin'] ?? '',
            'pan' => $seller['pan'] ?? '',
            'business_type' => $seller['business_type'] ?? '',
            'registered_address' => $seller['registered_address'] ?? '',
            'state' => $seller['gst_state'] ?? '',
            'state_code' => $seller['gst_state_code'] ?? '',
            'city' => $seller['gst_city'] ?? '',
            'pincode' => $seller['gst_pincode'] ?? '',
            'verified_status' => $seller['gst_verified_status'] ?? 'not_verified',
            'request_status' => function_exists('gst_verify_request_status_for_seller') ? gst_verify_request_status_for_seller($conn, $sellerId) : null,
        ],
    ]]);
}

// -----------------------------------------------------------------
// payout_request: seller asks to withdraw money from their available
// balance. The amount is held (deducted from available_balance)
// immediately so the same funds can't be requested twice; Admin then
// approves (case 'completed' in admin/payout_action.php, money stays
// deducted + total_paid increases) or rejects (money is refunded back
// to available_balance).
case 'payout_request': {
    $AGRI_MIN_WITHDRAW = 200.00;
    $seller = agri_seller_ensure_profile($conn, $sellerId);

    $amount = round((float)($_POST['amount'] ?? 0), 2);
    $method = trim($_POST['method'] ?? 'bank');
    if (!in_array($method, ['bank', 'upi'], true)) { $method = 'bank'; }

    if ($amount < $AGRI_MIN_WITHDRAW) {
        agri_json(['success' => false, 'error' => 'Minimum withdrawal amount is ₹' . number_format($AGRI_MIN_WITHDRAW, 0) . '.']);
    }
    if ($amount > (float)$seller['available_balance']) {
        agri_json(['success' => false, 'error' => 'That amount is more than your available balance.']);
    }

    if ($method === 'bank') {
        if (empty($seller['bank_account_number']) || empty($seller['bank_ifsc']) || empty($seller['bank_account_name'])) {
            agri_json(['success' => false, 'error' => 'Please add your bank account details in Payout Details first.']);
        }
        $accountDetails = $seller['bank_account_name'] . ' • A/C ****' . substr($seller['bank_account_number'], -4) . ' • IFSC ' . $seller['bank_ifsc'];
    } else {
        if (empty($seller['upi_id'])) {
            agri_json(['success' => false, 'error' => 'Please add your UPI ID in Payout Details first.']);
        }
        $accountDetails = 'UPI: ' . $seller['upi_id'];
    }

    $conn->begin_transaction();
    try {
        // Guard clause (available_balance >= ?) makes this safe even
        // against a double-click / race — only one request can ever
        // succeed in taking the last of the balance.
        $upd = $conn->prepare("UPDATE seller_payout_profiles SET available_balance = available_balance - ? WHERE user_id = ? AND available_balance >= ?");
        $upd->bind_param("did", $amount, $sellerId, $amount);
        $upd->execute();
        if ($upd->affected_rows < 1) {
            $conn->rollback();
            agri_json(['success' => false, 'error' => 'That amount is more than your available balance.']);
        }

        $ins = $conn->prepare("INSERT INTO payouts (seller_id, amount, method, account_details, status) VALUES (?, ?, ?, ?, 'pending')");
        $ins->bind_param("idss", $sellerId, $amount, $method, $accountDetails);
        $ins->execute();

        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        agri_json(['success' => false, 'error' => 'Could not submit your withdrawal request. Please try again.']);
    }

    agri_seller_notify(
        $conn, $sellerId, 'payout_requested', 'Withdrawal Requested',
        'Your withdrawal request of ₹' . number_format($amount, 2) . ' has been submitted and is pending admin approval.',
        'seller/dashboard.php#earnings'
    );

    // Notify Admin (spec §14 "Payout Request") — best-effort.
    require_once __DIR__ . '/../includes/admin_notifications_schema.php';
    agri_notify_admin(
        $conn,
        'payout_request',
        'Payout Request — ₹' . number_format($amount, 2),
        ($seller['business_name'] ?? ('Seller #' . $sellerId)) . ' requested a withdrawal via ' . strtoupper($method) . '.',
        'seller_payouts.php'
    );

    agri_json(['success' => true]);
}

// -----------------------------------------------------------------
case 'profile_save': {
    agri_seller_ensure_profile($conn, $sellerId);
    $businessName = trim($_POST['business_name'] ?? '');
    $bankName = trim($_POST['bank_account_name'] ?? '');
    $bankNumber = trim($_POST['bank_account_number'] ?? '');
    $bankIfsc = trim($_POST['bank_ifsc'] ?? '');
    $upi = trim($_POST['upi_id'] ?? '');

    $stmt = $conn->prepare(
        "UPDATE seller_payout_profiles SET business_name=?, bank_account_name=?, bank_account_number=?, bank_ifsc=?, upi_id=? WHERE user_id=?"
    );
    $stmt->bind_param("sssssi", $businessName, $bankName, $bankNumber, $bankIfsc, $upi, $sellerId);
    $ok = $stmt->execute();
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
// gst_save: Seller's own GST / Business Tax profile (Seller Dashboard
// -> Business / GST Details). This is the ONLY place a seller's GSTIN
// is captured/edited — every Seller Invoice's "Seller Details" block
// reads live from here until the invoice is generated, then it is
// frozen (see agri_seller_identity_snapshot() in seller_functions.php).
case 'gst_save': {
    agri_seller_ensure_profile($conn, $sellerId);

    $legalBusinessName = trim($_POST['legal_business_name'] ?? '');
    $gstStatus   = trim($_POST['gst_status'] ?? 'not_applicable');
    $gstin       = strtoupper(trim($_POST['gstin'] ?? ''));
    $pan         = strtoupper(trim($_POST['pan'] ?? ''));
    $businessType = trim($_POST['business_type'] ?? '');
    $address     = trim($_POST['registered_address'] ?? '');
    $state       = trim($_POST['state'] ?? '');
    $stateCode   = trim($_POST['state_code'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $pincode     = trim($_POST['pincode'] ?? '');

    if (!in_array($gstStatus, ['registered', 'composition', 'unregistered', 'not_applicable'], true)) {
        $gstStatus = 'not_applicable';
    }

    // Rule: mandatory GSTIN if Registered; never force it if not.
    if ($gstin !== '') {
        $v = gstin_validate($gstin);
        if (!$v['valid']) {
            agri_json(['success' => false, 'error' => $v['message'], 'field' => 'gstin']);
        }
        if ($stateCode === '') { $stateCode = $v['state_code']; }
        if ($state === '' && $v['state_name']) { $state = $v['state_name']; }
    } elseif ($gstStatus === 'registered') {
        agri_json(['success' => false, 'error' => 'GSTIN is required when GST Registration Status is "Registered".', 'field' => 'gstin']);
    }
    if ($pan !== '' && !gstin_pan_valid($pan)) {
        agri_json(['success' => false, 'error' => 'Invalid PAN format.', 'field' => 'pan']);
    }

    $oldStmt = $conn->prepare("SELECT gstin, business_name FROM seller_payout_profiles WHERE user_id = ?");
    $oldStmt->bind_param('i', $sellerId);
    $oldStmt->execute();
    $oldRow = $oldStmt->get_result()->fetch_assoc();
    $oldGstin = $oldRow['gstin'] ?? null;

    // A GSTIN edit after it was already Verified reverts it back to
    // Pending — a changed GSTIN can't stay "Verified" without re-check.
    $verifiedResetSql = '';
    if ($oldGstin !== null && $oldGstin !== $gstin) {
        $verifiedResetSql = ", gst_verified_status = 'not_verified'";
    }

    $stmt = $conn->prepare(
        "UPDATE seller_payout_profiles SET
            legal_business_name=?, gst_status=?, gstin=?, pan=?, business_type=?,
            registered_address=?, gst_state=?, gst_state_code=?, gst_city=?, gst_pincode=? $verifiedResetSql
         WHERE user_id=?"
    );
    $stmt->bind_param(
        'ssssssssssi',
        $legalBusinessName, $gstStatus, $gstin, $pan, $businessType,
        $address, $state, $stateCode, $city, $pincode, $sellerId
    );
    $ok = $stmt->execute();

    if ($ok) {
        gstin_log_change($conn, 'seller', $sellerId, $legalBusinessName ?: ($oldRow['business_name'] ?? 'Seller'), $oldGstin, $gstin !== '' ? $gstin : null,
            'seller', $sellerId, $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Seller');

        // The GSTIN changed and this seller's own verified_status was
        // just reset to 'not_verified' above — mirror that back onto
        // the linked Admin "Companies" record so a stale Verified
        // badge never lingers there after the underlying GST details
        // actually changed (Admin and Seller must always agree).
        if ($verifiedResetSql !== '') {
            gst_sync_reset_company_gst_on_seller_edit($conn, $sellerId);
        }
    }
    agri_json(['success' => $ok, 'error' => $ok ? null : 'Save failed.']);
}

// -----------------------------------------------------------------
// gst_verify_request: Seller clicks "Verify GSTIN" on their own GST
// Details. Unlike the Admin-initiated Companies-directory verify
// flow (which has to guess the seller account by name/email — see
// includes/gst_sync.php), this request carries the seller's own
// $sellerId from their authenticated session, so when Admin approves
// it there is zero ambiguity about which account gets marked verified.
case 'gst_verify_request': {
    $seller = agri_seller_ensure_profile($conn, $sellerId);
    $businessName = trim($seller['business_name'] ?? '');
    $legalBusinessName = trim($seller['legal_business_name'] ?? '');
    $gstin = trim($seller['gstin'] ?? '');

    $result = gst_verify_request_submit($conn, $sellerId, $businessName, $legalBusinessName, $gstin);
    if ($result['success']) {
        agri_seller_notify(
            $conn, $sellerId, 'gst_verify_requested', 'GST Verification Requested',
            'Your GST verification request has been submitted and is pending admin review.',
            'seller/dashboard.php#gst'
        );
        if (function_exists('gst_verify_request_notify_admin')) {
            gst_verify_request_notify_admin($conn, $seller['business_name'] ?? ('Seller #' . $sellerId), $gstin);
        }
    }
    agri_json($result);
}

// -----------------------------------------------------------------
// Seller's own Digital Signature / Official Business Stamp for the
// Authorized Signatory section of their Seller Invoices (this is the
// document shown on BUYER invoices for orders sold by this seller —
// see agri_buyer_invoice_signatory_block() in seller_functions.php).
// Never affects a Seller Invoice's own signatory, which is always
// AgriCart's — see admin/invoice_settings.php.
case 'signature_save': {
    agri_seller_ensure_profile($conn, $sellerId);
    $destDir = __DIR__ . '/../assets/uploads/invoice_signatures';
    $webPrefix = 'assets/uploads/invoice_signatures';

    $sets = [];
    $types = '';
    $params = [];

    if (!empty($_FILES['signature']) && ($_FILES['signature']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $res = agri_secure_upload_image($_FILES['signature'], $destDir, $webPrefix, 2 * 1024 * 1024);
        if (!$res['ok']) agri_json(['success' => false, 'error' => $res['error']]);
        $sets[] = 'signature_path = ?'; $types .= 's'; $params[] = $res['path'];
        $sets[] = "signature_status = 'uploaded'";
    }
    if (!empty($_FILES['stamp']) && ($_FILES['stamp']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $res = agri_secure_upload_image($_FILES['stamp'], $destDir, $webPrefix, 2 * 1024 * 1024);
        if (!$res['ok']) agri_json(['success' => false, 'error' => $res['error']]);
        $sets[] = 'stamp_path = ?'; $types .= 's'; $params[] = $res['path'];
        $sets[] = "stamp_status = 'uploaded'";
    }

    $signatoryName = trim($_POST['authorized_signatory_name'] ?? '');
    $designation = trim($_POST['signatory_designation'] ?? '');
    $sets[] = 'authorized_signatory_name = ?'; $types .= 's'; $params[] = $signatoryName;
    $sets[] = 'signatory_designation = ?'; $types .= 's'; $params[] = $designation;

    $params[] = $sellerId; $types .= 'i';
    $stmt = $conn->prepare("UPDATE seller_payout_profiles SET " . implode(', ', $sets) . " WHERE user_id = ?");
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    agri_json(['success' => $ok]);
}

// -----------------------------------------------------------------
default:
    http_response_code(400);
    agri_json(['success' => false, 'error' => 'Unknown action.']);
}
} catch (\Throwable $eTop) {
    // Most likely cause: agri_seller_columns() picked a column name that
    // doesn't exist on this install's orders/order_items tables. Check
    // includes/seller_functions.php -> agri_seller_columns() and add
    // your real column name to the matching candidate list.
    http_response_code(500);
    agri_json(['success' => false, 'error' => 'Server error. Please check your orders/order_items column mapping in includes/seller_functions.php.']);
}
