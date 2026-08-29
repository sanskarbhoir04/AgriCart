<?php
// =====================================================================
// pages/delete_order.php — Remove an order from the logged-in user's
// own "My Orders" history.
//
// This is a SOFT delete: it only hides the order from that user's own
// view (sets orders.hidden_by_user_at). The order row itself is never
// deleted — the seller/admin side still needs it for accounting, sales
// history, and dispute resolution.
//
// Only orders that are 'delivered' or 'cancelled' can be removed —
// an order that's still placed/packed/shipped is still "in flight"
// and hiding it would just make the user lose track of it.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

$response = ['success' => false];

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    $response['error'] = 'Please login to manage your orders.';
    echo json_encode($response);
    exit;
}

if (!csrf_verify()) {
    http_response_code(403);
    $response['error'] = 'Security token expired. Please refresh the page and try again.';
    echo json_encode($response);
    exit;
}

$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    $response['error'] = 'Invalid order.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("SELECT id, order_status FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    $response['error'] = 'Order not found.';
    echo json_encode($response);
    exit;
}

$removableStatuses = ['delivered', 'cancelled'];
if (!in_array($order['order_status'], $removableStatuses, true)) {
    $response['error'] = 'Only delivered or cancelled orders can be removed from your history.';
    echo json_encode($response);
    exit;
}

$upd = $conn->prepare("UPDATE orders SET hidden_by_user_at = NOW() WHERE id = ? AND user_id = ?");
$upd->bind_param("ii", $orderId, $userId);

if ($upd->execute()) {
    $response['success'] = true;
} else {
    $response['error'] = 'Could not remove this order right now. Please try again.';
}

echo json_encode($response);
