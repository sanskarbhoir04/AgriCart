<?php
// =====================================================================
// admin/payout_action.php — Approve or reject a seller's withdrawal
// request (a row in the `payouts` table).
//
// Approve -> status = 'completed', completed_at/completed_by_admin_id
//            set, seller_payout_profiles.total_paid increased. The
//            amount was already deducted from available_balance when
//            the seller submitted the request (see
//            seller/seller_api.php -> case 'payout_request').
// Reject  -> status = 'rejected', held amount is refunded back into
//            the seller's available_balance.
//
// Visible only to Super Admin or an admin holding the 'finance.payout'
// permission (see admin/includes/permissions.php + setup/admin_rbac.sql,
// where Finance Manager already has this permission).
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../includes/seller_functions.php';
requirePermission('finance.payout');

$response = ['success' => false];

if (!csrf_verify()) {
    http_response_code(403);
    $response['error'] = 'Security token expired. Please refresh the page and try again.';
    echo json_encode($response);
    exit;
}

$payoutId = (int)($_POST['payout_id'] ?? 0);
$decision = trim($_POST['decision'] ?? ''); // 'approve' or 'reject'
$note     = trim($_POST['note'] ?? '');

if ($payoutId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
    $response['error'] = 'Invalid request.';
    echo json_encode($response);
    exit;
}

$cur = $conn->prepare("SELECT id, seller_id, amount, status FROM payouts WHERE id = ? LIMIT 1");
$cur->bind_param('i', $payoutId);
$cur->execute();
$row = $cur->get_result()->fetch_assoc();

if (!$row) {
    $response['error'] = 'Withdrawal request not found.';
    echo json_encode($response);
    exit;
}
if ($row['status'] !== 'pending' && $row['status'] !== 'processing') {
    $response['error'] = 'This request has already been reviewed.';
    echo json_encode($response);
    exit;
}

$adminUserId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;

$conn->begin_transaction();
try {
    if ($decision === 'approve') {
        $upd = $conn->prepare(
            "UPDATE payouts SET status = 'completed', completed_at = NOW(), completed_by_admin_id = ?, notes = ? WHERE id = ?"
        );
        $upd->bind_param('isi', $adminUserId, $note, $payoutId);
        $upd->execute();

        $bal = $conn->prepare("UPDATE seller_payout_profiles SET total_paid = total_paid + ? WHERE user_id = ?");
        $bal->bind_param('di', $row['amount'], $row['seller_id']);
        $bal->execute();
    } else {
        $upd = $conn->prepare(
            "UPDATE payouts SET status = 'rejected', completed_at = NOW(), completed_by_admin_id = ?, notes = ? WHERE id = ?"
        );
        $upd->bind_param('isi', $adminUserId, $note, $payoutId);
        $upd->execute();

        // Refund the held amount back to the seller's available balance.
        $bal = $conn->prepare("UPDATE seller_payout_profiles SET available_balance = available_balance + ? WHERE user_id = ?");
        $bal->bind_param('di', $row['amount'], $row['seller_id']);
        $bal->execute();
    }

    // Best-effort notification to the seller.
    if (function_exists('agri_seller_notify')) {
        if ($decision === 'approve') {
            agri_seller_notify($conn, $row['seller_id'], 'payout_completed', 'Withdrawal Approved',
                'Your withdrawal of ₹' . number_format((float)$row['amount'], 2) . ' has been approved and processed.', 'seller/dashboard.php#earnings');
        } else {
            agri_seller_notify($conn, $row['seller_id'], 'payout_rejected', 'Withdrawal Rejected',
                'Your withdrawal request of ₹' . number_format((float)$row['amount'], 2) . ' was rejected' . ($note ? (': ' . $note) : '.') . ' The amount has been returned to your available balance.', 'seller/dashboard.php#earnings');
        }
    }

    $conn->commit();
    $response['success'] = true;

    logAdminActivity(
        $decision === 'approve' ? 'seller_payout_approved' : 'seller_payout_rejected',
        'finance', $payoutId, $row['status'], $decision === 'approve' ? 'completed' : 'rejected',
        'Payout #' . $payoutId . ' for seller #' . $row['seller_id'] . ' (₹' . number_format((float)$row['amount'], 2) . ') ' . ($decision === 'approve' ? 'approved' : 'rejected')
    );
} catch (\Throwable $e) {
    $conn->rollback();
    $response['error'] = 'Could not process this request. Please try again.';
}

echo json_encode($response);
