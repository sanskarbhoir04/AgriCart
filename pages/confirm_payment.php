<?php
// =====================================================================
// AgriCart — Submit payment proof for a booking (equipment rental).
//
// SECURITY FIX: this used to flip payment_status straight to 'paid'
// the moment the user clicked "I Have Paid Successfully" — no proof
// was ever checked. It now only records the user's claim (payment
// method, transaction/UTR ID, optional screenshot) and sets status to
// 'verification_pending'. An admin with the rental_bookings.verify_payment
// permission must approve it (see admin/payment_verification.php)
// before the booking is ever marked 'paid'.
//
// Cash on Delivery ('cod') is unaffected — there's no online proof to
// verify, payment happens in person at delivery.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/secure_upload.php';
header('Content-Type: application/json');

$response = ['success' => false];

// CSRF check (accepts POST field or header — payment.php sends it as a form field).
if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token expired. Please refresh the page and try again.']);
    exit;
}

$userId    = (int)($_SESSION['user_id'] ?? 0);
$bookingId = (int)($_POST['booking_id'] ?? 0);
$method    = trim($_POST['method'] ?? 'paid'); // 'paid' (online, needs verification) or 'cod'
if (!in_array($method, ['paid', 'cod'], true)) { $method = 'paid'; }

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit;
}
if ($bookingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit;
}

// Verify this booking belongs to the logged-in user and is already accepted.
$stmt = $conn->prepare(
    "SELECT id, status, payment_status FROM equipment_bookings WHERE id = ? AND user_id = ? LIMIT 1"
);
$stmt->bind_param("ii", $bookingId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}
if ($row['status'] === 'pending') {
    echo json_encode(['success' => false, 'message' => 'Owner ne ajun request accept keleli nahi.']);
    exit;
}
if (in_array($row['payment_status'], ['paid', 'verification_pending'], true)) {
    echo json_encode(['success' => true, 'message' => 'Payment already submitted for this booking.', 'payment_status' => $row['payment_status']]);
    exit;
}

// --- Cash on Delivery: unchanged, no proof needed -----------------------
if ($method === 'cod') {
    $upd = $conn->prepare("UPDATE equipment_bookings SET payment_status = 'cod', payment_method = 'cod' WHERE id = ? AND user_id = ?");
    $upd->bind_param("ii", $bookingId, $userId);
    if ($upd->execute()) {
        echo json_encode(['success' => true, 'payment_status' => 'cod']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }
    exit;
}

// --- Online payment: require proof, go to verification_pending ----------
$paymentMethod = trim($_POST['payment_method'] ?? 'upi');
$allowedMethods = ['upi', 'qr', 'bank_transfer', 'razorpay'];
if (!in_array($paymentMethod, $allowedMethods, true)) { $paymentMethod = 'upi'; }

$transactionId = trim($_POST['transaction_id'] ?? '');
if ($transactionId === '' || strlen($transactionId) < 4 || strlen($transactionId) > 100) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid transaction / UTR reference number.']);
    exit;
}
// Only allow safe characters in a transaction/UTR reference.
if (!preg_match('/^[A-Za-z0-9\-_.\/]+$/', $transactionId)) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID contains invalid characters.']);
    exit;
}

// Prevent the same transaction ID being used on more than one booking.
$dupCheck = $conn->prepare("SELECT id FROM equipment_bookings WHERE transaction_id = ? AND id != ? LIMIT 1");
$dupCheck->bind_param("si", $transactionId, $bookingId);
$dupCheck->execute();
if ($dupCheck->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'This transaction ID has already been submitted for another booking. If this is a mistake, contact support.']);
    exit;
}

$screenshotPath = null;
if (!empty($_FILES['screenshot']) && ($_FILES['screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $uploadResult = agri_secure_upload_image(
        $_FILES['screenshot'],
        __DIR__ . '/../assets/uploads/payments',
        'assets/uploads/payments',
        3 * 1024 * 1024
    );
    if (!$uploadResult['ok']) {
        echo json_encode(['success' => false, 'message' => $uploadResult['error']]);
        exit;
    }
    $screenshotPath = $uploadResult['path'];
}

$upd = $conn->prepare(
    "UPDATE equipment_bookings
        SET payment_status = 'verification_pending',
            payment_method = ?,
            transaction_id = ?,
            payment_submitted_at = NOW(),
            payment_screenshot = ?
      WHERE id = ? AND user_id = ?"
);
$upd->bind_param("sssii", $paymentMethod, $transactionId, $screenshotPath, $bookingId, $userId);

if ($upd->execute()) {
    echo json_encode([
        'success' => true,
        'payment_status' => 'verification_pending',
        'message' => 'Payment details submitted. An admin will verify and confirm shortly.',
    ]);
} else {
    // Duplicate transaction_id caught by the DB unique index (race condition
    // between the SELECT check above and this UPDATE).
    if ($conn->errno === 1062) {
        echo json_encode(['success' => false, 'message' => 'This transaction ID has already been submitted for another booking.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }
}
