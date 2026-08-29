<?php
/**
 * get_my_orders.php — AgriCart order history for the logged-in user.
 *
 * Matches confirmed `orders` schema: id, user_id, address_id, order_number,
 * total_amount, discount_amount, shipping_charge, final_amount, order_status,
 * payment_status('unpaid'/'paid'/'refunded'), coupon_code, notes, ordered_at.
 *
 * BUGFIX: this used to select a column named `status`, but the real column
 * (per setup/orders_setup.sql, and the one admin/order_action.php actually
 * writes to) is `order_status`. That mismatch meant the customer-facing
 * order tracker never advanced past "Order Placed" even after an admin
 * marked an order Packed/Shipped/Delivered.
 *
 * `payment_mode` wasn't visible in the columns screenshot yet — this file
 * detects it at runtime via SHOW COLUMNS and falls back to reading it out
 * of `notes` (place_order.php writes it there when the column is missing)
 * so the order history page still shows *something* useful either way.
 *
 * order_items columns are still assumed as (order_id, product_name,
 * quantity) — please confirm once you can share that table's structure.
 */

require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please login to view your orders.']);
    exit;
}
$userId = (int)$_SESSION['user_id'];

function agri_table_columns(mysqli $conn, string $table): array {
    $cols = [];
    try {
        $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
        if ($res) { while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; } }
    } catch (\Throwable $e) {}
    return $cols;
}

$orders = [];
try {
    $orderCols = agri_table_columns($conn, 'orders');
    $hasPaymentModeCol = in_array('payment_mode', $orderCols, true);
    $hasNotesCol = in_array('notes', $orderCols, true);
    $hasHiddenCol = in_array('hidden_by_user_at', $orderCols, true);
    $paymentModeSelect = $hasPaymentModeCol ? "o.payment_mode," : "";
    $notesSelect = $hasNotesCol ? "o.notes," : "";
    $hiddenWhere = $hasHiddenCol ? "AND o.hidden_by_user_at IS NULL" : "";

    $sql = "SELECT o.id, o.order_number, o.ordered_at, o.order_status,
                   o.payment_status, {$paymentModeSelect} {$notesSelect}
                   o.discount_amount, o.final_amount, o.coupon_code
            FROM orders o
            WHERE o.user_id = ? {$hiddenWhere}
            ORDER BY o.ordered_at DESC
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $orderRows = [];
    while ($row = $res->fetch_assoc()) { $orderRows[] = $row; }

    $itemsByOrder = [];
    if ($orderRows) {
        $orderIds = array_column($orderRows, 'id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $types = str_repeat('i', count($orderIds));
        try {
            $itemStmt = $conn->prepare("SELECT order_id, product_name, quantity, item_status FROM order_items WHERE order_id IN ($placeholders)");
            $itemStmt->bind_param($types, ...$orderIds);
            $itemStmt->execute();
            $itemRes = $itemStmt->get_result();
            while ($item = $itemRes->fetch_assoc()) {
                $itemsByOrder[$item['order_id']][] = [
                    'product_name' => $item['product_name'],
                    'quantity' => (int)$item['quantity'],
                    // Per-seller status — lets the buyer see "this seller's item
                    // shipped" even while the order's overall status (shown at
                    // the top of the card) is still held back by another
                    // seller's slower item on a multi-seller order.
                    'item_status' => $item['item_status'] ?? null,
                ];
            }
        } catch (\Throwable $eItems) {
            error_log('get_my_orders.php: order_items lookup failed (schema mismatch?) — ' . $eItems->getMessage());
        }
    }

    foreach ($orderRows as $o) {
        // Best-effort payment mode: real column if it exists, else parse the
        // "Payment mode: X" line place_order.php writes into notes as a fallback.
        $paymentMode = $hasPaymentModeCol ? ($o['payment_mode'] ?? 'COD') : 'COD';
        if (!$hasPaymentModeCol && $hasNotesCol && !empty($o['notes']) && preg_match('/Payment mode:\s*(\S+)/', $o['notes'], $m)) {
            $paymentMode = $m[1];
        }
        $orders[] = [
            'id' => (int)$o['id'],
            'order_number' => $o['order_number'],
            'created_at' => $o['ordered_at'],
            'order_status' => $o['order_status'],
            'payment_mode' => $paymentMode,
            'payment_status' => $o['payment_status'],
            'discount_amount' => (float)($o['discount_amount'] ?? 0),
            'final_amount' => (float)$o['final_amount'],
            'coupon_code' => $o['coupon_code'],
            'items' => $itemsByOrder[$o['id']] ?? [],
        ];
    }
} catch (\Throwable $e) {
    error_log('get_my_orders.php failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load your orders.']);
    exit;
}

echo json_encode(['success' => true, 'orders' => $orders]);
