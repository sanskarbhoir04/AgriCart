<?php
// =====================================================================
// admin/mandi.php — Admin-panel mandi price JSON endpoint.
// Requires an active admin session (previously had NO auth check at
// all — anyone could call this directly). Shares the same secured
// client as pages/mandi.php via includes/mandi_client.php.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();

header('Content-Type: application/json');

if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

require_once __DIR__ . '/../includes/mandi_client.php';

$debug = isset($_GET['debug']); // admin-only debug flag, gated behind the auth check above
echo json_encode(agri_mandi_fetch($_GET, $debug));
