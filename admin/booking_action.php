<?php
// =====================================================================
// admin/booking_action.php — Update an equipment_bookings.status value.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/agri_connect_functions.php';
include_once __DIR__ . '/../includes/agri_connect_schema.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/payment_verification_schema.php';
payment_verification_bootstrap_schema($conn);

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    $response['error'] = 'Not authorized.';
    echo json_encode($response);
    exit;
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$field     = trim($_POST['field'] ?? 'status'); // 'status' (default, backward-compatible) or 'payment_status'

if ($field === 'payment_status') {
    requirePermission('rental_bookings.confirm');
    $paymentStatus = trim($_POST['payment_status'] ?? '');
    $allowedPaymentStatuses = ['pending','paid','failed','verification_pending','refunded','cod'];

    if ($bookingId <= 0 || !in_array($paymentStatus, $allowedPaymentStatuses, true)) {
        $response['error'] = 'Invalid booking id or payment status.';
        echo json_encode($response);
        exit;
    }

    $stmt = $conn->prepare("UPDATE equipment_bookings SET payment_status = ? WHERE id = ?");
    if (!$stmt) {
        $response['error'] = 'payment_status column not found — run setup/equipment_bookings_upgrade.sql first.';
        echo json_encode($response);
        exit;
    }
    $stmt->bind_param("si", $paymentStatus, $bookingId);
    $response['success'] = $stmt->execute();
    if (!$response['success']) {
        $response['error'] = 'Update failed.';
    } else {
        logAdminActivity('booking_payment_status_changed', 'bookings', $bookingId, null, $paymentStatus, 'Booking #' . $bookingId . ' payment status changed to "' . $paymentStatus . '"');
    }

    echo json_encode($response);
    exit;
}

// ── verify_payment: admin approves/rejects a user-submitted payment proof ──
// (added as part of the secure payment-verification workflow — see
// pages/confirm_payment.php and admin/payment_verification.php)
if ($field === 'verify_payment') {
    require_once __DIR__ . '/../includes/security.php';
    if (!csrf_verify()) {
        http_response_code(403);
        $response['error'] = 'Security token expired. Please refresh the page and try again.';
        echo json_encode($response);
        exit;
    }
    requirePermission('rental_bookings.verify_payment');

    $decision = trim($_POST['decision'] ?? ''); // 'approve' or 'reject'
    $note     = trim($_POST['note'] ?? '');
    if ($bookingId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        $response['error'] = 'Invalid booking id or decision.';
        echo json_encode($response);
        exit;
    }

    $cur = $conn->prepare("SELECT payment_status FROM equipment_bookings WHERE id = ? LIMIT 1");
    $cur->bind_param("i", $bookingId);
    $cur->execute();
    $curRow = $cur->get_result()->fetch_assoc();
    if (!$curRow) {
        $response['error'] = 'Booking not found.';
        echo json_encode($response);
        exit;
    }
    if ($curRow['payment_status'] !== 'verification_pending') {
        $response['error'] = 'This booking has no payment submission awaiting verification.';
        echo json_encode($response);
        exit;
    }

    $newStatus   = $decision === 'approve' ? 'paid' : 'failed';
    $adminUserId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;

    $upd = $conn->prepare(
        "UPDATE equipment_bookings
            SET payment_status = ?, admin_verification_note = ?,
                payment_verified_by = ?, payment_verified_at = NOW()
          WHERE id = ? AND payment_status = 'verification_pending'"
    );
    $upd->bind_param("ssii", $newStatus, $note, $adminUserId, $bookingId);
    $response['success'] = $upd->execute() && $upd->affected_rows > 0;

    if (!$response['success']) {
        $response['error'] = 'Update failed (booking may have already been reviewed).';
    } else {
        logAdminActivity(
            $decision === 'approve' ? 'booking_payment_approved' : 'booking_payment_rejected',
            'bookings',
            $bookingId,
            'verification_pending',
            $newStatus,
            'Payment for booking #' . $bookingId . ' ' . ($decision === 'approve' ? 'approved' : 'rejected') . ($note !== '' ? (' — ' . $note) : '')
        );

        if ($decision === 'approve' && function_exists('agri_notify_user')) {
            try {
                if (function_exists('agri_connect_bootstrap_schema')) { agri_connect_bootstrap_schema($conn); }
                $bInfo = $conn->prepare("SELECT user_id, booking_number FROM equipment_bookings WHERE id = ? LIMIT 1");
                $bInfo->bind_param("i", $bookingId);
                $bInfo->execute();
                $b = $bInfo->get_result()->fetch_assoc();
                if ($b && $b['user_id']) {
                    agri_notify_user($conn, (int)$b['user_id'], 'Payment Verified ✅', 'Booking ' . $b['booking_number'] . ' cha payment verify zala aahe!', 'my_activity.php', 'payment');
                }
            } catch (\Throwable $eNotify) {}
        } elseif ($decision === 'reject' && function_exists('agri_notify_user')) {
            try {
                if (function_exists('agri_connect_bootstrap_schema')) { agri_connect_bootstrap_schema($conn); }
                $bInfo = $conn->prepare("SELECT user_id, booking_number FROM equipment_bookings WHERE id = ? LIMIT 1");
                $bInfo->bind_param("i", $bookingId);
                $bInfo->execute();
                $b = $bInfo->get_result()->fetch_assoc();
                if ($b && $b['user_id']) {
                    $msg = 'Booking ' . $b['booking_number'] . ' cha payment proof reject zala.' . ($note !== '' ? (' Karan: ' . $note) : '') . ' Krupaya punha payment karun submit kara.';
                    agri_notify_user($conn, (int)$b['user_id'], 'Payment Rejected', $msg, 'payment.php?booking_id=' . $bookingId, 'payment');
                }
            } catch (\Throwable $eNotify) {}
        }
    }

    echo json_encode($response);
    exit;
}

