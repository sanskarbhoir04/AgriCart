<?php
// =====================================================
// AgriCart — Save a rental booking (AJAX endpoint)
// Called from rental.php's confirmBooking()
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

function respond($arr) { echo json_encode($arr); exit; }

if (!isset($_SESSION['user_id'])) {
    respond(['success' => false, 'error' => 'कृपया आधी login करा.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_require')) {
    csrf_require('json');
}

$userId          = (int)$_SESSION['user_id'];
$equipmentId     = (int)($_POST['equipment_id'] ?? 0);
$startDate       = trim($_POST['start_date'] ?? '');
$endDate         = trim($_POST['end_date'] ?? '');
$deliveryAddress = trim($_POST['delivery_address'] ?? '');
$hours           = (int)($_POST['hours'] ?? 0); // optional — hours/day equipment will be used
$couponCode      = strtoupper(trim($_POST['coupon_code'] ?? ''));

if ($equipmentId <= 0 || $startDate === '' || $endDate === '') {
    respond(['success' => false, 'error' => 'सर्व आवश्यक माहिती द्या.']);
}

$start = DateTime::createFromFormat('Y-m-d', $startDate);
$end   = DateTime::createFromFormat('Y-m-d', $endDate);
if (!$start || !$end || $end < $start) {
    respond(['success' => false, 'error' => 'चुकीच्या तारखा.']);
}

$today = new DateTime('today');
if ($start < $today) {
    respond(['success' => false, 'error' => 'भूतकाळातील तारीख निवडता येत नाही.']);
}

// ── Get equipment price (server-side, don't trust client-sent amount) ──
$stmt = $conn->prepare("SELECT rent_per_day, rent_per_hour, pn, serial_no FROM equipment WHERE id = ? AND availability = 1 LIMIT 1");
if (!$stmt) {
    respond(['success' => false, 'error' => 'Database अजून update नाही झालेला. कृपया आधी setup/equipment_bookings_upgrade.sql run करा (rent_per_hour column missing).']);
}
$stmt->bind_param("i", $equipmentId);
$stmt->execute();
$eq = $stmt->get_result()->fetch_assoc();
if (!$eq) {
    respond(['success' => false, 'error' => 'हे equipment उपलब्ध नाही.']);
}
$rentPerDay  = (float)$eq['rent_per_day'];
$rentPerHour = isset($eq['rent_per_hour']) && $eq['rent_per_hour'] !== null ? (float)$eq['rent_per_hour'] : null;
$eqPn        = $eq['pn'] ?? null;
$eqSerialNo  = $eq['serial_no'] ?? null;

$totalDays = (int)$start->diff($end)->days + 1;

// ── Pricing: by the hour for a single-day booking (uses admin-set hourly rate if
//    configured, otherwise falls back to day-rate/8 as the implied hourly rate);
//    for multi-day bookings, pricing stays day-based. ──
$effHourlyRate = $rentPerHour !== null ? $rentPerHour : round($rentPerDay / 8, 2);
$totalHours = null;
if ($totalDays === 1 && $hours > 0) {
    $totalHours = min(24, max(1, $hours));
    $subtotal   = $effHourlyRate * $totalHours;
} else {
    if ($hours > 0) { $totalHours = min(24, max(1, $hours)); } // kept for reference, price still day-based
    $subtotal = $rentPerDay * $totalDays;
}
$fee       = round($subtotal * 0.05, 2);
$totalAmount = $subtotal + $fee;

// ── Coupon (validated server-side; the client's own check is just a UI convenience) ──
$AGRI_COUPONS = ['RENT10' => 10];
$couponDiscount = 0;
$couponApplied  = null;
if ($couponCode !== '' && isset($AGRI_COUPONS[$couponCode])) {
    $couponApplied  = $couponCode;
    $couponPct      = $AGRI_COUPONS[$couponCode];
    $couponDiscount = round($subtotal * $couponPct / 100, 2);
    $taxable        = $subtotal - $couponDiscount;
    $fee            = round($taxable * 0.05, 2);
    $totalAmount    = $taxable + $fee;
}

// ── Check for overlapping bookings (only ACCEPTED bookings block a slot —
//    a 'pending' request hasn't been accepted by the owner yet, so other
//    users can still request/see the same dates until it's confirmed) ──
$stmt = $conn->prepare(
    "SELECT id FROM equipment_bookings
     WHERE equipment_id = ? AND status IN ('confirmed','on_the_way')
       AND NOT (to_date < ? OR from_date > ?)
     LIMIT 1"
);
$stmt->bind_param("iss", $equipmentId, $startDate, $endDate);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    respond(['success' => false, 'error' => 'या तारखांसाठी हे equipment आधीच बुक झालेलं (accepted) आहे. कृपया calendar वर दुसऱ्या तारखा निवडा.']);
}

// ── Get contact details from the logged-in user's profile ──
$stmt = $conn->prepare("SELECT full_name, mobile FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$contactName   = $userRow['full_name'] ?? '';
$contactMobile = $userRow['mobile'] ?? '';

$bookingNumber = 'RNT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

$stmt = $conn->prepare(
    "INSERT INTO equipment_bookings
        (booking_number, equipment_id, pn, serial_no, user_id, from_date, to_date, total_days, total_hours, total_amount, status, payment_status, contact_name, contact_mobile, delivery_address)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?)"
);
if (!$stmt) {
    respond(['success' => false, 'error' => 'Database अजून update नाही झालेला. कृपया आधी setup/equipment_bookings_upgrade.sql run करा (total_hours / payment_status columns missing).']);
}
$stmt->bind_param(
    "sississiidsss",
    $bookingNumber, $equipmentId, $eqPn, $eqSerialNo, $userId, $startDate, $endDate, $totalDays, $totalHours, $totalAmount,
    $contactName, $contactMobile, $deliveryAddress
);

if ($stmt->execute()) {
    // Save this address as the user's default for next time (best-effort —
    // skipped silently if saved_address column doesn't exist yet).
    try {
        $su = $conn->prepare("UPDATE users SET saved_address=? WHERE id=?");
        $su->bind_param("si", $deliveryAddress, $userId);
        $su->execute();
    } catch (\Throwable $eSaveR) {}

    respond([
        'success'         => true,
        'booking_number'  => $bookingNumber,
        'total_amount'    => $totalAmount,
        'total_days'      => $totalDays,
        'total_hours'     => $totalHours,
        'status'          => 'pending',
        'coupon_applied'  => $couponApplied,
        'coupon_discount' => $couponDiscount,
        'note'            => 'तुमची request owner कडे पाठवली आहे. Owner ने accept केल्यावरच booking confirm होईल.',
    ]);
} else {
    respond(['success' => false, 'error' => 'Booking save करताना अडचण आली, पुन्हा try करा.']);
}
