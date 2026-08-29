<?php
// =====================================================================
// pages/owner_booking_action.php — lets an equipment OWNER (a logged-in
// farmer who self-listed equipment via insert_equipment.php, i.e.
// equipment.owner_user_id = their own user id) cancel a booking made
// on their equipment. Ownership is verified server-side; an owner can
// never touch a booking that isn't on their own equipment.
// =====================================================================
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

$ownerId   = (int)($_SESSION['user_id'] ?? 0);
$bookingId = (int)($_POST['booking_id'] ?? 0);
$action    = trim($_POST['action'] ?? 'cancel'); // only 'cancel' supported for owners

if (!$ownerId) {
    respond(['success' => false, 'error' => 'कृपया आधी login करा.']);
}
if ($bookingId <= 0) {
    respond(['success' => false, 'error' => 'Booking सापडली नाही.']);
}
if ($action !== 'cancel') {
    respond(['success' => false, 'error' => 'Invalid action.']);
}

// Verify this booking belongs to equipment OWNED by the logged-in user
$stmt = $conn->prepare(
    "SELECT eb.id, eb.booking_number, eb.status, eb.payment_status, eb.total_amount, eb.user_id,
            e.owner_user_id, e.name AS equipment_name
     FROM equipment_bookings eb
     JOIN equipment e ON e.id = eb.equipment_id
     WHERE eb.id = ? LIMIT 1"
);
$stmt->bind_param("i", $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    respond(['success' => false, 'error' => 'Booking सापडली नाही.']);
}
if ((int)$booking['owner_user_id'] !== $ownerId) {
    http_response_code(403);
    respond(['success' => false, 'error' => 'हे तुमचे equipment नाही, त्यामुळे ही booking cancel करता येणार नाही.']);
}
if ($booking['status'] === 'cancelled') {
    respond(['success' => false, 'error' => 'ही booking आधीच रद्द झाली आहे.']);
}
if ($booking['status'] === 'completed') {
    respond(['success' => false, 'error' => 'पूर्ण झालेली booking रद्द करता येत नाही.']);
}

$upd = $conn->prepare("UPDATE equipment_bookings SET status = 'cancelled' WHERE id = ?");
$upd->bind_param("i", $bookingId);
if (!$upd->execute()) {
    respond(['success' => false, 'error' => 'Booking रद्द करताना अडचण आली, पुन्हा try करा.']);
}

// Best-effort: track who cancelled it (skipped silently if column doesn't exist)
try {
    $cb = $conn->prepare("UPDATE equipment_bookings SET cancelled_by = 'owner' WHERE id = ?");
    $cb->bind_param("i", $bookingId);
    $cb->execute();
} catch (\Throwable $eCb) {}

// Notify the renter — mention refund timeline if payment was already made
if (function_exists('agri_notify_user') && $booking['user_id']) {
    try {
        if (function_exists('agri_connect_bootstrap_schema')) { agri_connect_bootstrap_schema($conn); }
        $wasPaid = in_array($booking['payment_status'], ['paid', 'cod'], true);
        $msg = 'तुमची booking ' . $booking['booking_number'] . ' (' . $booking['equipment_name'] . ') owner ने रद्द केली आहे.';
        if ($wasPaid) {
            $msg .= ' भरलेले ₹' . number_format((float)$booking['total_amount'], 2) . ' पुढील 7 दिवसांत परत (refund) केले जातील.';
        }
        agri_notify_user($conn, (int)$booking['user_id'], 'Booking Cancelled', $msg, 'my_activity.php', 'payment');
    } catch (\Throwable $eNotify) {}
}

respond(['success' => true, 'status' => 'cancelled']);