// ── default: update booking status (owner accept/reject/dispatch/etc.) ──
$status = trim($_POST['status'] ?? '');
$allowedStatuses = ['pending','confirmed','on_the_way','completed','cancelled'];

if ($bookingId <= 0 || !in_array($status, $allowedStatuses, true)) {
    $response['error'] = 'Invalid booking id or status.';
    echo json_encode($response);
    exit;
}

$bookingPermMap = [
    'confirmed' => 'rental_bookings.confirm', 'cancelled' => 'rental_bookings.cancel',
    'completed' => 'rental_bookings.complete', 'on_the_way' => 'rental_bookings.confirm',
    'pending' => 'rental_bookings.confirm',
];
requirePermission($bookingPermMap[$status] ?? 'rental_bookings.confirm');

// Remember the previous status so we only notify once, on the actual
// pending→cancelled / confirmed→cancelled transition (not on re-saves).
$prevStatus = null;
$prevStmt = $conn->prepare("SELECT status FROM equipment_bookings WHERE id = ? LIMIT 1");
if ($prevStmt) {
    $prevStmt->bind_param("i", $bookingId);
    $prevStmt->execute();
    $prevRow = $prevStmt->get_result()->fetch_assoc();
    $prevStatus = $prevRow['status'] ?? null;
}

$stmt = $conn->prepare("UPDATE equipment_bookings SET status = ? WHERE id = ?");
if (!$stmt) {
    $response['error'] = 'equipment_bookings table not found or column mismatch.';
    echo json_encode($response);
    exit;
}
$stmt->bind_param("si", $status, $bookingId);
$response['success'] = $stmt->execute();
if (!$response['success']) {
    $response['error'] = 'Update failed.';
} else {
    logAdminActivity('booking_status_changed', 'bookings', $bookingId, $prevStatus, $status, 'Booking #' . $bookingId . ' status changed from "' . $prevStatus . '" to "' . $status . '"');

    // ---- Automatic Inventory logic (Inventory module, section 7) ----
    // Equipment listings here are single-unit and booked by date range, so
    // there's no separate "available units" counter to increment/decrement
    // — availability is derived live from equipment_bookings in the
    // Inventory module. What we do log is the event itself, so it shows up
    // on the Stock History tab. Never blocks the booking status update.
    try {
        $inventorySchema = __DIR__ . '/includes/inventory_schema.php';
        if ($status !== $prevStatus && file_exists($inventorySchema)) {
            require_once $inventorySchema;
            inventory_bootstrap_schema($conn);
            $eqStmt = $conn->prepare("SELECT e.id, e.name FROM equipment_bookings eb JOIN equipment e ON e.id = eb.equipment_id WHERE eb.id = ?");
            $eqStmt->bind_param('i', $bookingId);
            $eqStmt->execute();
            $eq = $eqStmt->get_result()->fetch_assoc();
            if ($eq) {
                $invAction = null;
                if ($status === 'confirmed') { $invAction = 'equipment_booked'; }
                elseif (in_array($status, ['completed', 'cancelled'], true)) { $invAction = 'equipment_returned'; }
                if ($invAction) {
                    inv_log($conn, 'equipment', (int)$eq['id'], $eq['name'], $invAction, null, null, 'Booking #' . $bookingId . ' → ' . $status);
                }
            }
        }
    } catch (\Throwable $e) {
        // Never let inventory logging failures block a booking status update.
    }
}

