<?php
// =====================================================
// AgriCart — Get current user's latest booking status
// for a given equipment (AJAX endpoint for tracking modal)
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['found' => false]);
    exit;
}

$userId      = (int)$_SESSION['user_id'];
$equipmentId = (int)($_GET['equipment_id'] ?? 0);

if ($equipmentId <= 0) {
    echo json_encode(['found' => false]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, booking_number, from_date, to_date, total_amount, total_days, total_hours, status, payment_status
     FROM equipment_bookings
     WHERE equipment_id = ? AND user_id = ?
     ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param("ii", $equipmentId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['found' => false]);
    exit;
}

// Map DB status → 4-step tracker index used by the UI
// 0: Booking Confirmed, 1: Equipment Dispatched, 2: On the Way, 3: Delivered to Farm
$stepMap = [
    'pending'     => 0,
    'confirmed'   => 1,
    'on_the_way'  => 2,
    'delivered'   => 3,
    'completed'   => 3,
    'cancelled'   => 0,
];
$step = $stepMap[$row['status']] ?? 0;

echo json_encode([
    'found'          => true,
    'id'             => (int)$row['id'],
    'booking_number' => $row['booking_number'],
    'from_date'      => date('d M Y', strtotime($row['from_date'])),
    'to_date'        => date('d M Y', strtotime($row['to_date'])),
    'total_amount'   => $row['total_amount'],
    'total_days'     => $row['total_days'],
    'total_hours'    => $row['total_hours'],
    'status'         => $row['status'],
    'payment_status' => $row['payment_status'] ?? 'pending',
    'step'           => $step,
]);
