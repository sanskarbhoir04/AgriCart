<?php
// =====================================================================
// pages/get_my_history.php — Combined "My History" for the logged-in
// user: every marketplace order + every equipment rental booking,
// merged and sorted newest-first. Used by the History modal opened
// from the profile card in the header (site-wide).
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

// ── Base path (same logic as header.php/rental.php) — for building equipment image URLs ──
$_doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_this_dir = str_replace('\\', '/', realpath(dirname(__DIR__)));
$base_path = rtrim(str_replace($_doc_root, '', $_this_dir), '/');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.', 'orders' => [], 'bookings' => []]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ── Marketplace orders ──
$orders = [];
$stmt = $conn->prepare(
    "SELECT id, order_number, total_amount, payment_mode, payment_status, order_status, created_at
     FROM orders WHERE user_id = ? ORDER BY id DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $row['sort_ts'] = strtotime($row['created_at']);
    $row['created_at'] = date('d M Y, H:i', $row['sort_ts']);
    $row['items'] = [];
    $orders[] = $row;
}
foreach ($orders as &$order) {
    $itemStmt = $conn->prepare("SELECT product_name, price, quantity, subtotal FROM order_items WHERE order_id = ?");
    $itemStmt->bind_param("i", $order['id']);
    $itemStmt->execute();
    $itemRes = $itemStmt->get_result();
    while ($item = $itemRes->fetch_assoc()) {
        $order['items'][] = $item;
    }
}
unset($order);

// ── Equipment rental bookings ──
$bookings = [];
$bStmt = $conn->prepare(
    "SELECT b.id, b.booking_number, b.from_date, b.to_date, b.total_days, b.total_hours, b.total_amount, b.status, b.payment_status, b.equipment_id,
            b.contact_name, b.contact_mobile, b.delivery_address, b.pn, b.serial_no,
            e.name AS equipment_name, e.name_mr AS equipment_name_mr, e.owner_name, e.image AS equipment_image
     FROM equipment_bookings b
     LEFT JOIN equipment e ON e.id = b.equipment_id
     WHERE b.user_id = ? ORDER BY b.id DESC"
);
if ($bStmt) {
    $bStmt->bind_param("i", $userId);
    $bStmt->execute();
    $bRes = $bStmt->get_result();
    while ($row = $bRes->fetch_assoc()) {
        $row['sort_ts'] = strtotime($row['from_date']) ?: 0;
        $row['from_date_fmt'] = $row['from_date'] ? date('d M Y', strtotime($row['from_date'])) : '';
        $row['to_date_fmt']   = $row['to_date'] ? date('d M Y', strtotime($row['to_date'])) : '';
        $row['equipment_image_url'] = $base_path . '/' . ($row['equipment_image'] ?: 'assets/images/equipment.png');
        $row['equipment_image_fallback'] = $base_path . '/assets/images/equipment.png';
        $bookings[] = $row;
    }
}

echo json_encode([
    'success'  => true,
    'orders'   => $orders,
    'bookings' => $bookings,
]);