// ── Equipment pohochlं (completed/delivered): jar payment method Cash on Delivery
//    hota, to ata automatically 'paid' mhanun mark karaycha — admin la manually kahi
//    click karaychi garaj nahi. ──
if ($response['success'] && in_array($status, ['completed', 'delivered'], true)) {
    try {
        $payUpd = $conn->prepare("UPDATE equipment_bookings SET payment_status = 'paid' WHERE id = ? AND payment_status = 'cod'");
        $payUpd->bind_param("i", $bookingId);
        $payUpd->execute();
    } catch (\Throwable $ePay) {}
}

// ── Notify the renter: booking accepted → include a direct payment link ──
if ($response['success'] && $status === 'confirmed' && function_exists('agri_notify_user')) {
    try {
        if (function_exists('agri_connect_bootstrap_schema')) { agri_connect_bootstrap_schema($conn); }
        $bInfo = $conn->prepare(
            "SELECT eb.user_id, eb.booking_number, eb.total_amount, e.name AS equipment_name
             FROM equipment_bookings eb LEFT JOIN equipment e ON e.id = eb.equipment_id
             WHERE eb.id = ? LIMIT 1"
        );
        $bInfo->bind_param("i", $bookingId);
        $bInfo->execute();
        $b = $bInfo->get_result()->fetch_assoc();
        if ($b && $b['user_id']) {
            agri_notify_user(
                $conn,
                (int)$b['user_id'],
                'Booking Confirmed! 🎉',
                'तुमची booking ' . $b['booking_number'] . ' (' . $b['equipment_name'] . ') owner ने accept केली आहे. आता ₹' . number_format((float)$b['total_amount'], 2) . ' चे payment करा.',
                'payment.php?booking_id=' . (int)$bookingId,
                'payment'
            );
        }
    } catch (\Throwable $eNotify) {}
}

// ── Notify the renter: booking cancelled by admin → mention refund timeline
//    if payment had already been made (paid / cash-on-delivery). ──
if ($response['success'] && $status === 'cancelled' && $prevStatus !== 'cancelled' && function_exists('agri_notify_user')) {
    try {
        if (function_exists('agri_connect_bootstrap_schema')) { agri_connect_bootstrap_schema($conn); }
        $bInfo = $conn->prepare(
            "SELECT eb.user_id, eb.booking_number, eb.total_amount, eb.payment_status, e.name AS equipment_name
             FROM equipment_bookings eb LEFT JOIN equipment e ON e.id = eb.equipment_id
             WHERE eb.id = ? LIMIT 1"
        );
        $bInfo->bind_param("i", $bookingId);
        $bInfo->execute();
        $b = $bInfo->get_result()->fetch_assoc();
        if ($b && $b['user_id']) {
            $wasPaid = in_array($b['payment_status'], ['paid', 'cod'], true);
            $msg = 'तुमची booking ' . $b['booking_number'] . ' (' . $b['equipment_name'] . ') admin ने रद्द केली आहे.';
            if ($wasPaid) {
                $msg .= ' भरलेले ₹' . number_format((float)$b['total_amount'], 2) . ' पुढील 7 दिवसांत परत (refund) केले जातील.';
            }
            agri_notify_user($conn, (int)$b['user_id'], 'Booking Cancelled', $msg, 'my_activity.php', 'payment');
        }
    } catch (\Throwable $eNotify) {}
}

echo json_encode($response);
