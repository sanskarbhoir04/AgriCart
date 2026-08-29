<?php
// =====================================================
// AgriCart — Cancel a rental booking (AJAX endpoint)
// Called from my_activity.php's maCancelBooking().
// The logged-in USER can cancel their own booking as long
// as it hasn't already been completed or cancelled.
// On success, a notification is sent to the user confirming
// the cancellation and — if payment was already made — that
// the amount will be refunded within 7 days.
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/agri_connect_functions.php';
include_once __DIR__ . '/../includes/agri_connect_schema.php';
header('Content-Type: application/json');

function respond($arr) { echo json_encode($arr); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_require')) {
    csrf_require('json');
}

$userId    = (int)($_SESSION['user_id'] ?? 0);
$bookingId = (int)($_POST['booking_id'] ?? 0);

if (!$userId) {
    respond(['success' => false, 'error' => 'कृपया आधी login करा.']);
}
if ($bookingId <= 0) {
    respond(['success' => false, 'error' => 'Booking सापडली नाही.']);
}

$stmt = $conn->prepare(
    "SELECT eb.id, eb.booking_number, eb.status, eb.payment_status, eb.total_amount, eb.user_id,
            e.name AS equipment_name
     FROM equipment_bookings eb
     LEFT JOIN equipment e ON e.id = eb.equipment_id
     WHERE eb.id = ? AND eb.user_id = ? LIMIT 1"
);
$stmt->bind_param("ii", $bookingId, $userId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    respond(['success' => false, 'error' => 'ही booking तुमची नाही किंवा सापडली नाही.']);
}
if ($booking['status'] === 'cancelled') {
    respond(['success' => false, 'error' => 'ही booking आधीच रद्द झाली आहे.']);
}
if ($booking['status'] === 'completed') {
    respond(['success' => false, 'error' => 'पूर्ण झालेली booking रद्द करता येत नाही.']);
}

$upd = $conn->prepare("UPDATE equipment_bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
$upd->bind_param("ii", $bookingId, $userId);

if (!$upd->execute()) {
    respond(['success' => false, 'error' => 'Booking रद्द करताना अडचण आली, पुन्हा try करा.']);
}

// Best-effort: track who cancelled it (skipped silently if column doesn't exist)
try {
    $cb = $conn->prepare("UPDATE equipment_bookings SET cancelled_by = 'user' WHERE id = ?");
    $cb->bind_param("i", $bookingId);
    $cb->execute();
} catch (\Throwable $eCb) {}

$wasPaid = in_array($booking['payment_status'], ['paid', 'cod'], true);
$amount  = number_format((float)$booking['total_amount'], 2);
$equipName = $booking['equipment_name'] ?: 'Equipment';

$msg = $wasPaid
    ? 'तुमची booking ' . $booking['booking_number'] . ' (' . $equipName . ') रद्द केली आहे. भरलेले ₹' . $amount . ' पुढील 7 दिवसांत परत (refund) केले जातील.'
    : 'तुमची booking ' . $booking['booking_number'] . ' (' . $equipName . ') रद्द केली आहे.';

if (function_exists('agri_notify_user')) {
    try {
        if (function_exists('agri_connect_bootstrap_schema')) { agri_connect_bootstrap_schema($conn); }
        agri_notify_user($conn, $userId, 'Booking Cancelled', $msg, 'my_activity.php', 'payment');
    } catch (\Throwable $eNotify) {}
}

respond([
    'success'    => true,
    'status'     => 'cancelled',
    'was_paid'   => $wasPaid,
    'note'       => $wasPaid ? 'तुमचे पैसे 7 दिवसांत परत केले जातील.' : 'Booking रद्द झाली.',
]);
