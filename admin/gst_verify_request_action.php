<?php
// =====================================================================
// admin/gst_verify_request_action.php — Approve/reject a GST
// verification request from the seller-initiated queue (see
// includes/gst_verify_requests.php). The request row already carries
// the seller's exact user_id, so this never has to guess which account
// to update — unlike the Companies-directory verify flow.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/gst_verify_requests.php';
gst_verify_requests_bootstrap_schema($conn);
requirePermission('accounts.verify');

$response = ['success' => false];

if (!csrf_verify()) {
    http_response_code(403);
    $response['error'] = 'Security token expired. Please refresh the page and try again.';
    echo json_encode($response);
    exit;
}

$requestId = (int)($_POST['request_id'] ?? 0);
$decision  = trim($_POST['decision'] ?? ''); // 'approved' or 'rejected'
$note      = trim($_POST['note'] ?? '');

if ($requestId <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    $response['error'] = 'Invalid request.';
    echo json_encode($response);
    exit;
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$result = gst_verify_request_review($conn, $requestId, $decision, $adminName, $note);

if ($result['success'] && function_exists('logAdminActivity')) {
    logAdminActivity(
        $decision === 'approved' ? 'gst_verify_request_approved' : 'gst_verify_request_rejected',
        'gst_verification_requests', $requestId, 'pending', $decision,
        'GST verification request #' . $requestId . ' for seller #' . ($result['seller_user_id'] ?? 0) . ' ' . $decision
    );
}

echo json_encode($result);
