<?php
// =====================================================================
// admin/order_action.php — Update an order's status. Adjust table/column
// names here if your `orders` table differs (this assumes: orders.id,
// orders.order_status).
// =====================================================================
include __DIR__ . '/../includes/db.php';
agri_session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/order_sync.php';
require_once __DIR__ . '/includes/cod_payment_sync.php';

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}
csrf_require('json');

// -----------------------------------------------------------------
// get_details — full order info (+ line items) for the "view order"
// popup on report.php / index.php. Read-only, gated on reports.view
// OR orders.view so either the Orders tab or the Reports tab can use it.
// -----------------------------------------------------------------
if (($_POST['action'] ?? $_GET['action'] ?? '') === 'get_details') {
    if (!hasPermission('orders.view') && !hasPermission('reports.view') && !isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to view this.']);
        exit;
    }
    $orderId = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order id.']);
        exit;
    }

    $stmt = null;
    try {
        $stmt = $conn->prepare("SELECT o.*, u.full_name AS account_name, u.email AS account_email, u.mobile AS account_mobile FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?");
    } catch (\Throwable $e) { $stmt = null; }
    if (!$stmt) { $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?"); }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found.']);
        exit;
    }

    $items = [];
    try {
        $itemsStmt = $conn->prepare(
            "SELECT oi.product_id, oi.product_name, oi.quantity, oi.price, oi.item_status, oi.seller_id,
                    u.full_name AS seller_name
             FROM order_items oi
             LEFT JOIN users u ON u.id = oi.seller_id
             WHERE oi.order_id = ?"
        );
        $itemsStmt->bind_param('i', $orderId);
        $itemsStmt->execute();
        $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (\Throwable $e) {
        try {
            $itemsStmt = $conn->prepare("SELECT product_name, quantity FROM order_items WHERE order_id = ?");
            $itemsStmt->bind_param('i', $orderId);
            $itemsStmt->execute();
            $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (\Throwable $e2) { $items = []; }
    }

    $order['status_history'] = agri_order_get_status_history($conn, $orderId);

    // ---- Compose a full delivery address from whichever columns this
    // schema actually has (SELECT o.* above already pulled them under
    // their real names — we just don't know which key to read). Mirrors
    // the same candidate list + notes-fallback logic already used in
    // pages/invoice.php, so the popup matches the printed invoice.
    $pick = function(array $keys) use ($order) {
        foreach ($keys as $k) {
            if (!empty($order[$k])) return $order[$k];
        }
        return '';
    };
    $addrLine  = $pick(['delivery_address', 'address', 'shipping_address']);
    $village   = $pick(['delivery_village', 'village']);
    $line1Parts = array_filter([$addrLine, $village]);
    $line1 = trim(implode(', ', $line1Parts));
    if ($line1 === '') {
        $notes = $pick(['delivery_notes', 'notes']);
        if ($notes !== '') {
            $line1 = trim(preg_replace('/^Delivery:\s*[^\n]*\n?/i', '', (string)$notes));
        }
    }
    $cityState = trim(implode(', ', array_filter([$pick(['delivery_city', 'city']), $pick(['delivery_state', 'state'])])));
    $pin = $pick(['delivery_pincode', 'pincode', 'pin_code', 'delivery_pin']);

    $order['delivery_address_full'] = $line1 !== '' ? $line1 : null;
    $order['delivery_city_state']   = $cityState !== '' ? $cityState : null;
    $order['delivery_pin_full']     = $pin !== '' ? $pin : null;

    $order['items'] = $items;
    echo json_encode(['success' => true, 'order' => $order]);
    exit;
}

// -----------------------------------------------------------------
// get_history — status timeline for the Order Details page.
if (($_POST['action'] ?? $_GET['action'] ?? '') === 'get_history') {
    if (!hasPermission('orders.view') && !isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You do not have permission to view this.']);
        exit;
    }
    $orderId = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);
    if ($orderId <= 0) { echo json_encode(['success' => false, 'error' => 'Invalid order id.']); exit; }
    $history = agri_order_get_status_history($conn, $orderId);
    $history = array_map(static function ($h) {
        $h['new_status_label'] = agri_order_status_label($h['new_status']);
        return $h;
    }, $history);
    echo json_encode(['success' => true, 'data' => $history]);
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$status  = trim($_POST['status'] ?? '');
$reason  = trim($_POST['reason'] ?? '') ?: null;
// Full status vocabulary now matches order_items.item_status (minus
// 'new_order', whose order-level label is 'placed') — see includes/order_sync.php.
$allowedStatuses = ['placed','confirmed','packed','shipped','delivered','cancelled','returned','refunded'];

if ($orderId <= 0 || !in_array($status, $allowedStatuses, true)) {
    $response['error'] = 'Invalid order id or status.';
    echo json_encode($response);
    exit;
}

requirePermission(agri_order_permission_for_status($status));

// Read the previous status first so the Inventory module can tell whether
// this change is entering/leaving "cancelled" (see automatic inventory
// logic below — this never blocks the status update itself), and so we
// can validate the transition and reject invalid/duplicate jumps.
$prevStatus = null;
$prevStmt = $conn->prepare("SELECT order_status FROM orders WHERE id = ?");
if (!$prevStmt) {
    $response['error'] = 'orders table not found or column mismatch — check your schema.';
    echo json_encode($response);
    exit;
}
$prevStmt->bind_param('i', $orderId);
$prevStmt->execute();
$prevRow = $prevStmt->get_result()->fetch_assoc();
if (!$prevRow) {
    $response['error'] = 'Order not found.';
    echo json_encode($response);
    exit;
}
$prevStatus = $prevRow['order_status'];

if ($prevStatus === $status) {
    // No-op re-save (e.g. a stale page double-submitting the same value) —
    // nothing to change, nothing to log, but not an error either. Still
    // worth a COD payment-status self-heal here: if this order was already
    // Delivered before this fix existed, re-saving "Delivered" is a cheap,
    // safe way to correct a stale Unpaid without needing a full page reload.
    if (strtolower((string)$status) === 'delivered') {
        agri_order_sync_cod_payment_status($conn, $orderId);
    }
    echo json_encode(['success' => true, 'order_status' => $status, 'unchanged' => true]);
    exit;
}
if (!agri_admin_can_transition_status($prevStatus, $status)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Cannot change status from "' . agri_order_status_label($prevStatus) . '" to "' . agri_order_status_label($status) . '".']);
    exit;
}

$adminId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;
$adminName = $_SESSION['admin_name'] ?? null;

// ---- Cascade down to every order_item under this order (all sellers)
// so Seller Dashboards immediately reflect the same status, then let the
// shared recalculation function derive the final, authoritative
// orders.order_status from the resulting item states — this keeps a
// multi-seller order's other sellers' items from being corrupted by a
// single admin click, and guarantees the DB never ends up with Admin and
// Seller disagreeing about the status. ----
agri_order_cascade_admin_status($conn, $orderId, $status, $adminId ? (int)$adminId : null, $adminName, $reason);
$finalStatus = agri_order_recalculate($conn, $orderId, 'admin', $adminId ? (int)$adminId : null, $adminName, $reason);

if ($finalStatus === null) {
    // No order_items rows exist for this order (legacy/edge-case order) —
    // fall back to writing orders.order_status directly so the admin
    // action still succeeds, and log it the same way.
    $upd = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
    $upd->bind_param('si', $status, $orderId);
    if ($upd->execute()) {
        agri_order_log_history($conn, $orderId, null, $prevStatus, $status, $adminId ? (int)$adminId : null, 'admin', $adminName, $reason);
        $finalStatus = $status;
    }
}

$response['success'] = ($finalStatus !== null);
$response['order_status'] = $finalStatus;
$status = $finalStatus ?? $status; // keep the inventory-sync block below in sync with what was actually written

if (!$response['success']) {
    $response['error'] = 'Update failed.';
} else {
    logAdminActivity('order_status_changed', 'orders', $orderId, $prevStatus, $status, 'Order #' . $orderId . ' status changed to "' . $status . '"' . ($reason ? (' — ' . $reason) : ''));
    // Note: stock restoration for cancelled/returned items and earnings
    // release for delivered items are now handled per-item, inside
    // agri_order_cascade_admin_status() above (it reuses the exact same
    // agri_seller_reverse_sale()/agri_seller_make_earning_available()
    // functions the Seller Dashboard uses) — so every item's stock is
    // adjusted exactly once, correctly, even on a multi-seller order
    // where only some items end up cancelled. A second, order-level
    // stock adjustment here would double-count it.

    // ---- COD auto-settle: the moment this order's status lands on
    // 'delivered', a Cash on Delivery order has had its cash physically
    // collected — so flip payment_status Unpaid -> Paid right here, in
    // the same backend request, instead of leaving it stale until some
    // later page load. Only ever touches THIS order's row, only when
    // the payment method is genuinely COD (normalized), and never for
    // prepaid methods (UPI/card/netbanking/online), whose existing
    // payment_status logic is left untouched. See includes/cod_payment_sync.php.
    if (strtolower((string)$status) === 'delivered') {
        agri_order_sync_cod_payment_status($conn, $orderId);
    }
}

echo json_encode($response);
